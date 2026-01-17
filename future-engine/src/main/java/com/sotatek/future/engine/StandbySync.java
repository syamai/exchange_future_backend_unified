package com.sotatek.future.engine;

import com.google.gson.Gson;
import com.sotatek.future.entity.Command;
import com.sotatek.future.util.json.JsonUtil;
import java.util.Properties;
import java.util.concurrent.BlockingQueue;
import java.util.concurrent.LinkedBlockingQueue;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicLong;
import lombok.Builder;
import lombok.Data;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.producer.KafkaProducer;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.clients.producer.ProducerRecord;
import org.apache.kafka.common.serialization.StringSerializer;

/**
 * Handles synchronization of state from Primary to Standby shard.
 * Uses Kafka for reliable state replication.
 */
@Slf4j
public class StandbySync implements AutoCloseable {

    private static final int MAX_RETRY_ATTEMPTS = 3;
    private static final int RETRY_BACKOFF_MS = 100;
    private static final int QUEUE_CAPACITY = 10000;

    private final String shardId;
    private final String syncTopic;
    private final KafkaProducer<String, String> producer;
    private final Gson gson = JsonUtil.createGson();

    private final AtomicLong sequenceNumber = new AtomicLong(0);
    private final AtomicLong syncedCount = new AtomicLong(0);
    private final AtomicLong failedCount = new AtomicLong(0);
    private final AtomicLong consecutiveFailures = new AtomicLong(0);

    private final BlockingQueue<SyncMessage> retryQueue = new LinkedBlockingQueue<>(QUEUE_CAPACITY);
    private final Thread retryThread;

    private volatile boolean closed = false;

    /**
     * Create a new StandbySync instance.
     */
    public StandbySync(String shardId, String syncTopic, String bootstrapServers) {
        this.shardId = shardId;
        this.syncTopic = syncTopic;
        this.producer = createProducer(bootstrapServers);
        this.retryThread = new RetryThread();
        this.retryThread.setName("StandbySync-Retry-" + shardId);
        this.retryThread.setDaemon(true);
        this.retryThread.start();

        log.info("StandbySync initialized for shard {} with topic {}", shardId, syncTopic);
    }

    private KafkaProducer<String, String> createProducer(String bootstrapServers) {
        Properties props = new Properties();
        props.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, bootstrapServers);
        props.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
        props.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
        // High reliability settings for replication
        props.put(ProducerConfig.ACKS_CONFIG, "all");
        props.put(ProducerConfig.RETRIES_CONFIG, 3);
        props.put(ProducerConfig.MAX_IN_FLIGHT_REQUESTS_PER_CONNECTION, 1);
        props.put(ProducerConfig.ENABLE_IDEMPOTENCE_CONFIG, true);
        // Low latency settings
        props.put(ProducerConfig.LINGER_MS_CONFIG, 0);
        props.put(ProducerConfig.BATCH_SIZE_CONFIG, 16384);

        return new KafkaProducer<>(props);
    }

    /**
     * Synchronize a command to the standby.
     */
    public void syncCommand(Command command) {
        if (closed) {
            log.warn("StandbySync is closed, cannot sync command");
            return;
        }

        SyncMessage message = SyncMessage.builder()
                .shardId(shardId)
                .sequenceNumber(sequenceNumber.incrementAndGet())
                .command(command)
                .timestamp(System.currentTimeMillis())
                .build();

        sendSync(message);
    }

    /**
     * Synchronize a state snapshot (for initial sync or recovery).
     */
    public void syncSnapshot(StateSnapshot snapshot) {
        if (closed) {
            return;
        }

        SyncMessage message = SyncMessage.builder()
                .shardId(shardId)
                .sequenceNumber(sequenceNumber.incrementAndGet())
                .snapshot(snapshot)
                .isSnapshot(true)
                .timestamp(System.currentTimeMillis())
                .build();

        sendSyncBlocking(message);
    }

    /**
     * Send sync message asynchronously.
     */
    private void sendSync(SyncMessage message) {
        String payload = gson.toJson(message);
        ProducerRecord<String, String> record = new ProducerRecord<>(syncTopic, shardId, payload);

        producer.send(record, (metadata, exception) -> {
            if (exception != null) {
                handleSyncFailure(message, exception);
            } else {
                syncedCount.incrementAndGet();
                consecutiveFailures.set(0);
                log.debug("Synced message seq={} to standby", message.getSequenceNumber());
            }
        });
    }

    /**
     * Send sync message and wait for acknowledgment.
     */
    private void sendSyncBlocking(SyncMessage message) {
        String payload = gson.toJson(message);
        ProducerRecord<String, String> record = new ProducerRecord<>(syncTopic, shardId, payload);

        try {
            producer.send(record).get(5, TimeUnit.SECONDS);
            syncedCount.incrementAndGet();
            consecutiveFailures.set(0);
            log.info("Synced snapshot seq={} to standby", message.getSequenceNumber());
        } catch (Exception e) {
            handleSyncFailure(message, e);
        }
    }

    /**
     * Handle synchronization failure.
     */
    private void handleSyncFailure(SyncMessage message, Exception e) {
        failedCount.incrementAndGet();
        long failures = consecutiveFailures.incrementAndGet();

        log.error("Failed to sync message seq={} to standby: {}",
                message.getSequenceNumber(), e.getMessage());

        // Add to retry queue
        if (message.getRetryCount() < MAX_RETRY_ATTEMPTS) {
            message.setRetryCount(message.getRetryCount() + 1);
            if (!retryQueue.offer(message)) {
                log.error("Retry queue full, dropping sync message seq={}", message.getSequenceNumber());
            }
        } else {
            log.error("Max retries exceeded for sync message seq={}", message.getSequenceNumber());
        }

        // Alert on consecutive failures
        if (failures >= 10) {
            log.error("CRITICAL: {} consecutive sync failures for shard {}", failures, shardId);
            // TODO: Trigger alert via monitoring system
        }
    }

    /**
     * Get the current sequence number.
     */
    public long getSequenceNumber() {
        return sequenceNumber.get();
    }

    /**
     * Get the count of successfully synced messages.
     */
    public long getSyncedCount() {
        return syncedCount.get();
    }

    /**
     * Get the count of failed sync attempts.
     */
    public long getFailedCount() {
        return failedCount.get();
    }

    /**
     * Get the retry queue size.
     */
    public int getRetryQueueSize() {
        return retryQueue.size();
    }

    @Override
    public void close() {
        if (!closed) {
            closed = true;
            retryThread.interrupt();
            producer.flush();
            producer.close();
            log.info("StandbySync for shard {} closed. Synced: {}, Failed: {}",
                    shardId, syncedCount.get(), failedCount.get());
        }
    }

    /**
     * Background thread for retrying failed sync messages.
     */
    private class RetryThread extends Thread {
        @Override
        public void run() {
            while (!closed) {
                try {
                    SyncMessage message = retryQueue.poll(1, TimeUnit.SECONDS);
                    if (message != null) {
                        Thread.sleep(RETRY_BACKOFF_MS * message.getRetryCount());
                        log.debug("Retrying sync message seq={}, attempt={}",
                                message.getSequenceNumber(), message.getRetryCount());
                        sendSync(message);
                    }
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    break;
                } catch (Exception e) {
                    log.error("Error in retry thread: {}", e.getMessage());
                }
            }
        }
    }

    /**
     * Message structure for synchronization.
     */
    @Data
    @Builder
    public static class SyncMessage {
        private String shardId;
        private long sequenceNumber;
        private Command command;
        private StateSnapshot snapshot;
        private boolean isSnapshot;
        private long timestamp;
        @Builder.Default
        private int retryCount = 0;
    }

    /**
     * State snapshot for full synchronization.
     */
    @Data
    @Builder
    public static class StateSnapshot {
        private String shardId;
        private long sequenceNumber;
        private byte[] ordersData;
        private byte[] positionsData;
        private byte[] accountsData;
        private long timestamp;
    }
}

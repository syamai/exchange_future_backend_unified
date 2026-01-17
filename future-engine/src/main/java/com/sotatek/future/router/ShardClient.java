package com.sotatek.future.router;

import com.google.gson.Gson;
import com.sotatek.future.entity.Command;
import com.sotatek.future.util.json.JsonUtil;
import java.util.Properties;
import java.util.concurrent.Future;
import java.util.concurrent.atomic.AtomicLong;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.producer.KafkaProducer;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.clients.producer.ProducerRecord;
import org.apache.kafka.clients.producer.RecordMetadata;
import org.apache.kafka.common.serialization.StringSerializer;

/**
 * Client for sending commands to a specific shard via Kafka.
 */
@Slf4j
public class ShardClient implements AutoCloseable {

    private final String shardId;
    private final String topic;
    private final KafkaProducer<String, String> producer;
    private final Gson gson = JsonUtil.createGson();
    private final AtomicLong sentCount = new AtomicLong(0);
    private final AtomicLong errorCount = new AtomicLong(0);

    private volatile boolean closed = false;

    /**
     * Create a new ShardClient with the specified shard info and Kafka properties.
     */
    public ShardClient(ShardInfo shardInfo, Properties kafkaProps) {
        this.shardId = shardInfo.getShardId();
        this.topic = shardInfo.getKafkaInputTopic();
        this.producer = new KafkaProducer<>(kafkaProps);
        log.info("ShardClient created for shard {} with topic {}", shardId, topic);
    }

    /**
     * Create a new ShardClient with bootstrap servers.
     */
    public ShardClient(ShardInfo shardInfo, String bootstrapServers) {
        this(shardInfo, createDefaultProperties(bootstrapServers));
    }

    private static Properties createDefaultProperties(String bootstrapServers) {
        Properties props = new Properties();
        props.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, bootstrapServers);
        props.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
        props.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
        props.put(ProducerConfig.ACKS_CONFIG, "all");
        props.put(ProducerConfig.RETRIES_CONFIG, 3);
        props.put(ProducerConfig.RETRY_BACKOFF_MS_CONFIG, 100);
        props.put(ProducerConfig.MAX_IN_FLIGHT_REQUESTS_PER_CONNECTION, 1);
        props.put(ProducerConfig.ENABLE_IDEMPOTENCE_CONFIG, true);
        return props;
    }

    /**
     * Send a command to this shard asynchronously.
     */
    public void sendCommand(Command command) {
        if (closed) {
            throw new IllegalStateException("ShardClient is closed");
        }

        String key = extractPartitionKey(command);
        String value = gson.toJson(command);

        ProducerRecord<String, String> record = new ProducerRecord<>(topic, key, value);

        producer.send(record, (metadata, exception) -> {
            if (exception != null) {
                errorCount.incrementAndGet();
                handleSendError(command, exception);
            } else {
                sentCount.incrementAndGet();
                log.debug("Command sent to shard {} partition {} offset {}",
                        shardId, metadata.partition(), metadata.offset());
            }
        });
    }

    /**
     * Send a command to this shard synchronously and wait for acknowledgment.
     */
    public RecordMetadata sendCommandSync(Command command) throws Exception {
        if (closed) {
            throw new IllegalStateException("ShardClient is closed");
        }

        String key = extractPartitionKey(command);
        String value = gson.toJson(command);

        ProducerRecord<String, String> record = new ProducerRecord<>(topic, key, value);

        try {
            Future<RecordMetadata> future = producer.send(record);
            RecordMetadata metadata = future.get();
            sentCount.incrementAndGet();
            log.debug("Command sent synchronously to shard {} partition {} offset {}",
                    shardId, metadata.partition(), metadata.offset());
            return metadata;
        } catch (Exception e) {
            errorCount.incrementAndGet();
            handleSendError(command, e);
            throw e;
        }
    }

    /**
     * Send a raw JSON message to this shard.
     */
    public void sendRawMessage(String key, String jsonMessage) {
        if (closed) {
            throw new IllegalStateException("ShardClient is closed");
        }

        ProducerRecord<String, String> record = new ProducerRecord<>(topic, key, jsonMessage);

        producer.send(record, (metadata, exception) -> {
            if (exception != null) {
                errorCount.incrementAndGet();
                log.error("Failed to send raw message to shard {}: {}", shardId, exception.getMessage());
            } else {
                sentCount.incrementAndGet();
            }
        });
    }

    /**
     * Extract partition key from command (symbol or account ID).
     */
    private String extractPartitionKey(Command command) {
        if (command.isOrderCommand() && command.getOrder() != null) {
            return command.getOrder().getSymbol();
        }
        if (command.getData() != null) {
            // Try to extract symbol from other command types
            try {
                if (command.getPosition() != null) {
                    return command.getPosition().getSymbol();
                }
            } catch (ClassCastException ignored) {
                // Not a position command
            }
        }
        return shardId; // Default to shard ID
    }

    /**
     * Handle send errors with logging and potential retry logic.
     */
    private void handleSendError(Command command, Exception e) {
        log.error("Failed to send command to shard {}: code={}, error={}",
                shardId, command.getCode(), e.getMessage());
        // TODO: Implement retry queue or dead letter queue
    }

    /**
     * Flush any pending messages.
     */
    public void flush() {
        producer.flush();
    }

    /**
     * Get the shard ID.
     */
    public String getShardId() {
        return shardId;
    }

    /**
     * Get the topic name.
     */
    public String getTopic() {
        return topic;
    }

    /**
     * Get the number of successfully sent messages.
     */
    public long getSentCount() {
        return sentCount.get();
    }

    /**
     * Get the number of failed messages.
     */
    public long getErrorCount() {
        return errorCount.get();
    }

    @Override
    public void close() {
        if (!closed) {
            closed = true;
            producer.flush();
            producer.close();
            log.info("ShardClient for shard {} closed. Sent: {}, Errors: {}",
                    shardId, sentCount.get(), errorCount.get());
        }
    }
}

package com.sotatek.future.router;

import com.sotatek.future.engine.ShardHealthStatus;
import com.sotatek.future.engine.ShardedMatchingEngine;
import io.prometheus.client.Counter;
import io.prometheus.client.Gauge;
import io.prometheus.client.Histogram;
import io.prometheus.client.exporter.HTTPServer;
import java.io.IOException;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import lombok.extern.slf4j.Slf4j;

/**
 * Exports shard metrics to Prometheus for monitoring.
 */
@Slf4j
public class ShardMetricsExporter implements AutoCloseable {

    private static final String NAMESPACE = "matching_engine";

    // Counters
    private final Counter ordersProcessed;
    private final Counter tradesExecuted;
    private final Counter commandsReceived;
    private final Counter syncMessagesSent;
    private final Counter syncMessagesFailed;

    // Gauges
    private final Gauge activeOrders;
    private final Gauge assignedSymbols;
    private final Gauge memoryUsed;
    private final Gauge memoryMax;
    private final Gauge cpuUsage;
    private final Gauge standbyLag;
    private final Gauge pendingCommands;
    private final Gauge shardStatus;

    // Histograms
    private final Histogram processingLatency;
    private final Histogram matchingLatency;

    private final String shardId;
    private final ShardedMatchingEngine engine;
    private final ScheduledExecutorService scheduler;
    private HTTPServer httpServer;

    private volatile boolean closed = false;

    public ShardMetricsExporter(String shardId, ShardedMatchingEngine engine) {
        this.shardId = shardId;
        this.engine = engine;
        this.scheduler = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "MetricsExporter-" + shardId);
            t.setDaemon(true);
            return t;
        });

        // Initialize Prometheus metrics
        ordersProcessed = Counter.build()
                .namespace(NAMESPACE)
                .name("orders_processed_total")
                .help("Total number of orders processed")
                .labelNames("shard_id", "symbol", "side", "type")
                .register();

        tradesExecuted = Counter.build()
                .namespace(NAMESPACE)
                .name("trades_executed_total")
                .help("Total number of trades executed")
                .labelNames("shard_id", "symbol")
                .register();

        commandsReceived = Counter.build()
                .namespace(NAMESPACE)
                .name("commands_received_total")
                .help("Total number of commands received")
                .labelNames("shard_id", "command_type")
                .register();

        syncMessagesSent = Counter.build()
                .namespace(NAMESPACE)
                .name("sync_messages_sent_total")
                .help("Total number of sync messages sent to standby")
                .labelNames("shard_id")
                .register();

        syncMessagesFailed = Counter.build()
                .namespace(NAMESPACE)
                .name("sync_messages_failed_total")
                .help("Total number of failed sync messages")
                .labelNames("shard_id")
                .register();

        activeOrders = Gauge.build()
                .namespace(NAMESPACE)
                .name("active_orders")
                .help("Current number of active orders")
                .labelNames("shard_id", "symbol")
                .register();

        assignedSymbols = Gauge.build()
                .namespace(NAMESPACE)
                .name("assigned_symbols")
                .help("Number of symbols assigned to this shard")
                .labelNames("shard_id")
                .register();

        memoryUsed = Gauge.build()
                .namespace(NAMESPACE)
                .name("memory_used_bytes")
                .help("Memory currently in use")
                .labelNames("shard_id")
                .register();

        memoryMax = Gauge.build()
                .namespace(NAMESPACE)
                .name("memory_max_bytes")
                .help("Maximum available memory")
                .labelNames("shard_id")
                .register();

        cpuUsage = Gauge.build()
                .namespace(NAMESPACE)
                .name("cpu_usage_ratio")
                .help("CPU usage ratio (0-1)")
                .labelNames("shard_id")
                .register();

        standbyLag = Gauge.build()
                .namespace(NAMESPACE)
                .name("standby_lag_seconds")
                .help("Replication lag to standby in seconds")
                .labelNames("shard_id")
                .register();

        pendingCommands = Gauge.build()
                .namespace(NAMESPACE)
                .name("pending_commands")
                .help("Number of commands waiting to be processed")
                .labelNames("shard_id")
                .register();

        shardStatus = Gauge.build()
                .namespace(NAMESPACE)
                .name("shard_status")
                .help("Shard status (1=active, 0=inactive)")
                .labelNames("shard_id", "role")
                .register();

        processingLatency = Histogram.build()
                .namespace(NAMESPACE)
                .name("processing_latency_seconds")
                .help("Command processing latency in seconds")
                .labelNames("shard_id", "command_type")
                .buckets(0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0)
                .register();

        matchingLatency = Histogram.build()
                .namespace(NAMESPACE)
                .name("matching_latency_seconds")
                .help("Order matching latency in seconds")
                .labelNames("shard_id")
                .buckets(0.0001, 0.0005, 0.001, 0.005, 0.01, 0.025, 0.05, 0.1)
                .register();

        log.info("ShardMetricsExporter initialized for shard {}", shardId);
    }

    /**
     * Start the metrics HTTP server and periodic updates.
     */
    public void start(int port) throws IOException {
        httpServer = new HTTPServer(port);
        scheduler.scheduleAtFixedRate(this::updateMetrics, 0, 5, TimeUnit.SECONDS);
        log.info("Metrics server started on port {} for shard {}", port, shardId);
    }

    /**
     * Update all metrics from the engine.
     */
    private void updateMetrics() {
        if (closed) return;

        try {
            ShardHealthStatus status = engine.getHealthStatus();

            // Update gauges
            assignedSymbols.labels(shardId).set(status.getAssignedSymbols());
            memoryUsed.labels(shardId).set(status.getMemoryUsed());
            memoryMax.labels(shardId).set(status.getMemoryMax());
            pendingCommands.labels(shardId).set(status.getPendingCommandsQueueSize());
            standbyLag.labels(shardId).set(status.getStandbyLagMs() / 1000.0);

            // Update shard status
            boolean isActive = status.isHealthy();
            shardStatus.labels(shardId, status.getRole().toString()).set(isActive ? 1 : 0);

            log.debug("Updated metrics for shard {}: {}", shardId, status.toSummary());

        } catch (Exception e) {
            log.error("Error updating metrics for shard {}: {}", shardId, e.getMessage());
        }
    }

    /**
     * Record an order processed.
     */
    public void recordOrderProcessed(String symbol, String side, String type) {
        ordersProcessed.labels(shardId, symbol, side, type).inc();
    }

    /**
     * Record a trade executed.
     */
    public void recordTradeExecuted(String symbol) {
        tradesExecuted.labels(shardId, symbol).inc();
    }

    /**
     * Record a command received.
     */
    public void recordCommandReceived(String commandType) {
        commandsReceived.labels(shardId, commandType).inc();
    }

    /**
     * Record command processing time.
     */
    public void recordProcessingLatency(String commandType, double seconds) {
        processingLatency.labels(shardId, commandType).observe(seconds);
    }

    /**
     * Record order matching time.
     */
    public void recordMatchingLatency(double seconds) {
        matchingLatency.labels(shardId).observe(seconds);
    }

    /**
     * Record sync message sent.
     */
    public void recordSyncSent() {
        syncMessagesSent.labels(shardId).inc();
    }

    /**
     * Record sync message failed.
     */
    public void recordSyncFailed() {
        syncMessagesFailed.labels(shardId).inc();
    }

    /**
     * Update active orders count for a symbol.
     */
    public void updateActiveOrders(String symbol, long count) {
        activeOrders.labels(shardId, symbol).set(count);
    }

    @Override
    public void close() {
        if (!closed) {
            closed = true;
            scheduler.shutdown();
            if (httpServer != null) {
                httpServer.stop();
            }
            log.info("ShardMetricsExporter closed for shard {}", shardId);
        }
    }
}

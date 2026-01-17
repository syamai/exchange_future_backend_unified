package com.sotatek.future.engine;

import com.sotatek.future.router.ShardInfo.ShardRole;
import com.sotatek.future.router.ShardInfo.ShardStatus;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

/**
 * Health status information for a shard instance.
 * Used for monitoring and failover decisions.
 */
@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ShardHealthStatus {

    /**
     * Unique identifier for this shard.
     */
    private String shardId;

    /**
     * Current role (PRIMARY or STANDBY).
     */
    private ShardRole role;

    /**
     * Current operational status.
     */
    private ShardStatus status;

    /**
     * Number of symbols assigned to this shard.
     */
    private int assignedSymbols;

    /**
     * Number of active (open) orders.
     */
    private long activeOrders;

    /**
     * Number of active matchers.
     */
    private int matcherCount;

    /**
     * Timestamp of last processed command.
     */
    private long lastProcessedTime;

    /**
     * Total commands processed since startup.
     */
    private long commandsProcessed;

    /**
     * Orders processed per second (recent average).
     */
    private double ordersPerSecond;

    /**
     * Trades executed per second (recent average).
     */
    private double tradesPerSecond;

    /**
     * Average matching latency in milliseconds.
     */
    private double avgProcessingTimeMs;

    /**
     * 99th percentile matching latency in milliseconds.
     */
    private double p99ProcessingTimeMs;

    /**
     * Current CPU usage percentage.
     */
    private double cpuUsage;

    /**
     * Memory currently in use (bytes).
     */
    private long memoryUsed;

    /**
     * Maximum available memory (bytes).
     */
    private long memoryMax;

    /**
     * Replication lag for standby (milliseconds).
     */
    private long standbyLagMs;

    /**
     * Queue depth of pending commands.
     */
    private int pendingCommandsQueueSize;

    /**
     * Uptime in milliseconds.
     */
    private long uptimeMs;

    /**
     * Check if the shard is healthy.
     */
    public boolean isHealthy() {
        return status == ShardStatus.ACTIVE
                && (System.currentTimeMillis() - lastProcessedTime) < 30000; // 30 seconds threshold
    }

    /**
     * Check if the shard is ready to accept requests.
     */
    public boolean isReady() {
        return status == ShardStatus.ACTIVE && role == ShardRole.PRIMARY;
    }

    /**
     * Get memory usage percentage.
     */
    public double getMemoryUsagePercent() {
        if (memoryMax == 0) return 0;
        return (double) memoryUsed / memoryMax * 100;
    }

    /**
     * Get time since last command was processed.
     */
    public long getTimeSinceLastProcessed() {
        return System.currentTimeMillis() - lastProcessedTime;
    }

    /**
     * Create a summary string for logging.
     */
    public String toSummary() {
        return String.format(
                "Shard[%s] role=%s status=%s symbols=%d orders=%d processed=%d avgMs=%.2f mem=%.1f%%",
                shardId, role, status, assignedSymbols, activeOrders,
                commandsProcessed, avgProcessingTimeMs, getMemoryUsagePercent()
        );
    }
}

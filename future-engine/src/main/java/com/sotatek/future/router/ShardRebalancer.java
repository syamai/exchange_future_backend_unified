package com.sotatek.future.router;

import com.sotatek.future.entity.Order;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;
import lombok.Builder;
import lombok.Data;
import lombok.extern.slf4j.Slf4j;

/**
 * Handles automatic rebalancing of symbols across shards based on load metrics.
 */
@Slf4j
public class ShardRebalancer implements AutoCloseable {

    private static final double DEFAULT_LOAD_THRESHOLD = 0.8; // 80%
    private static final long DEFAULT_CHECK_INTERVAL_MS = 60000; // 1 minute

    private final OrderRouter orderRouter;
    private final Map<String, ShardMetrics> shardMetricsMap;
    private final Map<String, SymbolMetrics> symbolMetricsMap;
    private final ScheduledExecutorService scheduler;

    private double loadThreshold = DEFAULT_LOAD_THRESHOLD;
    private long checkIntervalMs = DEFAULT_CHECK_INTERVAL_MS;
    private final AtomicBoolean rebalancing = new AtomicBoolean(false);

    private volatile boolean closed = false;

    /**
     * Callback interface for rebalancing events.
     */
    public interface RebalanceCallback {
        /**
         * Export orders from source shard for migration.
         */
        List<Order> exportOrders(String shardId, String symbol);

        /**
         * Import orders to target shard.
         */
        void importOrders(String shardId, String symbol, List<Order> orders);

        /**
         * Remove symbol from source shard.
         */
        void removeSymbol(String shardId, String symbol);

        /**
         * Add symbol to target shard.
         */
        void addSymbol(String shardId, String symbol);
    }

    private RebalanceCallback callback;

    public ShardRebalancer(OrderRouter orderRouter) {
        this.orderRouter = orderRouter;
        this.shardMetricsMap = new HashMap<>();
        this.symbolMetricsMap = new HashMap<>();
        this.scheduler = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "ShardRebalancer");
            t.setDaemon(true);
            return t;
        });
    }

    /**
     * Set the rebalance callback handler.
     */
    public void setCallback(RebalanceCallback callback) {
        this.callback = callback;
    }

    /**
     * Start automatic rebalancing checks.
     */
    public void start() {
        scheduler.scheduleAtFixedRate(
                this::checkAndRebalance,
                checkIntervalMs,
                checkIntervalMs,
                TimeUnit.MILLISECONDS
        );
        log.info("ShardRebalancer started with interval {}ms, threshold {}%",
                checkIntervalMs, loadThreshold * 100);
    }

    /**
     * Stop automatic rebalancing.
     */
    public void stop() {
        scheduler.shutdown();
        try {
            if (!scheduler.awaitTermination(10, TimeUnit.SECONDS)) {
                scheduler.shutdownNow();
            }
        } catch (InterruptedException e) {
            scheduler.shutdownNow();
            Thread.currentThread().interrupt();
        }
        log.info("ShardRebalancer stopped");
    }

    /**
     * Update metrics for a shard.
     */
    public void updateShardMetrics(String shardId, ShardMetrics metrics) {
        shardMetricsMap.put(shardId, metrics);
    }

    /**
     * Update metrics for a symbol.
     */
    public void updateSymbolMetrics(String symbol, SymbolMetrics metrics) {
        symbolMetricsMap.put(symbol, metrics);
    }

    /**
     * Check shard loads and rebalance if necessary.
     */
    public void checkAndRebalance() {
        if (closed || !rebalancing.compareAndSet(false, true)) {
            return;
        }

        try {
            log.debug("Checking shard loads for rebalancing...");

            for (Map.Entry<String, ShardMetrics> entry : shardMetricsMap.entrySet()) {
                String shardId = entry.getKey();
                ShardMetrics metrics = entry.getValue();

                if (isOverloaded(metrics)) {
                    log.info("Shard {} is overloaded (CPU: {}%, OrderRate: {}/s)",
                            shardId, metrics.getCpuUsage() * 100, metrics.getOrdersPerSecond());

                    // Find a symbol to move
                    String symbolToMove = findLowestTrafficSymbol(shardId);
                    if (symbolToMove == null) {
                        log.warn("No suitable symbol to move from shard {}", shardId);
                        continue;
                    }

                    // Find target shard
                    String targetShard = findLowestLoadShard(shardId);
                    if (targetShard == null) {
                        log.warn("No suitable target shard for rebalancing");
                        continue;
                    }

                    // Perform rebalancing
                    rebalanceSymbol(symbolToMove, shardId, targetShard);
                }
            }
        } catch (Exception e) {
            log.error("Error during rebalancing check: {}", e.getMessage(), e);
        } finally {
            rebalancing.set(false);
        }
    }

    /**
     * Check if a shard is overloaded.
     */
    private boolean isOverloaded(ShardMetrics metrics) {
        return metrics.getCpuUsage() > loadThreshold
                || (metrics.getMaxOrdersPerSecond() > 0
                && metrics.getOrdersPerSecond() > metrics.getMaxOrdersPerSecond() * loadThreshold);
    }

    /**
     * Find the symbol with lowest traffic in a shard.
     */
    private String findLowestTrafficSymbol(String shardId) {
        return orderRouter.getSymbolsForShard(shardId).stream()
                .filter(s -> symbolMetricsMap.containsKey(s))
                .min(Comparator.comparingDouble(s -> symbolMetricsMap.get(s).getOrdersPerSecond()))
                .orElse(null);
    }

    /**
     * Find the shard with lowest load.
     */
    private String findLowestLoadShard(String excludeShardId) {
        return shardMetricsMap.entrySet().stream()
                .filter(e -> !e.getKey().equals(excludeShardId))
                .filter(e -> e.getValue().getCpuUsage() < loadThreshold * 0.7) // Ensure headroom
                .min(Comparator.comparingDouble(e -> e.getValue().getCpuUsage()))
                .map(Map.Entry::getKey)
                .orElse(null);
    }

    /**
     * Rebalance a symbol from one shard to another.
     */
    public void rebalanceSymbol(String symbol, String fromShard, String toShard) {
        if (callback == null) {
            log.error("No rebalance callback configured");
            return;
        }

        log.info("Rebalancing symbol {} from {} to {}", symbol, fromShard, toShard);

        try {
            // 1. Pause the symbol (reject new orders)
            orderRouter.pauseSymbol(symbol);
            log.debug("Paused symbol {}", symbol);

            // 2. Export pending orders from source shard
            List<Order> pendingOrders = callback.exportOrders(fromShard, symbol);
            log.debug("Exported {} orders for symbol {}", pendingOrders.size(), symbol);

            // 3. Add symbol to target shard
            callback.addSymbol(toShard, symbol);

            // 4. Import orders to target shard
            callback.importOrders(toShard, symbol, pendingOrders);
            log.debug("Imported {} orders to shard {}", pendingOrders.size(), toShard);

            // 5. Update routing table
            orderRouter.updateMapping(symbol, toShard);

            // 6. Remove symbol from source shard
            callback.removeSymbol(fromShard, symbol);

            // 7. Resume symbol
            orderRouter.resumeSymbol(symbol);

            log.info("Successfully rebalanced symbol {} from {} to {} ({} orders migrated)",
                    symbol, fromShard, toShard, pendingOrders.size());

        } catch (Exception e) {
            log.error("Failed to rebalance symbol {}: {}", symbol, e.getMessage(), e);
            // Try to resume the symbol even on failure
            orderRouter.resumeSymbol(symbol);
            throw new RuntimeException("Rebalancing failed for symbol " + symbol, e);
        }
    }

    /**
     * Manually trigger rebalancing of a specific symbol.
     */
    public void manualRebalance(String symbol, String targetShard) {
        ShardInfo currentShard = orderRouter.getShardForSymbol(symbol);
        if (currentShard == null) {
            throw new IllegalArgumentException("Symbol not found: " + symbol);
        }
        if (currentShard.getShardId().equals(targetShard)) {
            log.info("Symbol {} is already on shard {}", symbol, targetShard);
            return;
        }
        rebalanceSymbol(symbol, currentShard.getShardId(), targetShard);
    }

    /**
     * Get current rebalancing status.
     */
    public boolean isRebalancing() {
        return rebalancing.get();
    }

    /**
     * Set load threshold for triggering rebalancing.
     */
    public void setLoadThreshold(double threshold) {
        this.loadThreshold = threshold;
    }

    /**
     * Set check interval in milliseconds.
     */
    public void setCheckIntervalMs(long intervalMs) {
        this.checkIntervalMs = intervalMs;
    }

    @Override
    public void close() {
        if (!closed) {
            closed = true;
            stop();
            log.info("ShardRebalancer closed");
        }
    }

    /**
     * Metrics for a shard.
     */
    @Data
    @Builder
    public static class ShardMetrics {
        private String shardId;
        private double cpuUsage;
        private double memoryUsage;
        private double ordersPerSecond;
        private double tradesPerSecond;
        private double maxOrdersPerSecond;
        private int symbolCount;
        private long activeOrders;
        private long timestamp;
    }

    /**
     * Metrics for a symbol.
     */
    @Data
    @Builder
    public static class SymbolMetrics {
        private String symbol;
        private double ordersPerSecond;
        private double tradesPerSecond;
        private long activeOrders;
        private long timestamp;
    }
}

package com.sotatek.future.engine;

import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.Order;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;
import com.sotatek.future.router.ShardInfo;
import com.sotatek.future.router.ShardInfo.ShardRole;
import com.sotatek.future.service.OrderService;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.concurrent.CopyOnWriteArraySet;
import java.util.stream.Collectors;
import lombok.Getter;
import lombok.extern.slf4j.Slf4j;

/**
 * Sharded Matching Engine that processes only assigned symbols.
 * Each shard runs as an independent JVM instance.
 */
@Slf4j
public class ShardedMatchingEngine extends MatchingEngine {

    @Getter
    private final String shardId;

    @Getter
    private final Set<String> assignedSymbols;

    @Getter
    private final ShardRole role;

    private StandbySync standbySync;
    private ShardHealthStatus healthStatus;
    private long lastProcessedTime;
    private long commandsProcessed;

    /**
     * Create a new sharded matching engine.
     *
     * @param shardId         Unique identifier for this shard
     * @param symbols         Set of symbols this shard is responsible for
     * @param role            Role of this instance (PRIMARY or STANDBY)
     */
    public ShardedMatchingEngine(String shardId, Set<String> symbols, ShardRole role) {
        super();
        this.shardId = shardId;
        this.assignedSymbols = new CopyOnWriteArraySet<>(symbols);
        this.role = role;
        this.lastProcessedTime = System.currentTimeMillis();
        this.commandsProcessed = 0;

        // Initialize health status in constructor so it's always available
        this.healthStatus = ShardHealthStatus.builder()
                .shardId(shardId)
                .role(role)
                .status(ShardInfo.ShardStatus.ACTIVE)
                .assignedSymbols(symbols.size())
                .build();

        log.info("ShardedMatchingEngine created: shardId={}, role={}, symbols={}",
                shardId, role, symbols);
    }

    /**
     * Initialize with sharding configuration.
     */
    public void initialize(ShardedMatchingEngineConfig config) throws InvalidMatchingEngineConfigException {
        super.initialize(config);

        if (role == ShardRole.PRIMARY) {
            // Initialize standby synchronization for primary nodes
            this.standbySync = new StandbySync(shardId, config.getStandbySyncTopic(),
                    config.getKafkaBootstrapServers());
            log.info("Standby sync initialized for shard {}", shardId);
        }

        this.healthStatus = ShardHealthStatus.builder()
                .shardId(shardId)
                .role(role)
                .status(ShardInfo.ShardStatus.ACTIVE)
                .assignedSymbols(assignedSymbols.size())
                .build();
    }

    /**
     * Override onNewData to filter commands for assigned symbols only.
     */
    @Override
    public long onNewData(Command command) {
        // Global commands (like INITIALIZE_ENGINE, START_ENGINE) are processed by all shards
        if (isGlobalCommand(command)) {
            return super.onNewData(command);
        }

        // Check if this command is for a symbol assigned to this shard
        String symbol = extractSymbol(command);
        if (symbol != null && !assignedSymbols.contains(symbol)) {
            log.debug("Ignoring command for unassigned symbol: {} (shard: {})", symbol, shardId);
            return commands.size();
        }

        return super.onNewData(command);
    }

    /**
     * Check if a command is a global command that all shards should process.
     */
    private boolean isGlobalCommand(Command command) {
        CommandCode code = command.getCode();
        return code == CommandCode.INITIALIZE_ENGINE
                || code == CommandCode.START_ENGINE
                || code == CommandCode.STOP_ENGINE
                || code == CommandCode.LOAD_LEVERAGE_MARGIN
                || code == CommandCode.LOAD_TRADING_RULE
                || code == CommandCode.PAY_FUNDING
                || code == CommandCode.LOAD_BOT_ACCOUNT;
    }

    /**
     * Extract symbol from command.
     */
    private String extractSymbol(Command command) {
        if (command.isOrderCommand() && command.getOrder() != null) {
            return command.getOrder().getSymbol();
        }
        try {
            if (command.getPosition() != null) {
                return command.getPosition().getSymbol();
            }
        } catch (ClassCastException ignored) {
        }
        try {
            if (command.getInstrument() != null) {
                return command.getInstrument().getSymbol();
            }
        } catch (ClassCastException ignored) {
        }
        return null;
    }

    /**
     * Process tick with standby synchronization.
     */
    protected void onTickWithSync(Command command) {
        long startTime = System.currentTimeMillis();

        try {
            // Process the command
            onTick();

            // Sync state to standby if primary
            if (role == ShardRole.PRIMARY && standbySync != null) {
                standbySync.syncCommand(command);
            }

            commandsProcessed++;
            lastProcessedTime = System.currentTimeMillis();

        } catch (Exception e) {
            log.error("Error processing command in shard {}: {}", shardId, e.getMessage(), e);
            throw e;
        } finally {
            long processingTime = System.currentTimeMillis() - startTime;
            updateHealthMetrics(processingTime);
        }
    }

    /**
     * Add a new symbol to this shard (for rebalancing).
     */
    public void addSymbol(String symbol) {
        if (assignedSymbols.add(symbol)) {
            // Initialize matcher for the new symbol
            Matcher matcher = new Matcher(symbol);
            matchers.put(symbol, matcher);

            // Initialize trigger for the new symbol
            Trigger trigger = new Trigger(symbol, this);
            triggers.put(symbol, trigger);

            log.info("Added symbol {} to shard {}", symbol, shardId);
            healthStatus.setAssignedSymbols(assignedSymbols.size());
        }
    }

    /**
     * Remove a symbol from this shard (for rebalancing).
     * Returns the pending orders for migration.
     */
    public List<Order> removeSymbol(String symbol) {
        if (!assignedSymbols.contains(symbol)) {
            log.warn("Symbol {} not assigned to shard {}", symbol, shardId);
            return List.of();
        }

        // Get pending orders before removal from the matcher's order queues
        Matcher matcher = matchers.get(symbol);
        List<Order> pendingOrders = new java.util.ArrayList<>();
        if (matcher != null) {
            pendingOrders.addAll(matcher.getPendingOrdersQueue(com.sotatek.future.enums.OrderSide.BUY));
            pendingOrders.addAll(matcher.getPendingOrdersQueue(com.sotatek.future.enums.OrderSide.SELL));

            // Cancel all orders for this symbol
            for (Order order : pendingOrders) {
                matcher.cancelOrder(order);
            }
            matchers.remove(symbol);
        }

        // Remove trigger
        triggers.remove(symbol);

        // Remove from assigned symbols
        assignedSymbols.remove(symbol);

        log.info("Removed symbol {} from shard {}. Migrating {} orders",
                symbol, shardId, pendingOrders.size());
        healthStatus.setAssignedSymbols(assignedSymbols.size());

        return pendingOrders;
    }

    /**
     * Import orders for a symbol (during rebalancing).
     */
    public void importOrders(String symbol, List<Order> orders) {
        addSymbol(symbol);

        OrderService orderService = OrderService.getInstance();
        Matcher matcher = matchers.get(symbol);

        for (Order order : orders) {
            orderService.insert(order);
            if (order.isLimitOrder() && order.canBeMatched()) {
                matcher.getPendingOrdersQueue(order.getSide()).add(order);
            }
        }

        log.info("Imported {} orders for symbol {} to shard {}", orders.size(), symbol, shardId);
    }

    /**
     * Get health status for monitoring.
     */
    public ShardHealthStatus getHealthStatus() {
        return ShardHealthStatus.builder()
                .shardId(shardId)
                .role(role)
                .status(healthStatus.getStatus())
                .assignedSymbols(assignedSymbols.size())
                .activeOrders(getActiveOrderCount())
                .matcherCount(matchers.size())
                .lastProcessedTime(lastProcessedTime)
                .commandsProcessed(commandsProcessed)
                .memoryUsed(Runtime.getRuntime().totalMemory() - Runtime.getRuntime().freeMemory())
                .memoryMax(Runtime.getRuntime().maxMemory())
                .avgProcessingTimeMs(healthStatus.getAvgProcessingTimeMs())
                .build();
    }

    /**
     * Get the count of active orders across all assigned symbols.
     */
    private long getActiveOrderCount() {
        return matchers.values().stream()
                .mapToLong(m -> m.getPendingOrdersQueue(com.sotatek.future.enums.OrderSide.BUY).size()
                        + m.getPendingOrdersQueue(com.sotatek.future.enums.OrderSide.SELL).size())
                .sum();
    }

    /**
     * Update health metrics after processing.
     */
    private void updateHealthMetrics(long processingTime) {
        // Simple moving average for processing time
        double currentAvg = healthStatus.getAvgProcessingTimeMs();
        double newAvg = (currentAvg * 0.9) + (processingTime * 0.1);
        healthStatus.setAvgProcessingTimeMs(newAvg);
    }

    /**
     * Check if this shard handles a specific symbol.
     */
    public boolean handlesSymbol(String symbol) {
        return assignedSymbols.contains(symbol);
    }

    /**
     * Get all assigned symbols.
     */
    public Set<String> getAssignedSymbols() {
        return new HashSet<>(assignedSymbols);
    }

    /**
     * Shutdown the shard gracefully.
     */
    public void shutdown() {
        log.info("Shutting down shard {}", shardId);

        if (standbySync != null) {
            standbySync.close();
        }

        // Let parent handle the rest
        // Note: The parent class doesn't have a shutdown method,
        // so we just set stopEngine flag via STOP_ENGINE command
    }
}

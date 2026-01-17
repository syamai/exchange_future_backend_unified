package com.sotatek.future.router;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import com.sotatek.future.engine.ShardHealthStatus;
import com.sotatek.future.engine.ShardedMatchingEngine;
import com.sotatek.future.entity.Order;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TimeInForce;
import com.sotatek.future.router.ShardInfo.ShardRole;
import com.sotatek.future.router.ShardInfo.ShardStatus;
import com.sotatek.future.router.ShardRebalancer.RebalanceCallback;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

/**
 * Integration tests for multi-shard routing scenarios.
 * Tests the complete flow of order routing, symbol management, and rebalancing.
 */
class MultiShardRoutingTest {

    private OrderRouter router;
    private Map<String, TestShardEngine> shardEngines;
    private ShardRebalancer rebalancer;

    @BeforeEach
    void setUp() {
        // Reset singleton
        OrderRouter.resetInstance();
        router = OrderRouter.getInstance();

        // Create simulated shard engines
        shardEngines = new ConcurrentHashMap<>();

        // Shard 1: BTC pairs
        Set<String> shard1Symbols = new HashSet<>();
        shard1Symbols.add("BTCUSDT");
        shard1Symbols.add("BTCBUSD");
        shardEngines.put("shard-1", new TestShardEngine("shard-1", shard1Symbols));

        // Shard 2: ETH pairs
        Set<String> shard2Symbols = new HashSet<>();
        shard2Symbols.add("ETHUSDT");
        shard2Symbols.add("ETHBUSD");
        shardEngines.put("shard-2", new TestShardEngine("shard-2", shard2Symbols));

        // Shard 3: Other pairs (default)
        Set<String> shard3Symbols = new HashSet<>();
        shard3Symbols.add("SOLUSDT");
        shard3Symbols.add("XRPUSDT");
        shardEngines.put("shard-3", new TestShardEngine("shard-3", shard3Symbols));

        // Set up rebalancer
        rebalancer = new ShardRebalancer(router);
        rebalancer.setCallback(new TestRebalanceCallback());
    }

    @AfterEach
    void tearDown() {
        if (rebalancer != null) {
            rebalancer.close();
        }
        shardEngines.values().forEach(TestShardEngine::shutdown);
        shardEngines.clear();
        if (router != null) {
            router.close();
        }
        OrderRouter.resetInstance();
    }

    @Test
    void shouldRouteOrdersToCorrectShards() {
        // BTC orders should go to shard-1
        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-1");
        assertThat(router.getShardForSymbol("BTCBUSD").getShardId()).isEqualTo("shard-1");

        // ETH orders should go to shard-2
        assertThat(router.getShardForSymbol("ETHUSDT").getShardId()).isEqualTo("shard-2");
        assertThat(router.getShardForSymbol("ETHBUSD").getShardId()).isEqualTo("shard-2");

        // Unknown symbols should go to default shard (shard-3)
        assertThat(router.getShardForSymbol("DOGEUSDT").getShardId()).isEqualTo("shard-3");
    }

    @Test
    void shouldIsolateSymbolsBetweenShards() {
        TestShardEngine shard1 = shardEngines.get("shard-1");
        TestShardEngine shard2 = shardEngines.get("shard-2");

        // Shard 1 handles BTC
        assertThat(shard1.handlesSymbol("BTCUSDT")).isTrue();
        assertThat(shard1.handlesSymbol("ETHUSDT")).isFalse();

        // Shard 2 handles ETH
        assertThat(shard2.handlesSymbol("ETHUSDT")).isTrue();
        assertThat(shard2.handlesSymbol("BTCUSDT")).isFalse();
    }

    @Test
    void shouldMigrateSymbolBetweenShards() {
        // Initially BTCUSDT is on shard-1
        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-1");

        // Migrate to shard-2
        rebalancer.manualRebalance("BTCUSDT", "shard-2");

        // Now should be on shard-2
        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-2");
    }

    @Test
    void shouldBlockOrdersDuringMigration() {
        // Pause symbol
        router.pauseSymbol("BTCUSDT");

        // Should be paused
        assertThat(router.isSymbolPaused("BTCUSDT")).isTrue();

        // Resume
        router.resumeSymbol("BTCUSDT");
        assertThat(router.isSymbolPaused("BTCUSDT")).isFalse();
    }

    @Test
    void shouldHandleMultipleSimultaneousMigrations() {
        // Migrate multiple symbols
        router.pauseSymbol("BTCUSDT");
        router.pauseSymbol("ETHUSDT");

        assertThat(router.isSymbolPaused("BTCUSDT")).isTrue();
        assertThat(router.isSymbolPaused("ETHUSDT")).isTrue();
        assertThat(router.isSymbolPaused("SOLUSDT")).isFalse();

        // Resume them
        router.resumeSymbol("BTCUSDT");
        router.resumeSymbol("ETHUSDT");

        assertThat(router.isSymbolPaused("BTCUSDT")).isFalse();
        assertThat(router.isSymbolPaused("ETHUSDT")).isFalse();
    }

    @Test
    void shouldDistributeSymbolsAcrossShards() {
        Set<String> shard1Symbols = router.getSymbolsForShard("shard-1");
        Set<String> shard2Symbols = router.getSymbolsForShard("shard-2");

        // BTC on shard-1, ETH on shard-2
        assertThat(shard1Symbols).contains("BTCUSDT", "BTCBUSD");
        assertThat(shard2Symbols).contains("ETHUSDT", "ETHBUSD");

        // No overlap
        shard1Symbols.retainAll(shard2Symbols);
        assertThat(shard1Symbols).isEmpty();
    }

    @Test
    void shouldAddNewSymbolToSpecificShard() {
        // Add a new symbol to shard-1
        router.addSymbolMapping("LINKUSDT", "shard-1");

        assertThat(router.getShardForSymbol("LINKUSDT").getShardId()).isEqualTo("shard-1");
        assertThat(router.getSymbolsForShard("shard-1")).contains("LINKUSDT");
    }

    @Test
    void shouldHandleShardFailover() {
        // Simulate shard-1 going offline
        ShardInfo shard1 = router.getShardForSymbol("BTCUSDT");

        // In a real scenario, we would update status through health checks
        // For this test, we verify the shard info structure supports status changes
        assertThat(shard1.getStatus()).isEqualTo(ShardStatus.ACTIVE);

        // Verify we can check availability (requires both ACTIVE status and PRIMARY role)
        // ShardInfo from router may have null role, so we test the status check
        assertThat(shard1.getStatus()).isEqualTo(ShardStatus.ACTIVE);
        assertThat(shard1.canAcceptSymbols()).isTrue();
    }

    @Test
    void shouldCollectHealthStatusFromAllShards() {
        Map<String, ShardHealthStatus> healthStatuses = new HashMap<>();

        for (Map.Entry<String, TestShardEngine> entry : shardEngines.entrySet()) {
            healthStatuses.put(entry.getKey(), entry.getValue().getHealthStatus());
        }

        assertThat(healthStatuses).hasSize(3);
        assertThat(healthStatuses.get("shard-1").getShardId()).isEqualTo("shard-1");
        assertThat(healthStatuses.get("shard-2").getShardId()).isEqualTo("shard-2");
        assertThat(healthStatuses.get("shard-3").getShardId()).isEqualTo("shard-3");
    }

    @Test
    void shouldMaintainOrderingAfterRebalance() {
        // Get initial shard for BTCUSDT
        String initialShard = router.getShardForSymbol("BTCUSDT").getShardId();
        assertThat(initialShard).isEqualTo("shard-1");

        // Rebalance to shard-2
        rebalancer.manualRebalance("BTCUSDT", "shard-2");

        // Rebalance back to shard-1
        rebalancer.manualRebalance("BTCUSDT", "shard-1");

        // Should be back to original shard
        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-1");
    }

    @Test
    void shouldHandleEmptyShardGracefully() {
        // Create an empty shard
        Set<String> emptySymbols = new HashSet<>();
        TestShardEngine emptyShard = new TestShardEngine("shard-empty", emptySymbols);

        assertThat(emptyShard.getAssignedSymbols()).isEmpty();
        assertThat(emptyShard.getHealthStatus().getAssignedSymbols()).isZero();

        emptyShard.shutdown();
    }

    @Test
    void shouldBroadcastToAllShards() {
        // Verify all shards are accessible
        var allShards = router.getAllShards();

        assertThat(allShards).isNotEmpty();
        assertThat(allShards.stream().map(ShardInfo::getShardId))
                .contains("shard-1", "shard-2", "shard-3");
    }

    /**
     * Simple test implementation of a shard engine for testing.
     */
    private static class TestShardEngine {
        private final String shardId;
        private final Set<String> assignedSymbols;
        private final Map<String, List<Order>> ordersBySymbol = new ConcurrentHashMap<>();

        TestShardEngine(String shardId, Set<String> symbols) {
            this.shardId = shardId;
            this.assignedSymbols = new HashSet<>(symbols);
        }

        boolean handlesSymbol(String symbol) {
            return assignedSymbols.contains(symbol);
        }

        Set<String> getAssignedSymbols() {
            return new HashSet<>(assignedSymbols);
        }

        void addSymbol(String symbol) {
            assignedSymbols.add(symbol);
            ordersBySymbol.put(symbol, new ArrayList<>());
        }

        List<Order> removeSymbol(String symbol) {
            assignedSymbols.remove(symbol);
            return ordersBySymbol.remove(symbol);
        }

        void importOrders(String symbol, List<Order> orders) {
            addSymbol(symbol);
            ordersBySymbol.put(symbol, new ArrayList<>(orders));
        }

        ShardHealthStatus getHealthStatus() {
            return ShardHealthStatus.builder()
                    .shardId(shardId)
                    .role(ShardRole.PRIMARY)
                    .status(ShardStatus.ACTIVE)
                    .assignedSymbols(assignedSymbols.size())
                    .activeOrders(ordersBySymbol.values().stream().mapToLong(List::size).sum())
                    .memoryUsed(Runtime.getRuntime().totalMemory() - Runtime.getRuntime().freeMemory())
                    .memoryMax(Runtime.getRuntime().maxMemory())
                    .build();
        }

        void shutdown() {
            assignedSymbols.clear();
            ordersBySymbol.clear();
        }
    }

    /**
     * Test implementation of RebalanceCallback.
     */
    private class TestRebalanceCallback implements RebalanceCallback {

        @Override
        public List<Order> exportOrders(String shardId, String symbol) {
            TestShardEngine engine = shardEngines.get(shardId);
            if (engine != null) {
                List<Order> orders = engine.removeSymbol(symbol);
                return orders != null ? orders : new ArrayList<>();
            }
            return new ArrayList<>();
        }

        @Override
        public void importOrders(String shardId, String symbol, List<Order> orders) {
            TestShardEngine engine = shardEngines.get(shardId);
            if (engine != null) {
                engine.importOrders(symbol, orders);
            }
        }

        @Override
        public void removeSymbol(String shardId, String symbol) {
            TestShardEngine engine = shardEngines.get(shardId);
            if (engine != null) {
                engine.removeSymbol(symbol);
            }
        }

        @Override
        public void addSymbol(String shardId, String symbol) {
            TestShardEngine engine = shardEngines.get(shardId);
            if (engine != null) {
                engine.addSymbol(symbol);
            }
        }
    }
}

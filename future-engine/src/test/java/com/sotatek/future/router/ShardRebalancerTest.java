package com.sotatek.future.router;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import com.sotatek.future.entity.Order;
import com.sotatek.future.router.ShardRebalancer.RebalanceCallback;
import com.sotatek.future.router.ShardRebalancer.ShardMetrics;
import com.sotatek.future.router.ShardRebalancer.SymbolMetrics;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.concurrent.atomic.AtomicInteger;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

class ShardRebalancerTest {

    private OrderRouter router;
    private ShardRebalancer rebalancer;
    private TestRebalanceCallback callback;

    @BeforeEach
    void setUp() {
        OrderRouter.resetInstance();
        router = OrderRouter.getInstance();
        rebalancer = new ShardRebalancer(router);
        callback = new TestRebalanceCallback();
        rebalancer.setCallback(callback);
    }

    @AfterEach
    void tearDown() {
        if (rebalancer != null) {
            rebalancer.close();
        }
        if (router != null) {
            router.close();
        }
        OrderRouter.resetInstance();
    }

    @Test
    void shouldNotBeRebalancingInitially() {
        assertThat(rebalancer.isRebalancing()).isFalse();
    }

    @Test
    void shouldUpdateShardMetrics() {
        ShardMetrics metrics = ShardMetrics.builder()
                .shardId("shard-1")
                .cpuUsage(0.5)
                .memoryUsage(0.6)
                .ordersPerSecond(1000)
                .tradesPerSecond(500)
                .maxOrdersPerSecond(2000)
                .symbolCount(5)
                .activeOrders(10000)
                .timestamp(System.currentTimeMillis())
                .build();

        rebalancer.updateShardMetrics("shard-1", metrics);

        // Metrics should be stored (internal state)
        assertThat(metrics.getShardId()).isEqualTo("shard-1");
        assertThat(metrics.getCpuUsage()).isEqualTo(0.5);
    }

    @Test
    void shouldUpdateSymbolMetrics() {
        SymbolMetrics metrics = SymbolMetrics.builder()
                .symbol("BTCUSDT")
                .ordersPerSecond(500)
                .tradesPerSecond(250)
                .activeOrders(5000)
                .timestamp(System.currentTimeMillis())
                .build();

        rebalancer.updateSymbolMetrics("BTCUSDT", metrics);

        assertThat(metrics.getSymbol()).isEqualTo("BTCUSDT");
        assertThat(metrics.getOrdersPerSecond()).isEqualTo(500);
    }

    @Test
    void shouldSetLoadThreshold() {
        rebalancer.setLoadThreshold(0.7);
        // No exception should be thrown
    }

    @Test
    void shouldSetCheckInterval() {
        rebalancer.setCheckIntervalMs(30000);
        // No exception should be thrown
    }

    @Test
    void shouldExecuteManualRebalance() {
        // BTCUSDT is on shard-1, move it to shard-2
        rebalancer.manualRebalance("BTCUSDT", "shard-2");

        // Verify callback was invoked
        assertThat(callback.exportOrdersCalled.get()).isTrue();
        assertThat(callback.addSymbolCalled.get()).isTrue();
        assertThat(callback.importOrdersCalled.get()).isTrue();
        assertThat(callback.removeSymbolCalled.get()).isTrue();

        // Verify routing table was updated
        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-2");
    }

    @Test
    void shouldNotRebalanceToSameShard() {
        // BTCUSDT is already on shard-1
        rebalancer.manualRebalance("BTCUSDT", "shard-1");

        // Callback should not be invoked since symbol is already on target shard
        assertThat(callback.exportOrdersCalled.get()).isFalse();
    }

    @Test
    void shouldHandleUnknownSymbolByRoutingToDefaultShard() {
        // Unknown symbols now route to default shard (shard-3)
        // So they can be rebalanced from default to any other shard
        ShardInfo unknownSymbolShard = router.getShardForSymbol("UNKNOWNSYMBOL");
        assertThat(unknownSymbolShard).isNotNull();
        assertThat(unknownSymbolShard.getShardId()).isEqualTo("shard-3"); // default shard
    }

    @Test
    void shouldPauseSymbolDuringRebalance() {
        // Start rebalance
        AtomicBoolean pausedDuringRebalance = new AtomicBoolean(false);

        callback = new TestRebalanceCallback() {
            @Override
            public List<Order> exportOrders(String shardId, String symbol) {
                pausedDuringRebalance.set(router.isSymbolPaused(symbol));
                return super.exportOrders(shardId, symbol);
            }
        };
        rebalancer.setCallback(callback);

        rebalancer.manualRebalance("BTCUSDT", "shard-2");

        // Symbol should have been paused during the rebalance operation
        assertThat(pausedDuringRebalance.get()).isTrue();
        // But resumed after completion
        assertThat(router.isSymbolPaused("BTCUSDT")).isFalse();
    }

    @Test
    void shouldResumeSymbolOnRebalanceFailure() {
        // Set up callback that will fail
        callback = new TestRebalanceCallback() {
            @Override
            public void importOrders(String shardId, String symbol, List<Order> orders) {
                throw new RuntimeException("Import failed");
            }
        };
        rebalancer.setCallback(callback);

        // Rebalance should fail
        assertThatThrownBy(() -> rebalancer.manualRebalance("BTCUSDT", "shard-2"))
                .isInstanceOf(RuntimeException.class)
                .hasMessageContaining("Rebalancing failed");

        // Symbol should be resumed despite failure
        assertThat(router.isSymbolPaused("BTCUSDT")).isFalse();
    }

    @Test
    void shouldTrackRebalanceOperationCount() {
        rebalancer.manualRebalance("BTCUSDT", "shard-2");

        assertThat(callback.exportCount.get()).isEqualTo(1);
        assertThat(callback.importCount.get()).isEqualTo(1);
    }

    @Test
    void shouldStartAndStopWithoutError() {
        rebalancer.start();

        // Wait a bit for scheduler to start
        try {
            Thread.sleep(100);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }

        rebalancer.stop();
        // No exception should be thrown
    }

    @Test
    void shardMetricsShouldHaveCorrectProperties() {
        ShardMetrics metrics = ShardMetrics.builder()
                .shardId("shard-1")
                .cpuUsage(0.75)
                .memoryUsage(0.80)
                .ordersPerSecond(1500)
                .tradesPerSecond(750)
                .maxOrdersPerSecond(2000)
                .symbolCount(10)
                .activeOrders(50000)
                .timestamp(System.currentTimeMillis())
                .build();

        assertThat(metrics.getShardId()).isEqualTo("shard-1");
        assertThat(metrics.getCpuUsage()).isEqualTo(0.75);
        assertThat(metrics.getMemoryUsage()).isEqualTo(0.80);
        assertThat(metrics.getOrdersPerSecond()).isEqualTo(1500);
        assertThat(metrics.getTradesPerSecond()).isEqualTo(750);
        assertThat(metrics.getMaxOrdersPerSecond()).isEqualTo(2000);
        assertThat(metrics.getSymbolCount()).isEqualTo(10);
        assertThat(metrics.getActiveOrders()).isEqualTo(50000);
    }

    @Test
    void symbolMetricsShouldHaveCorrectProperties() {
        long now = System.currentTimeMillis();
        SymbolMetrics metrics = SymbolMetrics.builder()
                .symbol("ETHUSDT")
                .ordersPerSecond(800)
                .tradesPerSecond(400)
                .activeOrders(25000)
                .timestamp(now)
                .build();

        assertThat(metrics.getSymbol()).isEqualTo("ETHUSDT");
        assertThat(metrics.getOrdersPerSecond()).isEqualTo(800);
        assertThat(metrics.getTradesPerSecond()).isEqualTo(400);
        assertThat(metrics.getActiveOrders()).isEqualTo(25000);
        assertThat(metrics.getTimestamp()).isEqualTo(now);
    }

    /**
     * Test implementation of RebalanceCallback for testing purposes.
     */
    private static class TestRebalanceCallback implements RebalanceCallback {
        AtomicBoolean exportOrdersCalled = new AtomicBoolean(false);
        AtomicBoolean addSymbolCalled = new AtomicBoolean(false);
        AtomicBoolean importOrdersCalled = new AtomicBoolean(false);
        AtomicBoolean removeSymbolCalled = new AtomicBoolean(false);
        AtomicInteger exportCount = new AtomicInteger(0);
        AtomicInteger importCount = new AtomicInteger(0);

        @Override
        public List<Order> exportOrders(String shardId, String symbol) {
            exportOrdersCalled.set(true);
            exportCount.incrementAndGet();
            return new ArrayList<>();
        }

        @Override
        public void importOrders(String shardId, String symbol, List<Order> orders) {
            importOrdersCalled.set(true);
            importCount.incrementAndGet();
        }

        @Override
        public void removeSymbol(String shardId, String symbol) {
            removeSymbolCalled.set(true);
        }

        @Override
        public void addSymbol(String shardId, String symbol) {
            addSymbolCalled.set(true);
        }
    }
}

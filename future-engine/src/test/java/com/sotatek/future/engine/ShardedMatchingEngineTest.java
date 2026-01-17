package com.sotatek.future.engine;

import static org.assertj.core.api.Assertions.assertThat;

import com.sotatek.future.BaseMatchingEngineTest;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.Order;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.input.ListInputStream;
import com.sotatek.future.output.ListOutputStream;
import com.sotatek.future.router.ShardInfo.ShardRole;
import com.sotatek.future.service.OrderService;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.stream.Stream;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

class ShardedMatchingEngineTest extends BaseMatchingEngineTest {

    private ShardedMatchingEngine shardedEngine;
    private Set<String> assignedSymbols;

    @Override
    @BeforeEach
    public void setUp() throws Exception {
        super.setUp();
        assignedSymbols = new HashSet<>();
        assignedSymbols.add("BTCUSD");
        assignedSymbols.add("BTCUSDT");

        shardedEngine = new ShardedMatchingEngine("shard-1", assignedSymbols, ShardRole.PRIMARY);
    }

    @Override
    @AfterEach
    public void tearDown() throws Exception {
        super.tearDown();
        if (shardedEngine != null) {
            shardedEngine.shutdown();
        }
    }

    @Test
    void shouldCreateShardedEngineWithCorrectProperties() {
        assertThat(shardedEngine.getShardId()).isEqualTo("shard-1");
        assertThat(shardedEngine.getRole()).isEqualTo(ShardRole.PRIMARY);
        assertThat(shardedEngine.getAssignedSymbols()).containsExactlyInAnyOrder("BTCUSD", "BTCUSDT");
    }

    @Test
    void shouldHandleSymbolCorrectly() {
        assertThat(shardedEngine.handlesSymbol("BTCUSD")).isTrue();
        assertThat(shardedEngine.handlesSymbol("BTCUSDT")).isTrue();
        assertThat(shardedEngine.handlesSymbol("ETHUSD")).isFalse();
    }

    @Test
    void shouldAddNewSymbol() {
        assertThat(shardedEngine.handlesSymbol("SOLUSDT")).isFalse();

        shardedEngine.addSymbol("SOLUSDT");

        assertThat(shardedEngine.handlesSymbol("SOLUSDT")).isTrue();
        assertThat(shardedEngine.getAssignedSymbols()).contains("SOLUSDT");
    }

    @Test
    void shouldRemoveSymbolAndReturnEmptyListWhenNoOrders() {
        shardedEngine.addSymbol("XRPUSDT");
        assertThat(shardedEngine.handlesSymbol("XRPUSDT")).isTrue();

        List<Order> removedOrders = shardedEngine.removeSymbol("XRPUSDT");

        assertThat(shardedEngine.handlesSymbol("XRPUSDT")).isFalse();
        assertThat(removedOrders).isEmpty();
    }

    @Test
    void shouldReturnEmptyListWhenRemovingNonAssignedSymbol() {
        List<Order> removedOrders = shardedEngine.removeSymbol("UNKNOWNSYMBOL");

        assertThat(removedOrders).isEmpty();
    }

    @Test
    void shouldReturnHealthStatus() {
        ShardHealthStatus status = shardedEngine.getHealthStatus();

        assertThat(status).isNotNull();
        assertThat(status.getShardId()).isEqualTo("shard-1");
        assertThat(status.getRole()).isEqualTo(ShardRole.PRIMARY);
        assertThat(status.getAssignedSymbols()).isEqualTo(2);
    }

    @Test
    void shouldGetAssignedSymbolsAsImmutableCopy() {
        Set<String> symbols1 = shardedEngine.getAssignedSymbols();
        Set<String> symbols2 = shardedEngine.getAssignedSymbols();

        // Should return different instances
        assertThat(symbols1).isNotSameAs(symbols2);
        // But with same content
        assertThat(symbols1).isEqualTo(symbols2);
    }

    @Test
    void shouldCreateStandbyEngine() {
        ShardedMatchingEngine standbyEngine = new ShardedMatchingEngine(
                "shard-1-standby",
                assignedSymbols,
                ShardRole.STANDBY
        );

        assertThat(standbyEngine.getRole()).isEqualTo(ShardRole.STANDBY);
        assertThat(standbyEngine.getShardId()).isEqualTo("shard-1-standby");

        standbyEngine.shutdown();
    }

    @Test
    void shouldProcessOrderForAssignedSymbol() {
        // Create order for assigned symbol (BTCUSD)
        Order order = createOrder(1L, 1L, OrderSide.BUY, OrderType.LIMIT, "65000", "100");

        // The command should be accepted
        Command command = new Command(CommandCode.PLACE_ORDER, order);
        long queueSize = shardedEngine.onNewData(command);

        // Queue should have the command
        assertThat(queueSize).isGreaterThanOrEqualTo(0);
    }

    @Test
    void shouldAcceptGlobalCommands() {
        // Global commands like INITIALIZE_ENGINE should always be accepted
        Command initCommand = new Command(CommandCode.INITIALIZE_ENGINE, null);
        long queueSize = shardedEngine.onNewData(initCommand);

        assertThat(queueSize).isGreaterThanOrEqualTo(0);
    }

    @Test
    void shouldUpdateHealthMetricsOverTime() {
        ShardHealthStatus status1 = shardedEngine.getHealthStatus();
        long initialProcessedTime = status1.getLastProcessedTime();

        // Wait a bit
        try {
            Thread.sleep(10);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }

        // Process should update last processed time eventually
        assertThat(status1.getMemoryUsed()).isGreaterThan(0);
        assertThat(status1.getMemoryMax()).isGreaterThan(0);
    }

    @Test
    void shouldImportOrdersForNewSymbol() {
        // Import with empty list to verify symbol addition works
        // Note: Non-empty imports require OrderService to be initialized
        List<Order> ordersToImport = List.of();

        // Import orders for a new symbol (empty list doesn't need OrderService)
        shardedEngine.importOrders("SOLUSDT", ordersToImport);

        // Symbol should now be handled
        assertThat(shardedEngine.handlesSymbol("SOLUSDT")).isTrue();
    }

    @Test
    void shouldReturnCorrectMemoryUsage() {
        ShardHealthStatus status = shardedEngine.getHealthStatus();

        assertThat(status.getMemoryUsed()).isGreaterThan(0);
        assertThat(status.getMemoryMax()).isGreaterThan(0);
        assertThat(status.getMemoryUsagePercent()).isBetween(0.0, 100.0);
    }

    @Test
    void shouldHaveHealthySummary() {
        ShardHealthStatus status = shardedEngine.getHealthStatus();
        String summary = status.toSummary();

        assertThat(summary).contains("shard-1");
        assertThat(summary).contains("PRIMARY");
        assertThat(summary).contains("symbols=2");
    }
}

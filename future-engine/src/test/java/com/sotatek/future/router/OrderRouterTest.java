package com.sotatek.future.router;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import com.sotatek.future.router.OrderRouter.SymbolPausedException;
import com.sotatek.future.router.ShardInfo.ShardStatus;
import java.util.Set;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

class OrderRouterTest {

    private OrderRouter router;

    @BeforeEach
    void setUp() {
        // Reset singleton for each test
        OrderRouter.resetInstance();
        router = OrderRouter.getInstance();
    }

    @AfterEach
    void tearDown() {
        if (router != null) {
            router.close();
        }
        OrderRouter.resetInstance();
    }

    @Test
    void shouldReturnSingletonInstance() {
        OrderRouter instance1 = OrderRouter.getInstance();
        OrderRouter instance2 = OrderRouter.getInstance();

        assertThat(instance1).isSameAs(instance2);
    }

    @Test
    void shouldLoadDefaultShardMapping() {
        // Default mapping loaded in constructor
        ShardInfo btcShard = router.getShardForSymbol("BTCUSDT");
        ShardInfo ethShard = router.getShardForSymbol("ETHUSDT");

        assertThat(btcShard).isNotNull();
        assertThat(btcShard.getShardId()).isEqualTo("shard-1");

        assertThat(ethShard).isNotNull();
        assertThat(ethShard.getShardId()).isEqualTo("shard-2");
    }

    @Test
    void shouldReturnDefaultShardForUnknownSymbol() {
        ShardInfo unknownShard = router.getShardForSymbol("UNKNOWNUSDT");

        // Should return default shard (shard-3)
        assertThat(unknownShard).isNotNull();
        assertThat(unknownShard.getShardId()).isEqualTo("shard-3");
    }

    @Test
    void shouldAddSymbolMapping() {
        router.addSymbolMapping("SOLUSDT", "shard-1");

        ShardInfo shard = router.getShardForSymbol("SOLUSDT");
        assertThat(shard.getShardId()).isEqualTo("shard-1");
    }

    @Test
    void shouldRemoveSymbolMapping() {
        // First add a mapping
        router.addSymbolMapping("XRPUSDT", "shard-1");
        assertThat(router.getShardForSymbol("XRPUSDT").getShardId()).isEqualTo("shard-1");

        // Then remove it
        router.removeSymbolMapping("XRPUSDT");

        // Should now return default shard
        ShardInfo shard = router.getShardForSymbol("XRPUSDT");
        assertThat(shard.getShardId()).isEqualTo("shard-3");
    }

    @Test
    void shouldUpdateMapping() {
        // BTCUSDT is initially on shard-1
        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-1");

        // Update to shard-2
        router.updateMapping("BTCUSDT", "shard-2");

        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-2");
    }

    @Test
    void shouldThrowExceptionForInvalidShardInUpdateMapping() {
        assertThatThrownBy(() -> router.updateMapping("BTCUSDT", "invalid-shard"))
                .isInstanceOf(IllegalArgumentException.class)
                .hasMessageContaining("Unknown shard");
    }

    @Test
    void shouldPauseAndResumeSymbol() {
        assertThat(router.isSymbolPaused("BTCUSDT")).isFalse();

        router.pauseSymbol("BTCUSDT");
        assertThat(router.isSymbolPaused("BTCUSDT")).isTrue();

        router.resumeSymbol("BTCUSDT");
        assertThat(router.isSymbolPaused("BTCUSDT")).isFalse();
    }

    @Test
    void shouldGetAllShards() {
        var shards = router.getAllShards();

        assertThat(shards).isNotEmpty();
        assertThat(shards).hasSizeGreaterThanOrEqualTo(3);
    }

    @Test
    void shouldGetSymbolsForShard() {
        Set<String> shard1Symbols = router.getSymbolsForShard("shard-1");

        assertThat(shard1Symbols).contains("BTCUSDT", "BTCBUSD");
    }

    @Test
    void shouldReturnEmptySetForUnknownShard() {
        Set<String> symbols = router.getSymbolsForShard("unknown-shard");

        assertThat(symbols).isEmpty();
    }

    @Test
    void shouldThrowExceptionWhenAddingSymbolToUnknownShard() {
        assertThatThrownBy(() -> router.addSymbolMapping("NEWUSDT", "invalid-shard"))
                .isInstanceOf(IllegalArgumentException.class)
                .hasMessageContaining("Unknown shard");
    }

    @Test
    void shouldReturnBTCOnShard1AndETHOnShard2() {
        // BTC pairs should be on shard-1
        assertThat(router.getShardForSymbol("BTCUSDT").getShardId()).isEqualTo("shard-1");
        assertThat(router.getShardForSymbol("BTCBUSD").getShardId()).isEqualTo("shard-1");

        // ETH pairs should be on shard-2
        assertThat(router.getShardForSymbol("ETHUSDT").getShardId()).isEqualTo("shard-2");
        assertThat(router.getShardForSymbol("ETHBUSD").getShardId()).isEqualTo("shard-2");
    }

    @Test
    void shouldHandleMultiplePausedSymbols() {
        router.pauseSymbol("BTCUSDT");
        router.pauseSymbol("ETHUSDT");
        router.pauseSymbol("SOLUSDT");

        assertThat(router.isSymbolPaused("BTCUSDT")).isTrue();
        assertThat(router.isSymbolPaused("ETHUSDT")).isTrue();
        assertThat(router.isSymbolPaused("SOLUSDT")).isTrue();
        assertThat(router.isSymbolPaused("XRPUSDT")).isFalse();

        router.resumeSymbol("ETHUSDT");
        assertThat(router.isSymbolPaused("ETHUSDT")).isFalse();
        assertThat(router.isSymbolPaused("BTCUSDT")).isTrue();
    }

    @Test
    void shouldVerifyShardInfoProperties() {
        ShardInfo shard1 = router.getShardForSymbol("BTCUSDT");

        assertThat(shard1.getKafkaInputTopic()).isEqualTo("matching-engine-shard-1-input");
        assertThat(shard1.getKafkaOutputTopic()).isEqualTo("matching-engine-shard-1-output");
        assertThat(shard1.getKafkaSyncTopic()).isEqualTo("shard-sync-shard-1");
        assertThat(shard1.getStatus()).isEqualTo(ShardStatus.ACTIVE);
    }
}

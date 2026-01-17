package com.sotatek.future.router;

import static org.assertj.core.api.Assertions.assertThat;

import com.sotatek.future.router.ShardInfo.ShardRole;
import com.sotatek.future.router.ShardInfo.ShardStatus;
import org.junit.jupiter.api.Test;

class ShardInfoTest {

    @Test
    void shouldCreateShardInfoWithSimpleConstructor() {
        ShardInfo shard = new ShardInfo("shard-1", "matching-engine-shard-1-input");

        assertThat(shard.getShardId()).isEqualTo("shard-1");
        assertThat(shard.getKafkaInputTopic()).isEqualTo("matching-engine-shard-1-input");
        assertThat(shard.getStatus()).isEqualTo(ShardStatus.ACTIVE);
        assertThat(shard.getRole()).isEqualTo(ShardRole.PRIMARY);
    }

    @Test
    void shouldCreateShardInfoWithBuilder() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-2")
                .kafkaInputTopic("input-topic")
                .kafkaOutputTopic("output-topic")
                .kafkaSyncTopic("sync-topic")
                .primaryHost("primary.host.com")
                .primaryPort(9092)
                .standbyHost("standby.host.com")
                .standbyPort(9093)
                .status(ShardStatus.ACTIVE)
                .role(ShardRole.PRIMARY)
                .build();

        assertThat(shard.getShardId()).isEqualTo("shard-2");
        assertThat(shard.getPrimaryHost()).isEqualTo("primary.host.com");
        assertThat(shard.getPrimaryPort()).isEqualTo(9092);
        assertThat(shard.getStandbyHost()).isEqualTo("standby.host.com");
        assertThat(shard.getStandbyPort()).isEqualTo(9093);
    }

    @Test
    void shouldReturnAvailableWhenActiveAndPrimary() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-1")
                .status(ShardStatus.ACTIVE)
                .role(ShardRole.PRIMARY)
                .build();

        assertThat(shard.isAvailable()).isTrue();
    }

    @Test
    void shouldReturnNotAvailableWhenStandby() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-1")
                .status(ShardStatus.ACTIVE)
                .role(ShardRole.STANDBY)
                .build();

        assertThat(shard.isAvailable()).isFalse();
    }

    @Test
    void shouldReturnNotAvailableWhenMaintenance() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-1")
                .status(ShardStatus.MAINTENANCE)
                .role(ShardRole.PRIMARY)
                .build();

        assertThat(shard.isAvailable()).isFalse();
    }

    @Test
    void shouldReturnNotAvailableWhenOffline() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-1")
                .status(ShardStatus.OFFLINE)
                .role(ShardRole.PRIMARY)
                .build();

        assertThat(shard.isAvailable()).isFalse();
    }

    @Test
    void shouldAcceptSymbolsWhenActive() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-1")
                .status(ShardStatus.ACTIVE)
                .build();

        assertThat(shard.canAcceptSymbols()).isTrue();
    }

    @Test
    void shouldAcceptSymbolsWhenRebalancing() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-1")
                .status(ShardStatus.REBALANCING)
                .build();

        assertThat(shard.canAcceptSymbols()).isTrue();
    }

    @Test
    void shouldNotAcceptSymbolsWhenOffline() {
        ShardInfo shard = ShardInfo.builder()
                .shardId("shard-1")
                .status(ShardStatus.OFFLINE)
                .build();

        assertThat(shard.canAcceptSymbols()).isFalse();
    }

    @Test
    void shouldHaveAllStatusValues() {
        assertThat(ShardStatus.values()).containsExactly(
                ShardStatus.ACTIVE,
                ShardStatus.DEGRADED,
                ShardStatus.MAINTENANCE,
                ShardStatus.OFFLINE,
                ShardStatus.REBALANCING
        );
    }

    @Test
    void shouldHaveAllRoleValues() {
        assertThat(ShardRole.values()).containsExactly(
                ShardRole.PRIMARY,
                ShardRole.STANDBY,
                ShardRole.RECOVERING
        );
    }
}

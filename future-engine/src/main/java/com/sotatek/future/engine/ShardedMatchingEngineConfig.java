package com.sotatek.future.engine;

import com.sotatek.future.router.ShardInfo.ShardRole;
import java.util.HashSet;
import java.util.Set;
import lombok.Getter;
import lombok.Setter;

/**
 * Configuration for ShardedMatchingEngine.
 * Extends the base configuration with shard-specific settings.
 */
@Getter
@Setter
public class ShardedMatchingEngineConfig extends MatchingEngineConfig {

    /**
     * Unique identifier for this shard.
     */
    private String shardId;

    /**
     * Role of this engine instance (PRIMARY or STANDBY).
     */
    private ShardRole role = ShardRole.PRIMARY;

    /**
     * Set of symbols this shard is responsible for.
     */
    private Set<String> assignedSymbols = new HashSet<>();

    /**
     * Kafka topic for standby synchronization.
     */
    private String standbySyncTopic;

    /**
     * Kafka bootstrap servers.
     */
    private String kafkaBootstrapServers;

    /**
     * Enable standby synchronization.
     */
    private boolean standbySyncEnabled = true;

    /**
     * Standby synchronization mode (SYNC or ASYNC).
     */
    private SyncMode syncMode = SyncMode.ASYNC;

    /**
     * Health check port for this shard.
     */
    private int healthCheckPort = 8080;

    /**
     * Metrics export port for Prometheus.
     */
    private int metricsPort = 9090;

    public ShardedMatchingEngineConfig() {
        super();
    }

    public ShardedMatchingEngineConfig(String shardId, Set<String> symbols) {
        super();
        this.shardId = shardId;
        this.assignedSymbols = new HashSet<>(symbols);
        this.standbySyncTopic = "shard-sync-" + shardId;
    }

    /**
     * Add a symbol to this shard's responsibility.
     */
    public void addSymbol(String symbol) {
        assignedSymbols.add(symbol);
    }

    /**
     * Remove a symbol from this shard's responsibility.
     */
    public void removeSymbol(String symbol) {
        assignedSymbols.remove(symbol);
    }

    /**
     * Check if this shard handles a specific symbol.
     */
    public boolean handlesSymbol(String symbol) {
        return assignedSymbols.contains(symbol);
    }

    /**
     * Synchronization mode for standby replication.
     */
    public enum SyncMode {
        /**
         * Synchronous replication - wait for standby acknowledgment.
         * Higher latency but stronger consistency guarantee.
         */
        SYNC,

        /**
         * Asynchronous replication - don't wait for standby.
         * Lower latency but potential data loss on failover.
         */
        ASYNC
    }

    /**
     * Builder for fluent configuration.
     */
    public static Builder builder() {
        return new Builder();
    }

    public static class Builder {
        private final ShardedMatchingEngineConfig config = new ShardedMatchingEngineConfig();

        public Builder shardId(String shardId) {
            config.setShardId(shardId);
            config.setStandbySyncTopic("shard-sync-" + shardId);
            return this;
        }

        public Builder role(ShardRole role) {
            config.setRole(role);
            return this;
        }

        public Builder symbols(Set<String> symbols) {
            config.setAssignedSymbols(new HashSet<>(symbols));
            return this;
        }

        public Builder addSymbol(String symbol) {
            config.addSymbol(symbol);
            return this;
        }

        public Builder kafkaBootstrapServers(String servers) {
            config.setKafkaBootstrapServers(servers);
            return this;
        }

        public Builder standbySyncEnabled(boolean enabled) {
            config.setStandbySyncEnabled(enabled);
            return this;
        }

        public Builder syncMode(SyncMode mode) {
            config.setSyncMode(mode);
            return this;
        }

        public Builder healthCheckPort(int port) {
            config.setHealthCheckPort(port);
            return this;
        }

        public Builder metricsPort(int port) {
            config.setMetricsPort(port);
            return this;
        }

        public ShardedMatchingEngineConfig build() {
            if (config.getShardId() == null || config.getShardId().isEmpty()) {
                throw new IllegalStateException("Shard ID is required");
            }
            return config;
        }
    }
}

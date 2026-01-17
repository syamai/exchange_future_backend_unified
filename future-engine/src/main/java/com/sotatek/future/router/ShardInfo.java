package com.sotatek.future.router;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

/**
 * Shard information including connection details and status.
 */
@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ShardInfo {

    private String shardId;
    private String kafkaInputTopic;
    private String kafkaOutputTopic;
    private String kafkaSyncTopic;
    private String primaryHost;
    private int primaryPort;
    private String standbyHost;
    private int standbyPort;
    private ShardStatus status;
    private ShardRole role;

    /**
     * Simple constructor for basic shard setup.
     */
    public ShardInfo(String shardId, String kafkaInputTopic) {
        this.shardId = shardId;
        this.kafkaInputTopic = kafkaInputTopic;
        this.kafkaOutputTopic = "matching-engine-" + shardId + "-output";
        this.kafkaSyncTopic = "shard-sync-" + shardId;
        this.status = ShardStatus.ACTIVE;
        this.role = ShardRole.PRIMARY;
    }

    /**
     * Shard operational status.
     */
    public enum ShardStatus {
        ACTIVE,       // Normal operation
        DEGRADED,     // Switching to standby
        MAINTENANCE,  // Under maintenance
        OFFLINE,      // Offline
        REBALANCING   // Rebalancing symbols
    }

    /**
     * Shard role in the cluster.
     */
    public enum ShardRole {
        PRIMARY,      // Primary node - processes orders
        STANDBY,      // Standby node - ready for failover
        RECOVERING    // Recovering from failure
    }

    /**
     * Check if the shard is available for processing.
     */
    public boolean isAvailable() {
        return status == ShardStatus.ACTIVE && role == ShardRole.PRIMARY;
    }

    /**
     * Check if the shard can accept new symbols during rebalancing.
     */
    public boolean canAcceptSymbols() {
        return status == ShardStatus.ACTIVE || status == ShardStatus.REBALANCING;
    }
}

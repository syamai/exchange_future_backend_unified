import { ShardInfo, ShardRole, ShardStatus } from "./shard-info.interface";

/**
 * Default shard configuration
 * Can be overridden by environment variables:
 *   SHARD_1_SYMBOLS=BTCUSDT,BTCBUSD,BTCUSDC
 *   SHARD_2_SYMBOLS=ETHUSDT,ETHBUSD,ETHUSDC
 *   SHARD_3_SYMBOLS=* (default for all other symbols)
 */
export const DEFAULT_SHARD_CONFIG: ShardInfo[] = [
  {
    shardId: "shard-1",
    kafkaInputTopic: "matching-engine-shard-1-input",
    kafkaOutputTopic: "matching-engine-shard-1-output",
    kafkaSyncTopic: "shard-sync-shard-1",
    status: ShardStatus.ACTIVE,
    role: ShardRole.PRIMARY,
  },
  {
    shardId: "shard-2",
    kafkaInputTopic: "matching-engine-shard-2-input",
    kafkaOutputTopic: "matching-engine-shard-2-output",
    kafkaSyncTopic: "shard-sync-shard-2",
    status: ShardStatus.ACTIVE,
    role: ShardRole.PRIMARY,
  },
  {
    shardId: "shard-3",
    kafkaInputTopic: "matching-engine-shard-3-input",
    kafkaOutputTopic: "matching-engine-shard-3-output",
    kafkaSyncTopic: "shard-sync-shard-3",
    status: ShardStatus.ACTIVE,
    role: ShardRole.PRIMARY,
  },
];

/**
 * Default symbol to shard mapping
 * Shard-1: BTC pairs (highest volume)
 * Shard-2: ETH pairs (high volume)
 * Shard-3: All other symbols (default)
 */
export const DEFAULT_SYMBOL_MAPPING: Record<string, string> = {
  // Shard 1: BTC pairs
  BTCUSDT: "shard-1",
  BTCBUSD: "shard-1",
  BTCUSDC: "shard-1",

  // Shard 2: ETH pairs
  ETHUSDT: "shard-2",
  ETHBUSD: "shard-2",
  ETHUSDC: "shard-2",

  // Shard 3 is default for all other symbols
};

/**
 * Default shard ID for symbols not explicitly mapped
 */
export const DEFAULT_SHARD_ID = "shard-3";

/**
 * Legacy topic for backward compatibility when sharding is disabled
 */
export const LEGACY_MATCHING_ENGINE_TOPIC = "matching_engine_input";

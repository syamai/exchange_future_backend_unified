/**
 * Shard operational status
 */
export enum ShardStatus {
  ACTIVE = "ACTIVE",
  DEGRADED = "DEGRADED",
  MAINTENANCE = "MAINTENANCE",
  OFFLINE = "OFFLINE",
  REBALANCING = "REBALANCING",
}

/**
 * Shard role in the cluster
 */
export enum ShardRole {
  PRIMARY = "PRIMARY",
  STANDBY = "STANDBY",
  RECOVERING = "RECOVERING",
}

/**
 * Shard information including connection details and status
 */
export interface ShardInfo {
  shardId: string;
  kafkaInputTopic: string;
  kafkaOutputTopic: string;
  kafkaSyncTopic?: string;
  status: ShardStatus;
  role: ShardRole;
}

/**
 * Command to send to matching engine
 */
export interface MatchingEngineCommand<T = unknown> {
  code: string;
  data: T;
}

/**
 * Routing result
 */
export interface RoutingResult {
  shardId: string;
  topic: string;
  success: boolean;
  error?: string;
}

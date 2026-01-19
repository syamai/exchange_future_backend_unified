/**
 * Development environment configuration
 */
export interface EnvironmentConfig {
  readonly envName: string;
  readonly region: string;
  readonly vpcCidr: string;
  readonly eksNodeInstanceType: string;
  readonly eksNodeDesiredSize: number;
  readonly eksNodeMinSize: number;
  readonly eksNodeMaxSize: number;
  readonly rdsInstanceClass: string;
  readonly redisNodeType: string;
  readonly kafkaInstanceType: string;
  readonly dbUsername: string;

  // Spot Backend settings
  readonly spotBackend: {
    readonly enabled: boolean;
    readonly dbName: string;
    readonly redisDb: number;
  };
}

export const devConfig: EnvironmentConfig = {
  envName: 'dev',
  region: 'ap-northeast-2',
  vpcCidr: '10.0.0.0/16',

  // EKS - Spot instances for cost optimization
  // Target: 2000 TPS → 3 nodes for Backend + 3 Matching Engine Shards
  eksNodeInstanceType: 't3.large',
  eksNodeDesiredSize: 3,
  eksNodeMinSize: 2,
  eksNodeMaxSize: 6,

  // RDS - Upgraded for 2000 TPS
  // t3.medium: ~500-1000 TPS, t3.large: ~2000-3000 TPS
  rdsInstanceClass: 't3.large',

  // Redis - Upgraded for 2000 TPS
  // cache.t3.medium: ~20,000 ops/sec
  redisNodeType: 'cache.t3.medium',

  // Kafka (Redpanda on EC2)
  // t3.medium: ~20,000 msg/sec (sufficient for 2000 TPS)
  kafkaInstanceType: 't3.medium',

  // Database
  dbUsername: 'admin',

  // Spot Backend configuration
  // Shares VPC, EKS, Redis with Future Backend
  // Uses separate database and Redis DB index
  spotBackend: {
    enabled: true,
    dbName: 'spot_exchange',  // Separate database for spot trading
    redisDb: 2,               // Redis DB index (future uses 0-1)
  },
};

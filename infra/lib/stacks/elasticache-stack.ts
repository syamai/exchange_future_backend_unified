import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as elasticache from 'aws-cdk-lib/aws-elasticache';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';

export interface ElasticacheStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
  vpc: ec2.Vpc;
  eksSecurityGroup: ec2.SecurityGroup;
}

export class ElasticacheStack extends cdk.Stack {
  public readonly cluster: elasticache.CfnCacheCluster;

  constructor(scope: Construct, id: string, props: ElasticacheStackProps) {
    super(scope, id, props);

    const { config, vpc, eksSecurityGroup } = props;

    // Redis Security Group
    const redisSecurityGroup = new ec2.SecurityGroup(this, 'RedisSecurityGroup', {
      vpc,
      securityGroupName: `exchange-${config.envName}-redis-sg`,
      description: 'Security group for ElastiCache Redis',
      allowAllOutbound: false,
    });

    // Allow Redis access from EKS
    redisSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(6379),
      'Redis from EKS'
    );

    // Allow Redis access from VPC (for EKS node groups that use different SGs)
    redisSecurityGroup.addIngressRule(
      ec2.Peer.ipv4(vpc.vpcCidrBlock),
      ec2.Port.tcp(6379),
      'Redis from VPC'
    );

    // Subnet Group
    const subnetGroup = new elasticache.CfnSubnetGroup(this, 'SubnetGroup', {
      description: 'Subnet group for ElastiCache Redis',
      subnetIds: vpc.isolatedSubnets.map((subnet) => subnet.subnetId),
      cacheSubnetGroupName: `exchange-${config.envName}-redis-subnet`,
    });

    // Redis Cluster (single node for dev environment)
    this.cluster = new elasticache.CfnCacheCluster(this, 'Cluster', {
      clusterName: `exchange-${config.envName}-redis`,
      engine: 'redis',
      engineVersion: '7.0',
      cacheNodeType: config.redisNodeType,
      numCacheNodes: 1,
      cacheSubnetGroupName: subnetGroup.cacheSubnetGroupName,
      vpcSecurityGroupIds: [redisSecurityGroup.securityGroupId],

      // Dev environment: Disable snapshots
      snapshotRetentionLimit: 0,
    });

    this.cluster.addDependency(subnetGroup);

    // Outputs
    new cdk.CfnOutput(this, 'RedisEndpoint', {
      value: this.cluster.attrRedisEndpointAddress,
      exportName: `${config.envName}-RedisEndpoint`,
    });

    new cdk.CfnOutput(this, 'RedisPort', {
      value: this.cluster.attrRedisEndpointPort,
      exportName: `${config.envName}-RedisPort`,
    });
  }
}

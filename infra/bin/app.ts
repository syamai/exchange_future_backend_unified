#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { VpcStack } from '../lib/stacks/vpc-stack';
import { EksStack } from '../lib/stacks/eks-stack';
import { RdsStack } from '../lib/stacks/rds-stack';
import { ElasticacheStack } from '../lib/stacks/elasticache-stack';
import { KafkaStack } from '../lib/stacks/kafka-stack';
import { EcrStack } from '../lib/stacks/ecr-stack';
import { devConfig, EnvironmentConfig } from '../config/dev';

const app = new cdk.App();

// Get environment from context (default: dev)
const envName = app.node.tryGetContext('env') || 'dev';

// Select configuration based on environment
const configMap: Record<string, EnvironmentConfig> = {
  dev: devConfig,
  // Add more environments here:
  // staging: stagingConfig,
  // prod: prodConfig,
};

const config = configMap[envName];
if (!config) {
  throw new Error(`Unknown environment: ${envName}. Available: ${Object.keys(configMap).join(', ')}`);
}

// Environment props for all stacks
const envProps = {
  env: {
    account: process.env.CDK_DEFAULT_ACCOUNT,
    region: config.region,
  },
};

console.log(`Deploying to environment: ${config.envName}`);
console.log(`Region: ${config.region}`);

// ============================================================================
// Stack Definitions
// ============================================================================

// VPC Stack (foundation network)
const vpcStack = new VpcStack(app, `Exchange-${config.envName}-Vpc`, {
  ...envProps,
  config,
  description: `VPC for Exchange ${config.envName} environment`,
});

// ECR Stack (container registry) - No dependencies
const ecrStack = new EcrStack(app, `Exchange-${config.envName}-Ecr`, {
  ...envProps,
  config,
  description: `ECR repositories for Exchange ${config.envName} environment`,
});

// RDS Stack (MySQL)
const rdsStack = new RdsStack(app, `Exchange-${config.envName}-Rds`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
  description: `RDS MySQL for Exchange ${config.envName} environment`,
});

// ElastiCache Stack (Redis)
const elasticacheStack = new ElasticacheStack(app, `Exchange-${config.envName}-Redis`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
  description: `ElastiCache Redis for Exchange ${config.envName} environment`,
});

// Kafka Stack (Redpanda on EC2)
const kafkaStack = new KafkaStack(app, `Exchange-${config.envName}-Kafka`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
  description: `Kafka (Redpanda) for Exchange ${config.envName} environment`,
});

// EKS Stack (Kubernetes)
const eksStack = new EksStack(app, `Exchange-${config.envName}-Eks`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
  description: `EKS cluster for Exchange ${config.envName} environment`,
});

// ============================================================================
// Stack Dependencies
// ============================================================================

// All data/compute stacks depend on VPC
rdsStack.addDependency(vpcStack);
elasticacheStack.addDependency(vpcStack);
kafkaStack.addDependency(vpcStack);
eksStack.addDependency(vpcStack);

// ============================================================================
// Tags
// ============================================================================

// Add common tags to all resources
cdk.Tags.of(app).add('Project', 'exchange');
cdk.Tags.of(app).add('Environment', config.envName);
cdk.Tags.of(app).add('ManagedBy', 'cdk');

app.synth();

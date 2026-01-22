import { EKSClient, UpdateNodegroupConfigCommand, DescribeClusterCommand } from '@aws-sdk/client-eks';
import { RDSClient, StartDBInstanceCommand, StopDBInstanceCommand } from '@aws-sdk/client-rds';
import { EC2Client, StartInstancesCommand, StopInstancesCommand } from '@aws-sdk/client-ec2';
import {
  ElastiCacheClient,
  CreateCacheClusterCommand,
  DeleteCacheClusterCommand,
  DescribeCacheClustersCommand,
} from '@aws-sdk/client-elasticache';
import { DescribeSecurityGroupsCommand } from '@aws-sdk/client-ec2';
import { SSMClient, SendCommandCommand, GetCommandInvocationCommand } from '@aws-sdk/client-ssm';
import { STSClient, GetCallerIdentityCommand } from '@aws-sdk/client-sts';
import * as https from 'https';

const eksClient = new EKSClient({});
const rdsClient = new RDSClient({});
const ec2Client = new EC2Client({});
const elasticacheClient = new ElastiCacheClient({});
const ssmClient = new SSMClient({});
const stsClient = new STSClient({});

interface SchedulerEvent {
  action: 'scale-up' | 'scale-down';
  // EKS config
  clusterName: string;
  nodegroupName: string;
  desiredSize: number;
  minSize: number;
  maxSize: number;
  // RDS config
  rdsInstanceId?: string;
  // EC2 config (Kafka)
  ec2InstanceIds?: string[];
  // ElastiCache config
  elasticache?: {
    clusterId: string;
    nodeType: string;
    engine: string;
    engineVersion: string;
    subnetGroupName: string;
    securityGroupName: string;
  };
  // Matching Engine initialization config
  matchingEngineInit?: {
    kafkaInstanceId: string;
    preloadTopic: string;
    delaySeconds: number; // Wait time before sending init commands
  };
  // Kubernetes deployments to scale (prevents Cluster Autoscaler from scaling back up)
  k8sDeployments?: {
    namespace: string;
    name: string;
    scaleUpReplicas: number; // replicas when scaling up
  }[];
}

interface Result {
  service: string;
  status: 'success' | 'error' | 'skipped';
  message: string;
}

async function handleEks(event: SchedulerEvent): Promise<Result> {
  const { action, clusterName, nodegroupName, desiredSize, minSize, maxSize } = event;

  try {
    const command = new UpdateNodegroupConfigCommand({
      clusterName,
      nodegroupName,
      scalingConfig: { minSize, maxSize, desiredSize },
    });

    const response = await eksClient.send(command);
    return {
      service: 'EKS',
      status: 'success',
      message: `Nodegroup ${action}: updateId=${response.update?.id}`,
    };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error('EKS error:', message);
    return { service: 'EKS', status: 'error', message };
  }
}

async function handleRds(event: SchedulerEvent): Promise<Result> {
  const { action, rdsInstanceId } = event;

  if (!rdsInstanceId) {
    return { service: 'RDS', status: 'skipped', message: 'No RDS instance configured' };
  }

  try {
    if (action === 'scale-up') {
      const command = new StartDBInstanceCommand({ DBInstanceIdentifier: rdsInstanceId });
      await rdsClient.send(command);
      return { service: 'RDS', status: 'success', message: `Starting ${rdsInstanceId}` };
    } else {
      const command = new StopDBInstanceCommand({ DBInstanceIdentifier: rdsInstanceId });
      await rdsClient.send(command);
      return { service: 'RDS', status: 'success', message: `Stopping ${rdsInstanceId}` };
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    // RDS already started/stopped is not an error
    if (message.includes('InvalidDBInstanceState')) {
      return { service: 'RDS', status: 'success', message: `RDS already in desired state` };
    }
    console.error('RDS error:', message);
    return { service: 'RDS', status: 'error', message };
  }
}

async function handleEc2(event: SchedulerEvent): Promise<Result> {
  const { action, ec2InstanceIds } = event;

  if (!ec2InstanceIds || ec2InstanceIds.length === 0) {
    return { service: 'EC2', status: 'skipped', message: 'No EC2 instances configured' };
  }

  try {
    if (action === 'scale-up') {
      const command = new StartInstancesCommand({ InstanceIds: ec2InstanceIds });
      await ec2Client.send(command);
      return { service: 'EC2', status: 'success', message: `Starting ${ec2InstanceIds.join(', ')}` };
    } else {
      const command = new StopInstancesCommand({ InstanceIds: ec2InstanceIds });
      await ec2Client.send(command);
      return { service: 'EC2', status: 'success', message: `Stopping ${ec2InstanceIds.join(', ')}` };
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error('EC2 error:', message);
    return { service: 'EC2', status: 'error', message };
  }
}

async function lookupSecurityGroupByName(sgName: string): Promise<string | null> {
  try {
    const command = new DescribeSecurityGroupsCommand({
      Filters: [{ Name: 'group-name', Values: [sgName] }],
    });
    const response = await ec2Client.send(command);
    return response.SecurityGroups?.[0]?.GroupId || null;
  } catch (error) {
    console.error('Security group lookup error:', error);
    return null;
  }
}

// Generate EKS bearer token for Kubernetes API authentication
async function getEksToken(clusterName: string): Promise<string> {
  const region = process.env.AWS_REGION || 'ap-northeast-2';
  const credentials = await stsClient.config.credentials();

  // Create presigned URL for GetCallerIdentity (this is how EKS auth works)
  const stsHost = `sts.${region}.amazonaws.com`;
  const expires = 60;
  const now = new Date();
  const amzDate = now.toISOString().replace(/[:-]|\.\d{3}/g, '');
  const dateStamp = amzDate.slice(0, 8);

  // For EKS token, we use a simplified approach with exec credentials
  // The token format is: k8s-aws-v1.<base64-encoded-presigned-url>
  const presignedUrl = `https://${stsHost}/?Action=GetCallerIdentity&Version=2011-06-15&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=${encodeURIComponent(credentials.accessKeyId)}%2F${dateStamp}%2F${region}%2Fsts%2Faws4_request&X-Amz-Date=${amzDate}&X-Amz-Expires=${expires}&X-Amz-SignedHeaders=host%3Bx-k8s-aws-id`;

  // Simplified: Return a token that will work with IAM auth
  // In production, use aws-iam-authenticator or proper signing
  return `k8s-aws-v1.${Buffer.from(presignedUrl).toString('base64').replace(/=+$/, '').replace(/\+/g, '-').replace(/\//g, '_')}`;
}

// Scale Kubernetes deployments to specified replicas
async function handleK8sDeployments(event: SchedulerEvent): Promise<Result> {
  const { action, clusterName, k8sDeployments } = event;

  if (!k8sDeployments || k8sDeployments.length === 0) {
    return { service: 'K8sDeployments', status: 'skipped', message: 'No deployments configured' };
  }

  try {
    // Get cluster info
    const clusterInfo = await eksClient.send(new DescribeClusterCommand({ name: clusterName }));
    const endpoint = clusterInfo.cluster?.endpoint;
    const caData = clusterInfo.cluster?.certificateAuthority?.data;

    if (!endpoint || !caData) {
      return { service: 'K8sDeployments', status: 'error', message: 'Could not get cluster endpoint/CA' };
    }

    const results: string[] = [];

    for (const deployment of k8sDeployments) {
      const replicas = action === 'scale-down' ? 0 : deployment.scaleUpReplicas;

      try {
        // Use kubectl via SSM on Kafka instance (it has kubeconfig)
        const kafkaInstanceId = event.ec2InstanceIds?.[0]; // First EC2 is Kafka
        if (!kafkaInstanceId) {
          results.push(`${deployment.namespace}/${deployment.name}: skipped (no kubectl host)`);
          continue;
        }

        // Scale deployment using kubectl
        const scaleCommand = await ssmClient.send(
          new SendCommandCommand({
            InstanceIds: [kafkaInstanceId],
            DocumentName: 'AWS-RunShellScript',
            Parameters: {
              commands: [
                `kubectl scale deployment ${deployment.name} -n ${deployment.namespace} --replicas=${replicas} 2>&1 || echo "Scale command failed"`,
              ],
            },
          })
        );

        if (scaleCommand.Command?.CommandId) {
          // Don't wait, just fire and forget for speed
          results.push(`${deployment.namespace}/${deployment.name}: scaling to ${replicas}`);
        }
      } catch (deployError) {
        const msg = deployError instanceof Error ? deployError.message : String(deployError);
        results.push(`${deployment.namespace}/${deployment.name}: error - ${msg}`);
      }
    }

    // Wait a bit for scale commands to take effect
    await sleep(5000);

    return {
      service: 'K8sDeployments',
      status: 'success',
      message: results.join('; '),
    };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error('K8sDeployments error:', message);
    return { service: 'K8sDeployments', status: 'error', message };
  }
}

async function handleElastiCache(event: SchedulerEvent): Promise<Result> {
  const { action, elasticache } = event;

  if (!elasticache) {
    return { service: 'ElastiCache', status: 'skipped', message: 'No ElastiCache configured' };
  }

  const { clusterId, nodeType, engine, engineVersion, subnetGroupName, securityGroupName } = elasticache;

  try {
    if (action === 'scale-up') {
      // Check if cluster already exists
      try {
        const describeCommand = new DescribeCacheClustersCommand({ CacheClusterId: clusterId });
        const response = await elasticacheClient.send(describeCommand);
        const status = response.CacheClusters?.[0]?.CacheClusterStatus;
        if (status === 'available' || status === 'creating') {
          return { service: 'ElastiCache', status: 'success', message: `Cluster already exists (${status})` };
        }
      } catch (describeError) {
        // Cluster doesn't exist, proceed to create
      }

      // Look up security group by name
      const securityGroupId = await lookupSecurityGroupByName(securityGroupName);
      if (!securityGroupId) {
        return { service: 'ElastiCache', status: 'error', message: `Security group ${securityGroupName} not found` };
      }

      // Create cluster
      const createCommand = new CreateCacheClusterCommand({
        CacheClusterId: clusterId,
        CacheNodeType: nodeType,
        Engine: engine,
        EngineVersion: engineVersion,
        NumCacheNodes: 1,
        CacheSubnetGroupName: subnetGroupName,
        SecurityGroupIds: [securityGroupId],
        // No snapshots for dev
        SnapshotRetentionLimit: 0,
      });
      await elasticacheClient.send(createCommand);
      return { service: 'ElastiCache', status: 'success', message: `Creating cluster ${clusterId} (10-15 min)` };
    } else {
      // Delete cluster (no snapshot for dev)
      const deleteCommand = new DeleteCacheClusterCommand({
        CacheClusterId: clusterId,
        FinalSnapshotIdentifier: undefined, // No final snapshot
      });
      await elasticacheClient.send(deleteCommand);
      return { service: 'ElastiCache', status: 'success', message: `Deleting cluster ${clusterId}` };
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    // Already exists/deleted is not an error
    if (message.includes('CacheClusterAlreadyExists') || message.includes('CacheClusterNotFound')) {
      return { service: 'ElastiCache', status: 'success', message: `Cluster already in desired state` };
    }
    console.error('ElastiCache error:', message);
    return { service: 'ElastiCache', status: 'error', message };
  }
}

// Matching engine initialization commands
const INIT_COMMANDS = {
  INITIALIZE_ENGINE: JSON.stringify({
    code: 'INITIALIZE_ENGINE',
    data: {
      lastOrderId: 0,
      liquidationOrderIds: [],
      lastPositionId: 0,
      lastTradeId: 0,
      lastMarginHistoryId: 0,
      lastPositionHistoryId: 0,
      lastFundingHistoryId: 0,
    },
    timestamp: 0,
  }),
  UPDATE_INSTRUMENT: JSON.stringify({
    code: 'UPDATE_INSTRUMENT',
    data: {
      symbol: 'BTCUSDT',
      rootSymbol: 'BTC',
      state: 'Open',
      type: 0,
      base_underlying: 'BTC',
      quote_currency: 'USDT',
      underlying_symbol: 'BTC',
      settle_currency: 'USDT',
      initMargin: '0.01',
      maintainMargin: '0.005',
      deleverageable: true,
      makerFee: '0.0002',
      takerFee: '0.0004',
      settlementFee: '0',
      tickSize: '0.01',
      contractSize: '1',
      lotSize: '0.001',
      maxPrice: '1000000',
      maxOrderQty: '1000',
      multiplier: '1',
      contractType: 'USD_M',
    },
    timestamp: 0,
  }),
  START_ENGINE: JSON.stringify({
    code: 'START_ENGINE',
    data: {},
    timestamp: 0,
  }),
};

async function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForSsmCommand(commandId: string, instanceId: string): Promise<boolean> {
  for (let i = 0; i < 12; i++) {
    // Max 60 seconds
    await sleep(5000);
    try {
      const result = await ssmClient.send(
        new GetCommandInvocationCommand({
          CommandId: commandId,
          InstanceId: instanceId,
        })
      );
      if (result.Status === 'Success') return true;
      if (result.Status === 'Failed' || result.Status === 'Cancelled') return false;
    } catch (error) {
      // Command not ready yet
    }
  }
  return false;
}

async function waitForKafkaReady(kafkaInstanceId: string, maxWaitSeconds: number): Promise<boolean> {
  const startTime = Date.now();
  const maxWaitMs = maxWaitSeconds * 1000;

  console.log(`Waiting for Kafka to be ready (max ${maxWaitSeconds}s)...`);

  while (Date.now() - startTime < maxWaitMs) {
    try {
      // Try to run a health check command on Kafka
      const healthCheckCommand = await ssmClient.send(
        new SendCommandCommand({
          InstanceIds: [kafkaInstanceId],
          DocumentName: 'AWS-RunShellScript',
          Parameters: {
            commands: ['docker exec redpanda rpk cluster health'],
          },
        })
      );

      // Wait for command to complete
      const commandId = healthCheckCommand.Command?.CommandId;
      if (commandId) {
        const success = await waitForSsmCommand(commandId, kafkaInstanceId);
        if (success) {
          console.log('Kafka is ready!');
          return true;
        }
      }
    } catch (error) {
      // EC2 instance might not be running yet, or SSM agent not ready
      console.log(`Kafka not ready yet, retrying... (${Math.floor((Date.now() - startTime) / 1000)}s elapsed)`);
    }

    await sleep(15000); // Wait 15 seconds before retry
  }

  console.log('Timeout waiting for Kafka');
  return false;
}

async function handleMatchingEngineInit(event: SchedulerEvent): Promise<Result> {
  const { action, matchingEngineInit } = event;

  // Only run on scale-up
  if (action !== 'scale-up' || !matchingEngineInit) {
    return { service: 'MatchingEngineInit', status: 'skipped', message: 'Skipped (not scale-up or not configured)' };
  }

  const { kafkaInstanceId, preloadTopic, delaySeconds } = matchingEngineInit;

  try {
    // Wait for Kafka to be actually ready (not just a fixed delay)
    const kafkaReady = await waitForKafkaReady(kafkaInstanceId, delaySeconds);
    if (!kafkaReady) {
      return {
        service: 'MatchingEngineInit',
        status: 'error',
        message: `Kafka not ready after ${delaySeconds} seconds`,
      };
    }

    // Step 1: Reset Kafka state (delete consumer group and topic, recreate topic)
    console.log('Resetting Kafka state...');
    const resetCommand = await ssmClient.send(
      new SendCommandCommand({
        InstanceIds: [kafkaInstanceId],
        DocumentName: 'AWS-RunShellScript',
        Parameters: {
          commands: [
            'docker exec redpanda rpk group delete matching_engine 2>/dev/null || true',
            `docker exec redpanda rpk topic delete ${preloadTopic} 2>/dev/null || true`,
            'sleep 2',
            `docker exec redpanda rpk topic create ${preloadTopic} -p 1 -r 1`,
          ],
        },
      })
    );
    await waitForSsmCommand(resetCommand.Command!.CommandId!, kafkaInstanceId);

    // Step 2: Send INITIALIZE_ENGINE
    console.log('Sending INITIALIZE_ENGINE...');
    const initCommand = await ssmClient.send(
      new SendCommandCommand({
        InstanceIds: [kafkaInstanceId],
        DocumentName: 'AWS-RunShellScript',
        Parameters: {
          commands: [`echo '${INIT_COMMANDS.INITIALIZE_ENGINE}' | docker exec -i redpanda rpk topic produce ${preloadTopic}`],
        },
      })
    );
    await waitForSsmCommand(initCommand.Command!.CommandId!, kafkaInstanceId);

    // Step 3: Send UPDATE_INSTRUMENT
    console.log('Sending UPDATE_INSTRUMENT...');
    const updateCommand = await ssmClient.send(
      new SendCommandCommand({
        InstanceIds: [kafkaInstanceId],
        DocumentName: 'AWS-RunShellScript',
        Parameters: {
          commands: [`echo '${INIT_COMMANDS.UPDATE_INSTRUMENT}' | docker exec -i redpanda rpk topic produce ${preloadTopic}`],
        },
      })
    );
    await waitForSsmCommand(updateCommand.Command!.CommandId!, kafkaInstanceId);

    // Step 4: Send START_ENGINE
    console.log('Sending START_ENGINE...');
    const startCommand = await ssmClient.send(
      new SendCommandCommand({
        InstanceIds: [kafkaInstanceId],
        DocumentName: 'AWS-RunShellScript',
        Parameters: {
          commands: [`echo '${INIT_COMMANDS.START_ENGINE}' | docker exec -i redpanda rpk topic produce ${preloadTopic}`],
        },
      })
    );
    await waitForSsmCommand(startCommand.Command!.CommandId!, kafkaInstanceId);

    return {
      service: 'MatchingEngineInit',
      status: 'success',
      message: `Initialization commands sent to ${preloadTopic}`,
    };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error('MatchingEngineInit error:', message);
    return { service: 'MatchingEngineInit', status: 'error', message };
  }
}

export const handler = async (event: SchedulerEvent): Promise<{ statusCode: number; body: string }> => {
  console.log('Dev Environment Scheduler:', JSON.stringify(event, null, 2));

  const results: Result[] = [];

  if (event.action === 'scale-down') {
    // SCALE DOWN ORDER:
    // 1. First scale K8s deployments to 0 (prevents Cluster Autoscaler from scaling back up)
    // 2. Then scale down infrastructure

    // Step 1: Scale down K8s deployments FIRST (need EC2/Kafka running for kubectl)
    if (event.k8sDeployments && event.k8sDeployments.length > 0) {
      console.log('Scaling down K8s deployments first...');
      const k8sResult = await handleK8sDeployments(event);
      results.push(k8sResult);

      // Wait for deployments to scale down and pods to terminate
      console.log('Waiting for pods to terminate...');
      await sleep(10000);
    }

    // Step 2: Scale down infrastructure in parallel
    const [eksResult, rdsResult, ec2Result, elasticacheResult] = await Promise.all([
      handleEks(event),
      handleRds(event),
      handleEc2(event),
      handleElastiCache(event),
    ]);
    results.push(eksResult, rdsResult, ec2Result, elasticacheResult);

  } else {
    // SCALE UP ORDER:
    // 1. First scale up infrastructure
    // 2. Then restore K8s deployments

    // Step 1: Scale up infrastructure in parallel
    const [eksResult, rdsResult, ec2Result, elasticacheResult] = await Promise.all([
      handleEks(event),
      handleRds(event),
      handleEc2(event),
      handleElastiCache(event),
    ]);
    results.push(eksResult, rdsResult, ec2Result, elasticacheResult);

    // Step 2: Run matching engine initialization AFTER infrastructure is up
    if (event.matchingEngineInit) {
      const matchingEngineResult = await handleMatchingEngineInit(event);
      results.push(matchingEngineResult);
    }

    // Step 3: Restore K8s deployments (Cluster Autoscaler will handle node scaling)
    if (event.k8sDeployments && event.k8sDeployments.length > 0) {
      console.log('Restoring K8s deployments...');
      const k8sResult = await handleK8sDeployments(event);
      results.push(k8sResult);
    }
  }

  console.log('Results:', JSON.stringify(results, null, 2));

  const hasError = results.some((r) => r.status === 'error');

  return {
    statusCode: hasError ? 500 : 200,
    body: JSON.stringify({
      action: event.action,
      results,
    }),
  };
};

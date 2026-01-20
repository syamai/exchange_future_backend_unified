import { EKSClient, UpdateNodegroupConfigCommand } from '@aws-sdk/client-eks';
import { RDSClient, StartDBInstanceCommand, StopDBInstanceCommand } from '@aws-sdk/client-rds';
import { EC2Client, StartInstancesCommand, StopInstancesCommand } from '@aws-sdk/client-ec2';
import {
  ElastiCacheClient,
  CreateCacheClusterCommand,
  DeleteCacheClusterCommand,
  DescribeCacheClustersCommand,
} from '@aws-sdk/client-elasticache';
import { DescribeSecurityGroupsCommand } from '@aws-sdk/client-ec2';

const eksClient = new EKSClient({});
const rdsClient = new RDSClient({});
const ec2Client = new EC2Client({});
const elasticacheClient = new ElastiCacheClient({});

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

export const handler = async (event: SchedulerEvent): Promise<{ statusCode: number; body: string }> => {
  console.log('Dev Environment Scheduler:', JSON.stringify(event, null, 2));

  const results: Result[] = [];

  // Run all operations in parallel
  const [eksResult, rdsResult, ec2Result, elasticacheResult] = await Promise.all([
    handleEks(event),
    handleRds(event),
    handleEc2(event),
    handleElastiCache(event),
  ]);

  results.push(eksResult, rdsResult, ec2Result, elasticacheResult);

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

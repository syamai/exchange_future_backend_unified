import { EKSClient, UpdateNodegroupConfigCommand } from '@aws-sdk/client-eks';
import { RDSClient, StartDBInstanceCommand, StopDBInstanceCommand } from '@aws-sdk/client-rds';
import { EC2Client, StartInstancesCommand, StopInstancesCommand } from '@aws-sdk/client-ec2';

const eksClient = new EKSClient({});
const rdsClient = new RDSClient({});
const ec2Client = new EC2Client({});

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

export const handler = async (event: SchedulerEvent): Promise<{ statusCode: number; body: string }> => {
  console.log('Dev Environment Scheduler:', JSON.stringify(event, null, 2));

  const results: Result[] = [];

  // Run all operations in parallel
  const [eksResult, rdsResult, ec2Result] = await Promise.all([
    handleEks(event),
    handleRds(event),
    handleEc2(event),
  ]);

  results.push(eksResult, rdsResult, ec2Result);

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

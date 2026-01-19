import { EKSClient, UpdateNodegroupConfigCommand } from '@aws-sdk/client-eks';

const eksClient = new EKSClient({});

interface SchedulerEvent {
  action: 'scale-up' | 'scale-down';
  clusterName: string;
  nodegroupName: string;
  desiredSize: number;
  minSize: number;
  maxSize: number;
}

export const handler = async (event: SchedulerEvent): Promise<{ statusCode: number; body: string }> => {
  const { action, clusterName, nodegroupName, desiredSize, minSize, maxSize } = event;

  console.log(`EKS Scheduler: ${action} for ${clusterName}/${nodegroupName}`);
  console.log(`Scaling config: min=${minSize}, max=${maxSize}, desired=${desiredSize}`);

  try {
    const command = new UpdateNodegroupConfigCommand({
      clusterName,
      nodegroupName,
      scalingConfig: {
        minSize,
        maxSize,
        desiredSize,
      },
    });

    const response = await eksClient.send(command);

    console.log(`Successfully updated nodegroup: ${response.update?.id}`);

    return {
      statusCode: 200,
      body: JSON.stringify({
        message: `Successfully ${action} nodegroup`,
        updateId: response.update?.id,
        status: response.update?.status,
      }),
    };
  } catch (error) {
    console.error('Failed to update nodegroup:', error);
    throw error;
  }
};

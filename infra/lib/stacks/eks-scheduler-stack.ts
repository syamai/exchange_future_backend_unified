import * as cdk from 'aws-cdk-lib';
import * as lambda from 'aws-cdk-lib/aws-lambda';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as events from 'aws-cdk-lib/aws-events';
import * as targets from 'aws-cdk-lib/aws-events-targets';
import { NodejsFunction } from 'aws-cdk-lib/aws-lambda-nodejs';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';
import * as path from 'path';

export interface EksSchedulerStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
}

export class EksSchedulerStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props: EksSchedulerStackProps) {
    super(scope, id, props);

    const { config } = props;

    const clusterName = `exchange-${config.envName}`;
    const nodegroupName = `exchange-${config.envName}-spot-nodes`;

    // Lambda function for EKS node scaling
    const schedulerFn = new NodejsFunction(this, 'SchedulerFunction', {
      functionName: `exchange-${config.envName}-eks-scheduler`,
      entry: path.join(__dirname, '../lambda/eks-scheduler/index.ts'),
      handler: 'handler',
      runtime: lambda.Runtime.NODEJS_20_X,
      timeout: cdk.Duration.minutes(5),
      memorySize: 256,
      environment: {
        CLUSTER_NAME: clusterName,
        NODEGROUP_NAME: nodegroupName,
      },
    });

    // IAM permissions for EKS nodegroup scaling
    schedulerFn.addToRolePolicy(
      new iam.PolicyStatement({
        actions: ['eks:UpdateNodegroupConfig', 'eks:DescribeNodegroup'],
        resources: [
          `arn:aws:eks:${config.region}:${this.account}:nodegroup/${clusterName}/${nodegroupName}/*`,
          `arn:aws:eks:${config.region}:${this.account}:cluster/${clusterName}`,
        ],
      })
    );

    // Scale UP event payload (weekdays 09:00 KST = 00:00 UTC)
    const scaleUpPayload = {
      action: 'scale-up',
      clusterName,
      nodegroupName,
      desiredSize: config.eksNodeDesiredSize,
      minSize: config.eksNodeMinSize,
      maxSize: config.eksNodeMaxSize,
    };

    // Scale DOWN event payload (weekdays 22:00 KST = 13:00 UTC)
    const scaleDownPayload = {
      action: 'scale-down',
      clusterName,
      nodegroupName,
      desiredSize: 0,
      minSize: 0,
      maxSize: config.eksNodeMaxSize,
    };

    // EventBridge rule: Scale UP at 09:00 KST (00:00 UTC) on weekdays
    new events.Rule(this, 'ScaleUpRule', {
      ruleName: `exchange-${config.envName}-eks-scale-up`,
      description: 'Scale up EKS nodes at 09:00 KST on weekdays',
      schedule: events.Schedule.cron({
        minute: '0',
        hour: '0', // 00:00 UTC = 09:00 KST
        weekDay: 'MON-FRI',
      }),
      targets: [
        new targets.LambdaFunction(schedulerFn, {
          event: events.RuleTargetInput.fromObject(scaleUpPayload),
        }),
      ],
    });

    // EventBridge rule: Scale DOWN at 22:00 KST (13:00 UTC) on weekdays
    new events.Rule(this, 'ScaleDownRule', {
      ruleName: `exchange-${config.envName}-eks-scale-down`,
      description: 'Scale down EKS nodes at 22:00 KST on weekdays',
      schedule: events.Schedule.cron({
        minute: '0',
        hour: '13', // 13:00 UTC = 22:00 KST
        weekDay: 'MON-FRI',
      }),
      targets: [
        new targets.LambdaFunction(schedulerFn, {
          event: events.RuleTargetInput.fromObject(scaleDownPayload),
        }),
      ],
    });

    // Outputs
    new cdk.CfnOutput(this, 'SchedulerFunctionArn', {
      value: schedulerFn.functionArn,
      description: 'EKS Scheduler Lambda ARN',
    });

    new cdk.CfnOutput(this, 'ScaleUpSchedule', {
      value: '09:00 KST (Mon-Fri)',
      description: 'Scale up schedule',
    });

    new cdk.CfnOutput(this, 'ScaleDownSchedule', {
      value: '22:00 KST (Mon-Fri)',
      description: 'Scale down schedule',
    });

    // Manual invocation commands
    new cdk.CfnOutput(this, 'ManualScaleUpCommand', {
      value: `aws lambda invoke --function-name exchange-${config.envName}-eks-scheduler --payload '${JSON.stringify(scaleUpPayload)}' --cli-binary-format raw-in-base64-out /dev/stdout`,
      description: 'Command to manually scale up',
    });

    new cdk.CfnOutput(this, 'ManualScaleDownCommand', {
      value: `aws lambda invoke --function-name exchange-${config.envName}-eks-scheduler --payload '${JSON.stringify(scaleDownPayload)}' --cli-binary-format raw-in-base64-out /dev/stdout`,
      description: 'Command to manually scale down',
    });
  }
}

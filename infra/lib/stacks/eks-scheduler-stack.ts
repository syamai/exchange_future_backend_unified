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
  natInstanceId: string;
}

export class EksSchedulerStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props: EksSchedulerStackProps) {
    super(scope, id, props);

    const { config, natInstanceId } = props;

    // Resource identifiers
    const clusterName = `exchange-${config.envName}`;
    const nodegroupName = `exchange-${config.envName}-spot-nodes`;
    const rdsInstanceId = `exchange-${config.envName}-mysql`;
    const kafkaInstanceId = 'i-044548ca3fe3ae1a1'; // Kafka EC2 instance
    // NAT Instance is passed from VPC stack

    // Lambda function for dev environment scheduling
    const schedulerFn = new NodejsFunction(this, 'SchedulerFunction', {
      functionName: `exchange-${config.envName}-dev-scheduler`,
      entry: path.join(__dirname, '../lambda/eks-scheduler/index.ts'),
      handler: 'handler',
      runtime: lambda.Runtime.NODEJS_20_X,
      timeout: cdk.Duration.minutes(5),
      memorySize: 256,
      environment: {
        CLUSTER_NAME: clusterName,
        NODEGROUP_NAME: nodegroupName,
        RDS_INSTANCE_ID: rdsInstanceId,
        KAFKA_INSTANCE_ID: kafkaInstanceId,
      },
    });

    // IAM permissions for EKS nodegroup scaling
    schedulerFn.addToRolePolicy(
      new iam.PolicyStatement({
        sid: 'EKSPermissions',
        actions: ['eks:UpdateNodegroupConfig', 'eks:DescribeNodegroup'],
        resources: [
          `arn:aws:eks:${config.region}:${this.account}:nodegroup/${clusterName}/${nodegroupName}/*`,
          `arn:aws:eks:${config.region}:${this.account}:cluster/${clusterName}`,
        ],
      })
    );

    // IAM permissions for RDS start/stop
    schedulerFn.addToRolePolicy(
      new iam.PolicyStatement({
        sid: 'RDSPermissions',
        actions: ['rds:StartDBInstance', 'rds:StopDBInstance', 'rds:DescribeDBInstances'],
        resources: [`arn:aws:rds:${config.region}:${this.account}:db:${rdsInstanceId}`],
      })
    );

    // IAM permissions for EC2 start/stop (Kafka + NAT Instance) and security group lookup
    schedulerFn.addToRolePolicy(
      new iam.PolicyStatement({
        sid: 'EC2Permissions',
        actions: ['ec2:StartInstances', 'ec2:StopInstances', 'ec2:DescribeInstances'],
        resources: [
          `arn:aws:ec2:${config.region}:${this.account}:instance/${kafkaInstanceId}`,
          `arn:aws:ec2:${config.region}:${this.account}:instance/*`, // NAT Instance (dynamic ID)
        ],
      })
    );

    schedulerFn.addToRolePolicy(
      new iam.PolicyStatement({
        sid: 'EC2DescribePermissions',
        actions: ['ec2:DescribeSecurityGroups'],
        resources: ['*'],
      })
    );

    // IAM permissions for ElastiCache create/delete
    schedulerFn.addToRolePolicy(
      new iam.PolicyStatement({
        sid: 'ElastiCachePermissions',
        actions: [
          'elasticache:CreateCacheCluster',
          'elasticache:DeleteCacheCluster',
          'elasticache:DescribeCacheClusters',
        ],
        resources: [
          `arn:aws:elasticache:${config.region}:${this.account}:cluster:exchange-${config.envName}-redis`,
        ],
      })
    );

    // ElastiCache subnet group permission (needed for creating cluster)
    schedulerFn.addToRolePolicy(
      new iam.PolicyStatement({
        sid: 'ElastiCacheSubnetPermissions',
        actions: ['elasticache:DescribeCacheSubnetGroups'],
        resources: ['*'],
      })
    );

    // ElastiCache config
    const elasticacheConfig = {
      clusterId: `exchange-${config.envName}-redis`,
      nodeType: config.redisNodeType,
      engine: 'redis',
      engineVersion: '7.0',
      subnetGroupName: `exchange-${config.envName}-redis-subnet`,
      securityGroupName: `exchange-${config.envName}-redis-sg`,
    };

    // Scale UP event payload (weekdays 11:00 KST = 02:00 UTC)
    const scaleUpPayload = {
      action: 'scale-up',
      // EKS
      clusterName,
      nodegroupName,
      desiredSize: config.eksNodeDesiredSize,
      minSize: config.eksNodeMinSize,
      maxSize: config.eksNodeMaxSize,
      // RDS
      rdsInstanceId,
      // EC2 (Kafka + NAT Instance)
      ec2InstanceIds: [kafkaInstanceId, natInstanceId],
      // ElastiCache
      elasticache: elasticacheConfig,
    };

    // Scale DOWN event payload (weekdays 20:00 KST = 11:00 UTC)
    const scaleDownPayload = {
      action: 'scale-down',
      // EKS
      clusterName,
      nodegroupName,
      desiredSize: 0,
      minSize: 0,
      maxSize: config.eksNodeMaxSize,
      // RDS
      rdsInstanceId,
      // EC2 (Kafka + NAT Instance)
      ec2InstanceIds: [kafkaInstanceId, natInstanceId],
      // ElastiCache
      elasticache: elasticacheConfig,
    };

    // EventBridge rule: Scale UP at 11:00 KST (02:00 UTC) on weekdays
    new events.Rule(this, 'ScaleUpRule', {
      ruleName: `exchange-${config.envName}-dev-scale-up`,
      description: 'Start dev environment at 11:00 KST on weekdays (EKS + RDS + Kafka + Redis + NAT)',
      schedule: events.Schedule.cron({
        minute: '0',
        hour: '2', // 02:00 UTC = 11:00 KST
        weekDay: 'MON-FRI',
      }),
      targets: [
        new targets.LambdaFunction(schedulerFn, {
          event: events.RuleTargetInput.fromObject(scaleUpPayload),
        }),
      ],
    });

    // EventBridge rule: Scale DOWN at 20:00 KST (11:00 UTC) on weekdays
    new events.Rule(this, 'ScaleDownRule', {
      ruleName: `exchange-${config.envName}-dev-scale-down`,
      description: 'Stop dev environment at 20:00 KST on weekdays (EKS + RDS + Kafka + Redis + NAT)',
      schedule: events.Schedule.cron({
        minute: '0',
        hour: '11', // 11:00 UTC = 20:00 KST
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
      description: 'Dev Environment Scheduler Lambda ARN',
    });

    new cdk.CfnOutput(this, 'Schedule', {
      value: 'Weekdays: 11:00 KST (start) / 20:00 KST (stop)',
      description: 'Schedule for dev environment',
    });

    new cdk.CfnOutput(this, 'ManagedResources', {
      value: `EKS: ${clusterName}, RDS: ${rdsInstanceId}, Kafka: ${kafkaInstanceId}, Redis: ${elasticacheConfig.clusterId}, NAT: ${natInstanceId}`,
      description: 'Resources managed by scheduler',
    });

    // Manual invocation commands
    new cdk.CfnOutput(this, 'ManualStartCommand', {
      value: `aws lambda invoke --function-name exchange-${config.envName}-dev-scheduler --payload '${JSON.stringify(scaleUpPayload)}' --cli-binary-format raw-in-base64-out --region ${config.region} /dev/stdout`,
      description: 'Command to manually start dev environment',
    });

    new cdk.CfnOutput(this, 'ManualStopCommand', {
      value: `aws lambda invoke --function-name exchange-${config.envName}-dev-scheduler --payload '${JSON.stringify(scaleDownPayload)}' --cli-binary-format raw-in-base64-out --region ${config.region} /dev/stdout`,
      description: 'Command to manually stop dev environment',
    });
  }
}

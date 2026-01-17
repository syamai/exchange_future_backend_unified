import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as iam from 'aws-cdk-lib/aws-iam';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';

export interface KafkaStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
  vpc: ec2.Vpc;
  eksSecurityGroup: ec2.SecurityGroup;
}

export class KafkaStack extends cdk.Stack {
  public readonly instance: ec2.Instance;

  constructor(scope: Construct, id: string, props: KafkaStackProps) {
    super(scope, id, props);

    const { config, vpc, eksSecurityGroup } = props;

    // Kafka Security Group
    const kafkaSecurityGroup = new ec2.SecurityGroup(this, 'KafkaSecurityGroup', {
      vpc,
      securityGroupName: `exchange-${config.envName}-kafka-sg`,
      description: 'Security group for Kafka (Redpanda)',
      allowAllOutbound: true,
    });

    // Allow Kafka access from EKS
    kafkaSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(9092),
      'Kafka from EKS'
    );

    // Allow self access (inter-broker)
    kafkaSecurityGroup.addIngressRule(
      kafkaSecurityGroup,
      ec2.Port.tcp(9092),
      'Kafka inter-broker'
    );

    // Admin Console access from EKS
    kafkaSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(9644),
      'Redpanda Admin from EKS'
    );

    // Schema Registry
    kafkaSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(8081),
      'Schema Registry from EKS'
    );

    // IAM Role for EC2
    const role = new iam.Role(this, 'KafkaRole', {
      roleName: `exchange-${config.envName}-kafka-role`,
      assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonSSMManagedInstanceCore'),
      ],
    });

    // User Data Script (Redpanda installation)
    const userData = ec2.UserData.forLinux();
    userData.addCommands(
      '#!/bin/bash',
      'set -ex',
      '',
      '# Install Docker',
      'yum update -y',
      'amazon-linux-extras install docker -y',
      'systemctl start docker',
      'systemctl enable docker',
      'usermod -a -G docker ec2-user',
      '',
      '# Create data directory',
      'mkdir -p /data/redpanda',
      '',
      '# Get instance private IP',
      'PRIVATE_IP=$(curl -s http://169.254.169.254/latest/meta-data/local-ipv4)',
      '',
      '# Run Redpanda',
      'docker run -d --name redpanda \\',
      '  --restart always \\',
      '  -p 9092:9092 \\',
      '  -p 8081:8081 \\',
      '  -p 8082:8082 \\',
      '  -p 9644:9644 \\',
      '  -v /data/redpanda:/var/lib/redpanda/data \\',
      '  docker.redpanda.com/redpandadata/redpanda:latest \\',
      '  redpanda start \\',
      '  --advertise-kafka-addr PLAINTEXT://${PRIVATE_IP}:9092 \\',
      '  --smp 1 \\',
      '  --memory 1G \\',
      '  --overprovisioned',
      '',
      '# Wait for Redpanda to start',
      'sleep 30',
      '',
      '# Create topics for matching engine shards',
      'docker exec redpanda rpk topic create \\',
      '  matching-engine-shard-1-input \\',
      '  matching-engine-shard-1-output \\',
      '  matching-engine-shard-2-input \\',
      '  matching-engine-shard-2-output \\',
      '  matching-engine-shard-3-input \\',
      '  matching-engine-shard-3-output \\',
      '  shard-sync-shard-1 \\',
      '  shard-sync-shard-2 \\',
      '  shard-sync-shard-3 \\',
      '  -p 3 -r 1',
      '',
      '# Create legacy topics for backward compatibility',
      'docker exec redpanda rpk topic create \\',
      '  matching_engine_input \\',
      '  matching_engine_output \\',
      '  orderbook_output \\',
      '  -p 3 -r 1',
    );

    // EC2 Instance
    this.instance = new ec2.Instance(this, 'Instance', {
      instanceName: `exchange-${config.envName}-kafka`,
      instanceType: new ec2.InstanceType(config.kafkaInstanceType),
      machineImage: ec2.MachineImage.latestAmazonLinux2(),
      vpc,
      vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS },
      securityGroup: kafkaSecurityGroup,
      role,
      userData,
      blockDevices: [
        {
          deviceName: '/dev/xvda',
          volume: ec2.BlockDeviceVolume.ebs(50, {
            volumeType: ec2.EbsDeviceVolumeType.GP3,
            encrypted: true,
          }),
        },
      ],
    });

    // Outputs
    new cdk.CfnOutput(this, 'KafkaPrivateIp', {
      value: this.instance.instancePrivateIp,
      exportName: `${config.envName}-KafkaPrivateIp`,
    });

    new cdk.CfnOutput(this, 'KafkaInstanceId', {
      value: this.instance.instanceId,
      exportName: `${config.envName}-KafkaInstanceId`,
    });

    new cdk.CfnOutput(this, 'KafkaBootstrapServers', {
      value: `${this.instance.instancePrivateIp}:9092`,
      exportName: `${config.envName}-KafkaBootstrapServers`,
    });
  }
}

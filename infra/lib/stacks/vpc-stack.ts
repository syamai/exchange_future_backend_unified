import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';

export interface VpcStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
}

export class VpcStack extends cdk.Stack {
  public readonly vpc: ec2.Vpc;
  public readonly eksSecurityGroup: ec2.SecurityGroup;

  constructor(scope: Construct, id: string, props: VpcStackProps) {
    super(scope, id, props);

    const { config } = props;

    // VPC with 2 AZs, Public/Private/Isolated subnets
    this.vpc = new ec2.Vpc(this, 'Vpc', {
      vpcName: `exchange-${config.envName}-vpc`,
      ipAddresses: ec2.IpAddresses.cidr(config.vpcCidr),
      maxAzs: 2,

      // Cost optimization: Single NAT Gateway
      natGateways: 1,

      subnetConfiguration: [
        {
          name: 'Public',
          subnetType: ec2.SubnetType.PUBLIC,
          cidrMask: 24,
        },
        {
          name: 'Private',
          subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS,
          cidrMask: 24,
        },
        {
          name: 'Database',
          subnetType: ec2.SubnetType.PRIVATE_ISOLATED,
          cidrMask: 24,
        },
      ],
    });

    // Security Group for EKS cluster
    this.eksSecurityGroup = new ec2.SecurityGroup(this, 'EksSecurityGroup', {
      vpc: this.vpc,
      securityGroupName: `exchange-${config.envName}-eks-sg`,
      description: 'Security group for EKS cluster',
      allowAllOutbound: true,
    });

    // Add tags for EKS subnet auto-discovery
    cdk.Tags.of(this.vpc).add('Environment', config.envName);

    this.vpc.publicSubnets.forEach((subnet) => {
      cdk.Tags.of(subnet).add('kubernetes.io/role/elb', '1');
      cdk.Tags.of(subnet).add(
        `kubernetes.io/cluster/exchange-${config.envName}`,
        'shared'
      );
    });

    this.vpc.privateSubnets.forEach((subnet) => {
      cdk.Tags.of(subnet).add('kubernetes.io/role/internal-elb', '1');
      cdk.Tags.of(subnet).add(
        `kubernetes.io/cluster/exchange-${config.envName}`,
        'shared'
      );
    });

    // Outputs
    new cdk.CfnOutput(this, 'VpcId', {
      value: this.vpc.vpcId,
      exportName: `${config.envName}-VpcId`,
    });

    new cdk.CfnOutput(this, 'VpcCidr', {
      value: this.vpc.vpcCidrBlock,
      exportName: `${config.envName}-VpcCidr`,
    });
  }
}

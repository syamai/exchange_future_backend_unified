import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as eks from 'aws-cdk-lib/aws-eks';
import * as iam from 'aws-cdk-lib/aws-iam';
import { KubectlV29Layer } from '@aws-cdk/lambda-layer-kubectl-v29';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';

export interface EksStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
  vpc: ec2.Vpc;
  eksSecurityGroup: ec2.SecurityGroup;
}

export class EksStack extends cdk.Stack {
  public readonly cluster: eks.Cluster;

  constructor(scope: Construct, id: string, props: EksStackProps) {
    super(scope, id, props);

    const { config, vpc, eksSecurityGroup } = props;

    // IAM Role for kubectl access (admin)
    const mastersRole = new iam.Role(this, 'MastersRole', {
      assumedBy: new iam.AccountRootPrincipal(),
      roleName: `exchange-${config.envName}-eks-masters-role`,
    });

    // Kubectl Lambda Layer for EKS 1.29
    const kubectlLayer = new KubectlV29Layer(this, 'KubectlLayer');

    // EKS Cluster
    this.cluster = new eks.Cluster(this, 'Cluster', {
      clusterName: `exchange-${config.envName}`,
      vpc,
      vpcSubnets: [{ subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS }],
      version: eks.KubernetesVersion.V1_29,
      kubectlLayer,
      defaultCapacity: 0, // Create managed node group separately
      securityGroup: eksSecurityGroup,
      endpointAccess: eks.EndpointAccess.PUBLIC_AND_PRIVATE,
      mastersRole,

      // Cluster logging
      clusterLogging: [
        eks.ClusterLoggingTypes.API,
        eks.ClusterLoggingTypes.AUDIT,
        eks.ClusterLoggingTypes.AUTHENTICATOR,
      ],
    });

    // Add IAM user to cluster admin
    this.cluster.awsAuth.addUserMapping(
      iam.User.fromUserName(this, 'AdminUser', 'Alex'),
      { groups: ['system:masters'] }
    );

    // Spot instance Node Group (cost optimization)
    this.cluster.addNodegroupCapacity('SpotNodeGroup', {
      nodegroupName: `exchange-${config.envName}-spot-nodes`,
      instanceTypes: [
        new ec2.InstanceType(config.eksNodeInstanceType),
        new ec2.InstanceType('t3a.medium'), // Fallback
      ],
      capacityType: eks.CapacityType.SPOT,
      minSize: config.eksNodeMinSize,
      maxSize: config.eksNodeMaxSize,
      desiredSize: config.eksNodeDesiredSize,
      diskSize: 50,
      subnets: { subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS },
      labels: {
        'node-type': 'spot',
        environment: config.envName,
      },
    });

    // Outputs
    new cdk.CfnOutput(this, 'ClusterName', {
      value: this.cluster.clusterName,
      exportName: `${config.envName}-EksClusterName`,
    });

    new cdk.CfnOutput(this, 'KubeconfigCommand', {
      value: `aws eks update-kubeconfig --region ${config.region} --name ${this.cluster.clusterName}`,
      description: 'Command to update kubeconfig',
    });

    new cdk.CfnOutput(this, 'ClusterEndpoint', {
      value: this.cluster.clusterEndpoint,
      exportName: `${config.envName}-EksClusterEndpoint`,
    });

    new cdk.CfnOutput(this, 'ClusterSecurityGroupId', {
      value: this.cluster.clusterSecurityGroupId,
      exportName: `${config.envName}-EksClusterSecurityGroupId`,
    });
  }
}

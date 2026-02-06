import * as cdk from 'aws-cdk-lib';
import * as ecr from 'aws-cdk-lib/aws-ecr';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';

export interface EcrStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
}

export class EcrStack extends cdk.Stack {
  public readonly matchingEngineRepo: ecr.Repository;
  public readonly backendRepo: ecr.Repository;
  public readonly spotBackendRepo: ecr.Repository;
  public readonly spotEchoServerRepo: ecr.Repository;

  constructor(scope: Construct, id: string, props: EcrStackProps) {
    super(scope, id, props);

    const { config } = props;

    // Matching Engine Repository
    this.matchingEngineRepo = new ecr.Repository(this, 'MatchingEngineRepo', {
      repositoryName: 'exchange/matching-engine-shard',
      imageScanOnPush: true,
      imageTagMutability: ecr.TagMutability.MUTABLE,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
      emptyOnDelete: true,

      // Cost optimization: Auto-delete old images
      lifecycleRules: [
        {
          description: 'Keep last 10 images',
          maxImageCount: 10,
          rulePriority: 1,
        },
      ],
    });

    // Backend Repository (Future)
    this.backendRepo = new ecr.Repository(this, 'BackendRepo', {
      repositoryName: 'exchange/future-backend',
      imageScanOnPush: true,
      imageTagMutability: ecr.TagMutability.MUTABLE,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
      emptyOnDelete: true,

      lifecycleRules: [
        {
          description: 'Keep last 10 images',
          maxImageCount: 10,
          rulePriority: 1,
        },
      ],
    });

    // Spot Backend Repository (PHP/Laravel)
    this.spotBackendRepo = new ecr.Repository(this, 'SpotBackendRepo', {
      repositoryName: 'exchange/spot-backend',
      imageScanOnPush: true,
      imageTagMutability: ecr.TagMutability.MUTABLE,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
      emptyOnDelete: true,

      lifecycleRules: [
        {
          description: 'Keep last 10 images',
          maxImageCount: 10,
          rulePriority: 1,
        },
      ],
    });

    // Spot Echo Server Repository (Laravel Echo Server for socket.io)
    this.spotEchoServerRepo = new ecr.Repository(this, 'SpotEchoServerRepo', {
      repositoryName: 'exchange/spot-echo-server',
      imageScanOnPush: true,
      imageTagMutability: ecr.TagMutability.MUTABLE,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
      emptyOnDelete: true,

      lifecycleRules: [
        {
          description: 'Keep last 10 images',
          maxImageCount: 10,
          rulePriority: 1,
        },
      ],
    });

    // Outputs
    new cdk.CfnOutput(this, 'MatchingEngineRepoUri', {
      value: this.matchingEngineRepo.repositoryUri,
      exportName: `${config.envName}-MatchingEngineRepoUri`,
    });

    new cdk.CfnOutput(this, 'MatchingEngineRepoName', {
      value: this.matchingEngineRepo.repositoryName,
      exportName: `${config.envName}-MatchingEngineRepoName`,
    });

    new cdk.CfnOutput(this, 'BackendRepoUri', {
      value: this.backendRepo.repositoryUri,
      exportName: `${config.envName}-BackendRepoUri`,
    });

    new cdk.CfnOutput(this, 'BackendRepoName', {
      value: this.backendRepo.repositoryName,
      exportName: `${config.envName}-BackendRepoName`,
    });

    new cdk.CfnOutput(this, 'SpotBackendRepoUri', {
      value: this.spotBackendRepo.repositoryUri,
      exportName: `${config.envName}-SpotBackendRepoUri`,
    });

    new cdk.CfnOutput(this, 'SpotBackendRepoName', {
      value: this.spotBackendRepo.repositoryName,
      exportName: `${config.envName}-SpotBackendRepoName`,
    });

    new cdk.CfnOutput(this, 'SpotEchoServerRepoUri', {
      value: this.spotEchoServerRepo.repositoryUri,
      exportName: `${config.envName}-SpotEchoServerRepoUri`,
    });

    new cdk.CfnOutput(this, 'SpotEchoServerRepoName', {
      value: this.spotEchoServerRepo.repositoryName,
      exportName: `${config.envName}-SpotEchoServerRepoName`,
    });

    new cdk.CfnOutput(this, 'EcrLoginCommand', {
      value: `aws ecr get-login-password --region ${props.env?.region || 'ap-northeast-2'} | docker login --username AWS --password-stdin ${this.matchingEngineRepo.repositoryUri.split('/')[0]}`,
      description: 'Command to login to ECR',
    });
  }
}

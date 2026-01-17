# AWS 개발/테스트 인프라 구성 가이드 (AWS CDK)

## 개요

이 문서는 암호화폐 선물 거래소 시스템을 개발 및 테스트하기 위한 **비용 최적화된 AWS 인프라**를 AWS CDK (TypeScript)로 구성하는 방법을 다룹니다.

### 프로덕션 vs 개발 환경 비교

| 항목 | 프로덕션 | 개발/테스트 |
|------|---------|------------|
| 가용 영역 | 3 AZ | 1-2 AZ |
| EKS 노드 | m6i.2xlarge x 6+ | t3.medium x 2 |
| RDS | Multi-AZ, r6g.xlarge | Single-AZ, t3.micro |
| ElastiCache | 클러스터 모드, r6g.large | 단일 노드, t3.micro |
| MSK | 3 브로커, kafka.m5.large | 단일 브로커 or Redpanda |
| 예상 비용 | ~$3,000+/월 | ~$150-300/월 |

### AWS CDK를 선택한 이유

- **TypeScript 지원**: 기존 future-backend와 동일한 언어
- **타입 안전성**: 컴파일 타임에 오류 감지
- **IDE 지원**: 자동완성, 리팩토링
- **고수준 추상화**: L2/L3 Constructs로 간결한 코드
- **CloudFormation 통합**: 안정적인 배포 및 롤백

---

## 아키텍처 다이어그램

```
┌────────────────────────────────────────────────────────────────────┐
│                         AWS Cloud (Dev)                             │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │                    Region: ap-northeast-2                       ││
│  │                                                                 ││
│  │   ┌──────────────────────────────────────────────────────────┐ ││
│  │   │                  VPC: 10.0.0.0/16                         │ ││
│  │   │                                                           │ ││
│  │   │   ┌─────────────────────┐   ┌─────────────────────┐      │ ││
│  │   │   │      AZ-2a          │   │      AZ-2c          │      │ ││
│  │   │   │                     │   │                     │      │ ││
│  │   │   │ ┌─────────────────┐ │   │ ┌─────────────────┐ │      │ ││
│  │   │   │ │  Public Subnet  │ │   │ │  Public Subnet  │ │      │ ││
│  │   │   │ │   10.0.1.0/24   │ │   │ │   10.0.2.0/24   │ │      │ ││
│  │   │   │ │                 │ │   │ │                 │ │      │ ││
│  │   │   │ │  ┌───────────┐  │ │   │ │  ┌───────────┐  │ │      │ ││
│  │   │   │ │  │    ALB    │  │ │   │ │  │ NAT GW    │  │ │      │ ││
│  │   │   │ │  └───────────┘  │ │   │ │  └───────────┘  │ │      │ ││
│  │   │   │ └─────────────────┘ │   │ └─────────────────┘ │      │ ││
│  │   │   │                     │   │                     │      │ ││
│  │   │   │ ┌─────────────────┐ │   │ ┌─────────────────┐ │      │ ││
│  │   │   │ │ Private Subnet  │ │   │ │ Private Subnet  │ │      │ ││
│  │   │   │ │  10.0.11.0/24   │ │   │ │  10.0.12.0/24   │ │      │ ││
│  │   │   │ │                 │ │   │ │                 │ │      │ ││
│  │   │   │ │ ┌─────────────┐ │ │   │ │ ┌─────────────┐ │ │      │ ││
│  │   │   │ │ │  EKS Node   │ │ │   │ │ │  EKS Node   │ │ │      │ ││
│  │   │   │ │ │ t3.medium   │ │ │   │ │ │ t3.medium   │ │ │      │ ││
│  │   │   │ │ │             │ │ │   │ │ │             │ │ │      │ ││
│  │   │   │ │ │ • Backend   │ │ │   │ │ │ • Shard-1   │ │ │      │ ││
│  │   │   │ │ │ • Shard-2   │ │ │   │ │ │ • Shard-3   │ │ │      │ ││
│  │   │   │ │ └─────────────┘ │ │   │ │ └─────────────┘ │ │      │ ││
│  │   │   │ │                 │ │   │ │                 │ │      │ ││
│  │   │   │ │ ┌─────────────┐ │ │   │ │                 │ │      │ ││
│  │   │   │ │ │   Redpanda  │ │ │   │ │                 │ │      │ ││
│  │   │   │ │ │  t3.medium  │ │ │   │ │                 │ │      │ ││
│  │   │   │ │ └─────────────┘ │ │   │ │                 │ │      │ ││
│  │   │   │ └─────────────────┘ │   │ └─────────────────┘ │      │ ││
│  │   │   │                     │   │                     │      │ ││
│  │   │   │ ┌─────────────────┐ │   │                     │      │ ││
│  │   │   │ │    DB Subnet    │ │   │                     │      │ ││
│  │   │   │ │  10.0.21.0/24   │ │   │                     │      │ ││
│  │   │   │ │                 │ │   │                     │      │ ││
│  │   │   │ │ ┌─────┐ ┌─────┐ │ │   │                     │      │ ││
│  │   │   │ │ │ RDS │ │Redis│ │ │   │                     │      │ ││
│  │   │   │ │ └─────┘ └─────┘ │ │   │                     │      │ ││
│  │   │   │ └─────────────────┘ │   │                     │      │ ││
│  │   │   └─────────────────────┘   └─────────────────────┘      │ ││
│  │   │                                                           │ ││
│  │   └──────────────────────────────────────────────────────────┘ ││
│  │                                                                 ││
│  │   ┌──────────────────────────────────────────────────────────┐ ││
│  │   │  ECR: exchange/matching-engine-shard, exchange/backend    │ ││
│  │   │  S3:  exchange-dev-artifacts, exchange-dev-logs           │ ││
│  │   └──────────────────────────────────────────────────────────┘ ││
│  │                                                                 ││
│  └────────────────────────────────────────────────────────────────┘│
└────────────────────────────────────────────────────────────────────┘
```

---

## 사전 요구사항

### 필수 도구 설치

```bash
# Node.js (18.x 이상)
brew install node

# AWS CLI
curl "https://awscli.amazonaws.com/AWSCLIV2.pkg" -o "AWSCLIV2.pkg"
sudo installer -pkg AWSCLIV2.pkg -target /

# AWS CDK CLI
npm install -g aws-cdk

# kubectl
brew install kubectl

# eksctl (선택)
brew install eksctl
```

### AWS 자격 증명 설정

```bash
# AWS Configure
aws configure
# AWS Access Key ID: YOUR_ACCESS_KEY
# AWS Secret Access Key: YOUR_SECRET_KEY
# Default region name: ap-northeast-2
# Default output format: json

# 자격 증명 확인
aws sts get-caller-identity

# CDK Bootstrap (최초 1회)
cdk bootstrap aws://ACCOUNT_ID/ap-northeast-2
```

---

## 1. CDK 프로젝트 생성

### 1.1 디렉토리 구조

```
infra/
├── bin/
│   └── app.ts                 # CDK 앱 엔트리포인트
├── lib/
│   ├── stacks/
│   │   ├── vpc-stack.ts       # VPC 스택
│   │   ├── eks-stack.ts       # EKS 스택
│   │   ├── rds-stack.ts       # RDS 스택
│   │   ├── elasticache-stack.ts  # Redis 스택
│   │   ├── kafka-stack.ts     # Kafka (EC2) 스택
│   │   └── ecr-stack.ts       # ECR 스택
│   └── constructs/
│       └── redpanda-instance.ts  # Redpanda EC2 Construct
├── config/
│   └── dev.ts                 # 개발 환경 설정
├── package.json
├── tsconfig.json
└── cdk.json
```

### 1.2 프로젝트 초기화

```bash
mkdir -p infra && cd infra

# CDK 프로젝트 초기화
cdk init app --language typescript

# 필요한 패키지 설치
npm install @aws-cdk/aws-ec2 @aws-cdk/aws-eks @aws-cdk/aws-rds \
  @aws-cdk/aws-elasticache @aws-cdk/aws-ecr @aws-cdk/aws-iam \
  @aws-cdk/aws-secretsmanager
```

### 1.3 package.json

```json
{
  "name": "exchange-infra",
  "version": "1.0.0",
  "scripts": {
    "build": "tsc",
    "watch": "tsc -w",
    "cdk": "cdk",
    "deploy:dev": "cdk deploy --all -c env=dev",
    "destroy:dev": "cdk destroy --all -c env=dev",
    "diff": "cdk diff --all -c env=dev"
  },
  "devDependencies": {
    "@types/node": "^20.0.0",
    "typescript": "~5.3.0",
    "aws-cdk": "^2.120.0"
  },
  "dependencies": {
    "aws-cdk-lib": "^2.120.0",
    "constructs": "^10.0.0"
  }
}
```

---

## 2. 환경 설정

### 2.1 config/dev.ts

```typescript
// infra/config/dev.ts

export interface EnvironmentConfig {
  readonly envName: string;
  readonly region: string;
  readonly vpcCidr: string;
  readonly eksNodeInstanceType: string;
  readonly eksNodeDesiredSize: number;
  readonly eksNodeMinSize: number;
  readonly eksNodeMaxSize: number;
  readonly rdsInstanceClass: string;
  readonly redisNodeType: string;
  readonly kafkaInstanceType: string;
  readonly dbUsername: string;
}

export const devConfig: EnvironmentConfig = {
  envName: 'dev',
  region: 'ap-northeast-2',
  vpcCidr: '10.0.0.0/16',

  // EKS - Spot 인스턴스로 비용 절감
  eksNodeInstanceType: 't3.medium',
  eksNodeDesiredSize: 2,
  eksNodeMinSize: 1,
  eksNodeMaxSize: 4,

  // RDS - 개발 환경용 최소 스펙
  rdsInstanceClass: 'db.t3.micro',

  // Redis - 개발 환경용 최소 스펙
  redisNodeType: 'cache.t3.micro',

  // Kafka (Redpanda on EC2)
  kafkaInstanceType: 't3.medium',

  // Database
  dbUsername: 'admin',
};
```

---

## 3. CDK 스택 구현

### 3.1 CDK 앱 엔트리포인트

```typescript
// infra/bin/app.ts

#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { VpcStack } from '../lib/stacks/vpc-stack';
import { EksStack } from '../lib/stacks/eks-stack';
import { RdsStack } from '../lib/stacks/rds-stack';
import { ElasticacheStack } from '../lib/stacks/elasticache-stack';
import { KafkaStack } from '../lib/stacks/kafka-stack';
import { EcrStack } from '../lib/stacks/ecr-stack';
import { devConfig } from '../config/dev';

const app = new cdk.App();

const env = app.node.tryGetContext('env') || 'dev';
const config = env === 'dev' ? devConfig : devConfig; // Add more environments as needed

const envProps = {
  env: {
    account: process.env.CDK_DEFAULT_ACCOUNT,
    region: config.region,
  },
};

// VPC Stack (기반 네트워크)
const vpcStack = new VpcStack(app, `Exchange-${config.envName}-Vpc`, {
  ...envProps,
  config,
});

// ECR Stack (컨테이너 레지스트리)
const ecrStack = new EcrStack(app, `Exchange-${config.envName}-Ecr`, {
  ...envProps,
  config,
});

// RDS Stack (MySQL)
const rdsStack = new RdsStack(app, `Exchange-${config.envName}-Rds`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
});

// ElastiCache Stack (Redis)
const elasticacheStack = new ElasticacheStack(app, `Exchange-${config.envName}-Redis`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
});

// Kafka Stack (Redpanda on EC2)
const kafkaStack = new KafkaStack(app, `Exchange-${config.envName}-Kafka`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
});

// EKS Stack (Kubernetes)
const eksStack = new EksStack(app, `Exchange-${config.envName}-Eks`, {
  ...envProps,
  config,
  vpc: vpcStack.vpc,
  eksSecurityGroup: vpcStack.eksSecurityGroup,
});

// Dependencies
rdsStack.addDependency(vpcStack);
elasticacheStack.addDependency(vpcStack);
kafkaStack.addDependency(vpcStack);
eksStack.addDependency(vpcStack);

app.synth();
```

### 3.2 VPC 스택

```typescript
// infra/lib/stacks/vpc-stack.ts

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

    // VPC 생성 (2 AZ, Public/Private/Isolated 서브넷)
    this.vpc = new ec2.Vpc(this, 'Vpc', {
      vpcName: `exchange-${config.envName}-vpc`,
      ipAddresses: ec2.IpAddresses.cidr(config.vpcCidr),
      maxAzs: 2,

      // 비용 최적화: 단일 NAT Gateway
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

    // EKS 클러스터용 Security Group
    this.eksSecurityGroup = new ec2.SecurityGroup(this, 'EksSecurityGroup', {
      vpc: this.vpc,
      securityGroupName: `exchange-${config.envName}-eks-sg`,
      description: 'Security group for EKS cluster',
      allowAllOutbound: true,
    });

    // 태그 추가 (EKS 서브넷 자동 검색용)
    cdk.Tags.of(this.vpc).add('Environment', config.envName);

    this.vpc.publicSubnets.forEach((subnet, index) => {
      cdk.Tags.of(subnet).add('kubernetes.io/role/elb', '1');
      cdk.Tags.of(subnet).add(`kubernetes.io/cluster/exchange-${config.envName}`, 'shared');
    });

    this.vpc.privateSubnets.forEach((subnet, index) => {
      cdk.Tags.of(subnet).add('kubernetes.io/role/internal-elb', '1');
      cdk.Tags.of(subnet).add(`kubernetes.io/cluster/exchange-${config.envName}`, 'shared');
    });

    // Outputs
    new cdk.CfnOutput(this, 'VpcId', {
      value: this.vpc.vpcId,
      exportName: `${config.envName}-VpcId`,
    });
  }
}
```

### 3.3 EKS 스택

```typescript
// infra/lib/stacks/eks-stack.ts

import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as eks from 'aws-cdk-lib/aws-eks';
import * as iam from 'aws-cdk-lib/aws-iam';
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

    // EKS 클러스터 생성
    this.cluster = new eks.Cluster(this, 'Cluster', {
      clusterName: `exchange-${config.envName}`,
      vpc,
      vpcSubnets: [{ subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS }],
      version: eks.KubernetesVersion.V1_29,
      defaultCapacity: 0, // Managed Node Group으로 별도 생성
      securityGroup: eksSecurityGroup,
      endpointAccess: eks.EndpointAccess.PUBLIC_AND_PRIVATE,

      // 클러스터 로깅
      clusterLogging: [
        eks.ClusterLoggingTypes.API,
        eks.ClusterLoggingTypes.AUDIT,
        eks.ClusterLoggingTypes.AUTHENTICATOR,
      ],
    });

    // Spot 인스턴스 Node Group (비용 최적화)
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
        'environment': config.envName,
      },
    });

    // kubectl 설정 명령 출력
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
  }
}
```

### 3.4 RDS 스택 (MySQL)

```typescript
// infra/lib/stacks/rds-stack.ts

import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';

export interface RdsStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
  vpc: ec2.Vpc;
  eksSecurityGroup: ec2.SecurityGroup;
}

export class RdsStack extends cdk.Stack {
  public readonly instance: rds.DatabaseInstance;
  public readonly secret: secretsmanager.ISecret;

  constructor(scope: Construct, id: string, props: RdsStackProps) {
    super(scope, id, props);

    const { config, vpc, eksSecurityGroup } = props;

    // RDS Security Group
    const rdsSecurityGroup = new ec2.SecurityGroup(this, 'RdsSecurityGroup', {
      vpc,
      securityGroupName: `exchange-${config.envName}-rds-sg`,
      description: 'Security group for RDS MySQL',
      allowAllOutbound: false,
    });

    // EKS에서 MySQL 접근 허용
    rdsSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(3306),
      'MySQL from EKS'
    );

    // 개발자 로컬 접근 허용 (개발 환경용)
    rdsSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(), // TODO: 실제 IP로 제한
      ec2.Port.tcp(3306),
      'MySQL from developers'
    );

    // Database Credentials (Secrets Manager)
    this.secret = new secretsmanager.Secret(this, 'DbCredentials', {
      secretName: `exchange-${config.envName}-db-credentials`,
      generateSecretString: {
        secretStringTemplate: JSON.stringify({ username: config.dbUsername }),
        generateStringKey: 'password',
        excludePunctuation: true,
        passwordLength: 16,
      },
    });

    // RDS Parameter Group
    const parameterGroup = new rds.ParameterGroup(this, 'ParameterGroup', {
      engine: rds.DatabaseInstanceEngine.mysql({
        version: rds.MysqlEngineVersion.VER_8_0,
      }),
      parameters: {
        character_set_server: 'utf8mb4',
        collation_server: 'utf8mb4_unicode_ci',
      },
    });

    // RDS MySQL Instance
    this.instance = new rds.DatabaseInstance(this, 'Instance', {
      instanceIdentifier: `exchange-${config.envName}-mysql`,
      engine: rds.DatabaseInstanceEngine.mysql({
        version: rds.MysqlEngineVersion.VER_8_0,
      }),
      instanceType: new ec2.InstanceType(config.rdsInstanceClass),
      vpc,
      vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_ISOLATED },
      securityGroups: [rdsSecurityGroup],

      // Database 설정
      databaseName: 'future_exchange',
      credentials: rds.Credentials.fromSecret(this.secret),
      parameterGroup,

      // Storage
      allocatedStorage: 20,
      maxAllocatedStorage: 100,
      storageType: rds.StorageType.GP3,
      storageEncrypted: true,

      // 개발 환경 설정
      multiAz: false,
      publiclyAccessible: true, // 개발 환경용 (프로덕션에서는 false)
      deletionProtection: false,
      removalPolicy: cdk.RemovalPolicy.DESTROY,

      // 백업 (최소화)
      backupRetention: cdk.Duration.days(1),
      preferredBackupWindow: '03:00-04:00',
      preferredMaintenanceWindow: 'Mon:04:00-Mon:05:00',
    });

    // Outputs
    new cdk.CfnOutput(this, 'RdsEndpoint', {
      value: this.instance.dbInstanceEndpointAddress,
      exportName: `${config.envName}-RdsEndpoint`,
    });

    new cdk.CfnOutput(this, 'RdsSecretArn', {
      value: this.secret.secretArn,
      exportName: `${config.envName}-RdsSecretArn`,
    });
  }
}
```

### 3.5 ElastiCache 스택 (Redis)

```typescript
// infra/lib/stacks/elasticache-stack.ts

import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as elasticache from 'aws-cdk-lib/aws-elasticache';
import { Construct } from 'constructs';
import { EnvironmentConfig } from '../../config/dev';

export interface ElasticacheStackProps extends cdk.StackProps {
  config: EnvironmentConfig;
  vpc: ec2.Vpc;
  eksSecurityGroup: ec2.SecurityGroup;
}

export class ElasticacheStack extends cdk.Stack {
  public readonly cluster: elasticache.CfnCacheCluster;
  public readonly endpoint: string;

  constructor(scope: Construct, id: string, props: ElasticacheStackProps) {
    super(scope, id, props);

    const { config, vpc, eksSecurityGroup } = props;

    // Redis Security Group
    const redisSecurityGroup = new ec2.SecurityGroup(this, 'RedisSecurityGroup', {
      vpc,
      securityGroupName: `exchange-${config.envName}-redis-sg`,
      description: 'Security group for ElastiCache Redis',
      allowAllOutbound: false,
    });

    // EKS에서 Redis 접근 허용
    redisSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(6379),
      'Redis from EKS'
    );

    // Subnet Group
    const subnetGroup = new elasticache.CfnSubnetGroup(this, 'SubnetGroup', {
      description: 'Subnet group for ElastiCache Redis',
      subnetIds: vpc.isolatedSubnets.map(subnet => subnet.subnetId),
      cacheSubnetGroupName: `exchange-${config.envName}-redis-subnet`,
    });

    // Redis Cluster (단일 노드 - 개발 환경용)
    this.cluster = new elasticache.CfnCacheCluster(this, 'Cluster', {
      clusterName: `exchange-${config.envName}-redis`,
      engine: 'redis',
      engineVersion: '7.0',
      cacheNodeType: config.redisNodeType,
      numCacheNodes: 1,
      cacheSubnetGroupName: subnetGroup.cacheSubnetGroupName,
      vpcSecurityGroupIds: [redisSecurityGroup.securityGroupId],

      // 개발 환경: 스냅샷 비활성화
      snapshotRetentionLimit: 0,
    });

    this.cluster.addDependency(subnetGroup);

    // Outputs
    new cdk.CfnOutput(this, 'RedisEndpoint', {
      value: this.cluster.attrRedisEndpointAddress,
      exportName: `${config.envName}-RedisEndpoint`,
    });

    new cdk.CfnOutput(this, 'RedisPort', {
      value: this.cluster.attrRedisEndpointPort,
      exportName: `${config.envName}-RedisPort`,
    });
  }
}
```

### 3.6 Kafka 스택 (Redpanda on EC2)

```typescript
// infra/lib/stacks/kafka-stack.ts

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
  public readonly privateIp: string;

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

    // EKS에서 Kafka 접근 허용
    kafkaSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(9092),
      'Kafka from EKS'
    );

    // 자체 접근 허용 (inter-broker)
    kafkaSecurityGroup.addIngressRule(
      kafkaSecurityGroup,
      ec2.Port.tcp(9092),
      'Kafka inter-broker'
    );

    // Admin Console
    kafkaSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(9644),
      'Redpanda Admin from EKS'
    );

    // IAM Role for EC2
    const role = new iam.Role(this, 'KafkaRole', {
      roleName: `exchange-${config.envName}-kafka-role`,
      assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
      managedPolicies: [
        iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonSSMManagedInstanceCore'),
      ],
    });

    // User Data Script (Redpanda 설치)
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
      '# Create topics',
      'docker exec redpanda rpk topic create \\',
      '  matching-engine-shard-1-input \\',
      '  matching-engine-shard-1-output \\',
      '  matching-engine-shard-2-input \\',
      '  matching-engine-shard-2-output \\',
      '  matching-engine-shard-3-input \\',
      '  matching-engine-shard-3-output \\',
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
  }
}
```

### 3.7 ECR 스택

```typescript
// infra/lib/stacks/ecr-stack.ts

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

  constructor(scope: Construct, id: string, props: EcrStackProps) {
    super(scope, id, props);

    const { config } = props;

    // Matching Engine Repository
    this.matchingEngineRepo = new ecr.Repository(this, 'MatchingEngineRepo', {
      repositoryName: `exchange/matching-engine-shard`,
      imageScanOnPush: true,
      imageTagMutability: ecr.TagMutability.MUTABLE,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
      emptyOnDelete: true,

      // 비용 절감: 오래된 이미지 자동 삭제
      lifecycleRules: [
        {
          description: 'Keep last 10 images',
          maxImageCount: 10,
          rulePriority: 1,
        },
      ],
    });

    // Backend Repository
    this.backendRepo = new ecr.Repository(this, 'BackendRepo', {
      repositoryName: `exchange/future-backend`,
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

    new cdk.CfnOutput(this, 'BackendRepoUri', {
      value: this.backendRepo.repositoryUri,
      exportName: `${config.envName}-BackendRepoUri`,
    });
  }
}
```

---

## 4. 인프라 배포

### 4.1 CDK 배포

```bash
cd infra

# 의존성 설치
npm install

# TypeScript 컴파일
npm run build

# 변경사항 미리보기
npm run diff

# 전체 스택 배포 (약 20-30분 소요)
npm run deploy:dev

# 또는 개별 스택 배포
cdk deploy Exchange-dev-Vpc -c env=dev
cdk deploy Exchange-dev-Ecr -c env=dev
cdk deploy Exchange-dev-Rds -c env=dev
cdk deploy Exchange-dev-Redis -c env=dev
cdk deploy Exchange-dev-Kafka -c env=dev
cdk deploy Exchange-dev-Eks -c env=dev
```

### 4.2 kubeconfig 설정

```bash
# CDK 출력에서 명령어 확인 또는 직접 실행
aws eks update-kubeconfig --region ap-northeast-2 --name exchange-dev

# 연결 확인
kubectl get nodes
```

---

## 5. 애플리케이션 배포

### 5.1 Docker 이미지 빌드 및 푸시

```bash
# ECR 로그인
aws ecr get-login-password --region ap-northeast-2 | \
  docker login --username AWS --password-stdin \
  $(aws cloudformation describe-stacks --stack-name Exchange-dev-Ecr \
    --query 'Stacks[0].Outputs[?ExportName==`dev-MatchingEngineRepoUri`].OutputValue' \
    --output text | cut -d'/' -f1)

# Matching Engine 이미지 빌드 및 푸시
cd future-engine
ECR_URI=$(aws cloudformation describe-stacks --stack-name Exchange-dev-Ecr \
  --query 'Stacks[0].Outputs[?ExportName==`dev-MatchingEngineRepoUri`].OutputValue' \
  --output text)

docker build -t matching-engine-shard:1.0.0 -f Dockerfile.shard .
docker tag matching-engine-shard:1.0.0 ${ECR_URI}:1.0.0
docker push ${ECR_URI}:1.0.0

# Backend 이미지 빌드 및 푸시
cd ../future-backend
ECR_URI=$(aws cloudformation describe-stacks --stack-name Exchange-dev-Ecr \
  --query 'Stacks[0].Outputs[?ExportName==`dev-BackendRepoUri`].OutputValue' \
  --output text)

docker build -t future-backend:1.0.0 .
docker tag future-backend:1.0.0 ${ECR_URI}:1.0.0
docker push ${ECR_URI}:1.0.0
```

### 5.2 Kubernetes 시크릿 생성

```bash
# 네임스페이스 생성
kubectl create namespace exchange-dev

# RDS 시크릿 가져오기
DB_SECRET=$(aws secretsmanager get-secret-value \
  --secret-id exchange-dev-db-credentials \
  --query 'SecretString' --output text)

DB_HOST=$(aws cloudformation describe-stacks --stack-name Exchange-dev-Rds \
  --query 'Stacks[0].Outputs[?ExportName==`dev-RdsEndpoint`].OutputValue' --output text)

DB_USERNAME=$(echo $DB_SECRET | jq -r '.username')
DB_PASSWORD=$(echo $DB_SECRET | jq -r '.password')

# Database 시크릿 생성
kubectl create secret generic db-credentials \
  --namespace exchange-dev \
  --from-literal=MYSQL_HOST=${DB_HOST} \
  --from-literal=MYSQL_PORT=3306 \
  --from-literal=MYSQL_USERNAME=${DB_USERNAME} \
  --from-literal=MYSQL_PASSWORD=${DB_PASSWORD} \
  --from-literal=MYSQL_DATABASE=future_exchange

# Kafka 시크릿
KAFKA_IP=$(aws cloudformation describe-stacks --stack-name Exchange-dev-Kafka \
  --query 'Stacks[0].Outputs[?ExportName==`dev-KafkaPrivateIp`].OutputValue' --output text)

kubectl create secret generic kafka-credentials \
  --namespace exchange-dev \
  --from-literal=KAFKA_BOOTSTRAP_SERVERS=${KAFKA_IP}:9092

# Redis 시크릿
REDIS_HOST=$(aws cloudformation describe-stacks --stack-name Exchange-dev-Redis \
  --query 'Stacks[0].Outputs[?ExportName==`dev-RedisEndpoint`].OutputValue' --output text)

kubectl create secret generic redis-credentials \
  --namespace exchange-dev \
  --from-literal=REDIS_HOST=${REDIS_HOST} \
  --from-literal=REDIS_PORT=6379
```

### 5.3 Kubernetes 매니페스트 배포

```bash
# K8s overlays/aws-dev 생성 필요 (기존 dev와 별도)
kubectl apply -k k8s/overlays/aws-dev

# 또는 직접 배포
kubectl apply -f k8s/base/namespace.yaml
kubectl apply -f k8s/base/configmap.yaml
kubectl apply -f k8s/base/shard-1-statefulset.yaml
kubectl apply -f k8s/base/shard-2-statefulset.yaml
kubectl apply -f k8s/base/shard-3-statefulset.yaml
```

---

## 6. 연결 정보 확인

### 6.1 CloudFormation 출력 확인

```bash
# 모든 스택 출력 확인
for stack in Vpc Ecr Rds Redis Kafka Eks; do
  echo "=== Exchange-dev-${stack} ==="
  aws cloudformation describe-stacks --stack-name Exchange-dev-${stack} \
    --query 'Stacks[0].Outputs[*].[ExportName,OutputValue]' --output table
done
```

### 6.2 주요 엔드포인트

```bash
# EKS Cluster
aws eks describe-cluster --name exchange-dev --query 'cluster.endpoint'

# RDS
aws cloudformation describe-stacks --stack-name Exchange-dev-Rds \
  --query 'Stacks[0].Outputs[?ExportName==`dev-RdsEndpoint`].OutputValue' --output text

# Redis
aws cloudformation describe-stacks --stack-name Exchange-dev-Redis \
  --query 'Stacks[0].Outputs[?ExportName==`dev-RedisEndpoint`].OutputValue' --output text

# Kafka
aws cloudformation describe-stacks --stack-name Exchange-dev-Kafka \
  --query 'Stacks[0].Outputs[?ExportName==`dev-KafkaPrivateIp`].OutputValue' --output text
```

---

## 7. 비용 최적화 팁

### 7.1 사용하지 않을 때 리소스 중지

```bash
# EKS 노드 그룹 축소 (0으로 설정)
aws eks update-nodegroup-config \
  --cluster-name exchange-dev \
  --nodegroup-name exchange-dev-spot-nodes \
  --scaling-config desiredSize=0,minSize=0,maxSize=4

# Kafka EC2 인스턴스 중지
KAFKA_INSTANCE_ID=$(aws cloudformation describe-stacks --stack-name Exchange-dev-Kafka \
  --query 'Stacks[0].Outputs[?ExportName==`dev-KafkaInstanceId`].OutputValue' --output text)
aws ec2 stop-instances --instance-ids ${KAFKA_INSTANCE_ID}

# RDS 중지 (최대 7일)
aws rds stop-db-instance --db-instance-identifier exchange-dev-mysql
```

### 7.2 리소스 재시작

```bash
# EKS 노드 그룹 확장
aws eks update-nodegroup-config \
  --cluster-name exchange-dev \
  --nodegroup-name exchange-dev-spot-nodes \
  --scaling-config desiredSize=2,minSize=1,maxSize=4

# Kafka EC2 인스턴스 시작
aws ec2 start-instances --instance-ids ${KAFKA_INSTANCE_ID}

# RDS 시작
aws rds start-db-instance --db-instance-identifier exchange-dev-mysql
```

### 7.3 예상 월간 비용

| 리소스 | 스펙 | 예상 비용 (USD) |
|--------|------|----------------|
| EKS Cluster | 1 클러스터 | $73 |
| EKS Nodes (Spot) | t3.medium x 2 | ~$30 |
| RDS MySQL | db.t3.micro | ~$15 |
| ElastiCache | cache.t3.micro | ~$12 |
| EC2 (Kafka) | t3.medium | ~$30 |
| NAT Gateway | 1개 | ~$32 |
| EBS Storage | ~100GB | ~$10 |
| Data Transfer | ~10GB | ~$1 |
| **합계** | | **~$200/월** |

> **비용 절감 팁**:
> - 사용하지 않을 때 EKS 노드 0으로 축소
> - RDS 중지 (7일 후 자동 시작)
> - Reserved Instances 구매 (장기 사용 시)

---

## 8. 트러블슈팅

### 8.1 CDK 배포 실패

```bash
# 상세 로그 확인
cdk deploy --all -c env=dev --verbose

# CloudFormation 이벤트 확인
aws cloudformation describe-stack-events --stack-name Exchange-dev-Vpc \
  --query 'StackEvents[?ResourceStatus==`CREATE_FAILED`]'

# 스택 삭제 후 재시도
cdk destroy Exchange-dev-Eks -c env=dev
cdk deploy Exchange-dev-Eks -c env=dev
```

### 8.2 EKS 노드가 Ready 상태가 아닌 경우

```bash
# 노드 상태 확인
kubectl describe nodes

# EKS 애드온 확인
aws eks describe-addon --cluster-name exchange-dev --addon-name vpc-cni
aws eks describe-addon --cluster-name exchange-dev --addon-name coredns
```

### 8.3 RDS 연결 실패

```bash
# 보안 그룹 확인
aws ec2 describe-security-groups \
  --filters "Name=group-name,Values=exchange-dev-rds-sg"

# RDS 상태 확인
aws rds describe-db-instances --db-instance-identifier exchange-dev-mysql
```

### 8.4 Kafka 토픽 확인

```bash
# SSM으로 EC2 접속
KAFKA_INSTANCE_ID=$(aws cloudformation describe-stacks --stack-name Exchange-dev-Kafka \
  --query 'Stacks[0].Outputs[?ExportName==`dev-KafkaInstanceId`].OutputValue' --output text)

aws ssm start-session --target ${KAFKA_INSTANCE_ID}

# 토픽 확인
docker exec redpanda rpk topic list
```

---

## 9. 인프라 삭제

### 9.1 CDK Destroy

```bash
cd infra

# Kubernetes 리소스 먼저 삭제
kubectl delete namespace exchange-dev

# CDK로 인프라 삭제 (약 15-20분 소요)
npm run destroy:dev

# 또는 개별 스택 삭제 (역순)
cdk destroy Exchange-dev-Eks -c env=dev
cdk destroy Exchange-dev-Kafka -c env=dev
cdk destroy Exchange-dev-Redis -c env=dev
cdk destroy Exchange-dev-Rds -c env=dev
cdk destroy Exchange-dev-Ecr -c env=dev
cdk destroy Exchange-dev-Vpc -c env=dev
```

### 9.2 잔여 리소스 정리

```bash
# CloudWatch 로그 그룹 삭제
aws logs describe-log-groups --log-group-name-prefix /aws/eks/exchange-dev \
  --query 'logGroups[*].logGroupName' --output text | \
  xargs -I {} aws logs delete-log-group --log-group-name {}

# S3 버킷 (CDK 아티팩트) 비우기 및 삭제
aws s3 rm s3://cdk-*-exchange-* --recursive
```

---

## 10. 체크리스트

### 배포 전 체크리스트

- [ ] AWS CLI 설정 완료
- [ ] Node.js 18+ 설치
- [ ] AWS CDK CLI 설치 (`npm install -g aws-cdk`)
- [ ] CDK Bootstrap 완료
- [ ] kubectl 설치
- [ ] config/dev.ts 설정 확인

### 배포 후 체크리스트

- [ ] EKS 클러스터 연결 확인 (`kubectl get nodes`)
- [ ] 노드 상태 확인 (Ready)
- [ ] RDS 연결 테스트
- [ ] Redis 연결 테스트
- [ ] Kafka 토픽 생성 확인
- [ ] ECR 이미지 푸시 완료
- [ ] Pod 상태 확인 (Running)

---

## 참고 문서

- [AWS CDK 공식 문서](https://docs.aws.amazon.com/cdk/v2/guide/home.html)
- [프로덕션 인프라 가이드](./aws-infrastructure.md)
- [매칭 엔진 샤딩 가이드](./matching-engine-sharding.md)
- [프로덕션 배포 체크리스트](../production-deployment-checklist.md)

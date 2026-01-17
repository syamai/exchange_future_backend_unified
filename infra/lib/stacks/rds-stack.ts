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

    // Allow MySQL access from EKS
    rdsSecurityGroup.addIngressRule(
      eksSecurityGroup,
      ec2.Port.tcp(3306),
      'MySQL from EKS'
    );

    // Allow developer local access (dev environment only)
    // TODO: Restrict to actual developer IPs in production
    rdsSecurityGroup.addIngressRule(
      ec2.Peer.anyIpv4(),
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

      // Database settings
      databaseName: 'future_exchange',
      credentials: rds.Credentials.fromSecret(this.secret),
      parameterGroup,

      // Storage
      allocatedStorage: 20,
      maxAllocatedStorage: 100,
      storageType: rds.StorageType.GP3,
      storageEncrypted: true,

      // Dev environment settings
      multiAz: false,
      publiclyAccessible: true, // For dev environment (set to false in production)
      deletionProtection: false,
      removalPolicy: cdk.RemovalPolicy.DESTROY,

      // Backup (minimal for dev)
      backupRetention: cdk.Duration.days(1),
      preferredBackupWindow: '03:00-04:00',
      preferredMaintenanceWindow: 'Mon:04:00-Mon:05:00',
    });

    // Outputs
    new cdk.CfnOutput(this, 'RdsEndpoint', {
      value: this.instance.dbInstanceEndpointAddress,
      exportName: `${config.envName}-RdsEndpoint`,
    });

    new cdk.CfnOutput(this, 'RdsPort', {
      value: this.instance.dbInstanceEndpointPort,
      exportName: `${config.envName}-RdsPort`,
    });

    new cdk.CfnOutput(this, 'RdsSecretArn', {
      value: this.secret.secretArn,
      exportName: `${config.envName}-RdsSecretArn`,
    });

    new cdk.CfnOutput(this, 'RdsSecretName', {
      value: this.secret.secretName,
      exportName: `${config.envName}-RdsSecretName`,
    });
  }
}

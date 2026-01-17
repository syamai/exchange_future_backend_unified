# AWS 인프라 구성 가이드

## 개요

이 문서는 암호화폐 선물 거래소를 AWS에서 운영하기 위한 프로덕션 레벨 인프라 구성을 다룹니다. 고가용성, 저지연, 보안을 핵심 요구사항으로 설계되었습니다.

## 아키텍처 다이어그램

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                    AWS Cloud                                         │
│  ┌─────────────────────────────────────────────────────────────────────────────────┐│
│  │                              Region: ap-northeast-2                              ││
│  │                                                                                  ││
│  │   ┌──────────────────────────────────────────────────────────────────────────┐  ││
│  │   │                        VPC: 10.0.0.0/16                                   │  ││
│  │   │                                                                           │  ││
│  │   │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                       │  ││
│  │   │  │   AZ-2a     │  │   AZ-2b     │  │   AZ-2c     │                       │  ││
│  │   │  │             │  │             │  │             │                       │  ││
│  │   │  │ ┌─────────┐ │  │ ┌─────────┐ │  │ ┌─────────┐ │                       │  ││
│  │   │  │ │ Public  │ │  │ │ Public  │ │  │ │ Public  │ │  ← ALB, NAT Gateway   │  ││
│  │   │  │ │ Subnet  │ │  │ │ Subnet  │ │  │ │ Subnet  │ │                       │  ││
│  │   │  │ │10.0.1.0 │ │  │ │10.0.2.0 │ │  │ │10.0.3.0 │ │                       │  ││
│  │   │  │ └─────────┘ │  │ └─────────┘ │  │ └─────────┘ │                       │  ││
│  │   │  │             │  │             │  │             │                       │  ││
│  │   │  │ ┌─────────┐ │  │ ┌─────────┐ │  │ ┌─────────┐ │                       │  ││
│  │   │  │ │ Private │ │  │ │ Private │ │  │ │ Private │ │  ← EKS Nodes, MSK     │  ││
│  │   │  │ │ Subnet  │ │  │ │ Subnet  │ │  │ │ Subnet  │ │                       │  ││
│  │   │  │ │10.0.11.0│ │  │ │10.0.12.0│ │  │ │10.0.13.0│ │                       │  ││
│  │   │  │ └─────────┘ │  │ └─────────┘ │  │ └─────────┘ │                       │  ││
│  │   │  │             │  │             │  │             │                       │  ││
│  │   │  │ ┌─────────┐ │  │ ┌─────────┐ │  │ ┌─────────┐ │                       │  ││
│  │   │  │ │Database │ │  │ │Database │ │  │ │Database │ │  ← RDS, ElastiCache   │  ││
│  │   │  │ │ Subnet  │ │  │ │ Subnet  │ │  │ │ Subnet  │ │                       │  ││
│  │   │  │ │10.0.21.0│ │  │ │10.0.22.0│ │  │ │10.0.23.0│ │                       │  ││
│  │   │  │ └─────────┘ │  │ └─────────┘ │  │ └─────────┘ │                       │  ││
│  │   │  │             │  │             │  │             │                       │  ││
│  │   │  │ ┌─────────┐ │  │ ┌─────────┐ │  │ ┌─────────┐ │                       │  ││
│  │   │  │ │ Matching│ │  │ │ Matching│ │  │ │ Matching│ │  ← EC2 Dedicated      │  ││
│  │   │  │ │ Engine  │ │  │ │ Engine  │ │  │ │ Engine  │ │    (Low Latency)      │  ││
│  │   │  │ │10.0.31.0│ │  │ │10.0.32.0│ │  │ │10.0.33.0│ │                       │  ││
│  │   │  │ └─────────┘ │  │ └─────────┘ │  │ └─────────┘ │                       │  ││
│  │   │  └─────────────┘  └─────────────┘  └─────────────┘                       │  ││
│  │   │                                                                           │  ││
│  │   └──────────────────────────────────────────────────────────────────────────┘  ││
│  │                                                                                  ││
│  └─────────────────────────────────────────────────────────────────────────────────┘│
│                                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────────────────┐│
│  │                         DR Region: ap-northeast-1                                ││
│  │  (Cross-Region Replication for RDS, S3, Standby EKS Cluster)                    ││
│  └─────────────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────────────┘
```

## 1. 네트워크 구성 (VPC)

### 1.1 VPC 설계

```hcl
# terraform/modules/vpc/main.tf

resource "aws_vpc" "exchange" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name        = "exchange-vpc"
    Environment = var.environment
  }
}

# Internet Gateway
resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.exchange.id

  tags = {
    Name = "exchange-igw"
  }
}

# Availability Zones
data "aws_availability_zones" "available" {
  state = "available"
}

# Public Subnets (ALB, NAT Gateway)
resource "aws_subnet" "public" {
  count                   = 3
  vpc_id                  = aws_vpc.exchange.id
  cidr_block              = "10.0.${count.index + 1}.0/24"
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  map_public_ip_on_launch = true

  tags = {
    Name                        = "exchange-public-${count.index + 1}"
    "kubernetes.io/role/elb"    = "1"
    "kubernetes.io/cluster/exchange-eks" = "shared"
  }
}

# Private Subnets (EKS, MSK)
resource "aws_subnet" "private" {
  count             = 3
  vpc_id            = aws_vpc.exchange.id
  cidr_block        = "10.0.${count.index + 11}.0/24"
  availability_zone = data.aws_availability_zones.available.names[count.index]

  tags = {
    Name                              = "exchange-private-${count.index + 1}"
    "kubernetes.io/role/internal-elb" = "1"
    "kubernetes.io/cluster/exchange-eks" = "shared"
  }
}

# Database Subnets
resource "aws_subnet" "database" {
  count             = 3
  vpc_id            = aws_vpc.exchange.id
  cidr_block        = "10.0.${count.index + 21}.0/24"
  availability_zone = data.aws_availability_zones.available.names[count.index]

  tags = {
    Name = "exchange-database-${count.index + 1}"
  }
}

# Matching Engine Subnets (Dedicated, Low Latency)
resource "aws_subnet" "matching_engine" {
  count             = 3
  vpc_id            = aws_vpc.exchange.id
  cidr_block        = "10.0.${count.index + 31}.0/24"
  availability_zone = data.aws_availability_zones.available.names[count.index]

  tags = {
    Name = "exchange-matching-${count.index + 1}"
  }
}

# NAT Gateway (각 AZ에 하나씩 for HA)
resource "aws_eip" "nat" {
  count  = 3
  domain = "vpc"

  tags = {
    Name = "exchange-nat-eip-${count.index + 1}"
  }
}

resource "aws_nat_gateway" "main" {
  count         = 3
  allocation_id = aws_eip.nat[count.index].id
  subnet_id     = aws_subnet.public[count.index].id

  tags = {
    Name = "exchange-nat-${count.index + 1}"
  }

  depends_on = [aws_internet_gateway.main]
}

# Route Tables
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.exchange.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }

  tags = {
    Name = "exchange-public-rt"
  }
}

resource "aws_route_table" "private" {
  count  = 3
  vpc_id = aws_vpc.exchange.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.main[count.index].id
  }

  tags = {
    Name = "exchange-private-rt-${count.index + 1}"
  }
}

# VPC Flow Logs
resource "aws_flow_log" "main" {
  iam_role_arn    = aws_iam_role.flow_log.arn
  log_destination = aws_cloudwatch_log_group.flow_log.arn
  traffic_type    = "ALL"
  vpc_id          = aws_vpc.exchange.id

  tags = {
    Name = "exchange-flow-log"
  }
}
```

### 1.2 Security Groups

```hcl
# terraform/modules/security/main.tf

# ALB Security Group
resource "aws_security_group" "alb" {
  name        = "exchange-alb-sg"
  description = "Security group for Application Load Balancer"
  vpc_id      = var.vpc_id

  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTP (redirect to HTTPS)"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "exchange-alb-sg"
  }
}

# EKS Node Security Group
resource "aws_security_group" "eks_nodes" {
  name        = "exchange-eks-nodes-sg"
  description = "Security group for EKS worker nodes"
  vpc_id      = var.vpc_id

  ingress {
    description     = "From ALB"
    from_port       = 0
    to_port         = 65535
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  ingress {
    description = "Node to node"
    from_port   = 0
    to_port     = 65535
    protocol    = "-1"
    self        = true
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "exchange-eks-nodes-sg"
  }
}

# Matching Engine Security Group
resource "aws_security_group" "matching_engine" {
  name        = "exchange-matching-engine-sg"
  description = "Security group for Matching Engine"
  vpc_id      = var.vpc_id

  ingress {
    description     = "From EKS"
    from_port       = 8080
    to_port         = 8080
    protocol        = "tcp"
    security_groups = [aws_security_group.eks_nodes.id]
  }

  ingress {
    description     = "gRPC from EKS"
    from_port       = 9090
    to_port         = 9090
    protocol        = "tcp"
    security_groups = [aws_security_group.eks_nodes.id]
  }

  ingress {
    description = "Engine to Engine (Cluster)"
    from_port   = 0
    to_port     = 65535
    protocol    = "tcp"
    self        = true
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "exchange-matching-engine-sg"
  }
}

# RDS Security Group
resource "aws_security_group" "rds" {
  name        = "exchange-rds-sg"
  description = "Security group for RDS PostgreSQL"
  vpc_id      = var.vpc_id

  ingress {
    description     = "PostgreSQL from EKS"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.eks_nodes.id]
  }

  ingress {
    description     = "PostgreSQL from Matching Engine"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.matching_engine.id]
  }

  tags = {
    Name = "exchange-rds-sg"
  }
}

# ElastiCache Security Group
resource "aws_security_group" "elasticache" {
  name        = "exchange-elasticache-sg"
  description = "Security group for ElastiCache Redis"
  vpc_id      = var.vpc_id

  ingress {
    description     = "Redis from EKS"
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.eks_nodes.id]
  }

  ingress {
    description     = "Redis from Matching Engine"
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.matching_engine.id]
  }

  tags = {
    Name = "exchange-elasticache-sg"
  }
}

# MSK Security Group
resource "aws_security_group" "msk" {
  name        = "exchange-msk-sg"
  description = "Security group for MSK Kafka"
  vpc_id      = var.vpc_id

  ingress {
    description     = "Kafka from EKS"
    from_port       = 9092
    to_port         = 9098
    protocol        = "tcp"
    security_groups = [aws_security_group.eks_nodes.id]
  }

  ingress {
    description     = "Kafka from Matching Engine"
    from_port       = 9092
    to_port         = 9098
    protocol        = "tcp"
    security_groups = [aws_security_group.matching_engine.id]
  }

  ingress {
    description = "Zookeeper"
    from_port   = 2181
    to_port     = 2181
    protocol    = "tcp"
    self        = true
  }

  tags = {
    Name = "exchange-msk-sg"
  }
}
```

## 2. 컴퓨트 리소스

### 2.1 EKS 클러스터 (API, WebSocket, Backend Services)

```hcl
# terraform/modules/eks/main.tf

resource "aws_eks_cluster" "main" {
  name     = "exchange-eks"
  role_arn = aws_iam_role.eks_cluster.arn
  version  = "1.29"

  vpc_config {
    subnet_ids              = var.private_subnet_ids
    endpoint_private_access = true
    endpoint_public_access  = true
    public_access_cidrs     = ["0.0.0.0/0"]  # 프로덕션에서는 제한
    security_group_ids      = [var.eks_cluster_sg_id]
  }

  enabled_cluster_log_types = [
    "api",
    "audit",
    "authenticator",
    "controllerManager",
    "scheduler"
  ]

  tags = {
    Name = "exchange-eks"
  }
}

# API/Backend Node Group
resource "aws_eks_node_group" "api" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "api-nodes"
  node_role_arn   = aws_iam_role.eks_node.arn
  subnet_ids      = var.private_subnet_ids

  instance_types = ["c6i.2xlarge"]  # 8 vCPU, 16GB RAM
  capacity_type  = "ON_DEMAND"

  scaling_config {
    desired_size = 6
    min_size     = 3
    max_size     = 20
  }

  update_config {
    max_unavailable = 1
  }

  labels = {
    "node-type" = "api"
  }

  tags = {
    Name = "exchange-api-nodes"
  }
}

# WebSocket Node Group (메모리 최적화)
resource "aws_eks_node_group" "websocket" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "websocket-nodes"
  node_role_arn   = aws_iam_role.eks_node.arn
  subnet_ids      = var.private_subnet_ids

  instance_types = ["r6i.xlarge"]  # 4 vCPU, 32GB RAM
  capacity_type  = "ON_DEMAND"

  scaling_config {
    desired_size = 4
    min_size     = 2
    max_size     = 10
  }

  labels = {
    "node-type" = "websocket"
  }

  taint {
    key    = "dedicated"
    value  = "websocket"
    effect = "NO_SCHEDULE"
  }

  tags = {
    Name = "exchange-websocket-nodes"
  }
}

# Kafka Consumer Node Group
resource "aws_eks_node_group" "consumer" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "consumer-nodes"
  node_role_arn   = aws_iam_role.eks_node.arn
  subnet_ids      = var.private_subnet_ids

  instance_types = ["c6i.xlarge"]  # 4 vCPU, 8GB RAM
  capacity_type  = "ON_DEMAND"

  scaling_config {
    desired_size = 6
    min_size     = 3
    max_size     = 15
  }

  labels = {
    "node-type" = "consumer"
  }

  tags = {
    Name = "exchange-consumer-nodes"
  }
}
```

### 2.2 매칭 엔진 EC2 (Dedicated, Low Latency)

```hcl
# terraform/modules/matching-engine/main.tf

# Placement Group for low latency
resource "aws_placement_group" "matching_engine" {
  name     = "exchange-matching-engine-pg"
  strategy = "cluster"
}

# Launch Template
resource "aws_launch_template" "matching_engine" {
  name_prefix   = "exchange-matching-engine-"
  image_id      = data.aws_ami.amazon_linux_2.id
  instance_type = "c6i.4xlarge"  # 16 vCPU, 32GB RAM

  # Dedicated Tenancy for consistent performance
  placement {
    tenancy = "dedicated"
    group_name = aws_placement_group.matching_engine.name
  }

  # EBS Optimized
  ebs_optimized = true

  # Network interfaces with enhanced networking
  network_interfaces {
    associate_public_ip_address = false
    security_groups             = [var.matching_engine_sg_id]
    delete_on_termination       = true
  }

  # Instance metadata options
  metadata_options {
    http_endpoint               = "enabled"
    http_tokens                 = "required"
    http_put_response_hop_limit = 1
  }

  # Block device mappings
  block_device_mappings {
    device_name = "/dev/xvda"

    ebs {
      volume_size           = 100
      volume_type           = "gp3"
      iops                  = 16000
      throughput            = 1000
      encrypted             = true
      delete_on_termination = true
    }
  }

  # User data for initialization
  user_data = base64encode(templatefile("${path.module}/userdata.sh", {
    environment = var.environment
    kafka_brokers = var.kafka_brokers
  }))

  tag_specifications {
    resource_type = "instance"
    tags = {
      Name = "exchange-matching-engine"
      Role = "matching-engine"
    }
  }
}

# Auto Scaling Group
resource "aws_autoscaling_group" "matching_engine" {
  name                = "exchange-matching-engine-asg"
  vpc_zone_identifier = var.matching_engine_subnet_ids
  min_size            = 3
  max_size            = 9
  desired_capacity    = 3

  launch_template {
    id      = aws_launch_template.matching_engine.id
    version = "$Latest"
  }

  health_check_type         = "ELB"
  health_check_grace_period = 300

  # Instance refresh for updates
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 66
    }
  }

  tag {
    key                 = "Name"
    value               = "exchange-matching-engine"
    propagate_at_launch = true
  }
}

# Target Group for internal NLB
resource "aws_lb_target_group" "matching_engine" {
  name     = "exchange-matching-engine-tg"
  port     = 8080
  protocol = "TCP"
  vpc_id   = var.vpc_id

  health_check {
    enabled             = true
    interval            = 10
    port                = 8080
    protocol            = "TCP"
    healthy_threshold   = 2
    unhealthy_threshold = 2
  }
}

# Internal Network Load Balancer
resource "aws_lb" "matching_engine" {
  name               = "exchange-matching-engine-nlb"
  internal           = true
  load_balancer_type = "network"
  subnets            = var.matching_engine_subnet_ids

  enable_cross_zone_load_balancing = true

  tags = {
    Name = "exchange-matching-engine-nlb"
  }
}

resource "aws_lb_listener" "matching_engine" {
  load_balancer_arn = aws_lb.matching_engine.arn
  port              = 8080
  protocol          = "TCP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.matching_engine.arn
  }
}
```

### 2.3 매칭 엔진 User Data

```bash
#!/bin/bash
# terraform/modules/matching-engine/userdata.sh

set -e

# System tuning for low latency
cat >> /etc/sysctl.conf << 'EOF'
# Network tuning
net.core.rmem_max = 134217728
net.core.wmem_max = 134217728
net.ipv4.tcp_rmem = 4096 87380 134217728
net.ipv4.tcp_wmem = 4096 65536 134217728
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535

# VM tuning
vm.swappiness = 1
vm.dirty_ratio = 60
vm.dirty_background_ratio = 2
EOF
sysctl -p

# Disable transparent huge pages
echo never > /sys/kernel/mm/transparent_hugepage/enabled
echo never > /sys/kernel/mm/transparent_hugepage/defrag

# Install Java 17
amazon-linux-extras install java-openjdk17 -y

# Install CloudWatch Agent
yum install -y amazon-cloudwatch-agent
cat > /opt/aws/amazon-cloudwatch-agent/etc/config.json << 'EOF'
{
  "metrics": {
    "namespace": "Exchange/MatchingEngine",
    "metrics_collected": {
      "cpu": {
        "measurement": ["cpu_usage_idle", "cpu_usage_system", "cpu_usage_user"],
        "totalcpu": true
      },
      "mem": {
        "measurement": ["mem_used_percent", "mem_available"]
      },
      "disk": {
        "measurement": ["disk_used_percent"],
        "resources": ["/"]
      }
    }
  },
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          {
            "file_path": "/var/log/matching-engine/application.log",
            "log_group_name": "/exchange/matching-engine",
            "log_stream_name": "{instance_id}/application"
          },
          {
            "file_path": "/var/log/matching-engine/gc.log",
            "log_group_name": "/exchange/matching-engine",
            "log_stream_name": "{instance_id}/gc"
          }
        ]
      }
    }
  }
}
EOF
/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
  -a fetch-config -m ec2 -s \
  -c file:/opt/aws/amazon-cloudwatch-agent/etc/config.json

# Download and start matching engine
mkdir -p /opt/matching-engine /var/log/matching-engine
aws s3 cp s3://exchange-artifacts/matching-engine/latest.jar /opt/matching-engine/
aws s3 cp s3://exchange-artifacts/matching-engine/config.yml /opt/matching-engine/

# Create systemd service
cat > /etc/systemd/system/matching-engine.service << 'EOF'
[Unit]
Description=Exchange Matching Engine
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/matching-engine
ExecStart=/usr/bin/java \
  -Xms24g -Xmx24g \
  -XX:+UseZGC \
  -XX:+ZGenerational \
  -XX:+UseLargePages \
  -XX:LargePageSizeInBytes=2m \
  -Xlog:gc*:file=/var/log/matching-engine/gc.log:time,uptime:filecount=10,filesize=100m \
  -jar /opt/matching-engine/latest.jar \
  --spring.config.location=/opt/matching-engine/config.yml
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable matching-engine
systemctl start matching-engine
```

## 3. 데이터베이스

### 3.1 RDS PostgreSQL (Primary Database)

```hcl
# terraform/modules/rds/main.tf

resource "aws_db_subnet_group" "main" {
  name       = "exchange-db-subnet-group"
  subnet_ids = var.database_subnet_ids

  tags = {
    Name = "exchange-db-subnet-group"
  }
}

resource "aws_db_parameter_group" "postgres" {
  family = "postgres15"
  name   = "exchange-postgres-params"

  # Performance tuning
  parameter {
    name  = "shared_buffers"
    value = "{DBInstanceClassMemory/4}"  # 25% of memory
  }

  parameter {
    name  = "effective_cache_size"
    value = "{DBInstanceClassMemory*3/4}"  # 75% of memory
  }

  parameter {
    name  = "work_mem"
    value = "262144"  # 256MB
  }

  parameter {
    name  = "maintenance_work_mem"
    value = "2097152"  # 2GB
  }

  parameter {
    name  = "random_page_cost"
    value = "1.1"
  }

  parameter {
    name  = "effective_io_concurrency"
    value = "200"
  }

  parameter {
    name  = "max_connections"
    value = "500"
  }

  parameter {
    name  = "log_min_duration_statement"
    value = "100"  # Log queries > 100ms
  }

  tags = {
    Name = "exchange-postgres-params"
  }
}

# Primary Instance
resource "aws_db_instance" "primary" {
  identifier     = "exchange-postgres-primary"
  engine         = "postgres"
  engine_version = "15.4"

  instance_class        = "db.r6i.4xlarge"  # 16 vCPU, 128GB RAM
  allocated_storage     = 500
  max_allocated_storage = 2000
  storage_type          = "gp3"
  storage_throughput    = 500
  iops                  = 12000
  storage_encrypted     = true
  kms_key_id            = var.kms_key_arn

  db_name  = "exchange"
  username = var.db_username
  password = var.db_password
  port     = 5432

  multi_az               = true
  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [var.rds_sg_id]
  parameter_group_name   = aws_db_parameter_group.postgres.name

  backup_retention_period   = 35
  backup_window             = "03:00-04:00"
  maintenance_window        = "Mon:04:00-Mon:05:00"
  delete_automated_backups  = false
  copy_tags_to_snapshot     = true
  skip_final_snapshot       = false
  final_snapshot_identifier = "exchange-postgres-final"

  performance_insights_enabled          = true
  performance_insights_retention_period = 7

  enabled_cloudwatch_logs_exports = [
    "postgresql",
    "upgrade"
  ]

  auto_minor_version_upgrade = false
  deletion_protection        = true

  tags = {
    Name = "exchange-postgres-primary"
  }
}

# Read Replica
resource "aws_db_instance" "read_replica" {
  count = 2

  identifier     = "exchange-postgres-replica-${count.index + 1}"
  instance_class = "db.r6i.2xlarge"  # 8 vCPU, 64GB RAM

  replicate_source_db = aws_db_instance.primary.identifier

  vpc_security_group_ids = [var.rds_sg_id]
  parameter_group_name   = aws_db_parameter_group.postgres.name

  performance_insights_enabled          = true
  performance_insights_retention_period = 7

  auto_minor_version_upgrade = false

  tags = {
    Name = "exchange-postgres-replica-${count.index + 1}"
  }
}

# Cross-Region Read Replica for DR
resource "aws_db_instance" "dr_replica" {
  provider = aws.dr_region

  identifier     = "exchange-postgres-dr"
  instance_class = "db.r6i.2xlarge"

  replicate_source_db = aws_db_instance.primary.arn

  vpc_security_group_ids = [var.dr_rds_sg_id]
  db_subnet_group_name   = var.dr_db_subnet_group_name

  auto_minor_version_upgrade = false

  tags = {
    Name = "exchange-postgres-dr"
  }
}
```

### 3.2 ElastiCache Redis Cluster

```hcl
# terraform/modules/elasticache/main.tf

resource "aws_elasticache_subnet_group" "main" {
  name       = "exchange-redis-subnet-group"
  subnet_ids = var.database_subnet_ids
}

resource "aws_elasticache_parameter_group" "redis" {
  family = "redis7"
  name   = "exchange-redis-params"

  parameter {
    name  = "maxmemory-policy"
    value = "volatile-lru"
  }

  parameter {
    name  = "cluster-enabled"
    value = "yes"
  }

  parameter {
    name  = "cluster-allow-reads-when-down"
    value = "yes"
  }
}

# Redis Cluster Mode Enabled
resource "aws_elasticache_replication_group" "main" {
  replication_group_id = "exchange-redis"
  description          = "Exchange Redis Cluster"

  node_type            = "cache.r6g.xlarge"  # 4 vCPU, 26GB RAM
  num_node_groups      = 3                    # 3 shards
  replicas_per_node_group = 2                 # 2 replicas per shard

  port                 = 6379
  parameter_group_name = aws_elasticache_parameter_group.redis.name
  subnet_group_name    = aws_elasticache_subnet_group.main.name
  security_group_ids   = [var.elasticache_sg_id]

  automatic_failover_enabled = true
  multi_az_enabled           = true

  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
  auth_token                 = var.redis_auth_token

  snapshot_retention_limit = 7
  snapshot_window          = "03:00-05:00"
  maintenance_window       = "mon:05:00-mon:07:00"

  auto_minor_version_upgrade = false

  log_delivery_configuration {
    destination      = aws_cloudwatch_log_group.redis_slow.name
    destination_type = "cloudwatch-logs"
    log_format       = "json"
    log_type         = "slow-log"
  }

  log_delivery_configuration {
    destination      = aws_cloudwatch_log_group.redis_engine.name
    destination_type = "cloudwatch-logs"
    log_format       = "json"
    log_type         = "engine-log"
  }

  tags = {
    Name = "exchange-redis"
  }
}

resource "aws_cloudwatch_log_group" "redis_slow" {
  name              = "/exchange/redis/slow-log"
  retention_in_days = 30
}

resource "aws_cloudwatch_log_group" "redis_engine" {
  name              = "/exchange/redis/engine-log"
  retention_in_days = 30
}
```

### 3.3 TimescaleDB on EC2 (Self-managed)

```hcl
# terraform/modules/timescaledb/main.tf

resource "aws_launch_template" "timescaledb" {
  name_prefix   = "exchange-timescaledb-"
  image_id      = data.aws_ami.ubuntu.id
  instance_type = "r6i.2xlarge"  # 8 vCPU, 64GB RAM

  network_interfaces {
    associate_public_ip_address = false
    security_groups             = [var.timescaledb_sg_id]
  }

  block_device_mappings {
    device_name = "/dev/sda1"

    ebs {
      volume_size           = 50
      volume_type           = "gp3"
      encrypted             = true
      delete_on_termination = true
    }
  }

  # Data volume
  block_device_mappings {
    device_name = "/dev/sdf"

    ebs {
      volume_size           = 1000
      volume_type           = "gp3"
      iops                  = 16000
      throughput            = 1000
      encrypted             = true
      delete_on_termination = false
    }
  }

  user_data = base64encode(templatefile("${path.module}/timescaledb-userdata.sh", {
    postgres_password = var.postgres_password
  }))

  tag_specifications {
    resource_type = "instance"
    tags = {
      Name = "exchange-timescaledb"
    }
  }
}

resource "aws_instance" "timescaledb_primary" {
  launch_template {
    id      = aws_launch_template.timescaledb.id
    version = "$Latest"
  }

  subnet_id = var.database_subnet_ids[0]

  tags = {
    Name = "exchange-timescaledb-primary"
    Role = "primary"
  }
}

resource "aws_instance" "timescaledb_replica" {
  launch_template {
    id      = aws_launch_template.timescaledb.id
    version = "$Latest"
  }

  subnet_id = var.database_subnet_ids[1]

  tags = {
    Name = "exchange-timescaledb-replica"
    Role = "replica"
  }
}
```

## 4. 메시징 (Amazon MSK)

### 4.1 MSK Cluster

```hcl
# terraform/modules/msk/main.tf

resource "aws_msk_configuration" "main" {
  name           = "exchange-msk-config"
  kafka_versions = ["3.5.1"]

  server_properties = <<EOF
auto.create.topics.enable=false
default.replication.factor=3
min.insync.replicas=2
num.partitions=12
log.retention.hours=168
log.segment.bytes=1073741824
compression.type=lz4
message.max.bytes=10485760
replica.fetch.max.bytes=10485760
EOF
}

resource "aws_msk_cluster" "main" {
  cluster_name           = "exchange-msk"
  kafka_version          = "3.5.1"
  number_of_broker_nodes = 6

  broker_node_group_info {
    instance_type   = "kafka.m5.2xlarge"  # 8 vCPU, 32GB RAM
    client_subnets  = var.private_subnet_ids
    security_groups = [var.msk_sg_id]

    storage_info {
      ebs_storage_info {
        volume_size = 1000
        provisioned_throughput {
          enabled           = true
          volume_throughput = 250
        }
      }
    }
  }

  configuration_info {
    arn      = aws_msk_configuration.main.arn
    revision = aws_msk_configuration.main.latest_revision
  }

  encryption_info {
    encryption_in_transit {
      client_broker = "TLS"
      in_cluster    = true
    }
    encryption_at_rest_kms_key_arn = var.kms_key_arn
  }

  client_authentication {
    sasl {
      iam   = true
      scram = true
    }
  }

  logging_info {
    broker_logs {
      cloudwatch_logs {
        enabled   = true
        log_group = aws_cloudwatch_log_group.msk.name
      }

      s3 {
        enabled = true
        bucket  = var.logs_bucket_name
        prefix  = "msk/"
      }
    }
  }

  open_monitoring {
    prometheus {
      jmx_exporter {
        enabled_in_broker = true
      }
      node_exporter {
        enabled_in_broker = true
      }
    }
  }

  tags = {
    Name = "exchange-msk"
  }
}

resource "aws_cloudwatch_log_group" "msk" {
  name              = "/exchange/msk"
  retention_in_days = 30
}

# Kafka Topics
resource "null_resource" "kafka_topics" {
  depends_on = [aws_msk_cluster.main]

  provisioner "local-exec" {
    command = <<-EOT
      # Create topics
      kafka-topics.sh --bootstrap-server ${aws_msk_cluster.main.bootstrap_brokers_tls} \
        --create --topic orders.commands --partitions 24 --replication-factor 3

      kafka-topics.sh --bootstrap-server ${aws_msk_cluster.main.bootstrap_brokers_tls} \
        --create --topic trades.events --partitions 24 --replication-factor 3

      kafka-topics.sh --bootstrap-server ${aws_msk_cluster.main.bootstrap_brokers_tls} \
        --create --topic market.data --partitions 12 --replication-factor 3

      kafka-topics.sh --bootstrap-server ${aws_msk_cluster.main.bootstrap_brokers_tls} \
        --create --topic positions.updates --partitions 12 --replication-factor 3
    EOT
  }
}
```

## 5. 로드 밸런싱

### 5.1 Application Load Balancer

```hcl
# terraform/modules/alb/main.tf

resource "aws_lb" "main" {
  name               = "exchange-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [var.alb_sg_id]
  subnets            = var.public_subnet_ids

  enable_deletion_protection = true
  enable_http2               = true

  access_logs {
    bucket  = var.logs_bucket_name
    prefix  = "alb-access-logs"
    enabled = true
  }

  tags = {
    Name = "exchange-alb"
  }
}

# HTTPS Listener
resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.main.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = var.acm_certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.api.arn
  }
}

# HTTP to HTTPS Redirect
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.main.arn
  port              = "80"
  protocol          = "HTTP"

  default_action {
    type = "redirect"

    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

# API Target Group
resource "aws_lb_target_group" "api" {
  name        = "exchange-api-tg"
  port        = 3000
  protocol    = "HTTP"
  vpc_id      = var.vpc_id
  target_type = "ip"

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 15
    matcher             = "200"
    path                = "/health"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 3
  }

  stickiness {
    type            = "lb_cookie"
    cookie_duration = 86400
    enabled         = false
  }
}

# WebSocket Target Group
resource "aws_lb_target_group" "websocket" {
  name        = "exchange-websocket-tg"
  port        = 3001
  protocol    = "HTTP"
  vpc_id      = var.vpc_id
  target_type = "ip"

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 30
    matcher             = "200"
    path                = "/health"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 3
  }

  stickiness {
    type            = "lb_cookie"
    cookie_duration = 86400
    enabled         = true  # WebSocket needs sticky sessions
  }
}

# Listener Rules
resource "aws_lb_listener_rule" "api" {
  listener_arn = aws_lb_listener.https.arn
  priority     = 100

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.api.arn
  }

  condition {
    path_pattern {
      values = ["/api/*"]
    }
  }
}

resource "aws_lb_listener_rule" "websocket" {
  listener_arn = aws_lb_listener.https.arn
  priority     = 200

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.websocket.arn
  }

  condition {
    path_pattern {
      values = ["/ws/*", "/socket.io/*"]
    }
  }
}
```

### 5.2 CloudFront (CDN)

```hcl
# terraform/modules/cloudfront/main.tf

resource "aws_cloudfront_distribution" "main" {
  enabled             = true
  is_ipv6_enabled     = true
  comment             = "Exchange CDN"
  default_root_object = "index.html"
  price_class         = "PriceClass_200"
  aliases             = [var.domain_name, "www.${var.domain_name}"]

  origin {
    domain_name = aws_lb.main.dns_name
    origin_id   = "alb"

    custom_origin_config {
      http_port              = 80
      https_port             = 443
      origin_protocol_policy = "https-only"
      origin_ssl_protocols   = ["TLSv1.2"]
    }
  }

  # API behavior - no caching
  ordered_cache_behavior {
    path_pattern     = "/api/*"
    allowed_methods  = ["DELETE", "GET", "HEAD", "OPTIONS", "PATCH", "POST", "PUT"]
    cached_methods   = ["GET", "HEAD"]
    target_origin_id = "alb"

    forwarded_values {
      query_string = true
      headers      = ["Authorization", "Host", "Origin"]

      cookies {
        forward = "all"
      }
    }

    viewer_protocol_policy = "https-only"
    min_ttl                = 0
    default_ttl            = 0
    max_ttl                = 0
  }

  # WebSocket behavior
  ordered_cache_behavior {
    path_pattern     = "/ws/*"
    allowed_methods  = ["DELETE", "GET", "HEAD", "OPTIONS", "PATCH", "POST", "PUT"]
    cached_methods   = ["GET", "HEAD"]
    target_origin_id = "alb"

    forwarded_values {
      query_string = true
      headers      = ["*"]

      cookies {
        forward = "all"
      }
    }

    viewer_protocol_policy = "https-only"
    min_ttl                = 0
    default_ttl            = 0
    max_ttl                = 0
  }

  # Static assets - cached
  default_cache_behavior {
    allowed_methods        = ["GET", "HEAD", "OPTIONS"]
    cached_methods         = ["GET", "HEAD"]
    target_origin_id       = "alb"
    viewer_protocol_policy = "redirect-to-https"
    compress               = true

    forwarded_values {
      query_string = false

      cookies {
        forward = "none"
      }
    }

    min_ttl     = 0
    default_ttl = 86400
    max_ttl     = 31536000
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    acm_certificate_arn      = var.acm_certificate_arn
    ssl_support_method       = "sni-only"
    minimum_protocol_version = "TLSv1.2_2021"
  }

  # WAF
  web_acl_id = var.waf_acl_arn

  tags = {
    Name = "exchange-cdn"
  }
}
```

## 6. 모니터링 및 로깅

### 6.1 CloudWatch 구성

```hcl
# terraform/modules/monitoring/main.tf

# Log Groups
resource "aws_cloudwatch_log_group" "app_logs" {
  for_each = toset([
    "/exchange/api",
    "/exchange/websocket",
    "/exchange/matching-engine",
    "/exchange/consumers"
  ])

  name              = each.key
  retention_in_days = 30
  kms_key_id        = var.kms_key_arn
}

# Dashboard
resource "aws_cloudwatch_dashboard" "main" {
  dashboard_name = "exchange-main"

  dashboard_body = jsonencode({
    widgets = [
      {
        type   = "metric"
        x      = 0
        y      = 0
        width  = 12
        height = 6
        properties = {
          title  = "API Latency"
          region = var.region
          metrics = [
            ["AWS/ApplicationELB", "TargetResponseTime", "LoadBalancer", aws_lb.main.arn_suffix, { stat = "p99" }],
            ["...", { stat = "p95" }],
            ["...", { stat = "Average" }]
          ]
        }
      },
      {
        type   = "metric"
        x      = 12
        y      = 0
        width  = 12
        height = 6
        properties = {
          title  = "Request Count"
          region = var.region
          metrics = [
            ["AWS/ApplicationELB", "RequestCount", "LoadBalancer", aws_lb.main.arn_suffix]
          ]
        }
      },
      {
        type   = "metric"
        x      = 0
        y      = 6
        width  = 8
        height = 6
        properties = {
          title  = "RDS CPU"
          region = var.region
          metrics = [
            ["AWS/RDS", "CPUUtilization", "DBInstanceIdentifier", "exchange-postgres-primary"]
          ]
        }
      },
      {
        type   = "metric"
        x      = 8
        y      = 6
        width  = 8
        height = 6
        properties = {
          title  = "Redis Memory"
          region = var.region
          metrics = [
            ["AWS/ElastiCache", "DatabaseMemoryUsagePercentage", "CacheClusterId", "exchange-redis-0001-001"]
          ]
        }
      },
      {
        type   = "metric"
        x      = 16
        y      = 6
        width  = 8
        height = 6
        properties = {
          title  = "MSK Throughput"
          region = var.region
          metrics = [
            ["AWS/Kafka", "BytesInPerSec", "Cluster Name", "exchange-msk"],
            [".", "BytesOutPerSec", ".", "."]
          ]
        }
      }
    ]
  })
}

# Alarms
resource "aws_cloudwatch_metric_alarm" "api_latency" {
  alarm_name          = "exchange-api-latency-high"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3
  metric_name         = "TargetResponseTime"
  namespace           = "AWS/ApplicationELB"
  period              = 60
  statistic           = "p99"
  threshold           = 1  # 1 second

  dimensions = {
    LoadBalancer = aws_lb.main.arn_suffix
  }

  alarm_actions = [var.sns_topic_arn]
  ok_actions    = [var.sns_topic_arn]

  tags = {
    Name = "exchange-api-latency-alarm"
  }
}

resource "aws_cloudwatch_metric_alarm" "rds_cpu" {
  alarm_name          = "exchange-rds-cpu-high"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3
  metric_name         = "CPUUtilization"
  namespace           = "AWS/RDS"
  period              = 60
  statistic           = "Average"
  threshold           = 80

  dimensions = {
    DBInstanceIdentifier = "exchange-postgres-primary"
  }

  alarm_actions = [var.sns_topic_arn]
  ok_actions    = [var.sns_topic_arn]
}

resource "aws_cloudwatch_metric_alarm" "redis_memory" {
  alarm_name          = "exchange-redis-memory-high"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3
  metric_name         = "DatabaseMemoryUsagePercentage"
  namespace           = "AWS/ElastiCache"
  period              = 60
  statistic           = "Average"
  threshold           = 80

  dimensions = {
    CacheClusterId = "exchange-redis-0001-001"
  }

  alarm_actions = [var.sns_topic_arn]
  ok_actions    = [var.sns_topic_arn]
}
```

### 6.2 Prometheus & Grafana on EKS

```yaml
# kubernetes/monitoring/prometheus-values.yaml
prometheus:
  prometheusSpec:
    retention: 15d
    storageSpec:
      volumeClaimTemplate:
        spec:
          storageClassName: gp3
          accessModes: ["ReadWriteOnce"]
          resources:
            requests:
              storage: 100Gi

    additionalScrapeConfigs:
      - job_name: 'matching-engine'
        static_configs:
          - targets: ['matching-engine-nlb.internal:9090']
        metrics_path: '/metrics'

      - job_name: 'msk'
        static_configs:
          - targets: ['msk-broker-1:11001', 'msk-broker-2:11001', 'msk-broker-3:11001']

alertmanager:
  alertmanagerSpec:
    storage:
      volumeClaimTemplate:
        spec:
          storageClassName: gp3
          accessModes: ["ReadWriteOnce"]
          resources:
            requests:
              storage: 10Gi

  config:
    route:
      group_by: ['alertname', 'severity']
      group_wait: 30s
      group_interval: 5m
      repeat_interval: 12h
      receiver: 'slack-notifications'
      routes:
        - match:
            severity: critical
          receiver: 'pagerduty-critical'

    receivers:
      - name: 'slack-notifications'
        slack_configs:
          - api_url: '${SLACK_WEBHOOK_URL}'
            channel: '#exchange-alerts'

      - name: 'pagerduty-critical'
        pagerduty_configs:
          - service_key: '${PAGERDUTY_SERVICE_KEY}'

grafana:
  persistence:
    enabled: true
    storageClassName: gp3
    size: 20Gi

  adminPassword: "${GRAFANA_ADMIN_PASSWORD}"

  datasources:
    datasources.yaml:
      apiVersion: 1
      datasources:
        - name: Prometheus
          type: prometheus
          url: http://prometheus-server:80
          isDefault: true
        - name: CloudWatch
          type: cloudwatch
          jsonData:
            authType: default
            defaultRegion: ap-northeast-2
```

## 7. 보안

### 7.1 AWS WAF

```hcl
# terraform/modules/waf/main.tf

resource "aws_wafv2_web_acl" "main" {
  name        = "exchange-waf"
  description = "WAF for Exchange"
  scope       = "CLOUDFRONT"

  default_action {
    allow {}
  }

  # AWS Managed Rules - Common
  rule {
    name     = "AWSManagedRulesCommonRuleSet"
    priority = 1

    override_action {
      none {}
    }

    statement {
      managed_rule_group_statement {
        name        = "AWSManagedRulesCommonRuleSet"
        vendor_name = "AWS"
      }
    }

    visibility_config {
      cloudwatch_metrics_enabled = true
      metric_name                = "AWSManagedRulesCommonRuleSetMetric"
      sampled_requests_enabled   = true
    }
  }

  # AWS Managed Rules - SQL Injection
  rule {
    name     = "AWSManagedRulesSQLiRuleSet"
    priority = 2

    override_action {
      none {}
    }

    statement {
      managed_rule_group_statement {
        name        = "AWSManagedRulesSQLiRuleSet"
        vendor_name = "AWS"
      }
    }

    visibility_config {
      cloudwatch_metrics_enabled = true
      metric_name                = "AWSManagedRulesSQLiRuleSetMetric"
      sampled_requests_enabled   = true
    }
  }

  # Rate Limiting
  rule {
    name     = "RateLimitRule"
    priority = 3

    action {
      block {}
    }

    statement {
      rate_based_statement {
        limit              = 2000
        aggregate_key_type = "IP"
      }
    }

    visibility_config {
      cloudwatch_metrics_enabled = true
      metric_name                = "RateLimitRuleMetric"
      sampled_requests_enabled   = true
    }
  }

  # Geo blocking (if needed)
  rule {
    name     = "GeoBlockRule"
    priority = 4

    action {
      block {}
    }

    statement {
      geo_match_statement {
        country_codes = ["KP", "IR", "CU", "SY"]  # Sanctioned countries
      }
    }

    visibility_config {
      cloudwatch_metrics_enabled = true
      metric_name                = "GeoBlockRuleMetric"
      sampled_requests_enabled   = true
    }
  }

  visibility_config {
    cloudwatch_metrics_enabled = true
    metric_name                = "ExchangeWAFMetric"
    sampled_requests_enabled   = true
  }
}
```

### 7.2 Secrets Manager

```hcl
# terraform/modules/secrets/main.tf

resource "aws_secretsmanager_secret" "database" {
  name        = "exchange/database"
  description = "Database credentials"
  kms_key_id  = var.kms_key_arn

  tags = {
    Name = "exchange-database-secret"
  }
}

resource "aws_secretsmanager_secret_version" "database" {
  secret_id = aws_secretsmanager_secret.database.id
  secret_string = jsonencode({
    username = var.db_username
    password = var.db_password
    host     = aws_db_instance.primary.endpoint
    port     = 5432
    database = "exchange"
  })
}

resource "aws_secretsmanager_secret" "redis" {
  name        = "exchange/redis"
  description = "Redis credentials"
  kms_key_id  = var.kms_key_arn
}

resource "aws_secretsmanager_secret_version" "redis" {
  secret_id = aws_secretsmanager_secret.redis.id
  secret_string = jsonencode({
    auth_token = var.redis_auth_token
    endpoint   = aws_elasticache_replication_group.main.configuration_endpoint_address
    port       = 6379
  })
}

resource "aws_secretsmanager_secret" "api_keys" {
  name        = "exchange/api-keys"
  description = "API keys and secrets"
  kms_key_id  = var.kms_key_arn
}
```

## 8. 비용 예측

### 8.1 월간 예상 비용 (Production)

| 서비스 | 스펙 | 월 비용 (USD) |
|--------|------|---------------|
| **EKS Cluster** | 1 cluster | $73 |
| **EC2 (API Nodes)** | 6x c6i.2xlarge | $1,470 |
| **EC2 (WebSocket)** | 4x r6i.xlarge | $730 |
| **EC2 (Consumer)** | 6x c6i.xlarge | $735 |
| **EC2 (Matching Engine)** | 3x c6i.4xlarge (dedicated) | $2,940 |
| **RDS PostgreSQL** | db.r6i.4xlarge (Multi-AZ) | $4,380 |
| **RDS Read Replicas** | 2x db.r6i.2xlarge | $2,190 |
| **ElastiCache Redis** | 9x cache.r6g.xlarge | $2,430 |
| **MSK Kafka** | 6x kafka.m5.2xlarge | $4,320 |
| **NAT Gateway** | 3x (+ data transfer) | $300 |
| **ALB** | 1x (+ LCU) | $150 |
| **CloudFront** | 10TB transfer | $850 |
| **S3** | 1TB storage | $23 |
| **CloudWatch** | Logs + Metrics | $200 |
| **Secrets Manager** | 10 secrets | $4 |
| **WAF** | 10M requests | $100 |
| **Data Transfer** | 10TB | $900 |
| **기타** | Route 53, ACM 등 | $100 |
| **합계** | | **~$21,895/월** |

### 8.2 비용 최적화 전략

```hcl
# 1. Reserved Instances (1년 약정 시 ~40% 절감)
# EC2 Reserved: ~$8,000/월 절감
# RDS Reserved: ~$2,500/월 절감
# ElastiCache Reserved: ~$1,000/월 절감

# 2. Savings Plans
# Compute Savings Plan: 추가 10-20% 절감 가능

# 3. Spot Instances (비핵심 워크로드)
resource "aws_eks_node_group" "batch_spot" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "batch-spot-nodes"
  capacity_type   = "SPOT"  # 최대 90% 절감

  instance_types = ["c6i.xlarge", "c5.xlarge", "c5a.xlarge"]

  labels = {
    "node-type" = "batch"
  }

  taint {
    key    = "spot"
    value  = "true"
    effect = "NO_SCHEDULE"
  }
}

# 4. S3 Intelligent Tiering
resource "aws_s3_bucket" "logs" {
  bucket = "exchange-logs"
}

resource "aws_s3_bucket_intelligent_tiering_configuration" "logs" {
  bucket = aws_s3_bucket.logs.id
  name   = "entire-bucket"

  tiering {
    access_tier = "ARCHIVE_ACCESS"
    days        = 90
  }

  tiering {
    access_tier = "DEEP_ARCHIVE_ACCESS"
    days        = 180
  }
}
```

### 8.3 최적화 후 예상 비용

| 항목 | 최적화 전 | 최적화 후 | 절감액 |
|------|----------|----------|--------|
| 월간 비용 | $21,895 | **$13,500** | $8,395 |
| 연간 비용 | $262,740 | **$162,000** | $100,740 |

## 9. 배포 파이프라인

### 9.1 CI/CD with GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy to AWS

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

env:
  AWS_REGION: ap-northeast-2
  ECR_REGISTRY: ${{ secrets.AWS_ACCOUNT_ID }}.dkr.ecr.ap-northeast-2.amazonaws.com
  EKS_CLUSTER: exchange-eks

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Login to Amazon ECR
        uses: aws-actions/amazon-ecr-login@v2

      - name: Build and push API image
        run: |
          docker build -t $ECR_REGISTRY/exchange-api:${{ github.sha }} ./api
          docker push $ECR_REGISTRY/exchange-api:${{ github.sha }}

      - name: Build and push WebSocket image
        run: |
          docker build -t $ECR_REGISTRY/exchange-websocket:${{ github.sha }} ./websocket
          docker push $ECR_REGISTRY/exchange-websocket:${{ github.sha }}

  deploy:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Update kubeconfig
        run: |
          aws eks update-kubeconfig --name $EKS_CLUSTER --region $AWS_REGION

      - name: Deploy to EKS
        run: |
          # Update image tags
          kubectl set image deployment/api api=$ECR_REGISTRY/exchange-api:${{ github.sha }}
          kubectl set image deployment/websocket websocket=$ECR_REGISTRY/exchange-websocket:${{ github.sha }}

          # Wait for rollout
          kubectl rollout status deployment/api --timeout=300s
          kubectl rollout status deployment/websocket --timeout=300s
```

## 체크리스트

### 구축 순서

1. [ ] **네트워크 구성**
   - [ ] VPC 생성
   - [ ] 서브넷 구성 (Public, Private, Database, Matching Engine)
   - [ ] NAT Gateway 설정
   - [ ] Security Groups 생성

2. [ ] **데이터베이스 구축**
   - [ ] RDS PostgreSQL Primary 생성
   - [ ] Read Replica 생성
   - [ ] ElastiCache Redis Cluster 생성
   - [ ] TimescaleDB EC2 인스턴스 생성

3. [ ] **메시징 구축**
   - [ ] MSK Cluster 생성
   - [ ] Kafka Topics 생성

4. [ ] **컴퓨트 구축**
   - [ ] EKS Cluster 생성
   - [ ] Node Groups 생성
   - [ ] Matching Engine EC2 구성

5. [ ] **로드 밸런싱**
   - [ ] ALB 생성
   - [ ] CloudFront 구성

6. [ ] **보안 구성**
   - [ ] WAF 규칙 설정
   - [ ] Secrets Manager 구성
   - [ ] IAM 역할/정책 설정

7. [ ] **모니터링**
   - [ ] CloudWatch 대시보드 구성
   - [ ] Prometheus/Grafana 배포
   - [ ] 알람 설정

8. [ ] **DR 구성**
   - [ ] Cross-Region Replication 설정
   - [ ] DR 리전 EKS Cluster 대기

## 10. 비용 최적화 인프라 (10,000 TPS)

> ⚠️ **선행 조건**: 이 인프라로 10K TPS를 달성하려면 **Phase 1 코드 최적화**가 필요합니다.
> 현재 코드 기준 최대 TPS는 5K-10K이며, 코드 최적화 없이는 이 인프라의 성능을 활용할 수 없습니다.
> 자세한 내용은 [섹션 12. 코드 최적화 선행 조건](#12-코드-최적화-선행-조건)을 참조하세요.

위 설계는 100,000+ TPS를 목표로 한 대규모 인프라입니다. 10,000 TPS 수준의 비용 효율적인 설계가 필요한 경우 아래 구성을 참고하세요.

### 10.1 아키텍처 개요

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              AWS Cloud (10K TPS)                             │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                         Region: ap-northeast-2                          ││
│  │                                                                          ││
│  │  ┌────────────────────────────────────────────────────────────────────┐ ││
│  │  │                      VPC: 10.0.0.0/16                               │ ││
│  │  │                                                                     │ ││
│  │  │  ┌─────────────┐  ┌─────────────┐                                  │ ││
│  │  │  │   AZ-2a     │  │   AZ-2b     │                                  │ ││
│  │  │  │             │  │             │                                  │ ││
│  │  │  │ ┌─────────┐ │  │ ┌─────────┐ │                                  │ ││
│  │  │  │ │ Public  │ │  │ │ Public  │ │  ← ALB, NAT Gateway (AZ-2a only) │ ││
│  │  │  │ │ Subnet  │ │  │ │ Subnet  │ │                                  │ ││
│  │  │  │ └─────────┘ │  │ └─────────┘ │                                  │ ││
│  │  │  │             │  │             │                                  │ ││
│  │  │  │ ┌─────────┐ │  │ ┌─────────┐ │                                  │ ││
│  │  │  │ │ Private │ │  │ │ Private │ │  ← EKS (2 API, 2 WS, 2 Consumer) │ ││
│  │  │  │ │ Subnet  │ │  │ │ Subnet  │ │    Matching Engine (2x)          │ ││
│  │  │  │ └─────────┘ │  │ └─────────┘ │                                  │ ││
│  │  │  │             │  │             │                                  │ ││
│  │  │  │ ┌─────────┐ │  │ ┌─────────┐ │                                  │ ││
│  │  │  │ │Database │ │  │ │Database │ │  ← RDS (Primary + 1 Replica)     │ ││
│  │  │  │ │ Subnet  │ │  │ │ Subnet  │ │    Redis (3 nodes), MSK (3)      │ ││
│  │  │  │ └─────────┘ │  │ └─────────┘ │                                  │ ││
│  │  │  └─────────────┘  └─────────────┘                                  │ ││
│  │  │                                                                     │ ││
│  │  └────────────────────────────────────────────────────────────────────┘ ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
```

### 10.2 비용 비교 요약

| 구분 | 대규모 설계 (10만+ TPS) | 최적화 설계 (1만 TPS) | 절감율 |
|------|------------------------|----------------------|--------|
| **월간 비용** | ~$21,895 | **~$3,316** | 85% |
| **연간 비용** | ~$262,740 | **~$39,792** | 85% |
| **RI 적용 시** | ~$13,500/월 | **~$2,200/월** | - |

### 10.3 컴퓨트 리소스

#### EKS 노드 그룹

```hcl
# terraform/modules/eks-optimized/main.tf

resource "aws_eks_cluster" "main" {
  name     = "exchange-eks"
  role_arn = aws_iam_role.eks_cluster.arn
  version  = "1.29"

  vpc_config {
    subnet_ids              = var.private_subnet_ids
    endpoint_private_access = true
    endpoint_public_access  = true
  }

  # 비용 절감: 필수 로그만 활성화
  enabled_cluster_log_types = ["api", "audit"]
}

# API Nodes (축소: 6 → 2)
resource "aws_eks_node_group" "api" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "api-nodes"
  node_role_arn   = aws_iam_role.eks_node.arn
  subnet_ids      = var.private_subnet_ids

  instance_types = ["c6i.xlarge"]  # 4 vCPU, 8GB (기존 c6i.2xlarge)
  capacity_type  = "ON_DEMAND"

  scaling_config {
    desired_size = 2   # 기존 6
    min_size     = 2
    max_size     = 6   # 트래픽 증가 시 자동 확장
  }

  labels = {
    "node-type" = "api"
  }
}

# WebSocket Nodes (축소: 4 → 2)
resource "aws_eks_node_group" "websocket" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "websocket-nodes"
  node_role_arn   = aws_iam_role.eks_node.arn
  subnet_ids      = var.private_subnet_ids

  instance_types = ["r6i.large"]  # 2 vCPU, 16GB (기존 r6i.xlarge)
  capacity_type  = "ON_DEMAND"

  scaling_config {
    desired_size = 2   # 기존 4
    min_size     = 2
    max_size     = 5
  }

  labels = {
    "node-type" = "websocket"
  }

  taint {
    key    = "dedicated"
    value  = "websocket"
    effect = "NO_SCHEDULE"
  }
}

# Consumer Nodes (축소: 6 → 2)
resource "aws_eks_node_group" "consumer" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "consumer-nodes"
  node_role_arn   = aws_iam_role.eks_node.arn
  subnet_ids      = var.private_subnet_ids

  instance_types = ["c6i.large"]  # 2 vCPU, 4GB (기존 c6i.xlarge)
  capacity_type  = "ON_DEMAND"

  scaling_config {
    desired_size = 2   # 기존 6
    min_size     = 2
    max_size     = 6
  }

  labels = {
    "node-type" = "consumer"
  }
}
```

#### 매칭 엔진 (비용 최적화)

```hcl
# terraform/modules/matching-engine-optimized/main.tf

# Placement Group (클러스터 전략 유지)
resource "aws_placement_group" "matching_engine" {
  name     = "exchange-matching-engine-pg"
  strategy = "cluster"
}

# Launch Template (Shared Tenancy로 변경)
resource "aws_launch_template" "matching_engine" {
  name_prefix   = "exchange-matching-engine-"
  image_id      = data.aws_ami.amazon_linux_2.id
  instance_type = "c6i.xlarge"  # 4 vCPU, 8GB (기존 c6i.4xlarge)

  # Shared Tenancy (기존 Dedicated에서 변경 - 비용 절감)
  placement {
    group_name = aws_placement_group.matching_engine.name
    # tenancy = "default"  (기본값, Dedicated 제거)
  }

  ebs_optimized = true

  network_interfaces {
    associate_public_ip_address = false
    security_groups             = [var.matching_engine_sg_id]
    delete_on_termination       = true
  }

  block_device_mappings {
    device_name = "/dev/xvda"

    ebs {
      volume_size           = 50   # 기존 100GB
      volume_type           = "gp3"
      iops                  = 3000 # 기존 16000
      throughput            = 125  # 기존 1000
      encrypted             = true
      delete_on_termination = true
    }
  }

  user_data = base64encode(templatefile("${path.module}/userdata-optimized.sh", {
    environment   = var.environment
    kafka_brokers = var.kafka_brokers
  }))

  tag_specifications {
    resource_type = "instance"
    tags = {
      Name = "exchange-matching-engine"
      Role = "matching-engine"
    }
  }
}

# Auto Scaling Group (축소: 3 → 2)
resource "aws_autoscaling_group" "matching_engine" {
  name                = "exchange-matching-engine-asg"
  vpc_zone_identifier = var.private_subnet_ids  # 전용 서브넷 대신 Private 사용
  min_size            = 2   # 기존 3
  max_size            = 4   # 기존 9
  desired_capacity    = 2

  launch_template {
    id      = aws_launch_template.matching_engine.id
    version = "$Latest"
  }

  health_check_type         = "ELB"
  health_check_grace_period = 300

  tag {
    key                 = "Name"
    value               = "exchange-matching-engine"
    propagate_at_launch = true
  }
}
```

#### 매칭 엔진 User Data (최적화)

```bash
#!/bin/bash
# terraform/modules/matching-engine-optimized/userdata-optimized.sh

set -e

# System tuning (동일)
cat >> /etc/sysctl.conf << 'EOF'
net.core.rmem_max = 134217728
net.core.wmem_max = 134217728
net.ipv4.tcp_rmem = 4096 87380 134217728
net.ipv4.tcp_wmem = 4096 65536 134217728
net.core.somaxconn = 65535
vm.swappiness = 1
EOF
sysctl -p

echo never > /sys/kernel/mm/transparent_hugepage/enabled

# Install Java 17
amazon-linux-extras install java-openjdk17 -y

# CloudWatch Agent
yum install -y amazon-cloudwatch-agent

# Systemd service (메모리 조정: 24GB → 6GB)
cat > /etc/systemd/system/matching-engine.service << 'EOF'
[Unit]
Description=Exchange Matching Engine
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/matching-engine
ExecStart=/usr/bin/java \
  -Xms6g -Xmx6g \
  -XX:+UseZGC \
  -Xlog:gc*:file=/var/log/matching-engine/gc.log:time,uptime:filecount=5,filesize=50m \
  -jar /opt/matching-engine/latest.jar \
  --spring.config.location=/opt/matching-engine/config.yml
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable matching-engine
systemctl start matching-engine
```

### 10.4 데이터베이스

#### RDS PostgreSQL (최적화)

```hcl
# terraform/modules/rds-optimized/main.tf

resource "aws_db_subnet_group" "main" {
  name       = "exchange-db-subnet-group"
  subnet_ids = var.database_subnet_ids
}

resource "aws_db_parameter_group" "postgres" {
  family = "postgres15"
  name   = "exchange-postgres-params-optimized"

  # 소규모 인스턴스용 튜닝
  parameter {
    name  = "shared_buffers"
    value = "{DBInstanceClassMemory/4}"
  }

  parameter {
    name  = "effective_cache_size"
    value = "{DBInstanceClassMemory*3/4}"
  }

  parameter {
    name  = "work_mem"
    value = "65536"  # 64MB (기존 256MB)
  }

  parameter {
    name  = "maintenance_work_mem"
    value = "524288"  # 512MB (기존 2GB)
  }

  parameter {
    name  = "max_connections"
    value = "200"  # 기존 500
  }

  parameter {
    name  = "log_min_duration_statement"
    value = "200"  # 200ms 이상 쿼리만 로깅
  }
}

# Primary Instance (축소)
resource "aws_db_instance" "primary" {
  identifier     = "exchange-postgres-primary"
  engine         = "postgres"
  engine_version = "15.4"

  instance_class        = "db.r6i.xlarge"  # 4 vCPU, 32GB (기존 db.r6i.4xlarge)
  allocated_storage     = 200              # 기존 500GB
  max_allocated_storage = 500              # 기존 2000GB
  storage_type          = "gp3"
  iops                  = 3000             # 기존 12000
  storage_encrypted     = true

  db_name  = "exchange"
  username = var.db_username
  password = var.db_password
  port     = 5432

  multi_az               = true
  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [var.rds_sg_id]
  parameter_group_name   = aws_db_parameter_group.postgres.name

  backup_retention_period = 14  # 기존 35일
  backup_window           = "03:00-04:00"
  maintenance_window      = "Mon:04:00-Mon:05:00"

  performance_insights_enabled          = true
  performance_insights_retention_period = 7

  enabled_cloudwatch_logs_exports = ["postgresql"]

  deletion_protection = true

  tags = {
    Name = "exchange-postgres-primary"
  }
}

# Read Replica (1개로 축소)
resource "aws_db_instance" "read_replica" {
  identifier     = "exchange-postgres-replica"
  instance_class = "db.r6i.large"  # 2 vCPU, 16GB (기존 db.r6i.2xlarge x 2)

  replicate_source_db = aws_db_instance.primary.identifier

  vpc_security_group_ids = [var.rds_sg_id]
  parameter_group_name   = aws_db_parameter_group.postgres.name

  performance_insights_enabled          = true
  performance_insights_retention_period = 7

  tags = {
    Name = "exchange-postgres-replica"
  }
}

# DR Cross-Region Replica 제거 (비용 절감)
# 필요 시 수동 스냅샷 복원으로 대체
```

#### ElastiCache Redis (최적화)

```hcl
# terraform/modules/elasticache-optimized/main.tf

resource "aws_elasticache_subnet_group" "main" {
  name       = "exchange-redis-subnet-group"
  subnet_ids = var.database_subnet_ids
}

resource "aws_elasticache_parameter_group" "redis" {
  family = "redis7"
  name   = "exchange-redis-params-optimized"

  # 단일 샤드 모드
  parameter {
    name  = "maxmemory-policy"
    value = "volatile-lru"
  }

  # 클러스터 모드 비활성화 (단일 샤드)
  parameter {
    name  = "cluster-enabled"
    value = "no"
  }
}

# Redis Replication Group (단일 샤드)
resource "aws_elasticache_replication_group" "main" {
  replication_group_id = "exchange-redis"
  description          = "Exchange Redis (Optimized)"

  node_type             = "cache.r6g.large"  # 2 vCPU, 13GB (기존 r6g.xlarge)
  num_cache_clusters    = 3                   # Primary + 2 Replicas (기존 9노드)

  port                 = 6379
  parameter_group_name = aws_elasticache_parameter_group.redis.name
  subnet_group_name    = aws_elasticache_subnet_group.main.name
  security_group_ids   = [var.elasticache_sg_id]

  automatic_failover_enabled = true
  multi_az_enabled           = true

  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
  auth_token                 = var.redis_auth_token

  snapshot_retention_limit = 3  # 기존 7일
  snapshot_window          = "03:00-05:00"
  maintenance_window       = "mon:05:00-mon:07:00"

  tags = {
    Name = "exchange-redis"
  }
}
```

### 10.5 메시징 (MSK)

```hcl
# terraform/modules/msk-optimized/main.tf

resource "aws_msk_configuration" "main" {
  name           = "exchange-msk-config-optimized"
  kafka_versions = ["3.5.1"]

  server_properties = <<EOF
auto.create.topics.enable=false
default.replication.factor=3
min.insync.replicas=2
num.partitions=6
log.retention.hours=72
log.segment.bytes=536870912
compression.type=lz4
EOF
}

resource "aws_msk_cluster" "main" {
  cluster_name           = "exchange-msk"
  kafka_version          = "3.5.1"
  number_of_broker_nodes = 3  # 기존 6

  broker_node_group_info {
    instance_type   = "kafka.m5.large"  # 2 vCPU, 8GB (기존 m5.2xlarge)
    client_subnets  = var.private_subnet_ids[0:3]
    security_groups = [var.msk_sg_id]

    storage_info {
      ebs_storage_info {
        volume_size = 500  # 기존 1000GB
        # Provisioned throughput 제거 (비용 절감)
      }
    }
  }

  configuration_info {
    arn      = aws_msk_configuration.main.arn
    revision = aws_msk_configuration.main.latest_revision
  }

  encryption_info {
    encryption_in_transit {
      client_broker = "TLS"
      in_cluster    = true
    }
  }

  # SASL 인증만 사용 (간소화)
  client_authentication {
    sasl {
      iam = true
    }
  }

  logging_info {
    broker_logs {
      cloudwatch_logs {
        enabled   = true
        log_group = aws_cloudwatch_log_group.msk.name
      }
      # S3 로깅 제거 (비용 절감)
    }
  }

  # JMX만 활성화 (Node exporter 제거)
  open_monitoring {
    prometheus {
      jmx_exporter {
        enabled_in_broker = true
      }
      node_exporter {
        enabled_in_broker = false
      }
    }
  }

  tags = {
    Name = "exchange-msk"
  }
}

resource "aws_cloudwatch_log_group" "msk" {
  name              = "/exchange/msk"
  retention_in_days = 14  # 기존 30일
}
```

### 10.6 네트워크 (최적화)

```hcl
# terraform/modules/vpc-optimized/main.tf

resource "aws_vpc" "exchange" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "exchange-vpc"
  }
}

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.exchange.id
}

# 2개 AZ만 사용 (기존 3개)
data "aws_availability_zones" "available" {
  state = "available"
}

locals {
  azs = slice(data.aws_availability_zones.available.names, 0, 2)
}

# Public Subnets (2개)
resource "aws_subnet" "public" {
  count                   = 2
  vpc_id                  = aws_vpc.exchange.id
  cidr_block              = "10.0.${count.index + 1}.0/24"
  availability_zone       = local.azs[count.index]
  map_public_ip_on_launch = true

  tags = {
    Name                              = "exchange-public-${count.index + 1}"
    "kubernetes.io/role/elb"          = "1"
    "kubernetes.io/cluster/exchange-eks" = "shared"
  }
}

# Private Subnets (2개)
resource "aws_subnet" "private" {
  count             = 2
  vpc_id            = aws_vpc.exchange.id
  cidr_block        = "10.0.${count.index + 11}.0/24"
  availability_zone = local.azs[count.index]

  tags = {
    Name                                = "exchange-private-${count.index + 1}"
    "kubernetes.io/role/internal-elb"   = "1"
    "kubernetes.io/cluster/exchange-eks" = "shared"
  }
}

# Database Subnets (2개)
resource "aws_subnet" "database" {
  count             = 2
  vpc_id            = aws_vpc.exchange.id
  cidr_block        = "10.0.${count.index + 21}.0/24"
  availability_zone = local.azs[count.index]

  tags = {
    Name = "exchange-database-${count.index + 1}"
  }
}

# NAT Gateway - 1개만 (비용 절감, 기존 3개)
resource "aws_eip" "nat" {
  domain = "vpc"

  tags = {
    Name = "exchange-nat-eip"
  }
}

resource "aws_nat_gateway" "main" {
  allocation_id = aws_eip.nat.id
  subnet_id     = aws_subnet.public[0].id

  tags = {
    Name = "exchange-nat"
  }

  depends_on = [aws_internet_gateway.main]
}

# 단일 라우트 테이블 (모든 Private 서브넷 공유)
resource "aws_route_table" "private" {
  vpc_id = aws_vpc.exchange.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.main.id
  }

  tags = {
    Name = "exchange-private-rt"
  }
}

resource "aws_route_table_association" "private" {
  count          = 2
  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private.id
}

# VPC Flow Logs (선택적, 비용 절감을 위해 비활성화 가능)
# resource "aws_flow_log" "main" { ... }
```

### 10.7 비용 상세 내역

| 서비스 | 스펙 | 월 비용 (USD) |
|--------|------|---------------|
| **EKS Cluster** | 1 cluster | $73 |
| **EC2 (API Nodes)** | 2x c6i.xlarge | $245 |
| **EC2 (WebSocket)** | 2x r6i.large | $183 |
| **EC2 (Consumer)** | 2x c6i.large | $122 |
| **EC2 (Matching Engine)** | 2x c6i.xlarge | $245 |
| **RDS PostgreSQL** | db.r6i.xlarge (Multi-AZ) | $548 |
| **RDS Read Replica** | 1x db.r6i.large | $182 |
| **RDS Storage** | 200GB gp3 | $23 |
| **ElastiCache Redis** | 3x cache.r6g.large | $405 |
| **MSK Kafka** | 3x kafka.m5.large | $540 |
| **MSK Storage** | 500GB x 3 | $75 |
| **NAT Gateway** | 1x (+ 3TB transfer) | $135 |
| **ALB** | 1x (+ LCU) | $50 |
| **CloudFront** | 2TB transfer | $170 |
| **S3** | 200GB | $5 |
| **CloudWatch** | Logs + Metrics | $100 |
| **Data Transfer** | 3TB | $270 |
| **기타** | Route 53, ACM 등 | $50 |
| **합계** | | **~$3,421/월** |

### 10.8 Reserved Instance 적용 시

| 항목 | On-Demand | 1년 RI | 3년 RI |
|------|-----------|--------|--------|
| EC2 (All) | $795 | $505 (36% ↓) | $320 (60% ↓) |
| RDS | $753 | $480 (36% ↓) | $300 (60% ↓) |
| ElastiCache | $405 | $260 (36% ↓) | $165 (60% ↓) |
| **총 월 비용** | **$3,421** | **$2,500** | **$2,000** |

## 11. 스타트업 인프라 (5,000 TPS)

> ✅ **현재 코드에 최적화된 인프라**: 현재 코드베이스의 TPS 한계(5K-10K)에 맞춰 설계되었습니다.
> 코드 최적화 없이 바로 사용 가능하며, MVP 또는 초기 런칭 단계에 적합합니다.

MVP 또는 초기 런칭 단계에 적합한 최소 비용 인프라입니다.

### 11.1 아키텍처 개요

```
┌───────────────────────────────────────────────────────────────────┐
│                      AWS Cloud (5K TPS)                           │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │                  Region: ap-northeast-2                      │ │
│  │                                                              │ │
│  │  ┌─────────────────────────────────────────────────────────┐│ │
│  │  │                 VPC: 10.0.0.0/16                         ││ │
│  │  │                                                          ││ │
│  │  │  ┌─────────────┐  ┌─────────────┐                       ││ │
│  │  │  │   AZ-2a     │  │   AZ-2b     │                       ││ │
│  │  │  │             │  │             │                       ││ │
│  │  │  │ ┌─────────┐ │  │ ┌─────────┐ │                       ││ │
│  │  │  │ │ Public  │ │  │ │ Public  │ │  ← ALB, NAT (AZ-2a)   ││ │
│  │  │  │ └─────────┘ │  │ └─────────┘ │                       ││ │
│  │  │  │             │  │             │                       ││ │
│  │  │  │ ┌─────────┐ │  │ ┌─────────┐ │  ← EKS (통합 노드 3개)││ │
│  │  │  │ │ Private │ │  │ │ Private │ │    + Matching Engine  ││ │
│  │  │  │ └─────────┘ │  │ └─────────┘ │                       ││ │
│  │  │  │             │  │             │                       ││ │
│  │  │  │ ┌─────────┐ │  │ ┌─────────┐ │  ← RDS (Single + 1Rep)││ │
│  │  │  │ │Database │ │  │ │Database │ │    Redis (2 nodes)    ││ │
│  │  │  │ └─────────┘ │  │ └─────────┘ │    MSK Serverless     ││ │
│  │  │  └─────────────┘  └─────────────┘                       ││ │
│  │  └─────────────────────────────────────────────────────────┘│ │
│  └─────────────────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────────────────┘
```

### 11.2 비용 비교

| 구분 | 대규모 (10만 TPS) | 중규모 (1만 TPS) | 스타트업 (5천 TPS) |
|------|------------------|-----------------|-------------------|
| **월간 비용** | ~$21,895 | ~$3,421 | **~$1,850** |
| **연간 비용** | ~$262,740 | ~$41,052 | **~$22,200** |
| **RI 적용 시** | ~$13,500/월 | ~$2,200/월 | **~$1,200/월** |

### 11.3 컴퓨트 리소스

#### EKS 통합 노드 (단일 노드 그룹)

```hcl
# terraform/modules/eks-startup/main.tf

resource "aws_eks_cluster" "main" {
  name     = "exchange-eks"
  role_arn = aws_iam_role.eks_cluster.arn
  version  = "1.29"

  vpc_config {
    subnet_ids              = var.private_subnet_ids
    endpoint_private_access = true
    endpoint_public_access  = true
  }

  # 최소 로깅
  enabled_cluster_log_types = ["audit"]
}

# 통합 노드 그룹 (API + WebSocket + Consumer 통합)
resource "aws_eks_node_group" "general" {
  cluster_name    = aws_eks_cluster.main.name
  node_group_name = "general-nodes"
  node_role_arn   = aws_iam_role.eks_node.arn
  subnet_ids      = var.private_subnet_ids

  instance_types = ["c6i.xlarge"]  # 4 vCPU, 8GB
  capacity_type  = "ON_DEMAND"

  scaling_config {
    desired_size = 3
    min_size     = 2
    max_size     = 6
  }

  labels = {
    "node-type" = "general"
  }
}
```

#### 매칭 엔진 (단일 인스턴스 + Standby)

```hcl
# terraform/modules/matching-engine-startup/main.tf

resource "aws_launch_template" "matching_engine" {
  name_prefix   = "exchange-me-"
  image_id      = data.aws_ami.amazon_linux_2.id
  instance_type = "c6i.large"  # 2 vCPU, 4GB

  network_interfaces {
    associate_public_ip_address = false
    security_groups             = [var.matching_engine_sg_id]
  }

  block_device_mappings {
    device_name = "/dev/xvda"
    ebs {
      volume_size = 30
      volume_type = "gp3"
      iops        = 3000
      encrypted   = true
    }
  }

  user_data = base64encode(templatefile("${path.module}/userdata.sh", {
    heap_size = "3g"  # 인스턴스 크기에 맞게 조정
  }))
}

# Primary + Standby
resource "aws_autoscaling_group" "matching_engine" {
  name                = "exchange-me-asg"
  vpc_zone_identifier = var.private_subnet_ids
  min_size            = 1
  max_size            = 2
  desired_capacity    = 2  # Active-Standby

  launch_template {
    id      = aws_launch_template.matching_engine.id
    version = "$Latest"
  }
}
```

### 11.4 데이터베이스

#### RDS (단일 인스턴스 + Read Replica)

```hcl
# terraform/modules/rds-startup/main.tf

resource "aws_db_parameter_group" "postgres" {
  family = "postgres15"
  name   = "exchange-postgres-startup"

  parameter {
    name  = "max_connections"
    value = "100"
  }

  parameter {
    name  = "shared_buffers"
    value = "{DBInstanceClassMemory/4}"
  }
}

# Primary (Multi-AZ 대신 단일 AZ)
resource "aws_db_instance" "primary" {
  identifier     = "exchange-postgres"
  engine         = "postgres"
  engine_version = "15.4"

  instance_class        = "db.t4g.large"   # 2 vCPU, 8GB (ARM 기반, 비용 효율)
  allocated_storage     = 100
  max_allocated_storage = 200
  storage_type          = "gp3"
  storage_encrypted     = true

  db_name  = "exchange"
  username = var.db_username
  password = var.db_password

  multi_az               = false  # 비용 절감 (중요 데이터는 Replica로 보호)
  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [var.rds_sg_id]
  parameter_group_name   = aws_db_parameter_group.postgres.name

  backup_retention_period = 7
  backup_window           = "03:00-04:00"

  performance_insights_enabled = false  # 비용 절감

  deletion_protection = true
}

# Read Replica (다른 AZ에 배치하여 가용성 확보)
resource "aws_db_instance" "replica" {
  identifier     = "exchange-postgres-replica"
  instance_class = "db.t4g.medium"  # 2 vCPU, 4GB

  replicate_source_db    = aws_db_instance.primary.identifier
  vpc_security_group_ids = [var.rds_sg_id]
  availability_zone      = var.secondary_az  # Primary와 다른 AZ
}
```

#### ElastiCache Redis (최소 구성)

```hcl
# terraform/modules/elasticache-startup/main.tf

resource "aws_elasticache_parameter_group" "redis" {
  family = "redis7"
  name   = "exchange-redis-startup"

  parameter {
    name  = "maxmemory-policy"
    value = "volatile-lru"
  }
}

# 단일 샤드, Primary + 1 Replica
resource "aws_elasticache_replication_group" "main" {
  replication_group_id = "exchange-redis"
  description          = "Exchange Redis (Startup)"

  node_type          = "cache.t4g.medium"  # 2 vCPU, 3.09GB (ARM 기반)
  num_cache_clusters = 2                    # Primary + 1 Replica

  port                 = 6379
  parameter_group_name = aws_elasticache_parameter_group.redis.name
  subnet_group_name    = aws_elasticache_subnet_group.main.name
  security_group_ids   = [var.elasticache_sg_id]

  automatic_failover_enabled = true
  multi_az_enabled           = true

  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
  auth_token                 = var.redis_auth_token

  snapshot_retention_limit = 1
}
```

### 11.5 메시징 (MSK Serverless)

```hcl
# terraform/modules/msk-startup/main.tf

# MSK Serverless (사용량 기반 과금, 최소 비용)
resource "aws_msk_serverless_cluster" "main" {
  cluster_name = "exchange-msk"

  vpc_config {
    subnet_ids         = var.private_subnet_ids
    security_group_ids = [var.msk_sg_id]
  }

  client_authentication {
    sasl {
      iam {
        enabled = true
      }
    }
  }
}

# 또는 Self-managed Kafka on EKS (더 저렴)
# Strimzi Operator 사용
```

#### 대안: EKS에서 Kafka 운영 (Strimzi)

```yaml
# kubernetes/kafka/strimzi-cluster.yaml
apiVersion: kafka.strimzi.io/v1beta2
kind: Kafka
metadata:
  name: exchange-kafka
spec:
  kafka:
    version: 3.5.1
    replicas: 3
    listeners:
      - name: plain
        port: 9092
        type: internal
        tls: false
    config:
      offsets.topic.replication.factor: 3
      transaction.state.log.replication.factor: 3
      transaction.state.log.min.isr: 2
      default.replication.factor: 3
      min.insync.replicas: 2
      log.retention.hours: 48
    storage:
      type: persistent-claim
      size: 100Gi
      class: gp3
    resources:
      requests:
        memory: 2Gi
        cpu: 500m
      limits:
        memory: 4Gi
        cpu: 2
  zookeeper:
    replicas: 3
    storage:
      type: persistent-claim
      size: 20Gi
      class: gp3
    resources:
      requests:
        memory: 1Gi
        cpu: 250m
```

### 11.6 네트워크

```hcl
# terraform/modules/vpc-startup/main.tf

resource "aws_vpc" "exchange" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true
}

# 2개 AZ
locals {
  azs = slice(data.aws_availability_zones.available.names, 0, 2)
}

# Public Subnets
resource "aws_subnet" "public" {
  count                   = 2
  vpc_id                  = aws_vpc.exchange.id
  cidr_block              = "10.0.${count.index + 1}.0/24"
  availability_zone       = local.azs[count.index]
  map_public_ip_on_launch = true
}

# Private Subnets
resource "aws_subnet" "private" {
  count             = 2
  vpc_id            = aws_vpc.exchange.id
  cidr_block        = "10.0.${count.index + 11}.0/24"
  availability_zone = local.azs[count.index]
}

# NAT Gateway (1개)
resource "aws_nat_gateway" "main" {
  allocation_id = aws_eip.nat.id
  subnet_id     = aws_subnet.public[0].id
}
```

### 11.7 비용 상세 내역

| 서비스 | 스펙 | 월 비용 (USD) |
|--------|------|---------------|
| **EKS Cluster** | 1 cluster | $73 |
| **EC2 (EKS Nodes)** | 3x c6i.xlarge | $368 |
| **EC2 (Matching Engine)** | 2x c6i.large | $122 |
| **RDS PostgreSQL** | db.t4g.large | $117 |
| **RDS Replica** | db.t4g.medium | $58 |
| **RDS Storage** | 100GB gp3 | $12 |
| **ElastiCache Redis** | 2x cache.t4g.medium | $97 |
| **MSK Serverless** | ~5K msg/sec | $200 |
| **NAT Gateway** | 1x (+ 1TB transfer) | $78 |
| **ALB** | 1x | $30 |
| **CloudFront** | 500GB | $45 |
| **S3** | 50GB | $2 |
| **CloudWatch** | Basic | $50 |
| **Data Transfer** | 1TB | $90 |
| **기타** | Route 53 등 | $20 |
| **합계** | | **~$1,362/월** |

#### MSK 대신 Strimzi Kafka 사용 시

| 변경 항목 | 비용 변화 |
|----------|----------|
| MSK Serverless 제거 | -$200 |
| EKS 노드 1개 추가 (Kafka용) | +$122 |
| EBS 볼륨 (300GB) | +$36 |
| **조정 후 합계** | **~$1,320/월** |

### 11.8 Kubernetes 배포 구성

```yaml
# kubernetes/deployments/api.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: api
spec:
  replicas: 2
  selector:
    matchLabels:
      app: api
  template:
    spec:
      containers:
      - name: api
        image: exchange-api:latest
        resources:
          requests:
            cpu: 500m
            memory: 512Mi
          limits:
            cpu: 1000m
            memory: 1Gi
        ports:
        - containerPort: 3000
---
# kubernetes/deployments/websocket.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: websocket
spec:
  replicas: 2
  selector:
    matchLabels:
      app: websocket
  template:
    spec:
      containers:
      - name: websocket
        image: exchange-websocket:latest
        resources:
          requests:
            cpu: 250m
            memory: 512Mi
          limits:
            cpu: 500m
            memory: 1Gi
---
# kubernetes/deployments/consumer.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: consumer
spec:
  replicas: 2
  selector:
    matchLabels:
      app: consumer
  template:
    spec:
      containers:
      - name: consumer
        image: exchange-consumer:latest
        resources:
          requests:
            cpu: 250m
            memory: 256Mi
          limits:
            cpu: 500m
            memory: 512Mi
```

### 11.9 모니터링 (비용 최적화)

```hcl
# CloudWatch 최소 구성
resource "aws_cloudwatch_metric_alarm" "cpu_high" {
  alarm_name          = "exchange-cpu-high"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2
  metric_name         = "CPUUtilization"
  namespace           = "AWS/EC2"
  period              = 300
  statistic           = "Average"
  threshold           = 80
  alarm_actions       = [var.sns_topic_arn]
}

resource "aws_cloudwatch_metric_alarm" "rds_connections" {
  alarm_name          = "exchange-rds-connections"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2
  metric_name         = "DatabaseConnections"
  namespace           = "AWS/RDS"
  period              = 300
  statistic           = "Average"
  threshold           = 80
  alarm_actions       = [var.sns_topic_arn]
}
```

### 11.10 제약사항 및 고려사항

| 항목 | 제약 | 대응 방안 |
|------|------|----------|
| RDS Multi-AZ 없음 | Primary 장애 시 다운타임 | Replica 수동 승격 (5-10분) |
| Redis 2노드 | 메모리 3GB 제한 | 핫 데이터만 캐싱, TTL 최적화 |
| 단일 NAT | AZ 장애 시 외부 통신 불가 | VPC Endpoint 활용 |
| 통합 노드 그룹 | 리소스 경합 가능 | 리소스 요청/제한 설정 필수 |
| t4g 인스턴스 | 버스트 크레딧 소진 가능 | 모니터링 후 c6g로 전환 |

### 11.11 체크리스트

1. [ ] VPC 및 서브넷 생성 (2 AZ)
2. [ ] NAT Gateway 1개 설정
3. [ ] EKS 클러스터 및 노드 그룹 생성
4. [ ] RDS Primary + Replica 생성
5. [ ] ElastiCache Redis 생성 (2노드)
6. [ ] MSK Serverless 또는 Strimzi 배포
7. [ ] 매칭 엔진 EC2 배포
8. [ ] ALB 및 Ingress 설정
9. [ ] 기본 CloudWatch 알람 설정

---

## 12. 코드 최적화 선행 조건

> ⚠️ **중요**: 인프라 확장 전 반드시 코드 최적화가 선행되어야 합니다.

### 12.1 현재 코드 성능 한계

현재 코드베이스 분석 결과, 소프트웨어 자체의 TPS 한계는 **5,000 ~ 10,000 TPS**입니다.

```
┌─────────────────────────────────────────────────────────────────┐
│                    현재 시스템 병목 구조                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  주문 입력 → Kafka Consumer → BlockingQueue → Matcher → Output  │
│               (100ms poll)    (단일 스레드)           (batch=1)  │
│                    ↓              ↓                      ↓       │
│                 지연 발생      순차 처리              배칭 無     │
│                                                                  │
│  ► 소프트웨어 한계: 5K-10K TPS                                   │
│  ► 인프라만 확장해도 이 한계를 넘을 수 없음                       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 12.2 인프라 vs 코드 TPS 매칭

| 인프라 등급 | 인프라 TPS | 현재 코드 TPS | 실제 TPS | 비용 효율 |
|-------------|-----------|---------------|----------|-----------|
| 스타트업 (5K) | 5,000 | 5K-10K | **5,000** | ✅ 적정 |
| 중규모 (10K) | 10,000 | 5K-10K | **5K-10K** | ⚠️ 낭비 가능 |
| 대규모 (30K+) | 30,000+ | 5K-10K | **5K-10K** | ❌ 비용 낭비 |

**결론**: 코드 최적화 없이 인프라만 확장하면 비용 낭비입니다.

### 12.3 필수 코드 최적화 항목

인프라 확장 전 아래 최적화를 완료해야 합니다:

#### Phase 1: 기본 최적화 (5K → 30K TPS)

| 파일 | 현재 값 | 변경 값 | 예상 효과 |
|------|---------|---------|-----------|
| `MatchingEngineConfig.java` | `OUTPUT_BATCH_SIZE = 1` | `100` | 처리량 10x 향상 |
| `KafkaInputStream.java` | `poll(100ms)` | `poll(10ms)` | 지연 90% 감소 |
| `KafkaOutputStream.java` | 개별 전송 | 배치 전송 | 처리량 5x 향상 |
| `TriggerThread.java` | `interval = 5000ms` | `1000ms` | 트리거 응답 5x 향상 |

```java
// 변경 예시: MatchingEngineConfig.java
public class MatchingEngineConfig {
    // Before
    public static final int OUTPUT_BATCH_SIZE = 1;

    // After
    public static final int OUTPUT_BATCH_SIZE = 100;
}

// 변경 예시: KafkaInputStream.java
// Before
ConsumerRecords<String, String> records = consumer.poll(Duration.ofMillis(100));

// After
ConsumerRecords<String, String> records = consumer.poll(Duration.ofMillis(10));
```

#### Phase 2: 아키텍처 최적화 (30K → 100K TPS)

| 항목 | 현재 | 변경 | 예상 효과 |
|------|------|------|-----------|
| Event Loop | `LinkedBlockingQueue` | LMAX Disruptor | 지연 10x 감소 |
| 메모리 관리 | GC 의존 | Object Pooling | GC 정지 최소화 |
| 매칭 엔진 | 단일 프로세스 | 심볼별 샤딩 | 수평 확장 가능 |
| DB 쓰기 | 동기 쓰기 | 비동기 배치 | 처리량 10x 향상 |

```java
// Disruptor 패턴 적용 예시
// Before: LinkedBlockingQueue
private final BlockingQueue<Command> commandQueue = new LinkedBlockingQueue<>();

// After: LMAX Disruptor
private final Disruptor<CommandEvent> disruptor = new Disruptor<>(
    CommandEvent::new,
    RING_BUFFER_SIZE,  // 65536
    DaemonThreadFactory.INSTANCE,
    ProducerType.MULTI,
    new BusySpinWaitStrategy()
);
```

#### Phase 3: JVM 최적화

```bash
# 현재 (기본 설정)
java -jar matching-engine.jar

# 최적화 후
java \
  -Xms8g -Xmx8g \
  -XX:+UseZGC \
  -XX:+ZGenerational \
  -XX:+UseLargePages \
  -XX:+AlwaysPreTouch \
  -XX:+UseNUMA \
  -Xlog:gc*:file=gc.log:time \
  -jar matching-engine.jar
```

### 12.4 최적화 단계별 TPS 예상치

```
TPS
 │
100K│                                          ●─── Phase 2 + 인프라 확장
    │                                        /
 50K│                              ●────────/
    │                            /           Phase 2 완료
 30K│                  ●────────/
    │                /           Phase 1 완료
 10K│        ●──────/
    │      /         현재 코드
  5K│  ●──/
    │
    └───┬───────┬───────┬───────┬───────┬────────→
      현재   Phase1  Phase1  Phase2  Phase2
      코드   코드    +인프라  코드    +인프라
             최적화   확장    최적화   확장
```

### 12.5 권장 실행 순서

```
┌─────────────────────────────────────────────────────────────────┐
│  Step 1: 스타트업 인프라 (5K) + 현재 코드                        │
│  ─────────────────────────────────────────────────────────────  │
│  비용: ~$1,350/월 | TPS: 5K                                     │
│  → MVP 런칭, 초기 사용자 확보                                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Step 2: Phase 1 코드 최적화 (비용: $0)                          │
│  ─────────────────────────────────────────────────────────────  │
│  • OUTPUT_BATCH_SIZE: 1 → 100                                   │
│  • Kafka poll: 100ms → 10ms                                     │
│  • JVM 튜닝 적용                                                 │
│  → 동일 인프라에서 TPS: 5K → 20K-30K                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Step 3: 중규모 인프라 (10K-30K)로 확장                          │
│  ─────────────────────────────────────────────────────────────  │
│  비용: ~$3,400/월 | TPS: 30K                                    │
│  → 사용자 증가에 대응                                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Step 4: Phase 2 코드 최적화 (Disruptor, 샤딩)                   │
│  ─────────────────────────────────────────────────────────────  │
│  • Disruptor 패턴 적용                                          │
│  • 매칭 엔진 샤딩                                                │
│  • 비동기 DB 아키텍처                                            │
│  → TPS: 30K → 100K 가능                                         │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Step 5: 대규모 인프라 (100K)로 확장                             │
│  ─────────────────────────────────────────────────────────────  │
│  비용: ~$22,000/월 | TPS: 100K+                                 │
│  → 글로벌 거래소 수준                                            │
└─────────────────────────────────────────────────────────────────┘
```

### 12.6 비용 효율 최적화 요약

| 단계 | 작업 | 비용 | TPS | 비용/TPS |
|------|------|------|-----|----------|
| 현재 | - | $1,350 | 5K | $0.27 |
| Step 2 | 코드 최적화 | $0 | 20-30K | - |
| Step 3 | 인프라 확장 | +$2,050 | 30K | $0.11 |
| Step 4 | 아키텍처 개선 | $0 | 50-100K | - |
| Step 5 | 인프라 확장 | +$18,600 | 100K | $0.22 |

**핵심**: 코드 최적화는 비용 $0으로 TPS를 4-6배 향상시킵니다.

---

## 13. 인프라 스케일업 전략

> **전제 조건**: 아래 인프라 스펙은 해당 TPS를 처리할 수 있도록 **코드 최적화가 완료된 상태**를 가정합니다.
> 섹션 12의 코드 최적화를 먼저 완료하세요.

트래픽 증가에 따른 단계적 확장 가이드:

```
┌─────────────┬─────────────┬─────────────┬─────────────┬─────────────┐
│   5K TPS    │  10K TPS    │  30K TPS    │  50K TPS    │  100K TPS   │
│  ~$1,350    │  ~$3,400    │  ~$6,500    │  ~$12,000   │  ~$22,000   │
│ 현재 코드   │ Phase1 필요 │ Phase1 필요 │ Phase2 필요 │ Phase2 필요 │
├─────────────┼─────────────┼─────────────┼─────────────┼─────────────┤
│ EKS: 3x     │ API: 2x     │ API: 4x     │ API: 6x     │ API: 6x     │
│ c6i.xlarge  │ c6i.xlarge  │ c6i.xlarge  │ c6i.xlarge  │ c6i.2xlarge │
│ (통합)      │ WS: 2x      │ WS: 3x      │ WS: 4x      │ WS: 4x      │
│             │ Consumer:2x │ Consumer:4x │ Consumer:6x │ r6i.xlarge  │
├─────────────┼─────────────┼─────────────┼─────────────┼─────────────┤
│ ME: 2x      │ ME: 2x      │ ME: 3x      │ ME: 3x      │ ME: 3x      │
│ c6i.large   │ c6i.xlarge  │ c6i.xlarge  │ c6i.2xlarge │ c6i.4xlarge │
│             │             │             │             │ (Dedicated) │
├─────────────┼─────────────┼─────────────┼─────────────┼─────────────┤
│ RDS:        │ RDS:        │ RDS:        │ RDS:        │ RDS:        │
│ t4g.large   │ r6i.xlarge  │ r6i.2xlarge │ r6i.2xlarge │ r6i.4xlarge │
│ (Single)    │ (Multi-AZ)  │ (Multi-AZ)  │ (Multi-AZ)  │ (Multi-AZ)  │
├─────────────┼─────────────┼─────────────┼─────────────┼─────────────┤
│ Redis: 2    │ Redis: 3    │ Redis: 6    │ Redis: 6    │ Redis: 9    │
│ t4g.medium  │ r6g.large   │ r6g.large   │ r6g.xlarge  │ r6g.xlarge  │
├─────────────┼─────────────┼─────────────┼─────────────┼─────────────┤
│ Kafka:      │ MSK: 3      │ MSK: 3      │ MSK: 6      │ MSK: 6      │
│ Serverless  │ m5.large    │ m5.xlarge   │ m5.xlarge   │ m5.2xlarge  │
│ or Strimzi  │             │             │             │             │
└─────────────┴─────────────┴─────────────┴─────────────┴─────────────┘
```

### 13.1 스케일업 트리거 기준

| 지표 | 5K→10K | 10K→30K | 30K→50K | 50K→100K |
|------|--------|---------|---------|----------|
| CPU 사용률 | >70% | >70% | >70% | >70% |
| 메모리 사용률 | >80% | >80% | >80% | >80% |
| API 응답시간 (p99) | >500ms | >300ms | >200ms | >100ms |
| DB 커넥션 | >80% | >80% | >80% | >80% |
| Kafka Consumer Lag | >10K | >50K | >100K | >100K |
| **코드 최적화** | Phase1 | Phase1 | Phase2 | Phase2 |

### 13.2 마이그레이션 체크리스트

#### 5K → 10K TPS 전환

**선행 조건**: Phase 1 코드 최적화 완료

1. [ ] ✅ Phase 1 코드 최적화 완료 확인
2. [ ] RDS t4g.large → r6i.xlarge 업그레이드
3. [ ] RDS Multi-AZ 활성화
4. [ ] Redis t4g.medium → r6g.large 업그레이드
5. [ ] Redis 노드 2 → 3개 확장
6. [ ] EKS 노드 그룹 분리 (API, WS, Consumer)
7. [ ] MSK Serverless → MSK Provisioned 전환
8. [ ] 매칭 엔진 c6i.large → c6i.xlarge 업그레이드

#### 10K → 30K TPS 전환

1. [ ] EKS 노드 수 2배 확장
2. [ ] RDS r6i.xlarge → r6i.2xlarge 업그레이드
3. [ ] Redis 3 → 6노드 확장
4. [ ] MSK m5.large → m5.xlarge 업그레이드
5. [ ] 매칭 엔진 2 → 3대 확장

#### 30K → 50K+ TPS 전환

**선행 조건**: Phase 2 코드 최적화 완료 (Disruptor, 샤딩)

1. [ ] ✅ Phase 2 코드 최적화 완료 확인
2. [ ] 매칭 엔진 샤딩 구현 완료
3. [ ] 비동기 DB 아키텍처 적용 완료
4. [ ] 인프라 확장 진행

### 13.3 비용 증가 곡선

```
월 비용 ($)
    │
22K │                                          ●──── Phase2 코드 + 인프라
    │                                        /
12K │                              ●────────/
    │                            /
6.5K│                  ●────────/  Phase1 코드 + 인프라
    │                /
3.4K│        ●──────/
    │      /
1.4K│  ●──/  현재 코드
    │
    └───┬───────┬───────┬───────┬───────┬───────→ TPS
       5K     10K     30K     50K    100K
```

### 13.4 트레이드오프 및 위험 관리

| 최적화 항목 | 트레이드오프 | 위험도 | 대응 방안 |
|-------------|-------------|--------|----------|
| NAT 1개 | 단일 장애점 | 중 | AZ 장애 시 10-15분 복구 시간 |
| Redis 단일샤드 | 메모리 제한 (13GB) | 저 | 캐시 TTL 최적화, 불필요 데이터 제거 |
| Dedicated 제거 | 지연 변동 가능성 | 저 | Placement Group으로 완화 |
| Read Replica 1개 | 읽기 확장성 제한 | 저 | Redis 캐시로 읽기 부하 분산 |
| DR Region 없음 | 리전 장애 시 복구 지연 | 중 | 일일 스냅샷 Cross-Region 복사 |

### 13.5 10K TPS 인프라 체크리스트

#### 구축 순서

1. [ ] **코드 최적화 (Phase 1)** ⚠️ 필수 선행
   - [ ] `OUTPUT_BATCH_SIZE`: 1 → 100
   - [ ] Kafka poll timeout: 100ms → 10ms
   - [ ] JVM 튜닝 적용

2. [ ] **네트워크 구성**
   - [ ] VPC 생성 (2 AZ)
   - [ ] 서브넷 구성
   - [ ] NAT Gateway 1개 설정
   - [ ] Security Groups 생성

3. [ ] **데이터베이스 구축**
   - [ ] RDS PostgreSQL Primary 생성
   - [ ] Read Replica 1개 생성
   - [ ] ElastiCache Redis 생성 (3노드)

4. [ ] **메시징 구축**
   - [ ] MSK Cluster 생성 (3 브로커)
   - [ ] Kafka Topics 생성

5. [ ] **컴퓨트 구축**
   - [ ] EKS Cluster 생성
   - [ ] Node Groups 생성 (API, WS, Consumer)
   - [ ] Matching Engine EC2 구성 (2대)

6. [ ] **로드 밸런싱**
   - [ ] ALB 생성
   - [ ] CloudFront 구성 (선택)

7. [ ] **모니터링**
   - [ ] CloudWatch 대시보드 구성
   - [ ] 핵심 알람 설정

---

## 참고 자료

- [AWS Well-Architected Framework](https://aws.amazon.com/architecture/well-architected/)
- [EKS Best Practices Guide](https://aws.github.io/aws-eks-best-practices/)
- [Amazon MSK Developer Guide](https://docs.aws.amazon.com/msk/latest/developerguide/)
- [RDS PostgreSQL Best Practices](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/CHAP_BestPractices.html)

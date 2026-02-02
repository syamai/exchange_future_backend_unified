#!/bin/bash
# =============================================================================
# Dev Environment Status Check Script
#
# Quick status check for all dev environment components
#
# Usage: ./scripts/dev-environment-status.sh
# =============================================================================

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

REGION="ap-northeast-2"

echo -e "\n${GREEN}=== Dev Environment Status ===${NC}\n"

# EKS Nodes
echo -e "${YELLOW}EKS Nodes:${NC}"
aws eks describe-nodegroup \
    --cluster-name exchange-dev \
    --nodegroup-name exchange-dev-spot-nodes \
    --region "$REGION" \
    --query 'nodegroup.{desiredSize:scalingConfig.desiredSize,status:status}' \
    --output table 2>/dev/null || echo "  Unable to query"

echo ""
kubectl get nodes -o wide 2>/dev/null || echo "  No nodes available"

# RDS
echo -e "\n${YELLOW}RDS:${NC}"
aws rds describe-db-instances \
    --db-instance-identifier exchange-dev-mysql \
    --region "$REGION" \
    --query 'DBInstances[0].{Status:DBInstanceStatus,Class:DBInstanceClass}' \
    --output table 2>/dev/null || echo "  Unable to query"

# Kafka EC2
echo -e "\n${YELLOW}Kafka EC2:${NC}"
aws ec2 describe-instances \
    --instance-ids i-044548ca3fe3ae1a1 \
    --region "$REGION" \
    --query 'Reservations[0].Instances[0].{State:State.Name,IP:PrivateIpAddress}' \
    --output table 2>/dev/null || echo "  Unable to query"

# NAT Instance
echo -e "\n${YELLOW}NAT Instance:${NC}"
aws ec2 describe-instances \
    --instance-ids i-06d5bb3c9d01f720d \
    --region "$REGION" \
    --query 'Reservations[0].Instances[0].{State:State.Name,IP:PrivateIpAddress}' \
    --output table 2>/dev/null || echo "  Unable to query"

# Redis
echo -e "\n${YELLOW}Redis:${NC}"
aws elasticache describe-cache-clusters \
    --cache-cluster-id exchange-dev-redis \
    --region "$REGION" \
    --query 'CacheClusters[0].{Status:CacheClusterStatus,NodeType:CacheNodeType}' \
    --output table 2>/dev/null || echo "  Not found (deleted during scale-down)"

# Kubernetes Pods
echo -e "\n${YELLOW}Backend Pods:${NC}"
kubectl get pods -n future-backend-dev 2>/dev/null || echo "  No pods"

echo -e "\n${YELLOW}Matching Engine Pods:${NC}"
kubectl get pods -n matching-engine-dev 2>/dev/null || echo "  No pods"

echo ""

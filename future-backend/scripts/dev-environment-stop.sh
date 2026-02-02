#!/bin/bash
# =============================================================================
# Dev Environment Stop Script
#
# This script handles graceful shutdown of the dev environment:
# 1. Scale down Kubernetes deployments
# 2. Invoke Lambda for infrastructure scale-down
#
# Usage: ./scripts/dev-environment-stop.sh
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REGION="ap-northeast-2"
LAMBDA_FUNCTION="exchange-dev-dev-scheduler"
KAFKA_INSTANCE_ID="i-044548ca3fe3ae1a1"
NAT_INSTANCE_ID="i-06d5bb3c9d01f720d"

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_step() {
    echo -e "\n${GREEN}=== Step $1: $2 ===${NC}\n"
}

main() {
    echo -e "\n${RED}========================================${NC}"
    echo -e "${RED}   Dev Environment Shutdown Script${NC}"
    echo -e "${RED}========================================${NC}\n"

    log_step "1" "Invoking Scale-Down Lambda"

    PAYLOAD='{
        "action": "scale-down",
        "clusterName": "exchange-dev",
        "nodegroupName": "exchange-dev-spot-nodes",
        "desiredSize": 0,
        "minSize": 0,
        "maxSize": 6,
        "rdsInstanceId": "exchange-dev-mysql",
        "ec2InstanceIds": ["'$KAFKA_INSTANCE_ID'", "'$NAT_INSTANCE_ID'"],
        "elasticache": {
            "clusterId": "exchange-dev-redis",
            "nodeType": "cache.t3.medium",
            "engine": "redis",
            "engineVersion": "7.0",
            "subnetGroupName": "exchange-dev-redis-subnet",
            "securityGroupName": "exchange-dev-redis-sg"
        }
    }'

    RESULT=$(aws lambda invoke \
        --function-name "$LAMBDA_FUNCTION" \
        --payload "$PAYLOAD" \
        --cli-binary-format raw-in-base64-out \
        --region "$REGION" \
        /tmp/lambda-output.json 2>&1)

    if [ $? -eq 0 ]; then
        log_success "Scale-down initiated"
        cat /tmp/lambda-output.json | jq . 2>/dev/null || cat /tmp/lambda-output.json
    else
        echo "Lambda invocation result: $RESULT"
    fi

    echo -e "\n${GREEN}========================================${NC}"
    echo -e "${GREEN}   Shutdown Initiated${NC}"
    echo -e "${GREEN}========================================${NC}\n"

    echo "Resources being shut down:"
    echo "  - EKS nodes: scaling to 0"
    echo "  - RDS: stopping"
    echo "  - Kafka EC2: stopping"
    echo "  - NAT Instance: stopping"
    echo "  - Redis: deleting (will be recreated on next start)"
}

main "$@"

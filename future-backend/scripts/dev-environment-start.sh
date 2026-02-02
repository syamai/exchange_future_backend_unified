#!/bin/bash
# =============================================================================
# Dev Environment Start Script
#
# This script handles the complete startup of the dev environment including:
# 1. AWS infrastructure scale-up (EKS nodes, RDS, Kafka, NAT, Redis)
# 2. Wait for all infrastructure to be ready
# 3. Reset Kafka topics and consumer groups
# 4. Initialize matching engine
# 5. Verify all components are healthy
#
# Usage: ./scripts/dev-environment-start.sh
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
CLUSTER_NAME="exchange-dev"
NODEGROUP_NAME="exchange-dev-spot-nodes"
RDS_INSTANCE_ID="exchange-dev-mysql"
KAFKA_INSTANCE_ID="i-044548ca3fe3ae1a1"
NAT_INSTANCE_ID="i-06d5bb3c9d01f720d"
REDIS_CLUSTER_ID="exchange-dev-redis"
LAMBDA_FUNCTION="exchange-dev-dev-scheduler"

# Kafka configuration
KAFKA_BROKER_IP="10.0.2.51"
KAFKA_BROKER_PORT="9092"

# Kubernetes namespaces
BACKEND_NAMESPACE="future-backend-dev"
MATCHING_ENGINE_NAMESPACE="matching-engine-dev"

# Timeouts
MAX_WAIT_SECONDS=600
POLL_INTERVAL=10

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${GREEN}=== Step $1: $2 ===${NC}\n"
}

# =============================================================================
# Step 1: Scale up AWS Infrastructure
# =============================================================================
scale_up_infrastructure() {
    log_step "1" "Scaling up AWS Infrastructure"

    # Use Lambda function for coordinated scale-up
    log_info "Invoking scale-up Lambda function..."

    PAYLOAD='{
        "action": "scale-up",
        "clusterName": "exchange-dev",
        "nodegroupName": "exchange-dev-spot-nodes",
        "desiredSize": 3,
        "minSize": 2,
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
        log_success "Lambda scale-up initiated"
        cat /tmp/lambda-output.json | jq . 2>/dev/null || cat /tmp/lambda-output.json
    else
        log_error "Lambda invocation failed: $RESULT"
        exit 1
    fi
}

# =============================================================================
# Step 2: Wait for EKS Nodes
# =============================================================================
wait_for_eks_nodes() {
    log_step "2" "Waiting for EKS Nodes to be Ready"

    local elapsed=0
    while [ $elapsed -lt $MAX_WAIT_SECONDS ]; do
        READY_NODES=$(kubectl get nodes --no-headers 2>/dev/null | grep -c " Ready " || echo "0")

        if [ "$READY_NODES" -ge 2 ]; then
            log_success "EKS nodes are ready ($READY_NODES nodes)"

            # Uncordon all nodes just in case
            kubectl get nodes --no-headers | awk '{print $1}' | xargs -I {} kubectl uncordon {} 2>/dev/null || true
            return 0
        fi

        log_info "Waiting for EKS nodes... ($READY_NODES/2 ready, ${elapsed}s elapsed)"
        sleep $POLL_INTERVAL
        elapsed=$((elapsed + POLL_INTERVAL))
    done

    log_error "Timeout waiting for EKS nodes"
    exit 1
}

# =============================================================================
# Step 3: Wait for RDS
# =============================================================================
wait_for_rds() {
    log_step "3" "Waiting for RDS to be Available"

    local elapsed=0
    while [ $elapsed -lt $MAX_WAIT_SECONDS ]; do
        STATUS=$(aws rds describe-db-instances \
            --db-instance-identifier "$RDS_INSTANCE_ID" \
            --region "$REGION" \
            --query 'DBInstances[0].DBInstanceStatus' \
            --output text 2>/dev/null || echo "unknown")

        if [ "$STATUS" = "available" ]; then
            log_success "RDS is available"
            return 0
        fi

        log_info "Waiting for RDS... (status: $STATUS, ${elapsed}s elapsed)"
        sleep $POLL_INTERVAL
        elapsed=$((elapsed + POLL_INTERVAL))
    done

    log_error "Timeout waiting for RDS"
    exit 1
}

# =============================================================================
# Step 4: Wait for Kafka (Redpanda)
# =============================================================================
wait_for_kafka() {
    log_step "4" "Waiting for Kafka to be Ready"

    local elapsed=0
    while [ $elapsed -lt $MAX_WAIT_SECONDS ]; do
        # Check EC2 instance status
        INSTANCE_STATE=$(aws ec2 describe-instances \
            --instance-ids "$KAFKA_INSTANCE_ID" \
            --region "$REGION" \
            --query 'Reservations[0].Instances[0].State.Name' \
            --output text 2>/dev/null || echo "unknown")

        if [ "$INSTANCE_STATE" = "running" ]; then
            # Try to connect to Kafka via SSM
            CMD_ID=$(aws ssm send-command \
                --instance-ids "$KAFKA_INSTANCE_ID" \
                --document-name "AWS-RunShellScript" \
                --parameters '{"commands":["docker exec redpanda rpk cluster health"]}' \
                --region "$REGION" \
                --query 'Command.CommandId' \
                --output text 2>/dev/null || echo "")

            if [ -n "$CMD_ID" ]; then
                sleep 5
                RESULT=$(aws ssm get-command-invocation \
                    --command-id "$CMD_ID" \
                    --instance-id "$KAFKA_INSTANCE_ID" \
                    --region "$REGION" \
                    --query 'Status' \
                    --output text 2>/dev/null || echo "")

                if [ "$RESULT" = "Success" ]; then
                    log_success "Kafka is ready"
                    return 0
                fi
            fi
        fi

        log_info "Waiting for Kafka... (instance: $INSTANCE_STATE, ${elapsed}s elapsed)"
        sleep $POLL_INTERVAL
        elapsed=$((elapsed + POLL_INTERVAL))
    done

    log_error "Timeout waiting for Kafka"
    exit 1
}

# =============================================================================
# Step 5: Wait for Redis
# =============================================================================
wait_for_redis() {
    log_step "5" "Waiting for Redis to be Available"

    local elapsed=0
    while [ $elapsed -lt $MAX_WAIT_SECONDS ]; do
        STATUS=$(aws elasticache describe-cache-clusters \
            --cache-cluster-id "$REDIS_CLUSTER_ID" \
            --region "$REGION" \
            --query 'CacheClusters[0].CacheClusterStatus' \
            --output text 2>/dev/null || echo "not-found")

        if [ "$STATUS" = "available" ]; then
            log_success "Redis is available"
            return 0
        fi

        log_info "Waiting for Redis... (status: $STATUS, ${elapsed}s elapsed)"
        sleep $POLL_INTERVAL
        elapsed=$((elapsed + POLL_INTERVAL))
    done

    log_error "Timeout waiting for Redis"
    exit 1
}

# =============================================================================
# Step 6: Reset Kafka Topics and Consumer Groups
# =============================================================================
reset_kafka_state() {
    log_step "6" "Resetting Kafka Topics and Consumer Groups"

    # Delete and recreate preload topic to ensure clean state
    log_info "Cleaning up Kafka state..."

    aws ssm send-command \
        --instance-ids "$KAFKA_INSTANCE_ID" \
        --document-name "AWS-RunShellScript" \
        --parameters '{"commands":[
            "docker exec redpanda rpk group delete matching_engine 2>/dev/null || true",
            "docker exec redpanda rpk topic delete matching_engine_preload 2>/dev/null || true",
            "sleep 2",
            "docker exec redpanda rpk topic create matching_engine_preload -p 1 -r 1",
            "docker exec redpanda rpk topic list | grep matching"
        ]}' \
        --region "$REGION" \
        --output text > /dev/null 2>&1

    sleep 5
    log_success "Kafka state reset complete"
}

# =============================================================================
# Step 7: Wait for Backend Pods
# =============================================================================
wait_for_backend_pods() {
    log_step "7" "Waiting for Backend Pods to be Ready"

    local elapsed=0
    while [ $elapsed -lt $MAX_WAIT_SECONDS ]; do
        READY_PODS=$(kubectl get pods -n "$BACKEND_NAMESPACE" --no-headers 2>/dev/null | grep -E "Running.*1/1" | wc -l | tr -d ' ')
        TOTAL_PODS=$(kubectl get pods -n "$BACKEND_NAMESPACE" --no-headers 2>/dev/null | wc -l | tr -d ' ')

        if [ "$READY_PODS" -ge 2 ] && [ "$TOTAL_PODS" -gt 0 ]; then
            log_success "Backend pods are ready ($READY_PODS ready)"
            return 0
        fi

        log_info "Waiting for Backend pods... ($READY_PODS/$TOTAL_PODS ready, ${elapsed}s elapsed)"
        sleep $POLL_INTERVAL
        elapsed=$((elapsed + POLL_INTERVAL))
    done

    log_error "Timeout waiting for Backend pods"
    exit 1
}

# =============================================================================
# Step 8: Wait for Matching Engine Pod
# =============================================================================
wait_for_matching_engine_pod() {
    log_step "8" "Waiting for Matching Engine Pod to be Ready"

    local elapsed=0
    while [ $elapsed -lt $MAX_WAIT_SECONDS ]; do
        # Check if legacy matching engine is running
        READY=$(kubectl get pods -n "$MATCHING_ENGINE_NAMESPACE" -l app=matching-engine-legacy --no-headers 2>/dev/null | grep -c "Running.*1/1" || echo "0")

        if [ "$READY" -ge 1 ]; then
            log_success "Matching engine pod is ready"
            return 0
        fi

        log_info "Waiting for Matching engine pod... (${elapsed}s elapsed)"
        sleep $POLL_INTERVAL
        elapsed=$((elapsed + POLL_INTERVAL))
    done

    log_error "Timeout waiting for Matching engine pod"
    exit 1
}

# =============================================================================
# Step 9: Initialize Matching Engine
# =============================================================================
initialize_matching_engine() {
    log_step "9" "Initializing Matching Engine"

    # Wait a bit for matching engine to start listening
    sleep 10

    log_info "Sending INITIALIZE_ENGINE command..."
    aws ssm send-command \
        --instance-ids "$KAFKA_INSTANCE_ID" \
        --document-name "AWS-RunShellScript" \
        --parameters '{"commands":["echo '\''{"code":"INITIALIZE_ENGINE","data":{"lastOrderId":0,"liquidationOrderIds":[],"lastPositionId":0,"lastTradeId":0,"lastMarginHistoryId":0,"lastPositionHistoryId":0,"lastFundingHistoryId":0},"timestamp":0}'\'' | docker exec -i redpanda rpk topic produce matching_engine_preload"]}' \
        --region "$REGION" \
        --output text > /dev/null 2>&1

    sleep 2

    log_info "Sending UPDATE_INSTRUMENT command..."
    aws ssm send-command \
        --instance-ids "$KAFKA_INSTANCE_ID" \
        --document-name "AWS-RunShellScript" \
        --parameters '{"commands":["echo '\''{"code":"UPDATE_INSTRUMENT","data":{"symbol":"BTCUSDT","rootSymbol":"BTC","state":"Open","type":0,"base_underlying":"BTC","quote_currency":"USDT","underlying_symbol":"BTC","settle_currency":"USDT","initMargin":"0.01","maintainMargin":"0.005","deleverageable":true,"makerFee":"0.0002","takerFee":"0.0004","settlementFee":"0","tickSize":"0.01","contractSize":"1","lotSize":"0.001","maxPrice":"1000000","maxOrderQty":"1000","multiplier":"1","contractType":"USD_M"},"timestamp":0}'\'' | docker exec -i redpanda rpk topic produce matching_engine_preload"]}' \
        --region "$REGION" \
        --output text > /dev/null 2>&1

    sleep 2

    log_info "Sending START_ENGINE command..."
    aws ssm send-command \
        --instance-ids "$KAFKA_INSTANCE_ID" \
        --document-name "AWS-RunShellScript" \
        --parameters '{"commands":["echo '\''{"code":"START_ENGINE","data":{},"timestamp":0}'\'' | docker exec -i redpanda rpk topic produce matching_engine_preload"]}' \
        --region "$REGION" \
        --output text > /dev/null 2>&1

    sleep 5
    log_success "Matching engine initialization commands sent"
}

# =============================================================================
# Step 10: Verify Health
# =============================================================================
verify_health() {
    log_step "10" "Verifying System Health"

    # Check matching engine logs
    log_info "Checking matching engine logs..."
    ME_POD=$(kubectl get pods -n "$MATCHING_ENGINE_NAMESPACE" -l app=matching-engine-legacy -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")

    if [ -n "$ME_POD" ]; then
        LOGS=$(kubectl logs "$ME_POD" -n "$MATCHING_ENGINE_NAMESPACE" --tail=20 2>/dev/null || echo "")
        if echo "$LOGS" | grep -q "Start publish"; then
            log_success "Matching engine is processing commands"
        else
            log_warn "Matching engine may not be fully initialized. Check logs:"
            echo "$LOGS" | tail -10
        fi
    fi

    # Check backend health
    log_info "Checking backend health..."
    BE_POD=$(kubectl get pods -n "$BACKEND_NAMESPACE" -l app.kubernetes.io/name=future-backend -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")

    if [ -n "$BE_POD" ]; then
        HEALTH=$(kubectl exec "$BE_POD" -n "$BACKEND_NAMESPACE" -- curl -s http://localhost:3000/v1/orderbook/BTCUSDT 2>/dev/null || echo "failed")
        if echo "$HEALTH" | grep -q "statusCode"; then
            log_success "Backend is responding"
        else
            log_warn "Backend health check inconclusive"
        fi
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    echo -e "\n${GREEN}========================================${NC}"
    echo -e "${GREEN}   Dev Environment Startup Script${NC}"
    echo -e "${GREEN}========================================${NC}\n"

    START_TIME=$(date +%s)

    scale_up_infrastructure
    wait_for_eks_nodes
    wait_for_rds
    wait_for_kafka
    wait_for_redis
    reset_kafka_state
    wait_for_backend_pods
    wait_for_matching_engine_pod
    initialize_matching_engine
    verify_health

    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))

    echo -e "\n${GREEN}========================================${NC}"
    echo -e "${GREEN}   Startup Complete! (${DURATION}s)${NC}"
    echo -e "${GREEN}========================================${NC}\n"

    echo "Next steps:"
    echo "  1. Test API: kubectl port-forward svc/dev-future-backend 8080:80 -n $BACKEND_NAMESPACE"
    echo "  2. Check logs: kubectl logs -f -l app=matching-engine-legacy -n $MATCHING_ENGINE_NAMESPACE"
    echo "  3. Run TPS test: ./scripts/run-tps-test.sh"
}

main "$@"

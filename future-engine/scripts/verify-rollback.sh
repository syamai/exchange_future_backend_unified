#!/bin/bash

# Post-Rollback Verification Script
# Usage: ./scripts/verify-rollback.sh

NAMESPACE="matching-engine"
PROMETHEUS_URL="${PROMETHEUS_URL:-http://prometheus:9090}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PASS=0
FAIL=0
WARN=0

check() {
    local name=$1
    local result=$2
    local expected=${3:-0}

    if [ "$result" -eq "$expected" ]; then
        echo -e "  ${GREEN}✓${NC} ${name}"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}✗${NC} ${name}"
        FAIL=$((FAIL + 1))
    fi
}

check_warn() {
    local name=$1
    local result=$2

    if [ "$result" -eq 0 ]; then
        echo -e "  ${GREEN}✓${NC} ${name}"
        PASS=$((PASS + 1))
    else
        echo -e "  ${YELLOW}⚠${NC} ${name}"
        WARN=$((WARN + 1))
    fi
}

echo ""
echo "============================================"
echo "  Post-Rollback Verification"
echo "============================================"
echo ""

# 1. Pod Status
echo "1. Pod Status"
PODS_READY=$(kubectl get pods -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine \
    -o jsonpath='{.items[*].status.conditions[?(@.type=="Ready")].status}' | grep -c "True" || echo "0")
PODS_TOTAL=$(kubectl get pods -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine --no-headers | wc -l | tr -d ' ')

check "All pods running (${PODS_READY}/${PODS_TOTAL})" $((PODS_TOTAL - PODS_READY)) 0

# 2. Version Consistency
echo ""
echo "2. Version Consistency"
VERSIONS=$(kubectl get statefulset -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine \
    -o jsonpath='{.items[*].spec.template.spec.containers[0].image}' | tr ' ' '\n' | sort -u | wc -l | tr -d ' ')

check "All shards same version" $((VERSIONS - 1)) 0

echo "   Current versions:"
for shard in 1 2 3; do
    VER=$(kubectl get statefulset matching-engine-shard-${shard} -n ${NAMESPACE} \
        -o jsonpath='{.spec.template.spec.containers[0].image}' 2>/dev/null || echo "N/A")
    echo "     Shard ${shard}: ${VER}"
done

# 3. Health Endpoints
echo ""
echo "3. Health Endpoints"
for shard in 1 2 3; do
    POD="matching-engine-shard-${shard}-0"
    LIVE=$(kubectl exec -n ${NAMESPACE} ${POD} -- curl -sf http://localhost:8080/health/live 2>/dev/null && echo "0" || echo "1")
    READY=$(kubectl exec -n ${NAMESPACE} ${POD} -- curl -sf http://localhost:8080/health/ready 2>/dev/null && echo "0" || echo "1")

    check "Shard ${shard} liveness" $LIVE 0
    check "Shard ${shard} readiness" $READY 0
done

# 4. Recent Errors
echo ""
echo "4. Error Logs (last 5 minutes)"
ERROR_COUNT=$(kubectl logs -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine \
    --since=5m 2>/dev/null | grep -ci "error\|exception" || echo "0")

if [ "$ERROR_COUNT" -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} No errors in logs"
    PASS=$((PASS + 1))
elif [ "$ERROR_COUNT" -lt 10 ]; then
    echo -e "  ${YELLOW}⚠${NC} ${ERROR_COUNT} errors in logs"
    WARN=$((WARN + 1))
else
    echo -e "  ${RED}✗${NC} ${ERROR_COUNT} errors in logs"
    FAIL=$((FAIL + 1))
fi

# 5. Metrics (if Prometheus available)
echo ""
echo "5. Metrics"

# Orders per second
OPS=$(curl -sf "${PROMETHEUS_URL}/api/v1/query?query=sum(rate(matching_engine_orders_processed_total[1m]))" 2>/dev/null \
    | jq -r '.data.result[0].value[1] // "0"' 2>/dev/null || echo "N/A")

if [ "$OPS" != "N/A" ]; then
    echo "   Orders/sec: ${OPS}"
    if (( $(echo "$OPS > 0" | bc -l 2>/dev/null || echo "0") )); then
        echo -e "  ${GREEN}✓${NC} Order processing active"
        PASS=$((PASS + 1))
    else
        echo -e "  ${YELLOW}⚠${NC} No orders being processed"
        WARN=$((WARN + 1))
    fi
else
    echo -e "  ${YELLOW}⚠${NC} Prometheus metrics unavailable"
    WARN=$((WARN + 1))
fi

# Active orders
ACTIVE=$(curl -sf "${PROMETHEUS_URL}/api/v1/query?query=sum(matching_engine_active_orders_total)" 2>/dev/null \
    | jq -r '.data.result[0].value[1] // "N/A"' 2>/dev/null || echo "N/A")
echo "   Active orders: ${ACTIVE}"

# 6. Kafka Consumer Lag (if available)
echo ""
echo "6. Kafka Consumer Lag"
for shard in 1 2 3; do
    LAG=$(curl -sf "${PROMETHEUS_URL}/api/v1/query?query=kafka_consumer_lag{shard=\"shard-${shard}\"}" 2>/dev/null \
        | jq -r '.data.result[0].value[1] // "N/A"' 2>/dev/null || echo "N/A")
    if [ "$LAG" != "N/A" ]; then
        if [ "$LAG" -lt 1000 ]; then
            echo -e "  ${GREEN}✓${NC} Shard ${shard} lag: ${LAG}"
            PASS=$((PASS + 1))
        else
            echo -e "  ${YELLOW}⚠${NC} Shard ${shard} lag: ${LAG} (high)"
            WARN=$((WARN + 1))
        fi
    else
        echo "   Shard ${shard} lag: N/A"
    fi
done

# 7. Recent Restarts
echo ""
echo "7. Pod Restarts"
RESTARTS=$(kubectl get pods -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine \
    -o jsonpath='{.items[*].status.containerStatuses[0].restartCount}' | tr ' ' '+' | bc 2>/dev/null || echo "0")

check_warn "Total restarts: ${RESTARTS}" $((RESTARTS > 5 ? 1 : 0))

# Summary
echo ""
echo "============================================"
echo "  Summary"
echo "============================================"
echo ""
echo -e "  ${GREEN}Passed:${NC}  ${PASS}"
echo -e "  ${YELLOW}Warnings:${NC} ${WARN}"
echo -e "  ${RED}Failed:${NC}  ${FAIL}"
echo ""

if [ $FAIL -gt 0 ]; then
    echo -e "${RED}Verification FAILED - Investigate issues above${NC}"
    exit 1
elif [ $WARN -gt 0 ]; then
    echo -e "${YELLOW}Verification passed with warnings${NC}"
    exit 0
else
    echo -e "${GREEN}Verification PASSED - All checks OK${NC}"
    exit 0
fi

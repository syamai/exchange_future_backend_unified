#!/bin/bash
set -e

# Emergency Rollback Script - Use in critical situations only
# This script performs immediate rollback without confirmations
#
# Usage: ./scripts/emergency-rollback.sh [version]
#
# Examples:
#   ./scripts/emergency-rollback.sh          # Rollback to previous version
#   ./scripts/emergency-rollback.sh v1.2.0   # Rollback to specific version

NAMESPACE="matching-engine"
TARGET_VERSION=${1:-""}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/tmp/emergency-rollback-${TIMESTAMP}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "[$(date '+%H:%M:%S')] $1"
}

alert() {
    local message=$1
    local severity=${2:-"info"}

    # Slack notification (if webhook configured)
    if [ -n "$SLACK_WEBHOOK_URL" ]; then
        curl -s -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"[${severity^^}] ME Emergency Rollback: ${message}\"}" \
            ${SLACK_WEBHOOK_URL} > /dev/null 2>&1 || true
    fi

    # PagerDuty (if configured)
    if [ -n "$PAGERDUTY_KEY" ] && [ "$severity" = "critical" ]; then
        curl -s -X POST https://events.pagerduty.com/v2/enqueue \
            -H 'Content-Type: application/json' \
            -d "{
                \"routing_key\": \"${PAGERDUTY_KEY}\",
                \"event_action\": \"trigger\",
                \"payload\": {
                    \"summary\": \"ME Emergency Rollback: ${message}\",
                    \"severity\": \"critical\",
                    \"source\": \"matching-engine\"
                }
            }" > /dev/null 2>&1 || true
    fi

    log "$message"
}

echo ""
echo -e "${RED}============================================${NC}"
echo -e "${RED}  EMERGENCY ROLLBACK${NC}"
echo -e "${RED}============================================${NC}"
echo ""

# 1. Alert
alert "🚨 Emergency rollback initiated by $(whoami)" "critical"

# 2. Save current state
mkdir -p ${BACKUP_DIR}
log "Saving current state to ${BACKUP_DIR}..."

kubectl get all -n ${NAMESPACE} -o yaml > ${BACKUP_DIR}/all-resources.yaml 2>/dev/null || true
kubectl get pods -n ${NAMESPACE} -o wide > ${BACKUP_DIR}/pods.txt 2>/dev/null || true

for shard in 1 2 3; do
    kubectl logs statefulset/matching-engine-shard-${shard} -n ${NAMESPACE} \
        --tail=1000 > ${BACKUP_DIR}/shard-${shard}-logs.txt 2>/dev/null || true
done

# 3. Execute parallel rollback
log "Rolling back all shards simultaneously..."

if [ -n "$TARGET_VERSION" ]; then
    IMAGE="exchange/matching-engine-shard:${TARGET_VERSION}"
    log "Target version: ${TARGET_VERSION}"

    kubectl set image statefulset/matching-engine-shard-1 matching-engine=${IMAGE} -n ${NAMESPACE} &
    kubectl set image statefulset/matching-engine-shard-2 matching-engine=${IMAGE} -n ${NAMESPACE} &
    kubectl set image statefulset/matching-engine-shard-3 matching-engine=${IMAGE} -n ${NAMESPACE} &
else
    log "Rolling back to previous revision..."

    kubectl rollout undo statefulset/matching-engine-shard-1 -n ${NAMESPACE} &
    kubectl rollout undo statefulset/matching-engine-shard-2 -n ${NAMESPACE} &
    kubectl rollout undo statefulset/matching-engine-shard-3 -n ${NAMESPACE} &
fi

wait
log "Rollback commands executed"

# 4. Wait for completion
log "Waiting for rollback to complete (timeout: 5 minutes)..."

FAILED=0
for shard in 1 2 3; do
    if ! kubectl rollout status statefulset/matching-engine-shard-${shard} -n ${NAMESPACE} --timeout=300s; then
        log "Shard ${shard} rollback timeout!"
        FAILED=$((FAILED + 1))
    fi
done

# 5. Health check
log "Running health checks..."
sleep 15

HEALTHY=0
for shard in 1 2 3; do
    POD="matching-engine-shard-${shard}-0"
    if kubectl exec -n ${NAMESPACE} ${POD} -- curl -sf http://localhost:8080/health/ready > /dev/null 2>&1; then
        log "✓ Shard ${shard}: HEALTHY"
        HEALTHY=$((HEALTHY + 1))
    else
        log "✗ Shard ${shard}: UNHEALTHY"
    fi
done

# 6. Final status
echo ""
echo "============================================"
echo "  Rollback Summary"
echo "============================================"
echo ""

kubectl get pods -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine -o wide

echo ""
if [ $HEALTHY -eq 3 ]; then
    echo -e "${GREEN}✓ All shards healthy - Rollback successful${NC}"
    alert "✅ Emergency rollback completed - All shards healthy" "info"
else
    echo -e "${YELLOW}⚠ Only ${HEALTHY}/3 shards healthy${NC}"
    alert "⚠️ Emergency rollback completed - ${HEALTHY}/3 shards healthy" "warning"
fi

echo ""
echo "Backup saved to: ${BACKUP_DIR}"
echo ""
echo "Next steps:"
echo "  1. Verify order processing is working"
echo "  2. Check Grafana dashboards"
echo "  3. Investigate root cause"
echo "  4. Document incident"

#!/bin/bash
set -e

# Matching Engine Rollback Script
# Usage: ./scripts/rollback.sh [options]
#
# Options:
#   -s, --shard SHARD    Specific shard to rollback (1, 2, 3, or all)
#   -v, --version VER    Target version/image tag (default: previous revision)
#   -r, --revision REV   Target revision number
#   --emergency          Skip confirmations (use with caution)
#   --dry-run            Show what would be done without executing
#   -h, --help           Show this help message

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NAMESPACE="matching-engine"

# Default values
SHARD="all"
VERSION=""
REVISION=""
EMERGENCY=false
DRY_RUN=false

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -s|--shard)
            SHARD="$2"
            shift 2
            ;;
        -v|--version)
            VERSION="$2"
            shift 2
            ;;
        -r|--revision)
            REVISION="$2"
            shift 2
            ;;
        --emergency)
            EMERGENCY=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            head -20 "$0" | tail -18
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Validate shard input
validate_shard() {
    if [[ ! "$SHARD" =~ ^(1|2|3|all)$ ]]; then
        log_error "Invalid shard: $SHARD. Must be 1, 2, 3, or all"
        exit 1
    fi
}

# Get current status
show_current_status() {
    log "Current deployment status:"
    echo ""
    kubectl get statefulset -n ${NAMESPACE} -o wide
    echo ""
    kubectl get pods -n ${NAMESPACE} -l app.kubernetes.io/name=matching-engine -o wide
    echo ""
}

# Get rollout history
show_history() {
    local shard=$1
    log "Rollout history for shard-${shard}:"
    kubectl rollout history statefulset/matching-engine-shard-${shard} -n ${NAMESPACE}
    echo ""
}

# Rollback single shard
rollback_shard() {
    local shard=$1
    local statefulset="matching-engine-shard-${shard}"

    log "Rolling back ${statefulset}..."

    if [ "$DRY_RUN" = true ]; then
        log "[DRY-RUN] Would rollback ${statefulset}"
        return
    fi

    if [ -n "$VERSION" ]; then
        # Rollback to specific version
        local image="exchange/matching-engine-shard:${VERSION}"
        log "Setting image to: ${image}"
        kubectl set image statefulset/${statefulset} matching-engine=${image} -n ${NAMESPACE}
    elif [ -n "$REVISION" ]; then
        # Rollback to specific revision
        log "Rolling back to revision: ${REVISION}"
        kubectl rollout undo statefulset/${statefulset} -n ${NAMESPACE} --to-revision=${REVISION}
    else
        # Rollback to previous revision
        log "Rolling back to previous revision"
        kubectl rollout undo statefulset/${statefulset} -n ${NAMESPACE}
    fi

    # Wait for rollout
    log "Waiting for rollout to complete..."
    kubectl rollout status statefulset/${statefulset} -n ${NAMESPACE} --timeout=300s

    log_success "Shard ${shard} rollback completed"
}

# Health check
check_health() {
    local shard=$1
    local pod="matching-engine-shard-${shard}-0"

    log "Checking health for shard-${shard}..."

    if kubectl exec -n ${NAMESPACE} ${pod} -- curl -sf http://localhost:8080/health/ready > /dev/null 2>&1; then
        log_success "Shard ${shard} is healthy"
        return 0
    else
        log_error "Shard ${shard} health check failed"
        return 1
    fi
}

# Confirm action
confirm() {
    if [ "$EMERGENCY" = true ]; then
        return 0
    fi

    local message=$1
    read -p "${message} (y/N): " -n 1 -r
    echo
    [[ $REPLY =~ ^[Yy]$ ]]
}

# Main execution
main() {
    validate_shard

    echo "============================================"
    echo "  Matching Engine Rollback"
    echo "============================================"
    echo ""
    echo "Target shard(s): ${SHARD}"
    [ -n "$VERSION" ] && echo "Target version: ${VERSION}"
    [ -n "$REVISION" ] && echo "Target revision: ${REVISION}"
    [ "$DRY_RUN" = true ] && echo "Mode: DRY RUN"
    [ "$EMERGENCY" = true ] && log_warning "EMERGENCY MODE - No confirmations"
    echo ""

    show_current_status

    if [ "$SHARD" != "all" ]; then
        show_history "$SHARD"
    fi

    if ! confirm "Proceed with rollback?"; then
        log "Rollback cancelled"
        exit 0
    fi

    # Create backup
    BACKUP_DIR="/tmp/rollback-$(date +%Y%m%d_%H%M%S)"
    mkdir -p ${BACKUP_DIR}
    kubectl get all -n ${NAMESPACE} -o yaml > ${BACKUP_DIR}/pre-rollback-state.yaml
    log "Pre-rollback state saved to: ${BACKUP_DIR}"

    # Execute rollback
    if [ "$SHARD" = "all" ]; then
        for s in 1 2 3; do
            rollback_shard $s
        done
    else
        rollback_shard "$SHARD"
    fi

    # Post-rollback health check
    echo ""
    log "Running post-rollback health checks..."
    sleep 10

    HEALTHY=0
    if [ "$SHARD" = "all" ]; then
        for s in 1 2 3; do
            check_health $s && HEALTHY=$((HEALTHY + 1))
        done
        if [ $HEALTHY -eq 3 ]; then
            log_success "All shards are healthy!"
        else
            log_warning "Only ${HEALTHY}/3 shards are healthy"
        fi
    else
        check_health "$SHARD"
    fi

    echo ""
    echo "============================================"
    echo "  Rollback Complete"
    echo "============================================"
    show_current_status
}

main

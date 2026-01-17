#!/bin/bash
set -e

# Kubernetes Deployment Script for Sharded Matching Engine
# Usage: ./scripts/deploy-k8s.sh [environment] [options]
#
# Environments:
#   dev   - Development environment (default)
#   prod  - Production environment
#
# Options:
#   --dry-run    Show what would be applied without making changes
#   --diff       Show diff of changes
#   --delete     Delete resources instead of applying
#   -h, --help   Show this help message

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
K8S_DIR="$(dirname "$SCRIPT_DIR")/k8s"

# Default values
ENVIRONMENT="dev"
DRY_RUN=""
ACTION="apply"
DIFF=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        dev|prod)
            ENVIRONMENT="$1"
            shift
            ;;
        --dry-run)
            DRY_RUN="--dry-run=client"
            shift
            ;;
        --diff)
            DIFF="true"
            shift
            ;;
        --delete)
            ACTION="delete"
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

OVERLAY_DIR="${K8S_DIR}/overlays/${ENVIRONMENT}"

if [ ! -d "$OVERLAY_DIR" ]; then
    echo "Error: Overlay directory not found: $OVERLAY_DIR"
    exit 1
fi

echo "============================================"
echo "Deploying Sharded Matching Engine"
echo "============================================"
echo "Environment: ${ENVIRONMENT}"
echo "Overlay Dir: ${OVERLAY_DIR}"
echo "Action: ${ACTION}"
[ -n "$DRY_RUN" ] && echo "Mode: DRY RUN"
echo ""

# Check kubectl and kustomize
if ! command -v kubectl &> /dev/null; then
    echo "Error: kubectl not found"
    exit 1
fi

if ! command -v kustomize &> /dev/null; then
    echo "Warning: kustomize not found, using kubectl kustomize"
    KUSTOMIZE="kubectl kustomize"
else
    KUSTOMIZE="kustomize build"
fi

# Show diff if requested
if [ "$DIFF" = "true" ]; then
    echo "[INFO] Showing diff..."
    $KUSTOMIZE "$OVERLAY_DIR" | kubectl diff -f - || true
    echo ""
    read -p "Continue with apply? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 0
    fi
fi

# Apply or delete
echo "[INFO] Running: kubectl ${ACTION} -k ${OVERLAY_DIR} ${DRY_RUN}"
kubectl ${ACTION} -k "$OVERLAY_DIR" ${DRY_RUN}

if [ "$ACTION" = "apply" ] && [ -z "$DRY_RUN" ]; then
    echo ""
    echo "[INFO] Waiting for rollout..."

    NAMESPACE="matching-engine"
    if [ "$ENVIRONMENT" = "dev" ]; then
        NAMESPACE="matching-engine-dev"
    fi

    for shard in 1 2 3; do
        PREFIX=""
        [ "$ENVIRONMENT" = "dev" ] && PREFIX="dev-"

        STATEFULSET="${PREFIX}matching-engine-shard-${shard}"
        echo "[INFO] Waiting for ${STATEFULSET}..."
        kubectl rollout status statefulset/${STATEFULSET} -n ${NAMESPACE} --timeout=300s || true
    done
fi

echo ""
echo "============================================"
echo "Deployment Complete!"
echo "============================================"
echo ""
echo "Check status:"
echo "  kubectl get pods -n ${NAMESPACE:-matching-engine}"
echo ""
echo "View logs:"
echo "  kubectl logs -f statefulset/${PREFIX:-}matching-engine-shard-1 -n ${NAMESPACE:-matching-engine}"
echo ""

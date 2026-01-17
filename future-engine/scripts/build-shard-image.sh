#!/bin/bash
set -e

# Sharded Matching Engine Docker Image Build Script
# Usage: ./scripts/build-shard-image.sh [options]
#
# Options:
#   -t, --tag TAG        Image tag (default: latest)
#   -r, --registry REG   Registry URL (default: none)
#   -p, --push           Push to registry after build
#   --no-cache           Build without cache
#   -h, --help           Show this help message

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Default values
IMAGE_NAME="exchange/matching-engine-shard"
TAG="latest"
REGISTRY=""
PUSH=false
NO_CACHE=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--tag)
            TAG="$2"
            shift 2
            ;;
        -r|--registry)
            REGISTRY="$2"
            shift 2
            ;;
        -p|--push)
            PUSH=true
            shift
            ;;
        --no-cache)
            NO_CACHE="--no-cache"
            shift
            ;;
        -h|--help)
            head -15 "$0" | tail -13
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Full image name
if [ -n "$REGISTRY" ]; then
    FULL_IMAGE="${REGISTRY}/${IMAGE_NAME}:${TAG}"
else
    FULL_IMAGE="${IMAGE_NAME}:${TAG}"
fi

echo "============================================"
echo "Building Sharded Matching Engine Docker Image"
echo "============================================"
echo "Image: ${FULL_IMAGE}"
echo "Project Dir: ${PROJECT_DIR}"
echo ""

cd "$PROJECT_DIR"

# Build the image
echo "[1/3] Building Docker image..."
docker build \
    ${NO_CACHE} \
    -f Dockerfile.shard \
    -t "${FULL_IMAGE}" \
    --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
    --build-arg GIT_COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')" \
    .

echo ""
echo "[2/3] Image built successfully!"
docker images "${IMAGE_NAME}" --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}\t{{.CreatedAt}}"

# Push if requested
if [ "$PUSH" = true ]; then
    echo ""
    echo "[3/3] Pushing to registry..."
    docker push "${FULL_IMAGE}"
    echo "Pushed: ${FULL_IMAGE}"
else
    echo ""
    echo "[3/3] Skipping push (use -p to push)"
fi

echo ""
echo "============================================"
echo "Build Complete!"
echo "============================================"
echo ""
echo "To run a single shard:"
echo "  docker run -d \\"
echo "    -e SHARD_ID=shard-1 \\"
echo "    -e SHARD_ROLE=primary \\"
echo "    -e ASSIGNED_SYMBOLS=BTCUSDT,BTCBUSD \\"
echo "    -e KAFKA_BOOTSTRAP_SERVERS=kafka:9092 \\"
echo "    -p 8080:8080 -p 9090:9090 \\"
echo "    ${FULL_IMAGE}"
echo ""
echo "To run all shards with docker-compose:"
echo "  docker-compose -f docker-compose-sharded.yml up -d"
echo ""

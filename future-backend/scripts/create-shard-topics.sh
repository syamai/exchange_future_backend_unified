#!/bin/bash
#
# Create Kafka topics for sharded matching engine
#
# Usage:
#   ./scripts/create-shard-topics.sh [OPTIONS]
#
# Options:
#   -b, --bootstrap    Kafka bootstrap servers (default: localhost:9092)
#   -p, --partitions   Number of partitions per topic (default: 3)
#   -r, --replication  Replication factor (default: 1)
#   -d, --dry-run      Print commands without executing
#   -h, --help         Show this help message
#

set -e

# Default values
BOOTSTRAP_SERVERS="localhost:9092"
PARTITIONS=3
REPLICATION_FACTOR=1
DRY_RUN=false

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -b|--bootstrap)
            BOOTSTRAP_SERVERS="$2"
            shift 2
            ;;
        -p|--partitions)
            PARTITIONS="$2"
            shift 2
            ;;
        -r|--replication)
            REPLICATION_FACTOR="$2"
            shift 2
            ;;
        -d|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            head -20 "$0" | tail -15
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Topic definitions
# Format: topic_name:partitions:replication_factor:retention_ms
SHARD_TOPICS=(
    # Shard 1 - BTC pairs (highest volume)
    "matching-engine-shard-1-input:${PARTITIONS}:${REPLICATION_FACTOR}:604800000"
    "matching-engine-shard-1-output:${PARTITIONS}:${REPLICATION_FACTOR}:604800000"
    "shard-sync-shard-1:1:${REPLICATION_FACTOR}:86400000"

    # Shard 2 - ETH pairs
    "matching-engine-shard-2-input:${PARTITIONS}:${REPLICATION_FACTOR}:604800000"
    "matching-engine-shard-2-output:${PARTITIONS}:${REPLICATION_FACTOR}:604800000"
    "shard-sync-shard-2:1:${REPLICATION_FACTOR}:86400000"

    # Shard 3 - Other symbols (default)
    "matching-engine-shard-3-input:${PARTITIONS}:${REPLICATION_FACTOR}:604800000"
    "matching-engine-shard-3-output:${PARTITIONS}:${REPLICATION_FACTOR}:604800000"
    "shard-sync-shard-3:1:${REPLICATION_FACTOR}:86400000"
)

echo "============================================"
echo "  Kafka Shard Topics Creator"
echo "============================================"
echo ""
echo "Bootstrap Servers: ${BOOTSTRAP_SERVERS}"
echo "Default Partitions: ${PARTITIONS}"
echo "Replication Factor: ${REPLICATION_FACTOR}"
echo "Dry Run: ${DRY_RUN}"
echo ""

# Check if kafka-topics command is available
if ! command -v kafka-topics &> /dev/null && ! command -v kafka-topics.sh &> /dev/null; then
    # Try to use docker
    if command -v docker &> /dev/null; then
        echo -e "${YELLOW}kafka-topics not found, using docker...${NC}"
        KAFKA_CMD="docker exec -it kafka kafka-topics.sh"
    else
        echo -e "${RED}Error: kafka-topics command not found${NC}"
        echo "Please install Kafka CLI tools or use Docker"
        exit 1
    fi
else
    KAFKA_CMD="kafka-topics"
    if command -v kafka-topics.sh &> /dev/null; then
        KAFKA_CMD="kafka-topics.sh"
    fi
fi

# Function to create a topic
create_topic() {
    local topic_config="$1"
    IFS=':' read -r topic_name partitions replication retention <<< "$topic_config"

    local cmd="${KAFKA_CMD} --bootstrap-server ${BOOTSTRAP_SERVERS} \
        --create \
        --topic ${topic_name} \
        --partitions ${partitions} \
        --replication-factor ${replication} \
        --config retention.ms=${retention} \
        --if-not-exists"

    if [ "$DRY_RUN" = true ]; then
        echo -e "${YELLOW}[DRY-RUN]${NC} Would create topic: ${topic_name}"
        echo "  Command: ${cmd}"
    else
        echo -n "Creating topic: ${topic_name}... "
        if eval "${cmd}" 2>/dev/null; then
            echo -e "${GREEN}OK${NC}"
        else
            echo -e "${RED}FAILED${NC}"
            return 1
        fi
    fi
}

# Function to list existing topics
list_topics() {
    echo ""
    echo "Listing existing topics..."
    ${KAFKA_CMD} --bootstrap-server ${BOOTSTRAP_SERVERS} --list 2>/dev/null | grep -E "^matching-engine-|^shard-sync-" || echo "(no shard topics found)"
}

# Create topics
echo "Creating shard topics..."
echo ""

failed=0
for topic_config in "${SHARD_TOPICS[@]}"; do
    if ! create_topic "$topic_config"; then
        ((failed++))
    fi
done

echo ""

if [ "$DRY_RUN" = false ]; then
    if [ $failed -eq 0 ]; then
        echo -e "${GREEN}All topics created successfully!${NC}"
        list_topics
    else
        echo -e "${RED}${failed} topic(s) failed to create${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}Dry run completed. No topics were created.${NC}"
fi

echo ""
echo "============================================"
echo "  Topic Summary"
echo "============================================"
echo ""
echo "Input Topics (Backend -> Matching Engine):"
echo "  - matching-engine-shard-1-input (BTC pairs)"
echo "  - matching-engine-shard-2-input (ETH pairs)"
echo "  - matching-engine-shard-3-input (Other symbols)"
echo ""
echo "Output Topics (Matching Engine -> Backend):"
echo "  - matching-engine-shard-1-output"
echo "  - matching-engine-shard-2-output"
echo "  - matching-engine-shard-3-output"
echo ""
echo "Sync Topics (Primary -> Standby):"
echo "  - shard-sync-shard-1"
echo "  - shard-sync-shard-2"
echo "  - shard-sync-shard-3"
echo ""

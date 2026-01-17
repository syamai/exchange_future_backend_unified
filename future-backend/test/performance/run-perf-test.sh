#!/bin/bash
#
# Performance Test Runner
#
# Runs all performance tests and generates a combined report.
#
# Usage:
#   ./test/performance/run-perf-test.sh [test-type]
#
# Test Types:
#   all        Run all tests (default)
#   router     OrderRouter benchmark only
#   kafka      Kafka stress test only
#   k6         k6 load test only
#   quick      Quick smoke test (reduced iterations)
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RESULTS_DIR="${SCRIPT_DIR}/results"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_FILE="${RESULTS_DIR}/perf-report-${TIMESTAMP}.md"

# Default values
ROUTER_ORDERS=100000
KAFKA_MESSAGES=50000
K6_DURATION="60s"
K6_VUS=20

# Parse arguments
TEST_TYPE="${1:-all}"

if [[ "$TEST_TYPE" == "quick" ]]; then
    ROUTER_ORDERS=10000
    KAFKA_MESSAGES=5000
    K6_DURATION="10s"
    K6_VUS=5
    TEST_TYPE="all"
fi

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║           Future Exchange Performance Test Suite             ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo "Test Type: $TEST_TYPE"
echo "Results Dir: $RESULTS_DIR"
echo "Report: $REPORT_FILE"
echo ""

# Create results directory
mkdir -p "$RESULTS_DIR"

# Initialize report
cat > "$REPORT_FILE" << EOF
# Performance Test Report

**Date**: $(date '+%Y-%m-%d %H:%M:%S')
**Environment**: $(uname -s) $(uname -m)
**Node Version**: $(node --version 2>/dev/null || echo "N/A")

---

EOF

# Function to run OrderRouter benchmark
run_router_benchmark() {
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  Running OrderRouter Benchmark                           ${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    local output_file="${RESULTS_DIR}/router-benchmark-${TIMESTAMP}.txt"

    cd "$SCRIPT_DIR/../.."

    if npx ts-node test/performance/order-router-benchmark.ts \
        --orders=$ROUTER_ORDERS \
        --concurrency=10 \
        --warmup=1000 2>&1 | tee "$output_file"; then
        echo -e "${GREEN}✓ OrderRouter benchmark completed${NC}"
        ROUTER_RESULT="PASS"
    else
        echo -e "${RED}✗ OrderRouter benchmark failed${NC}"
        ROUTER_RESULT="FAIL"
    fi

    # Extract key metrics for report
    local throughput=$(grep -oP 'Throughput:\s+\K[\d,]+' "$output_file" | tr -d ',')
    local p95=$(grep -oP 'P95:\s+\K[\d.]+' "$output_file")

    cat >> "$REPORT_FILE" << EOF
## OrderRouter Benchmark

| Metric | Value |
|--------|-------|
| Orders | $ROUTER_ORDERS |
| Throughput | ${throughput:-N/A} orders/sec |
| P95 Latency | ${p95:-N/A} µs |
| Status | $ROUTER_RESULT |

EOF
}

# Function to run Kafka stress test
run_kafka_stress() {
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  Running Kafka Stress Test                               ${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    # Check if Kafka is running
    if ! docker ps --format '{{.Names}}' | grep -q 'kafka'; then
        echo -e "${YELLOW}⚠ Kafka not running. Skipping Kafka stress test.${NC}"
        KAFKA_RESULT="SKIPPED"
        cat >> "$REPORT_FILE" << EOF
## Kafka Stress Test

**Status**: SKIPPED (Kafka not running)

EOF
        return
    fi

    local output_file="${RESULTS_DIR}/kafka-stress-${TIMESTAMP}.txt"

    cd "$SCRIPT_DIR/../.."

    if npx ts-node test/performance/kafka-stress-test.ts \
        --broker=localhost:19092 \
        --messages=$KAFKA_MESSAGES \
        --batch-size=1000 \
        --concurrency=3 2>&1 | tee "$output_file"; then
        echo -e "${GREEN}✓ Kafka stress test completed${NC}"
        KAFKA_RESULT="PASS"
    else
        echo -e "${RED}✗ Kafka stress test failed${NC}"
        KAFKA_RESULT="FAIL"
    fi

    # Extract metrics
    local throughput=$(grep -oP 'Throughput:\s+\K[\d,]+' "$output_file" | tr -d ',' | head -1)
    local mbps=$(grep -oP 'Data Throughput:\s+\K[\d.]+' "$output_file")

    cat >> "$REPORT_FILE" << EOF
## Kafka Stress Test

| Metric | Value |
|--------|-------|
| Messages | $KAFKA_MESSAGES |
| Throughput | ${throughput:-N/A} msg/sec |
| Data Rate | ${mbps:-N/A} MB/s |
| Status | $KAFKA_RESULT |

EOF
}

# Function to run k6 load test
run_k6_load_test() {
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  Running k6 Load Test                                    ${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    if ! command -v k6 &> /dev/null; then
        echo -e "${YELLOW}⚠ k6 not installed. Skipping k6 load test.${NC}"
        echo -e "${YELLOW}  Install: brew install k6${NC}"
        K6_RESULT="SKIPPED"
        cat >> "$REPORT_FILE" << EOF
## k6 Load Test

**Status**: SKIPPED (k6 not installed)

To install k6:
\`\`\`bash
brew install k6  # macOS
\`\`\`

EOF
        return
    fi

    # Check if backend is running
    if ! curl -s http://localhost:3000/health > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠ Backend not running. Skipping k6 load test.${NC}"
        K6_RESULT="SKIPPED"
        cat >> "$REPORT_FILE" << EOF
## k6 Load Test

**Status**: SKIPPED (Backend not running at localhost:3000)

EOF
        return
    fi

    local output_file="${RESULTS_DIR}/k6-load-${TIMESTAMP}.txt"

    cd "$SCRIPT_DIR/../.."

    if k6 run \
        --vus $K6_VUS \
        --duration $K6_DURATION \
        --out json="${RESULTS_DIR}/k6-metrics-${TIMESTAMP}.json" \
        test/performance/load-test.k6.js 2>&1 | tee "$output_file"; then
        echo -e "${GREEN}✓ k6 load test completed${NC}"
        K6_RESULT="PASS"
    else
        echo -e "${RED}✗ k6 load test failed${NC}"
        K6_RESULT="FAIL"
    fi

    cat >> "$REPORT_FILE" << EOF
## k6 Load Test

| Metric | Value |
|--------|-------|
| VUs | $K6_VUS |
| Duration | $K6_DURATION |
| Status | $K6_RESULT |

See detailed results: \`results/k6-load-${TIMESTAMP}.txt\`

EOF
}

# Run tests based on type
case "$TEST_TYPE" in
    "router")
        run_router_benchmark
        ;;
    "kafka")
        run_kafka_stress
        ;;
    "k6")
        run_k6_load_test
        ;;
    "all")
        run_router_benchmark
        echo ""
        run_kafka_stress
        echo ""
        run_k6_load_test
        ;;
    *)
        echo -e "${RED}Unknown test type: $TEST_TYPE${NC}"
        echo "Valid types: all, router, kafka, k6, quick"
        exit 1
        ;;
esac

# Summary
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Performance Test Summary                                 ${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Add summary to report
cat >> "$REPORT_FILE" << EOF
---

## Summary

| Test | Result |
|------|--------|
| OrderRouter Benchmark | ${ROUTER_RESULT:-N/A} |
| Kafka Stress Test | ${KAFKA_RESULT:-N/A} |
| k6 Load Test | ${K6_RESULT:-N/A} |

---

*Generated by run-perf-test.sh*
EOF

echo "Results saved to: $RESULTS_DIR"
echo "Report: $REPORT_FILE"
echo ""

# Determine overall result
OVERALL="PASS"
if [[ "$ROUTER_RESULT" == "FAIL" ]] || [[ "$KAFKA_RESULT" == "FAIL" ]] || [[ "$K6_RESULT" == "FAIL" ]]; then
    OVERALL="FAIL"
fi

if [[ "$OVERALL" == "PASS" ]]; then
    echo -e "${GREEN}✓ All performance tests completed successfully${NC}"
else
    echo -e "${RED}✗ Some performance tests failed${NC}"
    exit 1
fi

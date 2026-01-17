# Performance Tests

This directory contains performance and load testing scripts for the Future Exchange backend.

## Test Suite

| Script | Description | Target |
|--------|-------------|--------|
| `order-router-benchmark.ts` | Pure OrderRouter throughput test | 100K orders/sec |
| `kafka-stress-test.ts` | Kafka producer stress test | 50K msg/sec |
| `load-test.k6.js` | Full API load test with k6 | P95 < 500ms |

## Quick Start

```bash
# Run all tests (quick mode)
./test/performance/run-perf-test.sh quick

# Run full test suite
./test/performance/run-perf-test.sh all

# Run specific test
./test/performance/run-perf-test.sh router
./test/performance/run-perf-test.sh kafka
./test/performance/run-perf-test.sh k6
```

## Prerequisites

### For Kafka Stress Test
```bash
# Start local Kafka (Redpanda)
docker-compose -f docker-compose.kafka-test.yml up -d
```

### For k6 Load Test
```bash
# Install k6 (macOS)
brew install k6

# Start backend server
yarn start:dev

# In another terminal, run k6
k6 run test/performance/load-test.k6.js
```

## Individual Tests

### OrderRouter Benchmark

Tests pure routing logic without network I/O:

```bash
npx ts-node test/performance/order-router-benchmark.ts --orders=100000 --concurrency=10
```

Options:
- `--orders=N` - Number of orders (default: 100000)
- `--concurrency=N` - Worker count (default: 10)
- `--warmup=N` - Warmup iterations (default: 1000)

### Kafka Stress Test

Tests actual Kafka message throughput:

```bash
npx ts-node test/performance/kafka-stress-test.ts --broker=localhost:19092 --messages=50000
```

Options:
- `--broker=HOST:PORT` - Kafka broker (default: localhost:19092)
- `--messages=N` - Message count (default: 50000)
- `--batch-size=N` - Batch size (default: 1000)
- `--concurrency=N` - Producer count (default: 3)

### k6 Load Test

Full API load testing:

```bash
# Smoke test
k6 run --vus 5 --duration 30s test/performance/load-test.k6.js

# Load test
k6 run --vus 50 --duration 5m test/performance/load-test.k6.js

# Stress test
k6 run test/performance/load-test.k6.js  # Uses built-in scenarios
```

Environment variables:
- `BASE_URL` - API URL (default: http://localhost:3000)
- `API_TOKEN` - Auth token

## Performance Targets

| Metric | Target | Description |
|--------|--------|-------------|
| OrderRouter throughput | 100K/sec | Pure routing without Kafka |
| Kafka throughput | 50K/sec | With actual message sending |
| API P95 latency | <500ms | End-to-end order creation |
| API error rate | <1% | Failed requests |

## Results

Test results are saved to `results/` directory:
- `perf-report-{timestamp}.md` - Combined report
- `router-benchmark-{timestamp}.txt` - Router test output
- `kafka-stress-{timestamp}.txt` - Kafka test output
- `k6-load-{timestamp}.txt` - k6 test output
- `k6-metrics-{timestamp}.json` - k6 detailed metrics

## Shard Distribution

Tests verify correct symbol-to-shard routing:

| Shard | Symbols |
|-------|---------|
| shard-1 | BTCUSDT, BTCBUSD, BTCUSDC |
| shard-2 | ETHUSDT, ETHBUSD, ETHUSDC |
| shard-3 | All other symbols |

## CI/CD Integration

```yaml
# Example GitHub Actions step
- name: Run Performance Tests
  run: |
    ./test/performance/run-perf-test.sh quick
    cat test/performance/results/perf-report-*.md >> $GITHUB_STEP_SUMMARY
```

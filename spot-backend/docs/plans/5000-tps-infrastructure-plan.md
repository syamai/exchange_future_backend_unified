# Spot Backend 5,000 TPS Infrastructure Plan

**Date**: 2026-01-24
**Target**: 5,000 TPS (Transactions Per Second)
**Current**: ~200 TPS

---

## 1. Executive Summary

현재 Spot 매칭 엔진의 순수 인메모리 성능은 27,000+ TPS로 충분하지만, 실제 운영 환경에서는 DB 쓰기, Redis 통신, 네트워크 지연 등으로 인해 ~200 TPS에 그치고 있습니다.

이 문서는 5,000 TPS를 달성하기 위한 인프라 구성 및 최적화 전략을 정의합니다.

---

## 2. Performance Benchmark Results

### 2.1 Pure In-Memory Matching (2026-01-24)

| Orders | Add/sec | Match/sec |
|--------|---------|-----------|
| 1,000 | 3,146,515 | 4,472 |
| 10,000 | 5,570,865 | 5,578 |
| 50,000 | 4,271,707 | 26,936 |
| 100,000 | 3,226,537 | 53,297 |

**Estimated Pure Matching TPS: 27,424**

### 2.2 Heap vs Array OrderBook

| Orders | Heap (ms) | Array (ms) | Speedup |
|--------|-----------|------------|---------|
| 100 | 0.1 | 0.25 | 2.6x |
| 1,000 | 0.27 | 16.22 | 59x |
| 5,000 | 1.42 | 425.62 | 300x |
| 10,000 | 3.58 | 1,634.17 | **456x** |

**Heap Insert Rate: 3,521,127/sec**

### 2.3 Current Bottlenecks

| Bottleneck | Latency | Impact |
|------------|---------|--------|
| DB Write (per order) | 5-10ms | 100-200 TPS max |
| Redis Polling | 1-50ms | Added latency |
| Single Worker | - | No parallelism |
| Sync I/O | - | Thread blocking |

---

## 3. Target Architecture

```
                    ┌─────────────────┐
                    │   Load Balancer │
                    │    (AWS ALB)    │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
        ┌──────────┐  ┌──────────┐  ┌──────────┐
        │ API Pod  │  │ API Pod  │  │ API Pod  │  x5 Pods
        │ (Swoole) │  │ (Swoole) │  │ (Swoole) │
        └────┬─────┘  └────┬─────┘  └────┬─────┘
             │             │             │
             └─────────────┼─────────────┘
                           ▼
                  ┌─────────────────┐
                  │  Redis Cluster  │◄── Order Queue (Stream)
                  │   (3 shards)    │◄── InMemory OrderBook Cache
                  └────────┬────────┘
                           │
        ┌──────────────────┼──────────────────┐
        ▼                  ▼                  ▼
  ┌───────────┐     ┌───────────┐     ┌───────────┐
  │ Matching  │     │ Matching  │     │ Matching  │  x10 Workers
  │ Worker    │     │ Worker    │     │ Worker    │
  │ (Swoole)  │     │ (Swoole)  │     │ (Swoole)  │
  └─────┬─────┘     └─────┬─────┘     └─────┬─────┘
        │                 │                 │
        └─────────────────┼─────────────────┘
                          ▼
                 ┌─────────────────┐
                 │   RDS MySQL     │◄── Async Batch Write
                 │   (Multi-AZ)    │
                 └─────────────────┘
```

---

## 4. Infrastructure Specifications

### 4.1 Compute Resources

| Component | Instance Type | vCPU | Memory | Count | Purpose |
|-----------|---------------|------|--------|-------|---------|
| API Server | c6i.xlarge | 4 | 8 GB | 5 | Swoole HTTP Server |
| Matching Worker | c6i.large | 2 | 4 GB | 10 | Symbol-based matching |
| EKS Node | c6i.2xlarge | 8 | 16 GB | 4 | Pod hosting |

### 4.2 Data Layer

| Component | Instance Type | Memory | Count | Purpose |
|-----------|---------------|--------|-------|---------|
| Redis | cache.r6g.large | 6.5 GB | 3 shards | Stream + Cache |
| RDS MySQL | db.r6g.xlarge | 32 GB | 1 (Multi-AZ) | Persistent storage |

### 4.3 Network

| Component | Specification | Purpose |
|-----------|---------------|---------|
| VPC | 10.0.0.0/16 | Isolated network |
| ALB | Application LB | HTTPS termination |
| NAT Gateway | Single AZ (dev) | Outbound traffic |

---

## 5. Optimization Strategies

### 5.1 Swoole Coroutines (Critical)

**Current: Synchronous Blocking**
```php
// Each DB call blocks the entire process
$order = Order::find($id);  // 1ms blocking
$order->save();             // 5ms blocking
// Total: 6ms per order = 166 TPS max
```

**Target: Async Non-blocking**
```php
// Coroutines allow concurrent I/O
go(function() use ($id) {
    $order = Order::find($id);  // Coroutine switches, 0ms blocking
    $order->save();             // Coroutine switches, 0ms blocking
});
// Single worker handles 1,000+ concurrent requests
```

**Expected Impact**: 10-50x throughput improvement

### 5.2 Redis Stream (Push vs Poll)

**Current: Polling-based**
```php
while (true) {
    $order = $redis->zpopmin('queue');  // Poll every 1-50ms
    if ($order) {
        $this->processOrder($order);
    }
    usleep($this->sleepTimeUs);  // Wasted cycles
}
```

**Target: Stream-based Push**
```php
while (true) {
    // Block until data arrives - no polling overhead
    $messages = $redis->xread(['orders' => $lastId], 'BLOCK', 0);
    foreach ($messages as $message) {
        $this->processOrder($message);
    }
}
```

**Expected Impact**:
- Latency: 50ms → <1ms
- CPU Usage: -80%

### 5.3 Database Batch Writes

**Current: Per-order writes**
```php
// Each match triggers immediate DB write
$order1->save();  // 5ms
$order2->save();  // 5ms
Trade::create($trade);  // 5ms
// Total: 15ms per match
```

**Target: Buffered batch writes**
```php
class WriteBuffer {
    private array $orders = [];
    private array $trades = [];

    public function add($order, $trade) {
        $this->orders[] = $order;
        $this->trades[] = $trade;

        if (count($this->orders) >= 100 || $this->timeout()) {
            $this->flush();
        }
    }

    public function flush() {
        DB::transaction(function() {
            Order::upsert($this->orders, ['id'], ['status', 'executed_quantity']);
            Trade::insert($this->trades);
        });
        // 100 orders in single transaction: 20ms
        // Per-order cost: 0.2ms (25x improvement)
    }
}
```

**Expected Impact**: DB load -90%, TPS +25x

### 5.4 Symbol-based Sharding

```
Trading Pairs Distribution:
├── Shard 1: BTC/USD, BTC/USDT     (High volume)
├── Shard 2: ETH/USD, ETH/USDT     (High volume)
├── Shard 3: SOL/USD, DOGE/USD     (Medium volume)
├── Shard 4: Other pairs           (Low volume)
└── Workers per shard: 2-3
```

**Routing Logic**:
```php
class SymbolRouter {
    private const SHARD_MAP = [
        'btc' => 1,
        'eth' => 2,
        'sol' => 3,
        'doge' => 3,
    ];

    public function getShardId(string $symbol): int {
        $coin = strtolower(explode('/', $symbol)[0]);
        return self::SHARD_MAP[$coin] ?? 4;  // Default shard
    }
}
```

**Expected Impact**: Linear scaling with worker count

---

## 6. Implementation Phases

### Phase 1: Quick Wins (Completed)

| Task | Status | Impact |
|------|--------|--------|
| Dynamic Polling (1-50ms) | ✅ Done | -83% latency |
| Batch Matching (20/cycle) | ✅ Done | 20x throughput |
| ZPOPMIN (atomic pop) | ✅ Done | -50% Redis RTT |
| Heap OrderBook | ✅ Done | 456x faster insert |
| DB Indexes | ✅ Done | Faster queries |

**Result**: ~800 TPS achievable

### Phase 2: Parallelization (1 week)

| Task | Effort | Impact |
|------|--------|--------|
| Increase workers to 10 | Low | 3x parallelism |
| Enable Redis Stream | Medium | Push-based |
| Symbol-based routing | Medium | Parallel matching |

**Target**: 1,500 TPS

### Phase 3: Caching Layer (1 week)

| Task | Effort | Impact |
|------|--------|--------|
| InMemory OrderBook in Redis | Medium | Fast reads |
| DB write buffering | Medium | -90% DB load |
| Read replicas for queries | Low | Read scaling |

**Target**: 3,000 TPS

### Phase 4: Swoole Migration (2 weeks)

| Task | Effort | Impact |
|------|--------|--------|
| Swoole HTTP Server | High | Async I/O |
| Coroutine DB driver | High | Non-blocking |
| Connection pooling | Medium | Resource efficiency |
| Load testing | Medium | Validation |

**Target**: 5,000+ TPS

---

## 7. Cost Estimation

### 7.1 Production Environment (5,000 TPS)

| Resource | Specification | Monthly Cost |
|----------|---------------|--------------|
| EKS Control Plane | - | $72 |
| EC2 (c6i.2xlarge x4) | On-Demand | $880 |
| EC2 (Spot Discount 70%) | - | -$616 |
| RDS (db.r6g.xlarge) | Multi-AZ | $450 |
| ElastiCache (r6g.large x3) | Cluster | $390 |
| ALB | - | $30 |
| Data Transfer | ~500GB | $50 |
| **Total (On-Demand)** | | **$1,872** |
| **Total (Spot)** | | **$1,256** |

### 7.2 Cost Optimization Options

| Option | Savings | Trade-off |
|--------|---------|-----------|
| Spot Instances (70%) | -$616/mo | Interruption risk |
| Reserved Instances (1yr) | -30% | Commitment |
| Graviton (ARM) | -20% | ARM compatibility |
| Single-AZ Redis | -50% | No HA |

### 7.3 Cost per Transaction

| TPS | Monthly Transactions | Cost/Million |
|-----|---------------------|--------------|
| 5,000 | 12.96B | $0.10 |
| 2,000 | 5.18B | $0.24 |
| 1,000 | 2.59B | $0.48 |

---

## 8. Monitoring & Observability

### 8.1 Key Metrics

| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| Order Latency P99 | <10ms | >50ms |
| Match Latency P99 | <5ms | >20ms |
| TPS | 5,000 | <4,000 |
| Error Rate | <0.01% | >0.1% |
| Queue Depth | <100 | >1,000 |

### 8.2 Dashboards

```
Grafana Dashboards:
├── Matching Engine Overview
│   ├── TPS (real-time)
│   ├── Latency percentiles
│   └── Error rates
├── Infrastructure
│   ├── CPU/Memory usage
│   ├── Redis operations
│   └── DB connections
└── Business Metrics
    ├── Orders per symbol
    ├── Trade volume
    └── User activity
```

### 8.3 Alerting

| Alert | Condition | Action |
|-------|-----------|--------|
| High Latency | P99 > 50ms for 5min | Scale workers |
| Queue Backup | Depth > 1,000 | Investigate bottleneck |
| Error Spike | Rate > 1% | Page on-call |
| Worker Down | Pod restart | Auto-heal (K8s) |

---

## 9. Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Swoole compatibility issues | Medium | High | Thorough testing, rollback plan |
| Redis cluster failure | Low | Critical | Multi-AZ, automatic failover |
| DB connection exhaustion | Medium | High | Connection pooling, limits |
| Traffic spike (10x) | Low | Medium | Auto-scaling, circuit breaker |

---

## 10. Success Criteria

### 10.1 Performance

- [ ] Sustained 5,000 TPS for 1 hour
- [ ] P99 latency < 10ms
- [ ] Zero order loss
- [ ] Error rate < 0.01%

### 10.2 Reliability

- [ ] 99.9% uptime
- [ ] Automatic failover < 30s
- [ ] Zero data inconsistency

### 10.3 Scalability

- [ ] Linear scaling to 10,000 TPS with 2x resources
- [ ] No code changes for horizontal scaling

---

## 11. Timeline

```
Week 1: Phase 2 (Parallelization)
├── Day 1-2: Worker scaling (3→10)
├── Day 3-4: Redis Stream integration
└── Day 5: Testing & validation

Week 2: Phase 3 (Caching)
├── Day 1-3: InMemory OrderBook cache
├── Day 4-5: DB write buffering
└── Day 5: Performance testing

Week 3-4: Phase 4 (Swoole)
├── Day 1-3: Swoole HTTP server
├── Day 4-6: Coroutine DB driver
├── Day 7-8: Integration testing
├── Day 9-10: Load testing
└── Day 11-14: Staging deployment & validation
```

---

## 12. Appendix

### A. Benchmark Commands

```bash
# InMemory OrderBook benchmark
php benchmarks/orderbook-benchmark-fast.php

# Heap vs Array comparison
php benchmarks/heap-orderbook-benchmark.php

# Redis operations benchmark
php benchmarks/redis-benchmark.php

# Polling strategy comparison
php benchmarks/polling-benchmark.php
```

### B. Configuration Files

```yaml
# k8s/overlays/prod/matching-worker.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: matching-worker
spec:
  replicas: 10
  template:
    spec:
      containers:
      - name: worker
        image: spot-backend:latest
        command: ["php", "artisan", "matching-engine:swoole"]
        resources:
          requests:
            cpu: "1000m"
            memory: "2Gi"
          limits:
            cpu: "2000m"
            memory: "4Gi"
```

### C. References

- [Swoole Documentation](https://www.swoole.co.uk/docs/)
- [Redis Streams](https://redis.io/docs/data-types/streams/)
- [AWS EKS Best Practices](https://aws.github.io/aws-eks-best-practices/)

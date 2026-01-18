# Spot Backend PHP Matching Engine Performance Optimization Plan

## Overview

This document outlines the performance optimization strategy for the spot-backend PHP matching engine, including infrastructure requirements and cost analysis.

**Current Status**: 100-200 TPS
**Target**: 2,000-5,000+ TPS
**Date**: 2025-01-18

---

## Table of Contents

1. [Current Architecture Analysis](#1-current-architecture-analysis)
2. [Bottleneck Identification](#2-bottleneck-identification)
3. [Optimization Phases](#3-optimization-phases)
4. [Infrastructure Requirements](#4-infrastructure-requirements)
5. [Cost Analysis](#5-cost-analysis)
6. [Spot vs Future Cost Comparison](#6-spot-vs-future-cost-comparison)
7. [Implementation Roadmap](#7-implementation-roadmap)

---

## 1. Current Architecture Analysis

### System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                    Current Architecture                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Client ──▶ Nginx ──▶ PHP-FPM (Laravel)                        │
│                           │                                     │
│                           ▼                                     │
│                      RabbitMQ Queue                             │
│                           │                                     │
│              ┌────────────┼────────────┐                       │
│              ▼            ▼            ▼                       │
│         Worker #1    Worker #2    Worker #3                    │
│              │            │            │                       │
│              └────────────┼────────────┘                       │
│                           ▼                                     │
│            Node.js Order Processor (app.js)                    │
│                           │                                     │
│                           ▼                                     │
│             PHP ProcessOrder Job (per symbol)                  │
│                           │                                     │
│              ┌────────────┼────────────┐                       │
│              ▼            ▼            ▼                       │
│           Redis       MySQL SP     WebSocket                   │
│        (Orderbook)   (Execute)   (Laravel Echo)                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Two Operating Modes

| Mode | Environment Variable | Description |
|------|---------------------|-------------|
| PHP Mode | `MATCHING_JAVA_ALLOW=false` | Built-in PHP matching engine |
| Java Mode | `MATCHING_JAVA_ALLOW=true` | External Java ME via Kafka |

### Current Specifications

| Component | Specification |
|-----------|---------------|
| API Server | 1x (1 CPU / 2GB) |
| Worker Server | 1x PM2 (~20 processes) |
| MySQL | 1x Master + 1x Slave |
| Redis | 1x Single Node |
| RabbitMQ | 1x Single Node |
| Echo Server | 1x WebSocket |

---

## 2. Bottleneck Identification

### Critical Bottlenecks

| Bottleneck | Location | Impact |
|------------|----------|--------|
| 200ms Polling | `ProcessOrder.php:130` | Max 5 matches/sec |
| Fixed Workers | `cron.yml` | Queue backlog |
| Single Thread | PHP per symbol | CPU waste |
| Sync WebSocket | `OrderService.php` | Blocking I/O |
| Serial DB Transactions | Stored Procedures | Lock contention |

### Code Analysis

```php
// ProcessOrder.php - Current bottleneck
while (true) {
    // ... matching logic ...
    usleep(200000); // 200ms = MAX 5 matches/second
}
```

### Throughput Limits by Component

| Component | Current Limit | Theoretical Max |
|-----------|---------------|-----------------|
| API Layer | ~500 req/s | 2000+ req/s |
| RabbitMQ Processing | ~300 msg/s | 1000+ msg/s |
| Redis Matching | 50-100 TPS/symbol | 500+ TPS/symbol |
| MySQL | 200-500 TPS | 2000+ TPS |
| WebSocket | ~1000 events/s | 5000+ events/s |

---

## 3. Optimization Phases

### Phase 1: Immediate Code Changes (0 Cost)

**Timeline**: 1-2 days
**Expected TPS**: 500-800 (4-5x improvement)

#### 1.1 Dynamic Polling Interval

```php
// Before
usleep(200000); // Fixed 200ms

// After - Dynamic polling
private $sleepTime = 1000; // Start: 1ms
private $maxSleepTime = 50000; // Max: 50ms
private $minSleepTime = 1000; // Min: 1ms

if ($this->lastMatchingSuccess) {
    $this->sleepTime = $this->minSleepTime;
} else {
    $this->sleepTime = min($this->sleepTime * 2, $this->maxSleepTime);
}
usleep($this->sleepTime);
```

#### 1.2 Batch Matching

```php
// Process multiple matches per loop iteration
for ($i = 0; $i < 20; $i++) {
    if ($this->matchMarketBuyOrder() ||
        $this->matchMarketSellOrder() ||
        $this->matchLimitOrders()) {
        $matched = true;
        $this->countMatch++;
    } else {
        break;
    }
}
```

#### 1.3 Async WebSocket Events

```php
// Before - Synchronous
$this->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_MATCHED, [$order]);

// After - Async via Redis
Redis::publish('orderbook:' . $currency . $coin, json_encode($data));
```

#### 1.4 PHP OPcache JIT Optimization

```ini
; php.ini
opcache.enable=1
opcache.jit_buffer_size=100M
opcache.jit=1255
```

---

### Phase 2: Code Refactoring + Worker Scaling

**Timeline**: 1 week
**Expected TPS**: 800-1,500 (6-10x improvement)

#### Changes

| Item | Before | After |
|------|--------|-------|
| RabbitMQ Workers | 3 | 10 |
| Worker Instance | t3.large x1 | t3.xlarge x1 |
| DB Transaction | Per-match | Batched |

#### DB Transaction Optimization

```php
public function matchOrdersBatch(array $matchPairs): void
{
    DB::connection('master')->beginTransaction();
    try {
        foreach ($matchPairs as $pair) {
            $this->executeMatch($pair['buy'], $pair['sell'], $pair['isBuyerMaker']);
        }
        DB::connection('master')->commit();

        // Events dispatched after transaction
        foreach ($matchPairs as $pair) {
            $this->queueEvents($pair);
        }
    } catch (Exception $e) {
        DB::connection('master')->rollBack();
        throw $e;
    }
}
```

---

### Phase 3: Redis Stream + In-Memory Orderbook

**Timeline**: 2-3 weeks
**Expected TPS**: 2,000-3,000 (15-20x improvement)

#### Architecture Change

```
┌─────────────────────────────────────────────────────────────────┐
│                    Phase 3 Architecture                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                 Redis Cluster (3 shards)                 │   │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │   │
│  │  │   Shard 1   │  │   Shard 2   │  │   Shard 3   │      │   │
│  │  │   Stream    │  │   Stream    │  │   Stream    │      │   │
│  │  └─────────────┘  └─────────────┘  └─────────────┘      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│              ┌───────────────┼───────────────┐                 │
│              ▼               ▼               ▼                 │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐  │
│  │  ME Server #1   │ │  ME Server #2   │ │  ME Server #3   │  │
│  │  (BTC/ETH)      │ │  (Altcoins-1)   │ │  (Altcoins-2)   │  │
│  │  In-Memory OB   │ │  In-Memory OB   │ │  In-Memory OB   │  │
│  └─────────────────┘ └─────────────────┘ └─────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### Redis Stream Consumer

```php
class StreamMatchingEngine
{
    public function consume()
    {
        $groupName = 'matching-engine';
        $consumerName = 'consumer-' . getmypid();

        while (true) {
            // Blocking read - no polling needed
            $messages = Redis::xreadgroup(
                $groupName,
                $consumerName,
                ['orders:btcusdt' => '>'],
                1,    // count
                1000  // block ms
            );

            foreach ($messages as $message) {
                $this->processOrder($message);
                Redis::xack('orders:btcusdt', $groupName, $message['id']);
            }
        }
    }
}
```

#### In-Memory Orderbook

```php
class InMemoryOrderBook
{
    private SplPriorityQueue $buyOrders;   // Max heap
    private SplPriorityQueue $sellOrders;  // Min heap
    private array $orderIndex = [];

    public function getMatchablePair(): ?array
    {
        if ($this->buyOrders->isEmpty() || $this->sellOrders->isEmpty()) {
            return null;
        }

        $topBuy = $this->buyOrders->top();
        $topSell = $this->sellOrders->top();

        if (bccomp($topBuy->price, $topSell->price, 8) >= 0) {
            $this->buyOrders->extract();
            $this->sellOrders->extract();
            return ['buy' => $topBuy, 'sell' => $topSell];
        }

        return null;
    }
}
```

---

### Phase 4: Swoole/High-Performance Architecture

**Timeline**: 1+ month
**Expected TPS**: 5,000-10,000 (30x+ improvement)

#### Swoole-based Matching Engine

```php
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class SwooleMatchingEngine
{
    private Channel $orderChannel;
    private InMemoryOrderBook $orderBook;

    public function start()
    {
        $this->orderChannel = new Channel(10000);
        $this->orderBook = new InMemoryOrderBook();

        // Order receiver coroutine
        Coroutine::create(function () {
            $redis = new Swoole\Coroutine\Redis();
            $redis->connect('127.0.0.1', 6379);

            while (true) {
                $order = $redis->brpop('orders:btcusdt', 1);
                if ($order) {
                    $this->orderChannel->push(json_decode($order[1], true));
                }
            }
        });

        // Matching coroutines (multiple)
        for ($i = 0; $i < 4; $i++) {
            Coroutine::create(function () {
                while (true) {
                    $order = $this->orderChannel->pop();
                    $this->orderBook->addOrder($order);

                    while ($pair = $this->orderBook->getMatchablePair()) {
                        $this->executeMatch($pair);
                    }
                }
            });
        }
    }
}
```

---

## 4. Infrastructure Requirements

### Phase 1 & 2: Minimal Infrastructure

```yaml
Infrastructure:
  API:
    type: t3.large
    count: 2
    autoscaling: 1-4

  Worker:
    type: t3.xlarge
    count: 1
    pm2_processes: 30

  Database:
    type: RDS db.r5.large
    master: 1
    replica: 1

  Cache:
    type: ElastiCache cache.r6g.medium
    count: 1

  Queue:
    type: RabbitMQ (t3.small)
    count: 1
```

### Phase 3: Medium Scale

```yaml
Infrastructure:
  API:
    type: c5.xlarge
    count: 3
    autoscaling: 2-6

  MatchingEngine:
    type: c5.xlarge
    count: 2
    dedicated: true

  Database:
    type: Aurora MySQL
    writer: db.r5.large x1
    reader: db.r5.large x2

  Cache:
    type: ElastiCache Redis Cluster
    node: cache.r6g.large
    shards: 3

  Queue:
    type: Redis Stream (shared with cache)
```

### Phase 4: Large Scale

```yaml
Infrastructure:
  API:
    type: c5.2xlarge (Swoole)
    count: 3
    autoscaling: 3-10

  MatchingEngine:
    type: c5.2xlarge (Swoole)
    count: 3
    symbol_sharding: true

  Database:
    type: Aurora MySQL
    writer: db.r5.xlarge x1
    reader: db.r5.large x3

  Cache:
    type: ElastiCache Redis Cluster
    node: cache.r6g.xlarge
    shards: 3
    replicas: 1

  WebSocket:
    type: c5.xlarge
    count: 2

  Monitoring:
    - CloudWatch
    - Prometheus/Grafana
```

---

## 5. Cost Analysis

### Monthly Cost by Phase (AWS Seoul Region)

| Phase | Components | Monthly Cost | TPS | $/TPS |
|-------|------------|--------------|-----|-------|
| Current | Baseline | ~$200 | 100-200 | $1.00-2.00 |
| Phase 1 | Code only | ~$200 | 500-800 | $0.25-0.40 |
| Phase 2 | +Worker scale | ~$275 | 800-1,500 | $0.18-0.34 |
| Phase 3 | +Redis Cluster, ME servers | ~$1,436 | 2,000-3,000 | $0.48-0.72 |
| Phase 4 | +Swoole, Aurora | ~$2,922 | 5,000-10,000 | $0.29-0.58 |

### Phase 3 Cost Breakdown

| Resource | Type | Unit Price | Qty | Monthly |
|----------|------|------------|-----|---------|
| ElastiCache | cache.r6g.large | $146 | 3 | $438 |
| Matching EC2 | c5.xlarge | $124 | 1 | $124 |
| Matching EC2 | c5.large | $62 | 2 | $124 |
| RDS MySQL | db.r5.large | $175 | 2 | $350 |
| API EC2 (HPA) | t3.large | $60 | 3 | $180 |
| Worker EC2 | t3.xlarge | $120 | 1 | $120 |
| RabbitMQ | t3.medium | $30 | 1 | $30 |
| ALB | - | $20 | 1 | $20 |
| Data Transfer | - | - | - | ~$50 |
| **Total** | | | | **~$1,436** |

### Phase 4 Cost Breakdown

| Resource | Type | Unit Price | Qty | Monthly |
|----------|------|------------|-----|---------|
| API EC2 | c5.xlarge | $124 | 3 | $372 |
| Matching EC2 | c5.2xlarge | $248 | 2 | $496 |
| Matching EC2 | c5.xlarge | $124 | 1 | $124 |
| ElastiCache | cache.r6g.xlarge | $292 | 3 | $876 |
| Aurora Writer | db.r5.xlarge | $350 | 1 | $350 |
| Aurora Reader | db.r5.large | $175 | 2 | $350 |
| Aurora Storage | $0.10/GB | - | 100GB | $10 |
| Aurora I/O | $0.20/1M | - | ~500M | $100 |
| WebSocket EC2 | c5.large | $62 | 2 | $124 |
| ALB | - | $20 | 1 | $20 |
| Data Transfer | - | - | - | ~$100 |
| **Total** | | | | **~$2,922** |

### Reserved Instance Savings

| Phase | On-Demand | 1yr RI (-30%) | 3yr RI (-50%) |
|-------|-----------|---------------|---------------|
| Phase 3 | $1,436 | ~$1,005 | ~$718 |
| Phase 4 | $2,922 | ~$2,045 | ~$1,461 |

---

## 6. Spot vs Future Cost Comparison

### Why Spot is Cheaper than Future

| Aspect | Spot | Future | Reason |
|--------|------|--------|--------|
| **Matching Engine** | PHP built-in (optional) | Java required | Future needs dedicated JVM servers |
| **Sharding** | None | 3 shards required | 3x Kafka topics, 3x ME servers |
| **Kafka** | Optional | Required | Future must have Kafka cluster |
| **Real-time Calc** | Price only | Mark price, funding, liquidation | Additional compute resources |
| **Position Mgmt** | None | Long/Short positions | 2-3x DB load |
| **Liquidation** | None | Liquidation + ADL | Dedicated workers needed |
| **DB Complexity** | Simple (balance) | Complex (margin, collateral) | Larger DB instances |

### Cost Comparison Table

| Target TPS | Spot Monthly | Future Monthly | Difference |
|------------|--------------|----------------|------------|
| 1,000 | ~$275 | ~$500 | +82% |
| 2,000 | ~$1,436 | ~$2,200 | +53% |
| 5,000 | ~$2,922 | ~$4,500 | +54% |

### Architecture Complexity

```
Spot Architecture (Simple):
  API → Queue → PHP Matching → DB → WebSocket

Future Architecture (Complex):
  API → Kafka → Java ME (3 shards) → Kafka → Worker → DB → WebSocket
         ↓
    Liquidation Worker
         ↓
    Funding Worker
         ↓
    ADL Worker
```

---

## 7. Implementation Roadmap

### Recommended Execution Order

```
Week 1: Phase 1 (Immediate)
├── Day 1-2: Dynamic polling + batch matching
├── Day 3: Async WebSocket events
├── Day 4: PHP OPcache optimization
└── Day 5: Testing & monitoring setup

Week 2: Phase 2 (Worker Scaling)
├── Day 1-2: RabbitMQ worker scale (3→10)
├── Day 3-4: DB transaction batching
├── Day 5: Infrastructure upgrade (t3.xlarge)
└── Weekend: Load testing

Week 3-4: Phase 3 (Architecture)
├── Week 3: Redis Stream implementation
├── Week 3: In-memory orderbook
├── Week 4: Dedicated ME servers
└── Week 4: Redis cluster setup

Month 2+: Phase 4 (High Performance)
├── Swoole/RoadRunner migration
├── Aurora MySQL migration
├── Symbol-based sharding
└── Full production deployment
```

### Success Metrics

| Phase | TPS Target | Latency Target | Success Criteria |
|-------|------------|----------------|------------------|
| Phase 1 | 500+ | <100ms | 4x improvement |
| Phase 2 | 1,000+ | <80ms | Stable under load |
| Phase 3 | 2,500+ | <50ms | No queue backlog |
| Phase 4 | 5,000+ | <30ms | Production ready |

### Rollback Plan

| Phase | Rollback Method | Time |
|-------|-----------------|------|
| Phase 1 | Git revert | 5 min |
| Phase 2 | Scale down workers | 10 min |
| Phase 3 | Switch to legacy Redis | 30 min |
| Phase 4 | Revert to PHP-FPM | 1 hour |

---

## Appendix

### A. Environment Variables

```bash
# Phase 1 optimizations
OP_CHECKING_INTERVAL=3000        # Reduce from 3000 to dynamic
MATCHING_BATCH_SIZE=20           # New: batch size
SOCKET_ASYNC_ENABLED=true        # New: async events

# Phase 3
REDIS_STREAM_ENABLED=true        # New: use Redis Stream
INMEMORY_ORDERBOOK=true          # New: in-memory orderbook
ME_DEDICATED_SERVERS=true        # New: dedicated ME

# Phase 4
SWOOLE_ENABLED=true              # New: Swoole runtime
SWOOLE_WORKERS=4                 # New: coroutine workers
```

### B. Monitoring Checklist

- [ ] TPS dashboard (orders/sec)
- [ ] Latency percentiles (p50, p95, p99)
- [ ] Queue depth monitoring
- [ ] CPU/Memory utilization
- [ ] DB connection pool status
- [ ] Redis memory usage
- [ ] Error rate tracking

### C. Load Testing Commands

```bash
# Phase 1 verification
wrk -t12 -c400 -d30s http://api/orders

# Phase 3 verification
wrk -t24 -c1000 -d60s http://api/orders

# Phase 4 verification
wrk -t48 -c2000 -d120s http://api/orders
```

---

## Implementation Status

### Completed Phases

| Phase | Status | Files Changed |
|-------|--------|---------------|
| **Phase 1** | ✅ Completed | `ProcessOrder.php`, `Dockerfile` |
| **Phase 2** | ✅ Completed | `cron.yml`, `database.php`, `OrderService.php`, `InvalidateUserBalanceCache.php` |
| **Phase 3** | ✅ Completed | `InMemoryOrderBook.php`, `StreamMatchingEngine.php`, `RunStreamMatchingEngine.php` |
| **Phase 4** | ✅ Completed | `SwooleMatchingEngine.php`, `RunSwooleMatchingEngine.php`, `Dockerfile` |

### Usage Commands

```bash
# Phase 1-2 (Default): Redis Sorted Set with dynamic polling
php artisan queue:work --queue=process_order

# Phase 3: Redis Stream with in-memory orderbook
USE_STREAM_MATCHING_ENGINE=true
php artisan matching-engine:stream usdt btc --stats

# Phase 4: Swoole coroutine-based engine
php artisan matching-engine:swoole usdt btc --workers=4
```

### Environment Variables Summary

```env
# Phase 2
ASYNC_BALANCE_UPDATE=true

# Phase 3
USE_STREAM_MATCHING_ENGINE=true

# Phase 4 (requires Swoole extension)
# No special env var - just use the swoole command
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-01-18 | Claude | Initial document |
| 2.0 | 2025-01-18 | Claude | Phase 1-4 implementation completed |

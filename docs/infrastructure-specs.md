# Exchange Infrastructure Architecture & Specifications

## 1. System Architecture Overview

### 1.1 Futures Trading Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              FUTURES TRADING SYSTEM                              │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────┐     ┌─────────────────────────────────────────────────────┐    │
│  │   Client    │     │                    AWS Cloud                         │    │
│  │  (Web/App)  │     │  ┌─────────────────────────────────────────────┐    │    │
│  └──────┬──────┘     │  │              EKS Cluster                     │    │    │
│         │            │  │  ┌─────────────────────────────────────────┐ │    │    │
│         │ HTTPS      │  │  │         future-backend (NestJS)         │ │    │    │
│         ▼            │  │  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  │ │    │    │
│  ┌──────────────┐    │  │  │  │   API   │  │ Worker  │  │   WS    │  │ │    │    │
│  │     ALB      │────┼──┼──┼─▶│ Server  │  │ (Kafka) │  │ Gateway │  │ │    │    │
│  │ (Load Bal.)  │    │  │  │  └────┬────┘  └────┬────┘  └────┬────┘  │ │    │    │
│  └──────────────┘    │  │  │       │            │            │       │ │    │    │
│                      │  │  └───────┼────────────┼────────────┼───────┘ │    │    │
│                      │  │          │            │            │         │    │    │
│                      │  │          ▼            ▼            ▼         │    │    │
│                      │  │  ┌─────────────────────────────────────────┐ │    │    │
│                      │  │  │              Kafka (Redpanda)           │ │    │    │
│                      │  │  │  ┌─────────┐ ┌─────────┐ ┌─────────┐   │ │    │    │
│                      │  │  │  │ Shard-1 │ │ Shard-2 │ │ Shard-3 │   │ │    │    │
│                      │  │  │  │  Input  │ │  Input  │ │  Input  │   │ │    │    │
│                      │  │  │  │ Output  │ │ Output  │ │ Output  │   │ │    │    │
│                      │  │  │  └────┬────┘ └────┬────┘ └────┬────┘   │ │    │    │
│                      │  │  └───────┼───────────┼───────────┼────────┘ │    │    │
│                      │  │          │           │           │          │    │    │
│                      │  │          ▼           ▼           ▼          │    │    │
│                      │  │  ┌─────────────────────────────────────────┐│    │    │
│                      │  │  │      Matching Engine (Java 17)          ││    │    │
│                      │  │  │  ┌─────────┐ ┌─────────┐ ┌─────────┐   ││    │    │
│                      │  │  │  │ Shard-1 │ │ Shard-2 │ │ Shard-3 │   ││    │    │
│                      │  │  │  │ BTC/USD │ │ ETH/USD │ │ Others  │   ││    │    │
│                      │  │  │  │ TreeSet │ │ TreeSet │ │ TreeSet │   ││    │    │
│                      │  │  │  └─────────┘ └─────────┘ └─────────┘   ││    │    │
│                      │  │  └─────────────────────────────────────────┘│    │    │
│                      │  └─────────────────────────────────────────────┘    │    │
│                      │                                                      │    │
│                      │  ┌──────────────┐        ┌──────────────┐           │    │
│                      │  │  RDS MySQL   │        │  ElastiCache │           │    │
│                      │  │  (Aurora)    │        │   (Redis)    │           │    │
│                      │  │              │        │              │           │    │
│                      │  │ future_      │        │ Sessions     │           │    │
│                      │  │ exchange DB  │        │ Cache        │           │    │
│                      │  │              │        │ Pub/Sub      │           │    │
│                      │  └──────────────┘        └──────────────┘           │    │
│                      └──────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────────┘

Data Flow:
1. Client → ALB → API Server (order validation)
2. API Server → Kafka Input Topic (order submission)
3. Matching Engine consumes from Kafka, executes match
4. Matching Engine → Kafka Output Topic (trade result)
5. Worker consumes trade, updates DB
6. WebSocket Gateway pushes real-time updates to client
```

### 1.2 Spot Trading Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                               SPOT TRADING SYSTEM                                │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────┐     ┌─────────────────────────────────────────────────────┐    │
│  │   Client    │     │                    AWS Cloud                         │    │
│  │  (Web/App)  │     │  ┌─────────────────────────────────────────────┐    │    │
│  └──────┬──────┘     │  │              EKS Cluster                     │    │    │
│         │            │  │  ┌─────────────────────────────────────────┐ │    │    │
│         │ HTTPS      │  │  │         spot-backend (PHP/Laravel)      │ │    │    │
│         ▼            │  │  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  │ │    │    │
│  ┌──────────────┐    │  │  │  │   API   │  │ Queue   │  │ Socket  │  │ │    │    │
│  │     ALB      │────┼──┼──┼─▶│ Server  │  │ Worker  │  │ Server  │  │ │    │    │
│  │ (Load Bal.)  │    │  │  │  │ PHP-FPM │  │ (Redis) │  │ (Echo)  │  │ │    │    │
│  └──────────────┘    │  │  │  └────┬────┘  └────┬────┘  └────┬────┘  │ │    │    │
│                      │  │  │       │            │            │       │ │    │    │
│                      │  │  └───────┼────────────┼────────────┼───────┘ │    │    │
│                      │  │          │            │            │         │    │    │
│                      │  │          ▼            ▼            ▼         │    │    │
│                      │  │  ┌─────────────────────────────────────────┐ │    │    │
│                      │  │  │          Redis (ElastiCache)            │ │    │    │
│                      │  │  │  ┌─────────────────────────────────┐   │ │    │    │
│                      │  │  │  │     Redis Streams / Sorted Set  │   │ │    │    │
│                      │  │  │  │  ┌───────┐ ┌───────┐ ┌───────┐  │   │ │    │    │
│                      │  │  │  │  │ BTC/  │ │ ETH/  │ │ Other │  │   │ │    │    │
│                      │  │  │  │  │ USDT  │ │ USDT  │ │ Pairs │  │   │ │    │    │
│                      │  │  │  │  └───┬───┘ └───┬───┘ └───┬───┘  │   │ │    │    │
│                      │  │  │  └──────┼─────────┼─────────┼──────┘   │ │    │    │
│                      │  │  └─────────┼─────────┼─────────┼──────────┘ │    │    │
│                      │  │            │         │         │            │    │    │
│                      │  │            ▼         ▼         ▼            │    │    │
│                      │  │  ┌─────────────────────────────────────────┐│    │    │
│                      │  │  │    PHP Matching Engine (Swoole)         ││    │    │
│                      │  │  │  ┌─────────────────────────────────┐   ││    │    │
│                      │  │  │  │      InMemoryOrderBook          │   ││    │    │
│                      │  │  │  │  ┌─────────┐    ┌─────────┐     │   ││    │    │
│                      │  │  │  │  │  Buy    │    │  Sell   │     │   ││    │    │
│                      │  │  │  │  │ Orders  │◄──►│ Orders  │     │   ││    │    │
│                      │  │  │  │  │ (Heap)  │    │ (Heap)  │     │   ││    │    │
│                      │  │  │  │  └─────────┘    └─────────┘     │   ││    │    │
│                      │  │  │  └─────────────────────────────────┘   ││    │    │
│                      │  │  │  ┌─────────────────────────────────┐   ││    │    │
│                      │  │  │  │   Swoole Coroutines (Async)     │   ││    │    │
│                      │  │  │  │  • Order Receiver               │   ││    │    │
│                      │  │  │  │  • Matching Workers (x4)        │   ││    │    │
│                      │  │  │  │  • DB Writers (x2)              │   ││    │    │
│                      │  │  │  └─────────────────────────────────┘   ││    │    │
│                      │  │  └─────────────────────────────────────────┘│    │    │
│                      │  └─────────────────────────────────────────────┘    │    │
│                      │                                                      │    │
│                      │  ┌──────────────┐        ┌──────────────┐           │    │
│                      │  │  RDS MySQL   │        │  ElastiCache │           │    │
│                      │  │  (Aurora)    │        │   (Redis)    │           │    │
│                      │  │              │        │              │           │    │
│                      │  │ spot_        │        │ Order Queue  │           │    │
│                      │  │ exchange DB  │        │ Sessions     │           │    │
│                      │  │              │        │ Cache        │           │    │
│                      │  └──────────────┘        └──────────────┘           │    │
│                      └──────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────────┘

Data Flow:
1. Client → ALB → API Server (order validation)
2. API Server → Redis Stream/Sorted Set (order queued)
3. Swoole Matching Engine reads from Redis (blocking)
4. InMemoryOrderBook executes price-time priority matching
5. DB Writer coroutines persist trades to MySQL
6. WebSocket pushes real-time updates to client
```

### 1.3 Combined Infrastructure (Shared Resources)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         UNIFIED EXCHANGE INFRASTRUCTURE                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│                              ┌─────────────────┐                                 │
│                              │   Route 53      │                                 │
│                              │   (DNS)         │                                 │
│                              └────────┬────────┘                                 │
│                                       │                                          │
│                              ┌────────▼────────┐                                 │
│                              │   CloudFront    │                                 │
│                              │   (CDN)         │                                 │
│                              └────────┬────────┘                                 │
│                                       │                                          │
│  ┌────────────────────────────────────┼────────────────────────────────────┐    │
│  │                         VPC (10.0.0.0/16)                                │    │
│  │                                    │                                     │    │
│  │  ┌─────────────────────────────────┼─────────────────────────────────┐  │    │
│  │  │              Public Subnets (10.0.1.0/24, 10.0.2.0/24)            │  │    │
│  │  │  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐    │  │    │
│  │  │  │     ALB      │      │     ALB      │      │  NAT Gateway │    │  │    │
│  │  │  │  (Futures)   │      │   (Spot)     │      │              │    │  │    │
│  │  │  └──────┬───────┘      └──────┬───────┘      └──────────────┘    │  │    │
│  │  └─────────┼─────────────────────┼───────────────────────────────────┘  │    │
│  │            │                     │                                       │    │
│  │  ┌─────────┼─────────────────────┼───────────────────────────────────┐  │    │
│  │  │         │  Private Subnets (10.0.11.0/24, 10.0.12.0/24)           │  │    │
│  │  │         │                     │                                    │  │    │
│  │  │  ┌──────▼───────────────────────────────────────────────────┐    │  │    │
│  │  │  │                    EKS Cluster                            │    │  │    │
│  │  │  │  ┌─────────────────────┐  ┌─────────────────────┐        │    │  │    │
│  │  │  │  │   Namespace:        │  │   Namespace:        │        │    │  │    │
│  │  │  │  │   future-backend    │  │   spot-backend      │        │    │  │    │
│  │  │  │  │  ┌───────────────┐  │  │  ┌───────────────┐  │        │    │  │    │
│  │  │  │  │  │ API (x2-3)    │  │  │  │ API (x2-3)    │  │        │    │  │    │
│  │  │  │  │  │ Worker (x3)   │  │  │  │ Worker (x3-10)│  │        │    │  │    │
│  │  │  │  │  │ WS Gateway    │  │  │  │ Socket Server │  │        │    │  │    │
│  │  │  │  │  │ Engine (x3)   │  │  │  │ Matching (x1) │  │        │    │  │    │
│  │  │  │  │  └───────────────┘  │  │  └───────────────┘  │        │    │  │    │
│  │  │  │  └─────────────────────┘  └─────────────────────┘        │    │  │    │
│  │  │  │                                                           │    │  │    │
│  │  │  │  ┌─────────────────────────────────────────────────┐     │    │  │    │
│  │  │  │  │              Kafka / Redpanda (EC2)              │     │    │  │    │
│  │  │  │  │  (Futures only - 6 topics x 3 partitions)       │     │    │  │    │
│  │  │  │  └─────────────────────────────────────────────────┘     │    │  │    │
│  │  │  └───────────────────────────────────────────────────────────┘    │  │    │
│  │  └───────────────────────────────────────────────────────────────────┘  │    │
│  │                                                                          │    │
│  │  ┌───────────────────────────────────────────────────────────────────┐  │    │
│  │  │              Database Subnets (10.0.21.0/24, 10.0.22.0/24)        │  │    │
│  │  │  ┌──────────────────────────┐  ┌──────────────────────────┐      │  │    │
│  │  │  │      RDS MySQL           │  │      ElastiCache         │      │  │    │
│  │  │  │  ┌────────────────────┐  │  │  ┌────────────────────┐  │      │  │    │
│  │  │  │  │ future_exchange    │  │  │  │ Redis Cluster      │  │      │  │    │
│  │  │  │  │ spot_exchange      │  │  │  │ DB 0-1: Futures    │  │      │  │    │
│  │  │  │  └────────────────────┘  │  │  │ DB 2-3: Spot       │  │      │  │    │
│  │  │  │  Writer + Reader(s)     │  │  │ DB 4+: Shared       │  │      │  │    │
│  │  │  └──────────────────────────┘  └──────────────────────────┘      │  │    │
│  │  └───────────────────────────────────────────────────────────────────┘  │    │
│  └──────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│  ┌──────────────────────────────────────────────────────────────────────────┐   │
│  │                              ECR Repositories                             │   │
│  │  • exchange/future-backend    • exchange/spot-backend                    │   │
│  │  • exchange/matching-engine-shard                                        │   │
│  └──────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Infrastructure Specifications by Tier

### 2.1 Development Environment (Dev)

**Purpose**: Local development, testing, CI/CD validation

| Component | Futures | Spot | Specification | Cost/Month |
|-----------|---------|------|---------------|------------|
| **EKS Control Plane** | Shared | Shared | Managed | $73 |
| **EKS Nodes** | Shared | Shared | t3.large x 3 (Spot) | ~$50 |
| **RDS MySQL** | future_exchange | spot_exchange | db.t3.large, 20GB | $60 |
| **ElastiCache** | DB 0-1 | DB 2-3 | cache.t3.medium | $35 |
| **Kafka (Redpanda)** | Yes | No | t3.medium EC2 | $30 |
| **NAT Gateway** | Shared | Shared | Single AZ | $45 |
| **ALB** | 1 | 1 | Shared possible | $20 |
| **ECR** | 2 repos | 1 repo | 10 images each | $3 |

**Total Estimated Cost: ~$316/month**

| Metric | Futures | Spot |
|--------|---------|------|
| **Target TPS** | 500-1,000 | 1,000-2,000 |
| **Latency (p99)** | <100ms | <50ms |
| **Concurrent Users** | 100 | 100 |
| **API Replicas** | 1 | 1 |
| **Worker Replicas** | 2 | 3 |
| **Engine Replicas** | 1 | 1 |

---

### 2.2 Low Tier (Staging / Small Production)

**Purpose**: Staging environment, small user base (<1,000 DAU)

| Component | Futures | Spot | Specification | Cost/Month |
|-----------|---------|------|---------------|------------|
| **EKS Control Plane** | Shared | Shared | Managed | $73 |
| **EKS Nodes** | Shared | Shared | t3.xlarge x 4 (Spot 70%) | $120 |
| **RDS MySQL** | future_exchange | spot_exchange | db.r6g.large, Multi-AZ, 50GB | $200 |
| **ElastiCache** | DB 0-1 | DB 2-3 | cache.r6g.large (2 nodes) | $150 |
| **Kafka (Redpanda)** | Yes | No | t3.large EC2, 100GB | $80 |
| **NAT Gateway** | Shared | Shared | 2 AZ | $90 |
| **ALB** | 1 | 1 | Separate | $40 |
| **ECR** | 2 repos | 1 repo | 20 images each | $5 |
| **CloudWatch** | Shared | Shared | Logs + Metrics | $50 |

**Total Estimated Cost: ~$808/month**

| Metric | Futures | Spot |
|--------|---------|------|
| **Target TPS** | 2,000 | 3,000-5,000 |
| **Latency (p99)** | <50ms | <30ms |
| **Concurrent Users** | 1,000 | 1,000 |
| **API Replicas** | 2 | 2 |
| **Worker Replicas** | 3 | 5 |
| **Engine Replicas** | 3 (sharded) | 1 (Swoole) |

---

### 2.3 Medium Tier (Production)

**Purpose**: Production environment (1,000-10,000 DAU)

| Component | Futures | Spot | Specification | Cost/Month |
|-----------|---------|------|---------------|------------|
| **EKS Control Plane** | Shared | Shared | Managed | $73 |
| **EKS Nodes** | Shared | Shared | c6i.xlarge x 6 (On-Demand) | $450 |
| **RDS MySQL** | future_exchange | spot_exchange | db.r6g.xlarge, Multi-AZ, 200GB | $500 |
| **RDS Read Replica** | 1 | 1 | db.r6g.large x 2 | $200 |
| **ElastiCache** | Dedicated | Dedicated | cache.r6g.xlarge (3 nodes cluster) | $400 |
| **MSK (Kafka)** | Yes | Optional | kafka.m5.large x 3 | $450 |
| **NAT Gateway** | Shared | Shared | 2 AZ + data transfer | $150 |
| **ALB** | Dedicated | Dedicated | With WAF | $100 |
| **ECR** | 2 repos | 1 repo | 50 images each | $10 |
| **CloudWatch** | Shared | Shared | Full observability | $150 |
| **Secrets Manager** | Shared | Shared | 10 secrets | $5 |

**Total Estimated Cost: ~$2,488/month**

| Metric | Futures | Spot |
|--------|---------|------|
| **Target TPS** | 5,000-10,000 | 10,000-20,000 |
| **Latency (p99)** | <30ms | <20ms |
| **Concurrent Users** | 10,000 | 10,000 |
| **API Replicas** | 3-5 | 3-5 |
| **Worker Replicas** | 6 | 10 |
| **Engine Replicas** | 3 (sharded) | 3 (per symbol) |

---

### 2.4 High Tier (Large Scale Production)

**Purpose**: High-traffic production (>10,000 DAU, institutional)

| Component | Futures | Spot | Specification | Cost/Month |
|-----------|---------|------|---------------|------------|
| **EKS Control Plane** | Dedicated | Dedicated | 2 clusters | $146 |
| **EKS Nodes** | Dedicated | Dedicated | c6i.2xlarge x 10 | $1,200 |
| **RDS Aurora** | future_exchange | spot_exchange | db.r6g.2xlarge, Multi-AZ, 500GB | $1,500 |
| **Aurora Replicas** | 2 | 2 | db.r6g.xlarge x 4 | $600 |
| **ElastiCache** | Dedicated | Dedicated | cache.r6g.2xlarge (6 nodes) | $1,200 |
| **MSK (Kafka)** | Yes | Optional | kafka.m5.xlarge x 6 | $1,200 |
| **NAT Gateway** | Dedicated | Dedicated | 3 AZ + high transfer | $400 |
| **ALB** | Dedicated | Dedicated | WAF + Shield | $300 |
| **Global Accelerator** | Shared | Shared | Low latency routing | $50 |
| **ECR** | 2 repos | 1 repo | 100 images each | $20 |
| **CloudWatch** | Full | Full | X-Ray, Container Insights | $400 |
| **Secrets Manager** | Shared | Shared | 30 secrets | $15 |

**Total Estimated Cost: ~$7,031/month**

| Metric | Futures | Spot |
|--------|---------|------|
| **Target TPS** | 20,000-50,000 | 50,000-100,000 |
| **Latency (p99)** | <10ms | <5ms |
| **Concurrent Users** | 100,000 | 100,000 |
| **API Replicas** | 6-10 | 6-10 |
| **Worker Replicas** | 12 | 20 |
| **Engine Replicas** | 6 (sharded) | 6 (per symbol group) |

---

## 3. Performance Comparison Summary

### 3.1 TPS by Tier

```
                    ┌─────────────────────────────────────────────────────────────┐
                    │              TPS Performance by Infrastructure Tier          │
                    ├─────────────────────────────────────────────────────────────┤
    100,000 TPS ─── │                                              ████████████   │
                    │                                              █ SPOT HIGH █   │
     50,000 TPS ─── │                                    ████████  ████████████   │
                    │                                    █FUTURES█  ████████████   │
     20,000 TPS ─── │                          ████████  █ HIGH  █  ████████████   │
                    │                          █ SPOT  █  ████████  ████████████   │
     10,000 TPS ─── │                ████████  █MEDIUM █  ████████  ████████████   │
                    │                █FUTURES█  ████████  ████████  ████████████   │
      5,000 TPS ─── │      ████████  █MEDIUM █  ████████  ████████  ████████████   │
                    │      █ SPOT  █  ████████  ████████  ████████  ████████████   │
      2,000 TPS ─── │████  █ LOW  █  ████████  ████████  ████████  ████████████   │
                    │█FUT█  ████████  ████████  ████████  ████████  ████████████   │
      1,000 TPS ─── │█LOW█  ████████  ████████  ████████  ████████  ████████████   │
                    │████  ████████  ████████  ████████  ████████  ████████████   │
        500 TPS ─── │████  ████████  ████████  ████████  ████████  ████████████   │
                    │████  ████████  ████████  ████████  ████████  ████████████   │
                    └─────────────────────────────────────────────────────────────┘
                         DEV        LOW       MEDIUM      HIGH
                       (~$316)    (~$808)   (~$2,488)  (~$7,031)
```

### 3.2 Detailed Comparison Table

| Tier | Monthly Cost | Futures TPS | Spot TPS | Latency (p99) | DAU Capacity |
|------|-------------|-------------|----------|---------------|--------------|
| **Dev** | $316 | 500-1,000 | 1,000-2,000 | 50-100ms | 100 |
| **Low** | $808 | 2,000 | 3,000-5,000 | 30-50ms | 1,000 |
| **Medium** | $2,488 | 5,000-10,000 | 10,000-20,000 | 10-30ms | 10,000 |
| **High** | $7,031 | 20,000-50,000 | 50,000-100,000 | 5-10ms | 100,000+ |

### 3.3 Why Spot TPS > Futures TPS

| Factor | Futures | Spot | Impact |
|--------|---------|------|--------|
| **Matching Engine** | Java (JVM overhead) | PHP Swoole (coroutines) | Spot 2-3x faster |
| **Message Queue** | Kafka (disk persist) | Redis (memory) | Spot 5-10x lower latency |
| **Complexity** | Margin, liquidation, funding | Simple balance transfer | Spot simpler |
| **DB Writes** | Position, margin updates | Order + transaction only | Spot 50% less |
| **Sharding** | 3 shards (symbol-based) | Per-symbol process | Spot more scalable |

---

## 4. Scaling Recommendations

### 4.1 Horizontal Scaling Points

| Component | Scaling Trigger | Action |
|-----------|----------------|--------|
| API Server | CPU > 70% | Add replica |
| Workers | Queue depth > 1000 | Add replica |
| Matching Engine | Latency > 10ms | Add shard/process |
| Redis | Memory > 80% | Scale up node type |
| RDS | CPU > 70% | Add read replica |
| RDS | Write IOPS > 80% | Scale up instance |

### 4.2 Vertical Scaling Recommendations

| Tier Upgrade | From | To | Expected Gain |
|--------------|------|-----|---------------|
| Dev → Low | t3.large | t3.xlarge | 2x TPS |
| Low → Medium | t3.xlarge | c6i.xlarge | 2-3x TPS |
| Medium → High | c6i.xlarge | c6i.2xlarge | 2x TPS |
| Redis scale | t3.medium | r6g.xlarge | 5x ops/sec |
| RDS scale | t3.large | r6g.xlarge | 3x IOPS |

---

## 5. Cost Optimization Tips

1. **Use Spot Instances** for EKS nodes (70% savings)
2. **Reserved Instances** for RDS (30-50% savings on 1-year)
3. **Single NAT Gateway** for dev/staging
4. **Shared Redis** between futures/spot with DB index separation
5. **Redpanda instead of MSK** for low-medium tiers (80% cheaper)
6. **Aurora Serverless v2** for variable workloads
7. **CloudFront caching** for static content and API responses

---

## 6. Deployment Commands by Tier

### Dev
```bash
cd infra
cdk deploy --all -c env=dev
```

### Low/Staging
```bash
cd infra
cdk deploy --all -c env=staging
```

### Medium/Production
```bash
cd infra
cdk deploy --all -c env=prod --require-approval broadening
```

### High/Enterprise
```bash
cd infra
# Deploy dedicated clusters
cdk deploy Exchange-prod-futures-* -c env=prod-futures
cdk deploy Exchange-prod-spot-* -c env=prod-spot
```

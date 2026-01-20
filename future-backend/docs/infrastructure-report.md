# Infrastructure Report - Future Backend

## Overview

- **Date**: 2026-01-20
- **Environment**: Development (AWS ap-northeast-2)
- **Target TPS**: 2,000+
- **Achieved TPS**: 2,167 (700 VUs, P95 < 700ms)

---

## Infrastructure Configuration

### EKS Cluster

| Component | Specification |
|-----------|---------------|
| Cluster Version | v1.29.15-eks |
| Region | ap-northeast-2 |
| Node Groups | Spot Instances |

### Node Configuration

| Instance Type | Count | vCPU | Memory | Purpose |
|---------------|-------|------|--------|---------|
| t3.large | 3 | 2 | 8GB | Worker nodes |
| t3a.medium | 3 | 2 | 4GB | Worker nodes |
| **Total** | **6** | **12** | **36GB** | |

### Auto Scaling

#### Horizontal Pod Autoscaler (HPA)

| Setting | Value |
|---------|-------|
| minReplicas | 5 |
| maxReplicas | 20 |
| Target CPU | 70% |
| Scale Up | 2 pods / 30s |
| Scale Down | 1 pod / 60s |

#### Cluster Autoscaler

| Setting | Value |
|---------|-------|
| Min Nodes | 3 |
| Max Nodes | 10 |
| Scale Down Delay | 10m |

### Application Pods

| Deployment | Replicas (idle) | Replicas (load) | CPU Limit | Memory Limit |
|------------|-----------------|-----------------|-----------|--------------|
| future-backend | 5 | 15-20 | 2 cores | 2Gi |
| order-worker | 1 | 1 | 1 core | 1Gi |

---

## Database & Cache

### MySQL (RDS)

| Setting | Value |
|---------|-------|
| Instance Type | db.t3.large |
| vCPU | 2 |
| Memory | 8GB |
| Storage | 100GB gp3 |
| Multi-AZ | No (Dev) |
| Connection Pool | 50 |

### Redis (ElastiCache)

| Setting | Value |
|---------|-------|
| Node Type | cache.t3.medium |
| Memory | 3GB |
| Nodes | 1 |

### Kafka (Redpanda on EC2)

| Setting | Value |
|---------|-------|
| Instance Type | t3.medium |
| vCPU | 2 |
| Memory | 4GB |
| Partitions | 3 per topic |

---

## Performance Results

### TPS Benchmarks

| VUs | TPS | P95 Latency | Success Rate | Status |
|-----|-----|-------------|--------------|--------|
| 400 | 1,067 | 885ms | 100% | ✅ |
| 500 | 1,651 | 680ms | 100% | ✅ |
| 600 | 1,851 | 771ms | 100% | ✅ |
| **700** | **2,167** | **674ms** | **100%** | ✅ **Best** |
| 800 | 1,945 | 925ms | 100% | ⚠️ |
| 1000 | 2,150 | 1,060ms | 100% | ❌ P95 exceeded |

### Optimization Applied

1. **Kafka Producer Connection Pooling** - Singleton pattern, avoid reconnect per request
2. **Promise.all Parallelization** - account/instrument fetch, TP/SL order saves
3. **Consumer Concurrency** - partitionsConsumedConcurrently: 10
4. **HPA** - Auto-scale pods based on CPU utilization
5. **Cluster Autoscaler** - Auto-scale nodes based on pending pods

---

## Cost Estimation (Monthly)

### Compute (EKS)

| Resource | Spec | Quantity | Unit Price | Monthly Cost |
|----------|------|----------|------------|--------------|
| EKS Cluster | - | 1 | $72 | $72 |
| t3.large (Spot) | 2vCPU/8GB | 3 | ~$20 | $60 |
| t3a.medium (Spot) | 2vCPU/4GB | 3 | ~$10 | $30 |
| **Subtotal** | | | | **$162** |

### Database & Cache

| Resource | Spec | Quantity | Monthly Cost |
|----------|------|----------|--------------|
| RDS db.t3.large | 2vCPU/8GB | 1 | $100 |
| ElastiCache cache.t3.medium | 3GB | 1 | $50 |
| **Subtotal** | | | **$150** |

### Messaging & Storage

| Resource | Spec | Quantity | Monthly Cost |
|----------|------|----------|--------------|
| EC2 t3.medium (Kafka) | 2vCPU/4GB | 1 | $30 |
| EBS gp3 | 100GB | 2 | $20 |
| **Subtotal** | | | **$50** |

### Network

| Resource | Quantity | Monthly Cost |
|----------|----------|--------------|
| NAT Gateway | 1 | $45 |
| Load Balancer (NLB) | 1 | $20 |
| Data Transfer | ~500GB | $45 |
| **Subtotal** | | **$110** |

### Total Monthly Cost

| Category | Cost |
|----------|------|
| Compute (EKS) | $162 |
| Database & Cache | $150 |
| Messaging & Storage | $50 |
| Network | $110 |
| **Total** | **$472/month** |

> Note: Spot instance pricing varies. Actual costs may be 30-50% lower during off-peak times.

---

## Scaling Recommendations

### For 3,000+ TPS

| Action | Expected Improvement | Additional Cost |
|--------|---------------------|-----------------|
| Upgrade nodes to t3.xlarge | +30-50% TPS | +$100/month |
| Increase DB connection pool (100) | +10-20% TPS | $0 |
| Add RDS read replica | +20-30% TPS | +$100/month |
| Enable batch processing | +50-100% TPS | $0 |

### For Production

| Change | Reason | Cost Impact |
|--------|--------|-------------|
| RDS Multi-AZ | High availability | +$100/month |
| Redis cluster mode | HA & throughput | +$100/month |
| 3-node Kafka cluster | Fault tolerance | +$60/month |
| Reserved instances | Cost optimization | -30% |

---

## Architecture Diagram

```
                    ┌─────────────────┐
                    │   Load Balancer │
                    │      (NLB)      │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
        ┌─────▼─────┐  ┌─────▼─────┐  ┌─────▼─────┐
        │  Backend  │  │  Backend  │  │  Backend  │
        │  Pod 1-5  │  │  Pod 6-10 │  │ Pod 11-20 │
        └─────┬─────┘  └─────┬─────┘  └─────┬─────┘
              │              │              │
              └──────────────┼──────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
    ┌────▼────┐        ┌─────▼─────┐       ┌─────▼─────┐
    │  MySQL  │        │   Redis   │       │   Kafka   │
    │  (RDS)  │        │(ElastiCache)      │(Redpanda) │
    └─────────┘        └───────────┘       └───────────┘
```

---

## Key Metrics to Monitor

| Metric | Threshold | Action |
|--------|-----------|--------|
| API P95 Latency | > 1000ms | Scale up pods |
| CPU Utilization | > 70% | HPA auto-scales |
| DB Connections | > 80% pool | Increase pool size |
| Kafka Lag | > 1000 msgs | Check consumers |
| Error Rate | > 1% | Investigate logs |

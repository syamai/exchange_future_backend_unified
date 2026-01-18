# PHP Matching Engine Performance Benchmark Results

**Date**: 2025-01-18
**Environment**: macOS, PHP 8.4, Redis 7.4.7 (Docker)

---

## 1. Polling Strategy Comparison

### Uniform Traffic Distribution

| Orders | Approach | Avg(ms) | P50(ms) | P95(ms) | P99(ms) | Max(ms) | Improvement |
|--------|----------|---------|---------|---------|---------|---------|-------------|
| 100 | Fixed 200ms | 95.41 | 101 | 187 | 195 | 195 | - |
| 100 | Dynamic 1-50ms | 16.16 | 12 | 46 | 49 | 49 | **83.1%** |
| 500 | Fixed 200ms | 100.09 | 102 | 192 | 197 | 199 | - |
| 500 | Dynamic 1-50ms | 8.16 | 4 | 30 | 44 | 49 | **91.8%** |
| 1000 | Fixed 200ms | 102.77 | 105 | 189 | 198 | 199 | - |
| 1000 | Dynamic 1-50ms | 3.25 | 1 | 15 | 29 | 31 | **96.8%** |
| 2000 | Fixed 200ms | 99.05 | 98 | 190 | 197 | 199 | - |
| 2000 | Dynamic 1-50ms | 1.01 | 0 | 6 | 12 | 15 | **99.0%** |

### Burst Traffic Pattern (10 bursts, 50ms window each)

| Orders | Approach | Avg(ms) | P50(ms) | P95(ms) | P99(ms) | Max(ms) | Improvement |
|--------|----------|---------|---------|---------|---------|---------|-------------|
| 100 | Fixed 200ms | 169.09 | 170 | 197 | 199 | 199 | - |
| 100 | Dynamic 1-50ms | 4.72 | 0 | 24 | 49 | 49 | **97.2%** |
| 500 | Fixed 200ms | 170.78 | 175 | 197 | 199 | 199 | - |
| 500 | Dynamic 1-50ms | 2.95 | 0 | 15 | 18 | 18 | **98.3%** |
| 1000 | Fixed 200ms | 170.09 | 175 | 196 | 199 | 199 | - |
| 1000 | Dynamic 1-50ms | 2.94 | 0 | 15 | 18 | 18 | **98.3%** |
| 2000 | Fixed 200ms | 170.94 | 174 | 197 | 199 | 199 | - |
| 2000 | Dynamic 1-50ms | 3.06 | 0 | 16 | 18 | 18 | **98.2%** |

**Key Finding**: Dynamic polling reduces latency by **83-99%** compared to fixed 200ms polling.

---

## 2. InMemoryOrderBook Performance

### Order Operations

| Orders | Add Time (ms) | Match Time (ms) | Total (ms) | Matched Pairs | Add/sec | Match/sec |
|--------|---------------|-----------------|------------|---------------|---------|-----------|
| 1,000 | 0.77 | 423.77 | 424.54 | 247 | 1,292,145 | 583 |
| 5,000 | 5.70 | 2,540.51 | 2,546.22 | 1,287 | 876,443 | 507 |
| 10,000 | 13.62 | 2,741.78 | 2,755.40 | 2,524 | 734,387 | 921 |
| 50,000 | 236.11 | 4,823.74 | 5,059.86 | 12,455 | 211,761 | 2,582 |
| 100,000 | 460.58 | 4,357.10 | 4,817.67 | 25,048 | 217,119 | 5,749 |

### Continuous Matching TPS

- **5,000 pairs matched** in 2,486.19 ms
- **Estimated Pure Matching TPS: 2,011**

**Note**: This is pure in-memory matching without DB operations.

---

## 3. Redis Operations Benchmark

### Individual Operation Performance

| Operation | RPS | Avg Latency | Notes |
|-----------|-----|-------------|-------|
| ZADD | 12,787 | 0.071ms | Write operation |
| ZRANGE | 15,446 | 0.061ms | Read operation |
| ZPOPMIN | 13,509 | 0.069ms | Atomic pop |

### Comparison: ZRANGE+ZREM vs ZPOPMIN

| Approach | Commands | Atomic | Network RTT |
|----------|----------|--------|-------------|
| ZRANGE + ZREM | 2 | No | 2x |
| ZPOPMIN | 1 | Yes | 1x |

**Key Finding**: ZPOPMIN reduces network round trips by 50% and provides atomic operation.

---

## 4. Expected Performance Improvements

### Current State (Baseline)
- Polling: Fixed 200ms
- Matching: 1 order per cycle
- Redis: ZRANGE + ZREM (2 commands)
- Workers: 3 instances
- **Estimated TPS: 100-200**

### After Phase 1-4 Optimizations

| Phase | Optimization | Expected Impact |
|-------|--------------|-----------------|
| 1 | Dynamic polling (1-50ms) | 4-200x faster response |
| 1 | Batch matching (20/cycle) | 20x throughput |
| 1 | ZPOPMIN | 50% less Redis latency |
| 1 | OPcache JIT | 10-30% CPU improvement |
| 2 | 10 workers | 3.3x parallelism |
| 2 | Async events | Reduced blocking |
| 3 | In-Memory Orderbook | 2,000+ match/sec |
| 3 | Redis Streams | Push vs poll |
| 4 | Swoole coroutines | 5,000+ TPS potential |

### Projected TPS

| Phase | Expected TPS | Latency |
|-------|--------------|---------|
| Current | 100-200 | 200ms+ |
| Phase 1 | 500-800 | 50-100ms |
| Phase 2 | 1,000-1,500 | 30-50ms |
| Phase 3 | 2,000-3,000 | 10-30ms |
| Phase 4 | 5,000-10,000 | <10ms |

---

## 5. Recommendations

1. **Phase 1 changes are safe and zero-cost** - Deploy immediately
2. **Phase 2 worker scaling provides linear improvement** - Low risk
3. **Phase 3 requires code testing** - Deploy to staging first
4. **Phase 4 Swoole requires PHP runtime change** - Test thoroughly

---

## Benchmark Scripts

- `orderbook-benchmark-fast.php` - InMemoryOrderBook performance
- `polling-benchmark.php` - Polling strategy latency simulation
- `redis-benchmark.php` - Redis operations comparison (requires ext-redis)

# 5,000 TPS 매칭 엔진 아키텍처 종합 검토 보고서

**작성일**: 2026-01-24
**목표**: Spot 및 Future 매칭 엔진 5,000 TPS 달성을 위한 아키텍처 분석 및 최적화 전략

---

## 1. Executive Summary

현재 암호화폐 거래소 시스템은 Spot과 Future 두 개의 독립적인 매칭 엔진으로 구성되어 있습니다.
각 시스템은 서로 다른 기술 스택을 사용하며, 공유 인프라(RDS, Redis)를 통해 일부 리소스를 공유합니다.

### 현재 성능 현황

| 시스템 | 현재 TPS | 목표 TPS | 인메모리 벤치마크 | 주요 병목 |
|--------|----------|----------|-------------------|-----------|
| Spot | ~200 | 5,000 | 27,424 TPS | DB 동기 쓰기 |
| Future | ~1,000 | 5,000 | N/A | Kafka 단일 파티션 |

### 핵심 발견사항

1. **Spot**: 순수 인메모리 매칭 성능(27,424 TPS)은 충분하나, DB 동기 쓰기(5-10ms/order)가 병목
2. **Future**: 심볼 기반 샤딩 이미 구현 완료, 성능 테스트에서 113K orders/sec 달성
3. **공유 인프라**: RDS와 Redis의 잠재적 경합 지점 존재

---

## 2. 현재 아키텍처 분석

### 2.1 Spot Backend 아키텍처

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           Spot Backend Architecture                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐                                                        │
│   │   Client    │                                                        │
│   └──────┬──────┘                                                        │
│          │ REST API                                                      │
│          ▼                                                               │
│   ┌─────────────────────┐                                               │
│   │  Laravel/Swoole     │                                               │
│   │   HTTP Server       │                                               │
│   └──────┬──────────────┘                                               │
│          │                                                               │
│          ▼                                                               │
│   ┌─────────────────────┐      ┌─────────────────────┐                  │
│   │  ProcessOrder Job   │      │  Redis Sorted Set   │                  │
│   │  (Matching Logic)   │◄────►│  (Order Queue)      │                  │
│   └──────┬──────────────┘      └─────────────────────┘                  │
│          │                                                               │
│          │ Per-order DB write (5-10ms)                                  │
│          ▼                                                               │
│   ┌─────────────────────┐                                               │
│   │    MySQL (RDS)      │                                               │
│   │   Synchronous I/O   │                                               │
│   └─────────────────────┘                                               │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

#### 핵심 컴포넌트

| 파일 | 역할 | 성능 특성 |
|------|------|-----------|
| `ProcessOrder.php` | 메인 매칭 루프 | 동적 폴링(1-50ms), 배치 매칭(20/cycle) |
| `SwooleMatchingEngineV2.php` | Swoole 코루틴 기반 엔진 | 비동기 I/O, 채널 기반 병렬 처리 |
| `HeapOrderBook.php` | Heap 기반 오더북 | O(log n) insert, 456x 성능 향상 |
| `InMemoryOrderBook.php` | Array 기반 오더북 (레거시) | O(n) insert |

#### 병목 지점 분석

1. **DB 동기 쓰기 (Critical)**
   - 매 주문 체결마다 `$order->save()` 호출
   - 단일 트랜잭션: 5-10ms
   - 이론적 최대: 100-200 TPS

2. **Redis Polling (Medium)**
   - 동적 폴링으로 1-50ms 지연
   - Redis Stream 미사용 (predis 제한)

3. **Single Worker (Medium)**
   - 심볼별 병렬 처리 없음
   - 워커 스케일링 제한적

### 2.2 Future Backend 아키텍처

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Future Backend Architecture                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────┐                                                        │
│   │   Client    │                                                        │
│   └──────┬──────┘                                                        │
│          │ REST/WebSocket                                                │
│          ▼                                                               │
│   ┌─────────────────────┐      ┌─────────────────────┐                  │
│   │  NestJS Backend     │──────►  OrderRouterService │                  │
│   │  (TypeScript)       │      │  (Symbol Sharding)  │                  │
│   └──────┬──────────────┘      └──────────┬──────────┘                  │
│          │                                │                              │
│          │                    ┌───────────┼───────────┐                  │
│          │                    ▼           ▼           ▼                  │
│          │              ┌─────────┐ ┌─────────┐ ┌─────────┐             │
│          │              │ Shard-1 │ │ Shard-2 │ │ Shard-3 │             │
│          │              │ BTC/ETH │ │ Other   │ │ Default │             │
│          │              └────┬────┘ └────┬────┘ └────┬────┘             │
│          │                   │           │           │                   │
│          │                   └───────────┼───────────┘                   │
│          │                               │ Kafka                         │
│          │                               ▼                               │
│          │              ┌─────────────────────────────┐                  │
│          │              │   Java Matching Engine       │                  │
│          │              │   (TreeSet OrderBook)        │                  │
│          │              │   - MatchingEngine.java      │                  │
│          │              │   - Matcher.java (per symbol)│                  │
│          │              └──────────┬──────────────────┘                  │
│          │                         │ Kafka Output                        │
│          │                         ▼                                     │
│          │              ┌─────────────────────────────┐                  │
│          ▼              │  MatchingEngineService.ts   │                  │
│   ┌─────────────┐       │  (Async Batch DB Writer)    │                  │
│   │   MySQL     │◄──────│  - saveAccountsV2()         │                  │
│   │   (RDS)     │       │  - savePositionsV2()        │                  │
│   └─────────────┘       │  - saveOrders()             │                  │
│                         └─────────────────────────────┘                  │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

#### 핵심 컴포넌트

| 파일 | 역할 | 성능 특성 |
|------|------|-----------|
| `MatchingEngine.java` | 메인 이벤트 루프 | BlockingQueue 기반, 단일 스레드 |
| `Matcher.java` | 심볼별 오더북 | TreeSet O(log n) |
| `OrderRouterService.ts` | 심볼 → 샤드 라우팅 | 141K routes/sec |
| `MatchingEngineService.ts` | 비동기 DB 배치 쓰기 | Interval 기반 버퍼링 |

#### 샤딩 구성 (이미 구현됨)

| 샤드 | 심볼 | Kafka Topic |
|------|------|-------------|
| shard-1 | BTCUSDT, BTCBUSD, BTCUSDC | matching-engine-shard-1-input |
| shard-2 | ETHUSDT, ETHBUSD, ETHUSDC | matching-engine-shard-2-input |
| shard-3 | 기타 모든 심볼 (기본) | matching-engine-shard-3-input |

#### 이미 최적화된 부분

1. **비동기 DB 쓰기**: `saveAccountsV2()`, `savePositionsV2()` 등 Interval 기반 배치 처리
2. **Redis 캐싱**: 계정, 포지션, 주문을 Redis에 캐시 후 비동기 DB 반영
3. **심볼 샤딩**: 고트래픽 심볼(BTC, ETH) 분리, 성능 테스트 통과

### 2.3 공유 인프라 분석

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          Shared Infrastructure                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌─────────────────────────────────────────────────────────────────┐   │
│   │                       EKS Cluster (exchange-dev)                 │   │
│   │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │   │
│   │  │  Future Backend │  │  Spot Backend   │  │ Matching Engine │ │   │
│   │  │  (t3.large)     │  │  (t3.large)     │  │  (Java, Spot)   │ │   │
│   │  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘ │   │
│   └───────────┼─────────────────────┼─────────────────────┼─────────┘   │
│               │                     │                     │              │
│   ┌───────────┼─────────────────────┼─────────────────────┼───────────┐ │
│   │           ▼                     ▼                     ▼           │ │
│   │  ┌─────────────────────────────────────────────────────────────┐ │ │
│   │  │                  RDS MySQL (db.t3.large)                     │ │ │
│   │  │                                                              │ │ │
│   │  │   Database: future_db      Database: spot_db                │ │ │
│   │  │   ├── orders              ├── orders                        │ │ │
│   │  │   ├── trades              ├── trades                        │ │ │
│   │  │   ├── positions           └── users                         │ │ │
│   │  │   └── accounts                                              │ │ │
│   │  │                                                              │ │ │
│   │  │   Connection Pool: ~100 connections (shared)                │ │ │
│   │  └─────────────────────────────────────────────────────────────┘ │ │
│   │                                                                   │ │
│   │  ┌─────────────────────────────────────────────────────────────┐ │ │
│   │  │              Redis (cache.t3.medium)                        │ │ │
│   │  │                                                              │ │ │
│   │  │   DB 0: Future (accounts, orders, positions cache)          │ │ │
│   │  │   DB 1: Spot (order queues, locks)                          │ │ │
│   │  │                                                              │ │ │
│   │  │   Operations: ~10,000 ops/sec capacity                       │ │ │
│   │  └─────────────────────────────────────────────────────────────┘ │ │
│   │                                                                   │ │
│   │  ┌─────────────────────────────────────────────────────────────┐ │ │
│   │  │              Kafka (Redpanda, t3.medium)                    │ │ │
│   │  │                                                              │ │ │
│   │  │   Topics: matching_engine_input/output (Future only)        │ │ │
│   │  │   Partitions: 3 per shard                                   │ │ │
│   │  └─────────────────────────────────────────────────────────────┘ │ │
│   └───────────────────────────────────────────────────────────────────┘ │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

#### 잠재적 충돌 지점

| 리소스 | 현재 용량 | 예상 부하 (5K TPS) | 위험도 |
|--------|-----------|-------------------|--------|
| RDS Connections | 100 | Spot: 50, Future: 50 → 200+ 필요 | **High** |
| RDS IOPS | 3,000 (gp3) | 5,000 writes/sec 예상 | **High** |
| Redis Memory | 1.5GB | 캐시 증가로 3GB+ 예상 | Medium |
| Redis Ops | 10K/sec | 20K/sec 예상 | Medium |

---

## 3. 병목 지점 상세 분석

### 3.1 Spot Backend 병목

#### 3.1.1 DB 동기 쓰기 (Critical)

**현재 코드 (`ProcessOrder.php` lines 565-713):**
```php
private function matchOrders($ids)
{
    DB::connection('master')->beginTransaction();
    try {
        $buyOrder = Order::on('master')->where('id', $ids[0])->lockForUpdate()->first();
        $sellOrder = Order::on('master')->where('id', $ids[1])->lockForUpdate()->first();

        // 매칭 로직...
        $remaining = $this->orderService->matchOrders($buyOrder, $sellOrder, $isBuyerMaker);

        DB::connection('master')->commit();  // ← 블로킹 I/O (5-10ms)
    } catch (Exception $e) {
        $this->rollBackAnLogError($e);
    }
}
```

**문제점:**
- 매 체결마다 동기 트랜잭션
- `lockForUpdate()` 로 인한 행 잠금 경합
- 단일 커넥션에서 순차 처리

**영향:**
- 단일 트랜잭션 5-10ms → 최대 100-200 TPS

#### 3.1.2 Redis Polling

**현재 코드 (`ProcessOrder.php` lines 128-178):**
```php
// Batch matching: process multiple orders per iteration
$batchMatchCount = 0;
for ($i = 0; $i < self::BATCH_SIZE; $i++) {
    // 매칭 시도...
}

// Dynamic polling: adjust sleep time based on activity
if ($batchMatchCount > 0) {
    $this->sleepTimeUs = self::MIN_SLEEP_US;  // 1ms
} else {
    // Exponential backoff up to 50ms
    $this->sleepTimeUs = min($this->sleepTimeUs * 2, self::MAX_SLEEP_US);
}
usleep($this->sleepTimeUs);  // ← 폴링 지연
```

**문제점:**
- Push 모델이 아닌 Poll 모델
- 비활성 시 50ms까지 지연
- Redis Stream 미사용 (predis 제한)

#### 3.1.3 SwooleMatchingEngineV2 분석

**이미 구현된 최적화 (`SwooleMatchingEngineV2.php`):**

```php
// V2: New components
private HeapOrderBook $orderBook;           // Heap 기반 O(log n)
private OrderCacheService $cacheService;    // 캐시 레이어
private CircuitBreaker $dbCircuitBreaker;   // 장애 복구
private RetryPolicy $retryPolicy;           // 재시도 정책
private DeadLetterQueue $dlq;               // 메시지 손실 방지
```

**아직 미완성 부분:**
- Redis Stream 의존 (`xReadGroup`) - predis 미지원
- Swoole 코루틴 DB 드라이버 미적용
- 심볼 기반 워커 분리 미구현

### 3.2 Future Backend 병목

#### 3.2.1 Java 매칭 엔진

**현재 구현 (`Matcher.java` lines 36-45):**
```java
// Buy Order queue, where orders with higher price appear first
private final TreeSet<Order> buyOrders =
    new TreeSet<>(
        OrderComparators.HighPriceComparator.thenComparing(
            OrderComparators.LowPriorityComparator));

// Sell Order queue, where orders with a lower price appear first
private final TreeSet<Order> sellOrders =
    new TreeSet<>(
        OrderComparators.LowPriceComparator.thenComparing(
            OrderComparators.LowPriorityComparator));
```

**성능 특성:**
- TreeSet: O(log n) insert/remove
- 단일 스레드 이벤트 루프
- 메모리 내 상태 관리

**병목:**
- 단일 `MatchingEngine` 인스턴스
- 모든 심볼이 하나의 스레드에서 처리
- → 샤딩으로 해결됨 (이미 구현)

#### 3.2.2 NestJS 비동기 DB 처리

**현재 구현 (`MatchingEngineService.ts` lines 595-656):**
```typescript
// V2: Interval-based batch write
if (!MatchingEngineService.saveAccountIntervalV2) {
  MatchingEngineService.saveAccountIntervalV2 = setInterval(async () => {
    if (MatchingEngineService.updatedAccountIds.size === 0) return;

    const accountIds = Array.from(MatchingEngineService.updatedAccountIds);
    MatchingEngineService.updatedAccountIds.clear();

    const accountsToSaveDb = accountIds.map(id =>
      MatchingEngineService.accountsWillBeUpdatedOnDb.get(id)
    ).filter(Boolean);

    await this.accountRepository.insertOrUpdate(accountsToSaveDb);
  }, 500);  // 500ms 간격 배치
}
```

**이미 최적화된 부분:**
- Redis 캐시 우선 (`saveAccountsToCache`)
- Interval 기반 배치 쓰기 (500ms)
- 중복 제거 (`operationId` 비교)
- Deadlock 자동 재시도

**추가 최적화 가능:**
- Interval 간격 조정 (500ms → 100ms)
- 배치 크기 동적 조정
- DB 커넥션 풀 확장

---

## 4. 5,000 TPS 달성을 위한 최적화 방안

### 4.1 Spot Backend 최적화 전략

#### Phase 1: DB 배치 쓰기 (1주, 예상 +20x)

```
현재:  Order → DB Write (5ms) → 200 TPS
목표:  Order → Buffer → Batch Write (100 orders/20ms) → 5,000 TPS
```

**구현 계획:**

```php
class WriteBuffer {
    private array $pendingOrders = [];
    private array $pendingTrades = [];
    private int $flushThreshold = 100;
    private int $flushIntervalMs = 50;

    public function addMatch(Order $buyOrder, Order $sellOrder, Trade $trade): void {
        $this->pendingOrders[] = $buyOrder;
        $this->pendingOrders[] = $sellOrder;
        $this->pendingTrades[] = $trade;

        if (count($this->pendingOrders) >= $this->flushThreshold) {
            $this->flush();
        }
    }

    public function flush(): void {
        if (empty($this->pendingOrders)) return;

        DB::transaction(function() {
            // Batch upsert - 단일 쿼리로 100개 처리
            Order::upsert(
                $this->pendingOrders,
                ['id'],
                ['status', 'executed_quantity', 'filled_quantity', 'updated_at']
            );
            Trade::insert($this->pendingTrades);
        });

        $this->pendingOrders = [];
        $this->pendingTrades = [];
    }
}
```

**예상 효과:**
- 100개 주문: 20ms (단일 트랜잭션)
- Per-order cost: 0.2ms
- TPS: 5,000+

#### Phase 2: Redis Stream 활성화 (1주, 예상 +5x latency 개선)

**현재 문제:**
```php
// predis는 XGROUP, XREAD 미지원
$messages = $redis->xread(['orders' => $lastId], 'BLOCK', 0);  // 에러
```

**해결 방안: phpredis 확장 사용**
```bash
pecl install redis
```

```php
// StreamMatchingEngineV2.php
class StreamMatchingEngineV2 {
    private \Redis $redis;  // phpredis 네이티브 클라이언트

    public function consume(): void {
        while ($this->running) {
            // Push 기반 - 즉시 수신
            $messages = $this->redis->xReadGroup(
                'matching-engine',
                $this->consumerId,
                ['spot:orders:stream:' . $this->symbol => '>'],
                50,  // count
                1000 // block ms
            );

            foreach ($messages as $stream => $entries) {
                foreach ($entries as $id => $data) {
                    $this->processOrder($data);
                    $this->redis->xAck($stream, 'matching-engine', [$id]);
                }
            }
        }
    }
}
```

**예상 효과:**
- Latency: 50ms → <1ms
- CPU 사용률: -80%

#### Phase 3: 심볼 기반 워커 샤딩 (1주)

```
┌─────────────────────────────────────────────────────────┐
│                   Symbol-based Workers                   │
├─────────────────────────────────────────────────────────┤
│                                                          │
│   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
│   │  Worker 1   │  │  Worker 2   │  │  Worker 3   │    │
│   │  BTC/USD    │  │  ETH/USD    │  │  Others     │    │
│   │  BTC/USDT   │  │  ETH/USDT   │  │             │    │
│   └──────┬──────┘  └──────┬──────┘  └──────┬──────┘    │
│          │                │                │            │
│          └────────────────┼────────────────┘            │
│                           │                             │
│                           ▼                             │
│              ┌─────────────────────────┐                │
│              │    Shared Write Buffer  │                │
│              │    (Batch DB Writer)    │                │
│              └─────────────────────────┘                │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**구현:**
```php
// artisan command
php artisan matching-engine:swoole usdt btc --shard=1
php artisan matching-engine:swoole usdt eth --shard=2
php artisan matching-engine:swoole usdt sol,xrp,doge --shard=3
```

#### Phase 4: Swoole 코루틴 완전 적용 (2주)

```php
// Coroutine-based non-blocking I/O
\Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

go(function() {
    $pool = new \Swoole\Database\PDOPool(
        (new \Swoole\Database\PDOConfig())
            ->withHost(env('DB_HOST'))
            ->withPort(env('DB_PORT'))
            // ...
    );

    // Connection pooling with coroutines
    $pdo = $pool->get();
    // Non-blocking query
    $result = $pdo->query("SELECT ...");
    $pool->put($pdo);
});
```

### 4.2 Future Backend 최적화 전략

Future는 이미 대부분 최적화되어 있으므로 미세 조정만 필요:

#### 4.2.1 DB 배치 간격 최적화

**현재:**
```typescript
setInterval(async () => { ... }, 500);  // 500ms
```

**개선:**
```typescript
setInterval(async () => { ... }, 100);  // 100ms
```

**예상 효과:**
- DB 반영 지연: 500ms → 100ms
- 배치 크기 자동 조정: 부하에 따라 50-500

#### 4.2.2 Kafka 파티션 최적화

**현재:**
- 샤드당 1 파티션

**개선:**
- 샤드당 3 파티션 (고트래픽 심볼)
- Consumer Group 병렬 처리

```bash
# 파티션 증설
kafka-topics.sh --alter --topic matching-engine-shard-1-input \
  --partitions 3 --bootstrap-server localhost:9092
```

#### 4.2.3 Java GC 튜닝

```bash
# G1GC 최적화
java -XX:+UseG1GC \
     -XX:MaxGCPauseMillis=50 \
     -XX:G1HeapRegionSize=16m \
     -Xms4g -Xmx8g \
     -jar MatchingEngine.jar
```

### 4.3 공유 인프라 최적화

#### 4.3.1 RDS 업그레이드

| 항목 | 현재 | 목표 | 비용 변화 |
|------|------|------|-----------|
| Instance | db.t3.large | db.r6g.xlarge | +$200/월 |
| IOPS | 3,000 (gp3) | 10,000 (io1) | +$100/월 |
| Connections | 100 | 500 | - |
| Read Replicas | 0 | 1 | +$150/월 |

#### 4.3.2 Redis 업그레이드

| 항목 | 현재 | 목표 | 비용 변화 |
|------|------|------|-----------|
| Instance | cache.t3.medium | cache.r6g.large | +$100/월 |
| Memory | 1.5GB | 6.5GB | - |
| Cluster | Single | 3 shards | +$200/월 |

#### 4.3.3 인프라 분리 옵션

**Option A: DB 인덱스 분리 (저비용)**
- 같은 RDS, 다른 데이터베이스
- 현재 구현됨 (future_db, spot_db)

**Option B: RDS 분리 (고비용, 고격리)**
- Spot 전용 RDS
- Future 전용 RDS
- +$300/월

**권장: Option A 유지** + IOPS 업그레이드

---

## 5. 아키텍처 개선 제안

### 5.1 목표 아키텍처 (5,000 TPS)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        Target Architecture (5,000 TPS)                           │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│   ┌─────────────────────────────────────────────────────────────────────────┐   │
│   │                            API Gateway (ALB)                             │   │
│   └─────────────────────────────────────┬───────────────────────────────────┘   │
│                                         │                                        │
│              ┌──────────────────────────┼──────────────────────────┐            │
│              │                          │                          │            │
│              ▼                          ▼                          ▼            │
│   ┌─────────────────────┐   ┌─────────────────────┐   ┌─────────────────────┐  │
│   │   Spot API Pods     │   │  Future API Pods    │   │   WebSocket Pods    │  │
│   │   (Swoole x 5)      │   │  (NestJS x 5)       │   │   (Socket.io x 3)   │  │
│   └──────────┬──────────┘   └──────────┬──────────┘   └─────────────────────┘  │
│              │                          │                                        │
│              │                          │                                        │
│   ┌──────────▼──────────┐   ┌──────────▼──────────┐                            │
│   │   Redis Stream      │   │   Kafka Cluster      │                            │
│   │   (Order Queue)     │   │   (3 Shards)         │                            │
│   │   - spot:btc        │   │   - shard-1 (BTC)    │                            │
│   │   - spot:eth        │   │   - shard-2 (ETH)    │                            │
│   │   - spot:others     │   │   - shard-3 (others) │                            │
│   └──────────┬──────────┘   └──────────┬──────────┘                            │
│              │                          │                                        │
│   ┌──────────▼──────────┐   ┌──────────▼──────────┐                            │
│   │  Spot Matching      │   │  Future Matching    │                            │
│   │  Workers (x 10)     │   │  Engine (Java x 3)  │                            │
│   │  - BTC Worker x 3   │   │  - Shard-1 Primary  │                            │
│   │  - ETH Worker x 3   │   │  - Shard-2 Primary  │                            │
│   │  - Others x 4       │   │  - Shard-3 Primary  │                            │
│   └──────────┬──────────┘   └──────────┬──────────┘                            │
│              │                          │                                        │
│              └─────────────┬────────────┘                                        │
│                            │                                                     │
│   ┌────────────────────────▼───────────────────────────┐                        │
│   │              Shared Write Buffer                    │                        │
│   │              (Async Batch Writer)                   │                        │
│   │              - 100ms flush interval                 │                        │
│   │              - 500 orders per batch                 │                        │
│   └────────────────────────┬───────────────────────────┘                        │
│                            │                                                     │
│   ┌────────────────────────▼───────────────────────────┐                        │
│   │                    Data Layer                       │                        │
│   │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │                        │
│   │  │  RDS MySQL  │  │   Redis     │  │  S3 (Logs)  │ │                        │
│   │  │  (r6g.xl)   │  │  (Cluster)  │  │             │ │                        │
│   │  │  10K IOPS   │  │  3 shards   │  │             │ │                        │
│   │  └─────────────┘  └─────────────┘  └─────────────┘ │                        │
│   └────────────────────────────────────────────────────┘                        │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 5.2 이벤트 드리븐 아키텍처

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         Event-Driven Flow (Spot)                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│   1. Order Placed                                                                │
│   ┌──────────────────────────────────────────────────────────────────────────┐  │
│   │  API Server                                                               │  │
│   │  └── Validate Order                                                       │  │
│   │      └── Publish to Redis Stream: "order.placed"                          │  │
│   │          └── Return 202 Accepted (async)                                  │  │
│   └──────────────────────────────────────────────────────────────────────────┘  │
│                                                                                  │
│   2. Order Matching                                                              │
│   ┌──────────────────────────────────────────────────────────────────────────┐  │
│   │  Matching Worker                                                          │  │
│   │  └── Consume "order.placed"                                               │  │
│   │      └── Match in HeapOrderBook (in-memory)                               │  │
│   │          └── Publish "order.matched" or "order.queued"                    │  │
│   └──────────────────────────────────────────────────────────────────────────┘  │
│                                                                                  │
│   3. Trade Persistence                                                           │
│   ┌──────────────────────────────────────────────────────────────────────────┐  │
│   │  DB Writer Worker                                                         │  │
│   │  └── Consume "order.matched"                                              │  │
│   │      └── Buffer trades (100ms window)                                     │  │
│   │          └── Batch INSERT/UPDATE                                          │  │
│   │              └── Publish "trade.persisted"                                │  │
│   └──────────────────────────────────────────────────────────────────────────┘  │
│                                                                                  │
│   4. Notification                                                                │
│   ┌──────────────────────────────────────────────────────────────────────────┐  │
│   │  Notification Worker                                                      │  │
│   │  └── Consume "trade.persisted"                                            │  │
│   │      └── Send WebSocket event                                             │  │
│   │          └── Update Redis cache                                           │  │
│   └──────────────────────────────────────────────────────────────────────────┘  │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 5.3 장애 복구 및 고가용성 전략

#### 5.3.1 Spot 매칭 엔진 HA

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                      Spot Matching Engine HA Strategy                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│   ┌─────────────────────┐     ┌─────────────────────┐                           │
│   │  Worker 1 (Active)  │     │  Worker 2 (Active)  │   Active-Active           │
│   │  BTC/USD Symbol     │     │  ETH/USD Symbol     │   per Symbol              │
│   │  ┌───────────────┐  │     │  ┌───────────────┐  │                           │
│   │  │ HeapOrderBook │  │     │  │ HeapOrderBook │  │                           │
│   │  │ (in-memory)   │  │     │  │ (in-memory)   │  │                           │
│   │  └───────────────┘  │     │  └───────────────┘  │                           │
│   └──────────┬──────────┘     └──────────┬──────────┘                           │
│              │                            │                                      │
│              │  Periodic Snapshot         │                                      │
│              ▼                            ▼                                      │
│   ┌─────────────────────────────────────────────────────────────────────────┐   │
│   │                     Redis (Snapshot Storage)                             │   │
│   │   Key: orderbook:btc:snapshot                                            │   │
│   │   Value: { buyOrders: [...], sellOrders: [...], timestamp: ... }         │   │
│   │   TTL: 60 seconds (refreshed every 30s)                                  │   │
│   └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
│   Recovery Flow:                                                                 │
│   1. Worker crash detected (health check fail)                                   │
│   2. K8s restarts pod                                                           │
│   3. New worker loads snapshot from Redis                                        │
│   4. Replays Redis Stream from last checkpoint                                   │
│   5. Worker becomes active (~5 seconds recovery)                                 │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

#### 5.3.2 Future 매칭 엔진 HA (이미 구현됨)

- Primary-Standby 구성 per shard
- Kafka Consumer Group 자동 리밸런싱
- ZooKeeper/Kafka 기반 리더 선출

---

## 6. 단계별 구현 로드맵

### Phase 1: Quick Wins (완료)

| 작업 | 상태 | 영향 |
|------|------|------|
| Dynamic Polling (1-50ms) | ✅ 완료 | -83% latency |
| Batch Matching (20/cycle) | ✅ 완료 | 20x throughput |
| ZPOPMIN (atomic pop) | ✅ 완료 | -50% Redis RTT |
| Heap OrderBook | ✅ 완료 | 456x faster insert |
| DB Indexes | ✅ 완료 | Faster queries |

**결과**: ~800 TPS 달성 가능

### Phase 2: Spot DB 배치 쓰기 (1주)

| 작업 | 노력 | 예상 TPS |
|------|------|----------|
| WriteBuffer 클래스 구현 | Medium | - |
| Batch INSERT/UPDATE | Medium | - |
| 트랜잭션 최적화 | Low | - |
| 테스트 및 검증 | Medium | - |

**목표**: 2,000 TPS

### Phase 3: Redis Stream + 워커 샤딩 (2주)

| 작업 | 노력 | 예상 TPS |
|------|------|----------|
| phpredis 확장 설치 | Low | - |
| StreamMatchingEngineV2 완성 | High | - |
| 심볼 기반 워커 분리 | Medium | - |
| Consumer Group 설정 | Low | - |

**목표**: 3,500 TPS

### Phase 4: Swoole 완전 적용 (2주)

| 작업 | 노력 | 예상 TPS |
|------|------|----------|
| Swoole Coroutine DB Pool | High | - |
| Connection Pooling | Medium | - |
| 비동기 I/O 전환 | High | - |
| 부하 테스트 | Medium | - |

**목표**: 5,000+ TPS

### Phase 5: 인프라 업그레이드 (1주)

| 작업 | 노력 | 비용 |
|------|------|------|
| RDS → r6g.xlarge | Low | +$200/월 |
| Redis → Cluster | Medium | +$300/월 |
| EKS 노드 증설 | Low | +$200/월 |

### 전체 타임라인

```
Week 1    ████████████████  Phase 2: DB Batch Write
Week 2    ████████████████  Phase 3: Redis Stream (1/2)
Week 3    ████████████████  Phase 3: Worker Sharding (2/2)
Week 4    ████████████████  Phase 4: Swoole Migration (1/2)
Week 5    ████████████████  Phase 4: Testing & Validation (2/2)
Week 6    ████████████████  Phase 5: Infrastructure Upgrade
```

---

## 7. 비용 대비 효과 분석

### 7.1 현재 비용 (개발 환경)

| 항목 | 월 비용 |
|------|---------|
| EKS Control Plane | $72 |
| EC2 (t3.large x 3, 9hr/day) | $23 |
| RDS (db.t3.large, 9hr/day) | $75 |
| Redis (cache.t3.medium, 9hr/day) | $13 |
| Kafka (t3.medium, 9hr/day) | $8 |
| NAT Instance | $3 |
| Storage (EBS, S3) | $20 |
| **합계** | **~$214** |

### 7.2 프로덕션 환경 예상 비용 (5,000 TPS)

| 항목 | 현재 | 목표 | 월 비용 |
|------|------|------|---------|
| EKS Control Plane | - | - | $72 |
| EKS Nodes | t3.large x 3 | c6i.2xlarge x 4 | $880 |
| Spot Discount | - | 70% | -$616 |
| RDS | db.t3.large | db.r6g.xlarge | $450 |
| Redis | cache.t3.medium | cache.r6g.large x 3 | $390 |
| Kafka | t3.medium | t3.large x 3 | $150 |
| ALB | - | - | $30 |
| Data Transfer | - | ~500GB | $50 |
| **합계 (On-Demand)** | | | **$2,022** |
| **합계 (Spot)** | | | **$1,406** |

### 7.3 ROI 분석

| 지표 | 현재 | 목표 | 개선 |
|------|------|------|------|
| TPS | 200 | 5,000 | 25x |
| 월 비용 | $214 | $1,406 | 6.5x |
| 비용/M 트랜잭션 | $0.41 | $0.11 | 73% 절감 |

**결론**: 비용 6.5배 증가로 처리량 25배 향상, 트랜잭션당 비용 73% 절감

---

## 8. 리스크 평가 및 완화 전략

| 리스크 | 확률 | 영향 | 완화 전략 |
|--------|------|------|-----------|
| Swoole 호환성 이슈 | Medium | High | 단계적 마이그레이션, 롤백 계획 |
| Redis Cluster 장애 | Low | Critical | Multi-AZ, 자동 페일오버 |
| DB 커넥션 고갈 | Medium | High | 커넥션 풀 확장, 모니터링 |
| 트래픽 급증 (10x) | Low | Medium | Auto-scaling, Circuit Breaker |
| 데이터 불일치 | Low | Critical | 이벤트 소싱, 재처리 메커니즘 |

---

## 9. 모니터링 및 알림

### 9.1 핵심 메트릭

| 메트릭 | 목표 | 알림 임계값 |
|--------|------|-------------|
| Order Latency P99 | <10ms | >50ms |
| Match Latency P99 | <5ms | >20ms |
| TPS | 5,000 | <4,000 |
| Error Rate | <0.01% | >0.1% |
| Queue Depth | <100 | >1,000 |
| DB Connections | <400 | >450 |

### 9.2 대시보드 구성

```
Grafana Dashboards:
├── Matching Engine Overview
│   ├── TPS (real-time)
│   ├── Latency percentiles (P50, P95, P99)
│   └── Error rates by type
├── Infrastructure
│   ├── CPU/Memory usage per pod
│   ├── Redis operations/sec
│   ├── DB connections & IOPS
│   └── Kafka consumer lag
└── Business Metrics
    ├── Orders per symbol
    ├── Trade volume (USD)
    └── Active users
```

---

## 10. 결론 및 권고사항

### 10.1 핵심 권고사항

1. **Spot 우선 최적화**: Future는 이미 최적화됨. Spot의 DB 배치 쓰기가 가장 큰 ROI
2. **단계적 접근**: Phase 2 → 3 → 4 순서로 점진적 개선
3. **인프라 선행 준비**: RDS IOPS 증설을 Phase 2 시작 전에 완료
4. **모니터링 강화**: 각 Phase 완료 후 메트릭 기반 검증

### 10.2 Next Steps

1. **즉시 (Week 1)**: Spot WriteBuffer 클래스 구현 및 테스트
2. **단기 (Week 2-3)**: phpredis 설치, Redis Stream 활성화
3. **중기 (Week 4-5)**: Swoole 코루틴 완전 적용
4. **완료 (Week 6)**: 인프라 업그레이드 및 부하 테스트

### 10.3 성공 기준

- [ ] Sustained 5,000 TPS for 1 hour
- [ ] P99 latency < 10ms
- [ ] Zero order loss
- [ ] Error rate < 0.01%
- [ ] Cost per million transactions < $0.15

---

## Appendix A: 벤치마크 명령어

```bash
# Spot - HeapOrderBook 벤치마크
cd spot-backend
php benchmarks/heap-orderbook-benchmark.php

# Spot - 전체 시스템 벤치마크
php benchmarks/orderbook-benchmark-fast.php

# Future - OrderRouter 성능 테스트
cd future-backend
npx ts-node test/performance/order-router-benchmark.ts --orders=100000

# Future - 전체 성능 테스트
./test/performance/run-perf-test.sh all
```

## Appendix B: 설정 파일 위치

| 시스템 | 파일 | 설명 |
|--------|------|------|
| Spot | `.env` | 환경 변수 |
| Spot | `config/database.php` | DB 설정 |
| Future | `config/default.yml` | 샤딩 설정 |
| Future | `src/configs/matching.config.ts` | 매칭 엔진 설정 |
| Infra | `infra/config/dev.ts` | AWS CDK 설정 |

## Appendix C: 참고 문서

- [Swoole Documentation](https://www.swoole.co.uk/docs/)
- [Redis Streams](https://redis.io/docs/data-types/streams/)
- [Kafka Best Practices](https://kafka.apache.org/documentation/)
- [AWS EKS Best Practices](https://aws.github.io/aws-eks-best-practices/)
- `spot-backend/docs/plans/5000-tps-infrastructure-plan.md` (기존 계획)
- `future-backend/docs/implementation-guide/matching-engine-sharding.md` (샤딩 가이드)

# Spot Backend Functional Requirements (기능 요구사항)

> **SSOT (Single Source of Truth)**: 이 문서는 모든 성능 수치의 단일 정의 출처입니다.
> 다른 문서에서 수치 언급 시 `FR-XX-XXX` 형식으로 참조하세요.

## Performance Requirements (성능 요구사항)

### FR-PF-001: Matching Engine TPS
- **Target**: 5,000 TPS (Swoole 모드)
- **Minimum**: 2,000 TPS (Stream 모드)
- **Current**: ~1,000 TPS (개선 필요)

### FR-PF-002: Order Processing Latency
- **Target p50**: < 10ms
- **Target p99**: < 50ms
- **Current p99**: ~200ms (개선 필요)

### FR-PF-003: OrderBook Insertion Time
- **Target**: O(log n) - Heap 기반
- **Current**: O(n) - Array 기반 (개선 필요)

### FR-PF-004: Database Query Reduction
- **Target**: 80% 감소 (캐시 적용 후)
- **Method**: Redis 캐시 레이어

### FR-PF-005: Batch Processing Size
- **Swoole Mode**: 50 orders/cycle
- **Stream Mode**: 20 orders/cycle

---

## Reliability Requirements (안정성 요구사항)

### FR-RL-001: Circuit Breaker Thresholds
- **Failure Threshold**: 5 consecutive failures
- **Recovery Timeout**: 30 seconds
- **Half-Open Test Requests**: 3

### FR-RL-002: Retry Policy
- **Max Retries**: 3
- **Base Delay**: 100ms
- **Max Delay**: 30 seconds
- **Strategy**: Exponential backoff with jitter

### FR-RL-003: Dead Letter Queue
- **Max Retry Before DLQ**: 3
- **DLQ Retention**: 7 days
- **Alert Threshold**: 100 messages/hour

### FR-RL-004: Health Check Intervals
- **Liveness**: 10 seconds
- **Readiness**: 5 seconds
- **DB Connection Check**: 30 seconds

---

## Observability Requirements (관측성 요구사항)

### FR-OB-001: Metrics Collection
- **Order Processing Latency**: Histogram (buckets: 1, 5, 10, 25, 50, 100, 250, 500, 1000ms)
- **TPS**: Counter per symbol
- **Error Rate**: Counter by type
- **Queue Depth**: Gauge

### FR-OB-002: Correlation ID Format
- **Format**: `{YYYYMMDD}-{random_hex_16}`
- **Example**: `20260123-a1b2c3d4e5f67890`

### FR-OB-003: Log Retention
- **Application Logs**: 30 days
- **Error Logs**: 90 days
- **Audit Logs**: 1 year

---

## Data Structure Requirements (자료구조 요구사항)

### FR-DS-001: Heap Implementation
- **Buy Orders**: MaxHeap (highest price first)
- **Sell Orders**: MinHeap (lowest price first)
- **Time Complexity**: O(log n) insert, O(1) peek, O(log n) extract

### FR-DS-002: Order Cache
- **TTL**: 3600 seconds (1 hour)
- **Eviction**: LRU
- **Key Format**: `order:{order_id}`

### FR-DS-003: Redis Stream
- **Consumer Group**: `matching-engine-group`
- **Max Pending Age**: 60 seconds
- **Batch Read Size**: 10-50 (configurable)

---

## Database Requirements (데이터베이스 요구사항)

### FR-DB-001: Required Indexes
```sql
-- Index 1: OrderBook loading
CREATE INDEX idx_orders_currency_coin_status ON orders(currency, coin, status);

-- Index 2: Status queries
CREATE INDEX idx_orders_status_updated ON orders(status, updated_at);

-- Index 3: User trading pairs
CREATE INDEX idx_orders_user_currency_coin ON orders(user_id, currency, coin);
```

### FR-DB-002: Connection Pool
- **Master Pool Size**: 20
- **Report Pool Size**: 10
- **Connection Timeout**: 5 seconds

---

## Interface Requirements (인터페이스 요구사항)

### FR-IF-001: OrderQueueInterface
```php
interface OrderQueueInterface {
    public function push(array $order): void;
    public function pop(int $batchSize): array;
    public function ack(string $messageId): void;
    public function nack(string $messageId): void;
    public function sendToDLQ(string $messageId, array $data, string $reason): void;
}
```

### FR-IF-002: CircuitBreakerInterface
```php
interface CircuitBreakerInterface {
    public function execute(callable $action): mixed;
    public function getState(): string;  // CLOSED, OPEN, HALF_OPEN
    public function reset(): void;
}
```

---

## Priority Matrix

| ID | Category | Priority | Status |
|----|----------|----------|--------|
| FR-DS-001 | Data Structure | P0 | 🔴 TODO |
| FR-PF-004 | Performance | P0 | 🔴 TODO |
| FR-DB-001 | Database | P0 | 🔴 TODO |
| FR-RL-003 | Reliability | P0 | 🔴 TODO |
| FR-RL-001 | Reliability | P1 | 🔴 TODO |
| FR-RL-002 | Reliability | P1 | 🔴 TODO |
| FR-IF-001 | Interface | P1 | 🔴 TODO |
| FR-OB-001 | Observability | P2 | 🔴 TODO |
| FR-OB-002 | Observability | P2 | 🔴 TODO |

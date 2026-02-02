# Technical Design: Spot Backend Performance Upgrade

## 1. Overview

spot-backend 매칭 엔진의 성능 및 안정성 개선을 위한 기술 설계 문서.

## 2. Architecture Changes

### 2.1 Current vs Target Architecture

```
[Current]
Order → Redis Queue → Array-based OrderBook → DB Query per Order → Response
                           O(n) insert           N+1 problem

[Target]
Order → Redis Stream → Heap-based OrderBook → Redis Cache → Response
                           O(log n) insert        80% less DB
```

### 2.2 Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Matching Engine                          │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │ OrderQueue   │───▶│ HeapOrderBook│───▶│ TradeExecutor│      │
│  │ Interface    │    │ (SplHeap)    │    │              │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
│         │                   │                   │               │
│         ▼                   ▼                   ▼               │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │ Redis Stream │    │ Order Cache  │    │ Circuit      │      │
│  │ + DLQ        │    │ (Redis)      │    │ Breaker      │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
└─────────────────────────────────────────────────────────────────┘
```

## 3. Detailed Design

### 3.1 Heap-based OrderBook (FR-DS-001)

```php
// Buy orders: MaxHeap (highest price first)
class BuyOrderHeap extends SplMaxHeap {
    protected function compare($a, $b): int {
        $priceCompare = bccomp($a['price'], $b['price'], 8);
        if ($priceCompare !== 0) return $priceCompare;
        return $a['timestamp'] <=> $b['timestamp']; // FIFO for same price
    }
}

// Sell orders: MinHeap (lowest price first)
class SellOrderHeap extends SplMinHeap {
    protected function compare($a, $b): int {
        $priceCompare = bccomp($b['price'], $a['price'], 8);
        if ($priceCompare !== 0) return $priceCompare;
        return $a['timestamp'] <=> $b['timestamp'];
    }
}
```

### 3.2 Redis Cache Layer (FR-PF-004)

```php
class OrderCacheService {
    private const TTL = 3600; // FR-DS-002

    public function get(int $orderId): ?array {
        $cached = Redis::hgetall("order:{$orderId}");
        return $cached ?: null;
    }

    public function set(int $orderId, array $data): void {
        Redis::hmset("order:{$orderId}", $data);
        Redis::expire("order:{$orderId}", self::TTL);
    }

    public function invalidate(int $orderId): void {
        Redis::del("order:{$orderId}");
    }
}
```

### 3.3 Circuit Breaker (FR-RL-001)

```php
class CircuitBreaker {
    private const FAILURE_THRESHOLD = 5;  // FR-RL-001
    private const RECOVERY_TIMEOUT = 30;  // FR-RL-001

    private string $state = 'CLOSED';
    private int $failureCount = 0;
    private ?int $lastFailureTime = null;

    public function execute(callable $action): mixed {
        if ($this->state === 'OPEN') {
            if (time() - $this->lastFailureTime >= self::RECOVERY_TIMEOUT) {
                $this->state = 'HALF_OPEN';
            } else {
                throw new CircuitOpenException();
            }
        }

        try {
            $result = $action();
            $this->onSuccess();
            return $result;
        } catch (Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }
}
```

### 3.4 Dead Letter Queue (FR-RL-003)

```php
class DeadLetterQueue {
    private const MAX_RETRIES = 3;  // FR-RL-003
    private const DLQ_KEY = 'matching-engine:dlq';

    public function shouldMoveToDLQ(string $messageId): bool {
        $retries = (int) Redis::get("retry:{$messageId}") ?? 0;
        return $retries >= self::MAX_RETRIES;
    }

    public function send(string $messageId, array $data, string $reason): void {
        Redis::xadd(self::DLQ_KEY, '*', [
            'original_id' => $messageId,
            'data' => json_encode($data),
            'reason' => $reason,
            'timestamp' => time(),
        ]);
    }
}
```

### 3.5 Exponential Backoff (FR-RL-002)

```php
class RetryPolicy {
    private const MAX_RETRIES = 3;      // FR-RL-002
    private const BASE_DELAY_MS = 100;  // FR-RL-002
    private const MAX_DELAY_MS = 30000; // FR-RL-002

    public function getDelay(int $attempt): int {
        $delay = min(
            self::BASE_DELAY_MS * pow(2, $attempt),
            self::MAX_DELAY_MS
        );
        $jitter = random_int(0, (int)($delay * 0.1));
        return $delay + $jitter;
    }
}
```

## 4. Database Schema Changes

### 4.1 New Indexes (FR-DB-001)

```sql
-- Migration: add_performance_indexes
ALTER TABLE orders ADD INDEX idx_orders_currency_coin_status (currency, coin, status);
ALTER TABLE orders ADD INDEX idx_orders_status_updated (status, updated_at);
ALTER TABLE orders ADD INDEX idx_orders_user_currency_coin (user_id, currency, coin);
```

## 5. Interface Definitions

### 5.1 OrderQueueInterface (FR-IF-001)

```php
interface OrderQueueInterface {
    public function push(array $order): void;
    public function pop(int $batchSize): array;
    public function ack(string $messageId): void;
    public function nack(string $messageId): void;
    public function sendToDLQ(string $messageId, array $data, string $reason): void;
}
```

## 6. Migration Strategy

1. **Phase 1**: 새 컴포넌트 추가 (기존 코드 영향 없음)
2. **Phase 2**: Feature flag로 점진적 전환
3. **Phase 3**: 기존 코드 제거

## 7. Testing Strategy

- Unit tests: Heap 정렬, Circuit Breaker 상태 전이
- Integration tests: Redis 캐시, DLQ 흐름
- Load tests: 5,000 TPS 벤치마크

# Prompt History

## Date
2026-01-25 02:06:37 KST

## Prompt
WriteBuffer 클래스 구현 - Spot Backend 5,000 TPS 성능 최적화 Phase 2의 핵심 컴포넌트로, Future-backend의 saveAccountsV2 패턴을 참조하여 DB 배치 쓰기 기능 구현

## Result

### 구현 개요

Spot Backend의 성능 병목인 DB 동기 쓰기를 해결하기 위해 WriteBuffer 클래스를 구현했습니다.
Future-backend의 `saveAccountsV2` 패턴을 참조하여 비동기 배치 쓰기를 지원합니다.

**성능 개선 목표:**
- Before: 5-10ms per order (동기 쓰기) → 100-200 TPS
- After: 100 orders in 20ms batch → 0.2ms per order → 5,000 TPS

### 생성된 파일

| 파일 | 경로 | 설명 |
|------|------|------|
| WriteBufferInterface.php | `app/Services/Buffer/` | 버퍼 인터페이스 정의 |
| WriteBuffer.php | `app/Services/Buffer/` | 비동기 배치 쓰기 구현 (Production) |
| SyncWriteBuffer.php | `app/Services/Buffer/` | 동기 쓰기 구현 (Testing) |
| FlushResult.php | `app/Services/Buffer/` | Flush 결과 DTO |
| WriteBufferFactory.php | `app/Services/Buffer/` | 환경별 버퍼 팩토리 |
| WriteBufferTest.php | `tests/Unit/Services/Buffer/` | WriteBuffer 단위 테스트 (13개) |
| WriteBufferFactoryTest.php | `tests/Unit/Services/Buffer/` | Factory 단위 테스트 (5개) |

### 핵심 기능

#### 1. WriteBuffer (비동기 배치 쓰기)
```php
class WriteBuffer implements WriteBufferInterface
{
    // Map<orderId, orderData> - 동일 order 업데이트 병합
    private array $orderBuffer = [];

    // Set<userId:currency> - 동일 계정 잔액 변경 누적
    private array $balanceBuffer = [];

    // 주기적 flush (500ms 또는 100개 도달 시)
    public function shouldFlush(): bool;

    // Deadlock 자동 재시도 (최대 3회)
    private function retryFlush(): FlushResult;
}
```

#### 2. Future-backend 패턴 적용
- `Map<id, data>` → PHP `array $orderBuffer[orderId]`
- `Set<id>` → PHP `array_keys($orderBuffer)`
- `setInterval(500ms)` → `shouldFlush()` + `getTimeSinceLastFlush()`
- `insertOrUpdate` → MySQL `INSERT ... ON DUPLICATE KEY UPDATE`
- Deadlock retry → `retryFlush()` with exponential backoff

#### 3. 환경별 자동 선택
```php
WriteBufferFactory::create()
// Testing: SyncWriteBuffer (즉시 쓰기)
// Production: WriteBuffer (배치 쓰기)
```

### 테스트 결과

```
Tests\Unit\Services\Buffer\WriteBufferTest
  ✓ it adds orders to buffer
  ✓ it merges multiple updates to same order
  ✓ it adds trades to buffer
  ✓ it adds balance updates to buffer
  ✓ it merges balance updates for same user currency
  ✓ it should flush when buffer reaches max size
  ✓ it should not flush when buffer is below max size
  ✓ it should flush when interval elapsed with data
  ✓ it clears buffer
  ✓ it returns empty result when nothing to flush
  ✓ flush result provides correct statistics
  ✓ flush result marks failure with errors
  ✓ it tracks statistics correctly

Tests\Unit\Services\Buffer\WriteBufferFactoryTest
  ✓ it creates sync buffer in testing environment
  ✓ it creates sync buffer explicitly
  ✓ it creates async buffer with custom config
  ✓ sync buffer has correct stats structure
  ✓ async buffer has correct stats structure

Tests: 18 passed
Total Unit Tests: 43 passed
```

### 환경 변수 설정

```env
# WriteBuffer 설정
WRITE_BUFFER_MAX_SIZE=100          # 버퍼 최대 크기
WRITE_BUFFER_FLUSH_INTERVAL_MS=500 # Flush 주기 (ms)
WRITE_BUFFER_MAX_RETRIES=3         # Deadlock 재시도 횟수
WRITE_BUFFER_SYNC=false            # true = 동기 모드 (디버깅용)
```

### 다음 단계

| 우선순위 | 작업 | 상태 |
|----------|------|------|
| 1 | WriteBuffer를 ProcessOrder/OrderService에 통합 | Pending |
| 2 | phpredis 확장 설치 (Redis Stream 활성화) | Pending |
| 3 | RDS IOPS 증설 (3K → 10K) | Pending |

### 관련 문서

- `spot-backend/docs/plans/5000-tps-infrastructure-plan.md` - 전체 성능 최적화 계획
- `docs/plans/2026-01-24-5000-tps-architecture-review.md` - 아키텍처 검토
- `future-backend/src/modules/matching-engine/matching-engine.service.ts` - saveAccountsV2 참조

## API Usage
해당 없음 (외부 API 호출 없음)

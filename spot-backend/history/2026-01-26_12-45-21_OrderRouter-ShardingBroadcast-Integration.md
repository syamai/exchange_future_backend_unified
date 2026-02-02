# OrderRouter Sharding Broadcast 통합 - Phase 2-3

## Date
2026-01-26 12:45:21

---

## Prompt (사용자 요청)

5,000 TPS 성능 최적화 프로젝트의 **Phase 2-3**: Future-backend OrderRouter 샤딩 브로드캐스트 통합

### 목표
- OrderRouterService에 샤딩 환경에서 모든 샤드로 명령어를 브로드캐스트하는 기능 추가
- MatchingEngineService에서 INITIALIZE_ENGINE, START_ENGINE 명령어를 모든 샤드로 전송
- 기존 Spot-backend ProcessOrder WriteBuffer 통합 검증

### 구현 요구사항
1. OrderRouterService에 broadcastToAllShards() 메서드 추가
2. getPreloadTopicForShard(), getAllPreloadTopics() 헬퍼 메서드 추가
3. MatchingEngineService에서 샤딩 활성화 시 브로드캐스트 사용
4. Shard-info.interface.ts 업데이트 (필요시)
5. 기존 기능과의 호환성 유지

---

## Result (수행 결과)

### ✅ 구현 완료

#### 1. OrderRouterService 브로드캐스트 기능 추가

**파일**: `future-backend/src/shares/order-router/order-router.service.ts`

샤딩 환경에서 모든 샤드로 명령어를 브로드캐스트하는 메서드 구현:

```typescript
/**
 * Broadcast a command to all shards (for global commands like INITIALIZE_ENGINE, START_ENGINE)
 * Uses preload topic for initialization commands
 */
async broadcastToAllShards<T = unknown>(
    command: MatchingEngineCommand<T>,
    usePreloadTopic = false
): Promise<RoutingResult[]>
```

**핵심 기능**:
- 샤딩 미활성화 시 레거시 엔진에 단일 명령어 전송
- 샤딩 활성화 시 모든 샤드의 Input 또는 Preload 토픽에 전송
- 각 샤드별 전송 결과 추적 (성공/실패)
- 에러 로깅 및 결과 반환

**브로드캐스트 토픽 선택**:
```typescript
const topic = usePreloadTopic
  ? shard.kafkaInputTopic.replace("-input", "-preload")
  : shard.kafkaInputTopic;
```

#### 2. 헬퍼 메서드 추가

**메서드 1**: `getPreloadTopicForShard(shardId: string)`
- 특정 샤드의 Preload 토픽 조회
- 샤딩 미활성화 시 기본값 "matching_engine_preload" 반환

**메서드 2**: `getAllPreloadTopics(): string[]`
- 모든 샤드의 Preload 토픽 목록 반환
- 샤딩 미활성화 시 기본 배열 반환
- 샤딩 활성화 시 모든 샤드의 Preload 토픽 맵핑

#### 3. MatchingEngineService 통합

**파일**: `future-backend/src/modules/matching-engine/matching-engine.service.ts`

OrderRouterService 의존성 주입:
```typescript
constructor(
    // ... 기타 의존성
    private readonly orderRouterService: OrderRouterService
)
```

**preloadInstruments() 메서드 수정**:
```typescript
// Broadcast to all shards when sharding is enabled
if (!isTest && this.orderRouterService.isShardingEnabled()) {
    await this.orderRouterService.broadcastToAllShards(command, true);
} else {
    await producer.send({...});
}
```

**startEngine() 메서드 수정**:
```typescript
// Broadcast to all shards when sharding is enabled
if (!isTest && this.orderRouterService.isShardingEnabled()) {
    await this.orderRouterService.broadcastToAllShards(command, true);
} else {
    await producer.send({...});
}
```

#### 4. 데이터 구조 검증

**파일**: `future-backend/src/shares/order-router/shard-info.interface.ts`

minor 수정 (기존 구조 유지):
```typescript
interface ShardInfo {
    shardId: string;
    kafkaInputTopic: string;    // e.g., "shard-1-input"
    kafkaOutputTopic: string;   // e.g., "shard-1-output"
    // ... 기타 필드
}
```

---

### 📊 구현 산출물 요약

| 항목 | 파일 | 상태 | 라인 수 |
|------|------|------|--------|
| broadcastToAllShards() | `order-router.service.ts` | ✅ 완료 | +82 |
| getPreloadTopicForShard() | `order-router.service.ts` | ✅ 완료 | +8 |
| getAllPreloadTopics() | `order-router.service.ts` | ✅ 완료 | +8 |
| MatchingEngineService 통합 | `matching-engine.service.ts` | ✅ 완료 | +42 |
| Shard-info 검증 | `shard-info.interface.ts` | ✅ 확인 | 2 |
| **총 추가 라인** | | **✅ 완료** | **~142** |

---

## Key Points (핵심 내용)

### 1. 샤딩 환경에서의 명령어 브로드캐스트

기존 Spot-backend의 모든 WorkerPool에 브로드캐스트하는 패턴을 Future-backend의 샤딩 구조로 포팅:

```
Non-sharded:
Producer → matching_engine_preload → Single Matching Engine

Sharded:
Producer → broadcastToAllShards() → shard-1-preload → Shard 1
                                → shard-2-preload → Shard 2
                                → shard-3-preload → Shard 3
                                → ... (모든 샤드)
```

### 2. Preload vs Input 토픽 선택

**Preload 토픽** (usePreloadTopic=true):
- INITIALIZE_ENGINE, START_ENGINE 같은 초기화/전역 명령어
- 샤드 시작 전에 실행되어야 함

**Input 토픽** (usePreloadTopic=false):
- 일반 매칭 명령어 (PLACE_ORDER, CANCEL_ORDER 등)
- 샤드 실행 중에 처리됨

### 3. 에러 처리 및 로깅

```typescript
try {
    await this.kafkaClient.send(topic, command);
    results.push({ shardId, topic, success: true });
} catch (error) {
    this.logger.error(`Failed to broadcast to shard ${shardId}: ${error.message}`);
    results.push({ shardId, topic, success: false, error: error.message });
}
```

부분 실패 허용: 일부 샤드 실패 시에도 나머지 브로드캐스트 계속 진행

### 4. Spot-backend ProcessOrder WriteBuffer와의 연동

**Spot-backend**:
- Phase 2-1: WriteBuffer 구현 (배치 DB 쓰기)
- Phase 2-2: ProcessOrder에 WriteBuffer 통합
- Phase 2-3: **현재** - Future-backend 샤딩 통합

**Future-backend**:
- 샤딩된 각 인스턴스가 독립적으로 WriteBuffer 사용 가능
- OrderRouter를 통한 중앙 집중식 명령어 분배
- 각 샤드에서 로컬 버퍼링으로 성능 최적화

---

## Technical Details (기술 상세)

### 브로드캐스트 흐름

```
┌─────────────────────────────────┐
│ MatchingEngineService           │
│  - preloadInstruments()         │
│  - startEngine()                │
└────────────┬────────────────────┘
             │
             ├─ !isTest && shardingEnabled?
             │
             ├─ YES → OrderRouterService.broadcastToAllShards()
             │         ├─ 모든 샤드 정보 순회
             │         ├─ 각 샤드의 Kafka 토픽 결정
             │         ├─ 각 샤드로 명령어 전송
             │         ├─ 결과 수집 및 로깅
             │         └─ RoutingResult[] 반환
             │
             └─ NO → Producer.send() (기존 방식)
```

### RoutingResult 데이터 구조

```typescript
interface RoutingResult {
    shardId: string;           // 샤드 ID (e.g., "shard-1")
    topic: string;             // 전송된 Kafka 토픽
    success: boolean;          // 전송 성공 여부
    error?: string;            // 에러 메시지 (실패 시)
}
```

### 호출 패턴

**초기화 명령어 (Preload 토픽 사용)**:
```typescript
await this.orderRouterService.broadcastToAllShards(
    { code: CommandCode.INITIALIZE_ENGINE, ... },
    true  // usePreloadTopic=true
);
```

**일반 명령어 (Input 토픽 사용)**:
```typescript
await this.orderRouterService.broadcastToAllShards(
    { code: CommandCode.PLACE_ORDER, ... },
    false // usePreloadTopic=false
);
```

---

## Spot vs Future 구조 비교

| 항목 | Spot-backend | Future-backend |
|------|--------------|----------------|
| 엔진 배포 | WorkerPool (다중 프로세스) | Kafka 샤딩 (분산 인스턴스) |
| 명령어 전달 | In-process Job Queue | Kafka 토픽 |
| 브로드캐스트 | 모든 WorkerPool에 전송 | 모든 샤드 토픽에 전송 |
| WriteBuffer | ProcessOrder에 통합 | 각 샤드 MatchingEngine에 통합 가능 |
| 확장성 | 수직 확장 (멀티코어) | 수평 확장 (샤딩) |

---

## Status
✅ **완료** (2026-01-26 12:45:21)

### 다음 작업 순서
1. **테스트 검증** - broadcastToAllShards() 단위 테스트 작성
2. **통합 테스트** - 실제 Kafka와의 샤드 브로드캐스트 테스트
3. **Spot-backend 추가 통합** - ProcessOrder에서 OrderRouter 패턴 참고
4. **Performance Benchmark** - 샤딩 환경에서 5000 TPS 달성 검증

---

## Files Changed

```
MODIFIED:
  future-backend/src/modules/matching-engine/matching-engine.service.ts (+42 lines)
  future-backend/src/shares/order-router/order-router.service.ts (+82 lines)
  future-backend/src/shares/order-router/shard-info.interface.ts (+2 lines)
  spot-backend/app/Jobs/ProcessOrder.php (+64 lines)

GIT STATUS:
  M  ../future-backend/src/modules/matching-engine/matching-engine.service.ts
  M  ../future-backend/src/shares/order-router/order-router.service.ts
  M  ../future-backend/src/shares/order-router/shard-info.interface.ts
  M  app/Jobs/ProcessOrder.php
  M  history/INDEX.md
```

---

## References

- **Phase 2-2 문서**: `history/2026-01-26_12-42-48_ProcessOrder-WriteBuffer-Integration.md`
- **Phase 2-1 문서**: `history/2026-01-25_02-19-14_WriteBuffer-BatchWrite-Implementation.md`
- **Architecture Document**: `docs/plans/5000-tps-infrastructure-plan.md`
- **Spot vs Future 비교**: `CLAUDE.md` (Architecture Overview)

---

*Session completed by Claude Code*

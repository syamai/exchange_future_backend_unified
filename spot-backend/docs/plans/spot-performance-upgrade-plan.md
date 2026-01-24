# Work Plan: Spot Backend Performance Upgrade

## Task Overview

| Priority | Task | Estimated Complexity |
|----------|------|---------------------|
| P0-1 | Heap 자료구조 전환 | Medium |
| P0-2 | Redis 캐시 레이어 | Medium |
| P0-3 | DB 인덱스 추가 | Low |
| P0-4 | Dead Letter Queue | Medium |
| P1-1 | Circuit Breaker | Medium |
| P1-2 | 지수 백오프 재시도 | Low |
| P1-3 | OrderQueue 인터페이스 | Medium |
| P2-1 | Prometheus 메트릭 | Low |
| P2-2 | Correlation ID | Low |
| P2-3 | 테스트 확대 | Medium |

## Dependency Graph

```
P0-3 (Index) ─────────────────────────────┐
                                          │
P0-1 (Heap) ──────┬──▶ P0-2 (Cache) ──────┼──▶ P1-3 (Interface)
                  │                       │
P0-4 (DLQ) ───────┼──▶ P1-1 (CB) ─────────┘
                  │         │
                  │         ▼
                  └──▶ P1-2 (Retry) ──▶ P2-1 (Metrics)
                                              │
                                              ▼
                                        P2-2 (CorrelationID)
                                              │
                                              ▼
                                        P2-3 (Tests)
```

## Execution Order

### Batch 1 (Parallel)
- [x] P0-3: DB 인덱스 추가
- [x] P0-1: Heap 자료구조 전환

### Batch 2 (Parallel)
- [ ] P0-2: Redis 캐시 레이어
- [ ] P0-4: Dead Letter Queue

### Batch 3 (Parallel)
- [ ] P1-1: Circuit Breaker
- [ ] P1-2: 지수 백오프

### Batch 4
- [ ] P1-3: OrderQueue 인터페이스 추상화

### Batch 5 (Parallel)
- [ ] P2-1: Prometheus 메트릭
- [ ] P2-2: Correlation ID

### Batch 6
- [ ] P2-3: 테스트 커버리지 확대

# PRD: Spot Backend Performance & Reliability Upgrade

## Overview

### Problem Statement
현재 spot-backend의 매칭 엔진은 목표 TPS(5,000)에 미달하며, 장애 복구 메커니즘이 부족합니다.

### Goals
1. **성능 향상**: FR-PF-001 참조 (5,000 TPS 달성)
2. **안정성 강화**: 장애 시 자동 복구 및 데이터 손실 방지
3. **관측성 개선**: 실시간 모니터링 및 추적 가능

### Non-Goals
- 새로운 주문 유형 추가
- UI/UX 변경
- API 인터페이스 변경

---

## User Stories

### US-001: 고성능 주문 처리
**As a** trader
**I want** 주문이 빠르게 처리되기를
**So that** 시장 변동에 신속히 대응할 수 있다

**Acceptance Criteria:**
- [ ] 주문 처리 지연시간 p99 < 50ms (FR-PF-002 참조)
- [ ] 초당 5,000건 이상 처리 가능 (FR-PF-001 참조)

### US-002: 주문 손실 방지
**As a** trader
**I want** 시스템 장애 시에도 주문이 손실되지 않기를
**So that** 자산을 안전하게 거래할 수 있다

**Acceptance Criteria:**
- [ ] 실패 주문은 DLQ로 이동 (FR-RL-003 참조)
- [ ] DLQ 메시지 수동 재처리 가능

### US-003: 시스템 장애 자동 복구
**As an** operator
**I want** DB 장애 시 시스템이 자동 복구되기를
**So that** 수동 개입 없이 서비스 연속성 유지

**Acceptance Criteria:**
- [ ] Circuit Breaker 패턴 적용 (FR-RL-001 참조)
- [ ] 30초 내 자동 복구 시도

---

## Requirements

### P0: Critical (Must Have)

| ID | Requirement | Reference |
|----|-------------|-----------|
| REQ-P0-001 | InMemoryOrderBook을 Heap 기반으로 전환 | FR-DS-001 |
| REQ-P0-002 | Redis 캐시 레이어 추가로 DB 쿼리 80% 감소 | FR-PF-004 |
| REQ-P0-003 | 필수 DB 인덱스 추가 | FR-DB-001 |
| REQ-P0-004 | Dead Letter Queue 구현 | FR-RL-003 |

### P1: Important (Should Have)

| ID | Requirement | Reference |
|----|-------------|-----------|
| REQ-P1-001 | Circuit Breaker 패턴 구현 | FR-RL-001 |
| REQ-P1-002 | 지수 백오프 재시도 로직 | FR-RL-002 |
| REQ-P1-003 | OrderQueueInterface 추상화 | FR-IF-001 |

### P2: Nice to Have

| ID | Requirement | Reference |
|----|-------------|-----------|
| REQ-P2-001 | Prometheus 메트릭 수집 | FR-OB-001 |
| REQ-P2-002 | Correlation ID 추적 | FR-OB-002 |
| REQ-P2-003 | 테스트 커버리지 80% 달성 | - |

---

## Success Metrics

| Metric | Current | Target | Measurement |
|--------|---------|--------|-------------|
| TPS (Swoole) | ~1,000 | 5,000 | Benchmark test |
| p99 Latency | ~200ms | <50ms | APM metrics |
| DB Queries/Order | ~3 | ~0.6 | Query log |
| Error Recovery Time | Manual | <30s | Incident log |

---

## Timeline

| Phase | Duration | Deliverables |
|-------|----------|--------------|
| P0 Implementation | - | Heap, Cache, Index, DLQ |
| P1 Implementation | - | Circuit Breaker, Retry, Interface |
| P2 Implementation | - | Metrics, Correlation ID, Tests |
| Verification | - | Load test, Code review |

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Heap 전환 시 기존 로직 손상 | High | 단위 테스트 선작성, 점진적 마이그레이션 |
| Redis 캐시 불일치 | Medium | Write-through 캐시 전략, TTL 설정 |
| Circuit Breaker 오작동 | Medium | 임계값 튜닝, 모니터링 대시보드 |

---

## Appendix

### Related Documents
- `docs/glossary.md` - 용어 정의
- `docs/functional-requirements.md` - 수치 정의
- `docs/design/spot-performance-upgrade-design.md` - 기술 설계

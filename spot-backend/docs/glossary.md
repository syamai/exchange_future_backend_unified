# Spot Backend Glossary (용어 정의)

> **SSOT (Single Source of Truth)**: 이 문서는 spot-backend 프로젝트의 모든 용어에 대한 단일 정의 출처입니다.

## Core Domain Terms

### Order (주문)
사용자가 특정 가격과 수량으로 자산을 매수/매도하려는 의사 표시.

### OrderBook (오더북)
특정 거래쌍에 대한 모든 활성 주문을 가격순으로 정렬한 자료구조.
- **Bid**: 매수 주문 (높은 가격 우선)
- **Ask**: 매도 주문 (낮은 가격 우선)

### Trade (체결)
매수 주문과 매도 주문이 매칭되어 실제 거래가 성사된 결과.

### Matching Engine (매칭 엔진)
주문을 받아 오더북에서 매칭 가능한 상대 주문을 찾아 체결하는 핵심 엔진.

### InMemoryOrderBook
메모리 기반 오더북 구현체. DB 의존성 없이 빠른 매칭을 위한 자료구조.

## Technical Terms

### Heap (힙)
완전 이진 트리 기반의 자료구조. O(log n) 삽입/삭제 보장.
- **MaxHeap**: 최대값이 루트에 위치 (Bid용)
- **MinHeap**: 최소값이 루트에 위치 (Ask용)

### Circuit Breaker (서킷 브레이커)
연속적인 실패 감지 시 일시적으로 요청을 차단하여 시스템 보호하는 패턴.
- **CLOSED**: 정상 상태, 요청 허용
- **OPEN**: 장애 상태, 요청 차단
- **HALF_OPEN**: 복구 테스트 상태

### Dead Letter Queue (DLQ)
처리 실패한 메시지를 별도 저장하여 추후 분석/재처리하는 큐.

### Exponential Backoff (지수 백오프)
재시도 간격을 지수적으로 증가시키는 전략. `delay = base * 2^attempt`

### Correlation ID
요청의 전체 생명주기를 추적하기 위한 고유 식별자.

## Order Status (주문 상태)

| Status | Description |
|--------|-------------|
| `NEW` | 주문 생성됨 (미처리) |
| `PENDING` | 매칭 대기 중 |
| `EXECUTING` | 매칭 진행 중 |
| `EXECUTED` | 완전 체결 |
| `PARTIALLY_FILLED` | 부분 체결 |
| `CANCELED` | 취소됨 |

## Order Types (주문 유형)

| Type | Description |
|------|-------------|
| `LIMIT` | 지정가 주문 - 특정 가격에 체결 |
| `MARKET` | 시장가 주문 - 즉시 최적가 체결 |

## Performance Metrics

### TPS (Transactions Per Second)
초당 처리 가능한 트랜잭션 수.

### Latency (지연시간)
요청 접수부터 응답까지의 시간.
- **p50**: 50% 요청이 이 시간 내 완료
- **p99**: 99% 요청이 이 시간 내 완료

## Abbreviations

| Abbr | Full Name | Description |
|------|-----------|-------------|
| DLQ | Dead Letter Queue | 실패 메시지 큐 |
| CB | Circuit Breaker | 서킷 브레이커 |
| TPS | Transactions Per Second | 초당 처리량 |
| SSOT | Single Source of Truth | 단일 정보 출처 |
| FR | Functional Requirement | 기능 요구사항 |

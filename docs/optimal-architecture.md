# 암호화폐 선물 거래소 최적 아키텍처

## 개요

이 문서는 암호화폐 선물 거래소의 최적 아키텍처를 정의합니다. Binance, Bybit 등 업계 리더의 베스트 프랙티스와 2024-2025년 최신 기술 트렌드를 반영했습니다.

## 현재 시스템 vs 최적 아키텍처

| 영역 | 현재 시스템 | 최적 아키텍처 | 개선 필요도 |
|------|-------------|--------------|-------------|
| **매칭 엔진** | Java 단일 스레드 | Rust/C++ 또는 최적화된 Java + 샤딩 | ⭐⭐⭐ |
| **메시징** | Kafka | Kafka (KRaft 모드) | ⭐⭐ |
| **DB** | MySQL Master/Report | PostgreSQL + TimescaleDB | ⭐⭐⭐ |
| **캐싱** | Redis | Redis Cluster | ⭐⭐ |
| **이벤트 패턴** | 비동기 Kafka | CQRS + Event Sourcing | ⭐⭐⭐⭐ |
| **장애 복구** | 미구현 | Hot/Warm Standby + WAL | ⭐⭐⭐⭐⭐ |

## 타겟 아키텍처 다이어그램

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                 CLIENT LAYER                                     │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐         │
│   │   Web    │  │  Mobile  │  │   Bot    │  │   API    │  │  Admin   │         │
│   │  (Next)  │  │  (RN)    │  │  Client  │  │  Users   │  │  Panel   │         │
│   └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘         │
└────────┼─────────────┼─────────────┼─────────────┼─────────────┼────────────────┘
         │             │             │             │             │
         └─────────────┴──────┬──────┴─────────────┴─────────────┘
                              │
                    ┌─────────▼─────────┐
                    │   API Gateway     │
                    │  (Kong/Traefik)   │
                    │  Rate Limiting    │
                    │  Auth / WAF       │
                    └─────────┬─────────┘
                              │
         ┌────────────────────┼────────────────────┐
         │                    │                    │
┌────────▼────────┐  ┌───────▼────────┐  ┌───────▼────────┐
│  REST API       │  │  WebSocket     │  │   Admin        │
│  Service        │  │  Gateway       │  │   Service      │
│  (NestJS)       │  │  (Socket.io)   │  │   (NestJS)     │
└────────┬────────┘  └───────┬────────┘  └───────┬────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
    ┌─────────▼────┐  ┌─────▼─────┐  ┌─────▼─────┐
    │   Command    │  │   Query   │  │  Event    │
    │   Service    │  │  Service  │  │  Store    │
    │   (Write)    │  │  (Read)   │  │  (WAL)    │
    └──────┬───────┘  └─────┬─────┘  └─────┬─────┘
           │                │              │
           │     ┌──────────┴──────────────┘
           │     │
    ┌──────▼─────▼──────────────────────────────────────────┐
    │                    KAFKA CLUSTER                       │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
    │  │ orders.cmd  │  │ trades.evt  │  │ market.data │    │
    │  └─────────────┘  └─────────────┘  └─────────────┘    │
    └───────────────────────────┬───────────────────────────┘
                                │
    ┌───────────────────────────▼───────────────────────────┐
    │                  MATCHING ENGINE CLUSTER               │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
    │  │  Shard 1    │  │  Shard 2    │  │  Shard 3    │    │
    │  │  BTC/USDT   │  │  ETH/USDT   │  │  Others     │    │
    │  │  (Primary)  │  │  (Primary)  │  │  (Primary)  │    │
    │  │      │      │  │      │      │  │      │      │    │
    │  │  Standby    │  │  Standby    │  │  Standby    │    │
    │  └─────────────┘  └─────────────┘  └─────────────┘    │
    └───────────────────────────────────────────────────────┘
                                │
    ┌───────────────────────────▼───────────────────────────┐
    │                    DATA LAYER                          │
    │                                                        │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
    │  │ PostgreSQL  │  │   Redis     │  │ TimescaleDB │    │
    │  │  Cluster    │  │  Cluster    │  │  (OHLCV)    │    │
    │  │  (CQRS)     │  │  (Cache)    │  │             │    │
    │  │             │  │             │  │             │    │
    │  │ Write│Read  │  │ Hot Data    │  │ Time-Series │    │
    │  │  DB  │ DB   │  │ Session     │  │ Analytics   │    │
    │  └─────────────┘  └─────────────┘  └─────────────┘    │
    └───────────────────────────────────────────────────────┘
```

## 핵심 아키텍처 패턴

### 1. 매칭 엔진 샤딩

심볼별로 독립적인 매칭 엔진 인스턴스를 운영하여 수평 확장성을 확보합니다.

- **장점**: 무한 확장 가능, 장애 격리
- **구현**: [matching-engine-sharding.md](./implementation-guide/matching-engine-sharding.md)

### 2. CQRS + Event Sourcing

명령(쓰기)과 쿼리(읽기)를 분리하고, 모든 상태 변경을 이벤트로 저장합니다.

- **장점**: 감사 추적, 상태 재구성, 성능 최적화
- **구현**: [cqrs-event-sourcing.md](./implementation-guide/cqrs-event-sourcing.md)

### 3. 멀티 데이터베이스 아키텍처

용도에 맞는 데이터베이스를 선택하여 최적의 성능을 달성합니다.

- **PostgreSQL**: 트랜잭션 데이터 (ACID)
- **Redis**: 실시간 캐시, 세션
- **TimescaleDB**: 시계열 데이터 (차트)
- **구현**: [database-architecture.md](./implementation-guide/database-architecture.md)

### 4. 장애 복구 (Disaster Recovery)

Hot Standby와 WAL 기반 이벤트 재생으로 즉시 복구를 보장합니다.

- **목표 RTO**: < 30초
- **목표 RPO**: 0 (데이터 손실 없음)
- **구현**: [disaster-recovery.md](./implementation-guide/disaster-recovery.md)

## 성능 목표

| 메트릭 | 현재 (추정) | 목표 | 업계 리더 |
|--------|------------|------|----------|
| 매칭 지연시간 | 10-50ms | **<5ms** | 5ms (Binance) |
| 주문 처리량 | ~10K/초 | **100K/초** | 1M/초 |
| WebSocket 지연 | ~200ms | **<50ms** | 50ms |
| 시스템 가동률 | 99% | **99.99%** | 99.99% |
| 장애 복구 시간 | 수동 | **<30초** | 즉시 |

## 기술 스택 권장사항

### 인프라

| 컴포넌트 | 현재 | 권장 |
|----------|------|------|
| Container | Docker | Kubernetes |
| API Gateway | - | Kong / Traefik |
| Service Mesh | - | Istio (선택) |
| Monitoring | - | Prometheus + Grafana |
| Logging | - | ELK Stack |
| Tracing | - | Jaeger |

### 데이터베이스

| 용도 | 현재 | 권장 |
|------|------|------|
| 주 데이터베이스 | MySQL | PostgreSQL 15+ |
| 캐시 | Redis | Redis Cluster 7+ |
| 시계열 | - | TimescaleDB |
| 검색 | - | Elasticsearch (선택) |

### 메시징

| 용도 | 현재 | 권장 |
|------|------|------|
| 이벤트 스트리밍 | Kafka | Kafka 3.5+ (KRaft) |
| 실시간 알림 | Socket.io | Socket.io + Redis Adapter |

## 구현 가이드 문서

1. [매칭 엔진 샤딩](./implementation-guide/matching-engine-sharding.md)
2. [CQRS + Event Sourcing](./implementation-guide/cqrs-event-sourcing.md)
3. [데이터베이스 아키텍처](./implementation-guide/database-architecture.md)
4. [장애 복구 시스템](./implementation-guide/disaster-recovery.md)
5. [성능 최적화](./implementation-guide/performance-optimization.md)
6. [마이그레이션 로드맵](./implementation-guide/migration-roadmap.md)

## 참고 자료

- Binance Futures 아키텍처 (2024 업그레이드: 10ms → 5ms)
- Bybit 기술 스택
- Apache Kafka 공식 문서
- PostgreSQL + TimescaleDB 가이드
- CQRS/Event Sourcing 패턴 (Martin Fowler)

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a cryptocurrency futures exchange system consisting of two main components:
- **future-engine**: Java 17 high-performance order matching engine
- **future-backend**: NestJS TypeScript REST API & WebSocket server

The components communicate via Kafka for asynchronous order processing and real-time updates.

## Build & Run Commands

### Future Engine (Java)

```bash
cd future-engine

# Build
mvn clean package

# Test
mvn clean verify

# Run matching engine
java -jar target/MatchingEngine-1.0-shaded.jar
```

### Future Backend (NestJS)

```bash
cd future-backend

# Initial setup
cp .env.example .env
docker-compose up -d         # Start MySQL, Redis, Kafka
yarn install

# Development server
yarn start:dev

# Production build & run
yarn build
yarn start:prod

# Lint & Format
yarn lint
yarn format

# Test
make init-test               # Initialize test database (required first time)
yarn test                    # Run all tests
yarn test:watch              # Watch mode
yarn test:cov                # With coverage
yarn test:e2e                # End-to-end tests
yarn test -- --testPathPattern="order"  # Run specific test file

# Database migrations
yarn typeorm:run             # Run migrations
yarn typeorm:revert          # Revert last migration (run multiple times for multiple reverts)
yarn typeorm:create <name>   # Create new empty migration
yarn typeorm:migrate         # Generate migration from entity changes
```

### TypeORM 마이그레이션 체크리스트

Entity 변경 시 반드시 다음 순서를 따름:

1. **Entity 수정**: `@Column`, `@Index`, `@ManyToOne` 등 데코레이터 추가/수정
2. **마이그레이션 생성**: `yarn typeorm:create <마이그레이션명>`
3. **SQL 작성**: up() 메서드에 CREATE/ALTER, down() 메서드에 롤백 SQL
4. **로컬 테스트**: `yarn typeorm:run`
5. **롤백 테스트**: `yarn typeorm:revert` 후 다시 `yarn typeorm:run`
6. **커밋**: Entity 파일과 마이그레이션 파일 함께 커밋
7. **배포 후**: Pod에서 `yarn typeorm:run` 실행 확인

**주의사항**:
- `@Index` 데코레이터만 추가하면 DB에 인덱스 생성 안 됨 (마이그레이션 필수)
- 프로덕션 DB에 직접 DDL 실행 금지
- 대용량 테이블 변경 시 락 발생 주의

```bash
# Console commands (workers)
yarn console:dev matching-engine:load              # Initialize matching engine
yarn console:dev matching-engine:save-accounts-to-db
yarn console:dev matching-engine:save-positions
yarn console:dev matching-engine:save-orders-to-db
yarn console:dev matching-engine:save-trades
yarn console:dev matching-engine:notify            # WebSocket notifications
yarn console:dev funding:pay                       # Pay funding fees
yarn console:dev seed                              # Seed database
```

## Architecture

### Data Flow

```
Client → Backend (REST/WS) → Kafka → Engine (Matching) → Kafka → Backend → DB/Redis/WebSocket
```

### Kafka Topics

| Topic | Direction | Purpose |
|-------|-----------|---------|
| `matching_engine_input` | Backend → Engine | Orders, cancellations |
| `matching_engine_output` | Engine → Backend | Trades, position updates |
| `save_order_from_client_v2` | Backend → Engine | New orders |
| `orderbook_output` | Engine → Backend | Orderbook updates |

### Key Modules

**future-engine** (`src/main/java/com/sotatek/future/`):
- `engine/MatchingEngine.java`: Main event loop, command routing
- `engine/Matcher.java`: Per-symbol order matching with TreeSet orderbook
- `engine/Trigger.java`: Stop/TP/SL/Trailing order triggers
- `service/LiquidationService.java`: Liquidation, ADL, insurance fund
- `service/MarginCalculator.java`: PNL, fee calculations (USD-M/COIN-M)
- `service/PositionCalculator.java`: Liquidation price calculations

**future-backend** (`src/`):
- `modules/matching-engine/`: Kafka consumer/producer, data sync
- `modules/order/`: Order API, validation
- `modules/position/`: Position management, leverage/margin
- `modules/events/`: WebSocket gateway (Socket.io)
- `shares/kafka-client/`: Kafka wrapper
- `shares/redis-client/`: Redis caching

### Database Connections

Backend uses two MySQL connections:
- **master**: Write operations
- **report**: Read operations (replicas)

### Redis Cache Keys

```
accounts:userId_{id}:accountId_{id}
orders:userId_{id}:orderId_{id}
positions:userId_{id}:positionId_{id}
```

## Coding Conventions

### Java (future-engine)
- Follows Google Java Style Guide
- Use `intellij-java-google-style.xml` for IntelliJ
- Commits follow Conventional Commits

### TypeScript (future-backend)
- Use absolute imports (not relative)
- Filename/URL paths use `-` as separator
- Entity files: singular noun (`order.entity.ts`)
- Service files: plural noun (`orders.service.ts`)
- Avoid `any` type
- Avoid `SELECT *` in SQL
- Column names: camelCase
- Table names: plural nouns

## Domain Concepts

- **Margin Modes**: Cross (shared margin) / Isolated (per-position margin)
- **Contract Types**: USD-M (USDT settled) / COIN-M (coin settled)
- **Order Types**: Limit, Market, Stop-Limit, Stop-Market, Trailing Stop
- **Time-in-Force**: GTC (Good Till Cancel), IOC (Immediate or Cancel), FOK (Fill or Kill)
- **Liquidation Flow**: Market liquidation → Insurance fund → ADL (Auto-Deleveraging)

## Infrastructure Requirements

- MySQL 8.x (Master/Report)
- Redis 6.x
- Apache Kafka 3.x
- Node.js 14.17.0
- Java 17
- Maven 3.8.x

## Implementation Guides

Detailed architecture documentation is available in `docs/implementation-guide/`:
- `aws-infrastructure.md` - AWS 프로덕션 인프라 구성
- `async-db-architecture.md` - 비동기 DB 처리로 고성능 달성
- `matching-engine-sharding.md` - 매칭 엔진 샤딩 전략
- `cqrs-event-sourcing.md` - CQRS + Event Sourcing 패턴
- `disaster-recovery.md` - 장애 복구 시스템
- `performance-optimization.md` - 성능 최적화 기법
- `rollback-procedure.md` - 롤백 절차서 (신규)

---

## Development History (2024-01-16)

### 매칭 엔진 샤딩 구현 완료

`docs/implementation-guide/matching-engine-sharding.md` 가이드를 기반으로 심볼 기반 수평 샤딩 구현 완료.

#### 1. 구현된 Java 클래스

**Router 패키지** (`future-engine/src/main/java/com/sotatek/future/router/`):
| 파일 | 설명 |
|-----|-----|
| `ShardInfo.java` | 샤드 정보 (상태, 역할, Kafka 토픽) |
| `ShardClient.java` | 샤드별 Kafka Producer |
| `OrderRouter.java` | 심볼 → 샤드 라우팅 (싱글톤) |
| `ShardRebalancer.java` | 자동/수동 리밸런싱 |
| `ShardMetricsExporter.java` | Prometheus 메트릭 |
| `ShardHealthServer.java` | HTTP 헬스체크 엔드포인트 |

**Engine 패키지** (`future-engine/src/main/java/com/sotatek/future/engine/`):
| 파일 | 설명 |
|-----|-----|
| `ShardedMatchingEngine.java` | 샤딩된 매칭 엔진 (MatchingEngine 상속) |
| `ShardedMatchingEngineConfig.java` | 샤드 설정 빌더 |
| `ShardHealthStatus.java` | 헬스 상태 DTO |
| `StandbySync.java` | Primary → Standby 동기화 |

**CLI** (`future-engine/src/main/java/com/sotatek/future/`):
- `ShardedMatchingEngineCLI.java` - 샤드 실행 CLI

**수정된 파일**:
- `MatchingEngine.java` - constructor/onTick을 protected로 변경

#### 2. 테스트 코드 (66개 테스트)

`future-engine/src/test/java/com/sotatek/future/`:
```
router/
├── ShardInfoTest.java           # 11개 테스트
├── OrderRouterTest.java         # 15개 테스트
├── ShardRebalancerTest.java     # 14개 테스트
└── MultiShardRoutingTest.java   # 12개 테스트
engine/
└── ShardedMatchingEngineTest.java  # 14개 테스트
```

테스트 실행: `mvn test -Dtest=ShardInfoTest,OrderRouterTest,ShardedMatchingEngineTest,ShardRebalancerTest,MultiShardRoutingTest`

#### 3. Docker 설정

`future-engine/`:
| 파일 | 설명 |
|-----|-----|
| `Dockerfile.shard` | 멀티스테이지 빌드 (Maven + JRE) |
| `docker-compose-sharded.yml` | 3샤드 × 2(Primary/Standby) 구성 |
| `docker-compose-sharded.dev.yml` | 개발용 단일 샤드 |
| `.dockerignore` | 빌드 컨텍스트 최적화 |
| `.env.sharded.example` | 환경변수 예제 |

```bash
# 이미지 빌드
./scripts/build-shard-image.sh -t 1.0.0

# 실행
docker-compose -f docker-compose-sharded.yml up -d
```

#### 4. Kubernetes 매니페스트

`future-engine/k8s/`:
```
base/
├── kustomization.yaml
├── namespace.yaml
├── configmap.yaml
├── secret.yaml
├── rbac.yaml
├── services.yaml
├── shard-{1,2,3}-statefulset.yaml
├── pdb.yaml
├── servicemonitor.yaml
└── grafana-dashboards-configmap.yaml
overlays/
├── dev/kustomization.yaml
└── prod/
    ├── kustomization.yaml
    ├── namespace.yaml
    └── network-policy.yaml
```

```bash
# 배포
./scripts/deploy-k8s.sh dev      # 개발
./scripts/deploy-k8s.sh prod     # 프로덕션

# 검증
kubectl kustomize k8s/base
```

#### 5. 모니터링 대시보드

`future-engine/monitoring/`:
```
dashboards/
├── matching-engine-overview.json  # 전체 샤드 개요
├── matching-engine-jvm.json       # JVM 메트릭
└── matching-engine-orders.json    # 주문 처리 상세
alerts/
└── matching-engine-alerts.yaml    # 11개 알림 규칙
```

#### 6. 운영 스크립트

`future-engine/scripts/`:
| 스크립트 | 용도 |
|---------|-----|
| `build-shard-image.sh` | Docker 이미지 빌드 |
| `deploy-k8s.sh` | K8s 배포 |
| `rollback.sh` | 대화형 롤백 |
| `emergency-rollback.sh` | 긴급 롤백 |
| `verify-rollback.sh` | 롤백 후 검증 |

### 다음 단계 (TODO)

구현 체크리스트 (`docs/implementation-guide/matching-engine-sharding.md` 참조):

- [x] Order Router 구현
- [x] ShardedMatchingEngine 구현
- [x] Primary/Standby 동기화
- [x] 테스트 코드 작성
- [x] Docker 이미지 빌드
- [x] Kubernetes 매니페스트
- [x] 모니터링 대시보드
- [x] 알림 규칙
- [x] 롤백 절차 문서화
- [x] Backend OrderRouter 통합
- [x] 실제 Kafka 토픽 생성 (로컬 테스트 완료)
- [x] 성능 테스트 스크립트 (141K orders/sec 달성)
- [x] 스테이징 테스트 계획 문서화
- [x] 프로덕션 배포 체크리스트 문서화
- [x] 스테이징 환경 실제 테스트 실행 (2025-01-17 완료)
  - Phase 1: 기본 기능 테스트 19/19 통과
  - Phase 2: 성능 테스트 113K orders/sec 달성
  - Phase 3: 장애 시나리오 테스트 9/9 통과
  - Phase 4: 모니터링 검증 13/13 통과
- [ ] 프로덕션 배포

### 빠른 시작

```bash
cd future-engine

# 빌드 및 테스트
mvn clean verify

# 샤딩 테스트만 실행
mvn test -Dtest=*Shard*,*Router*,MultiShardRoutingTest

# Docker 이미지 빌드
./scripts/build-shard-image.sh -t latest

# K8s 배포 (dev)
./scripts/deploy-k8s.sh dev --dry-run
```

### 샤드 구성

| 샤드 | 심볼 | 메모리 | 설명 |
|-----|------|-------|-----|
| shard-1 | BTCUSDT, BTCBUSD, BTCUSDC | 8-12GB | 최고 트래픽 |
| shard-2 | ETHUSDT, ETHBUSD, ETHUSDC | 6-8GB | 높은 트래픽 |
| shard-3 | SOL, XRP, ADA, DOT, MATIC... | 4-6GB | 기타 (기본 샤드) |

### 주요 설정 파일 위치

```
future-engine/
├── src/main/java/com/sotatek/future/
│   ├── router/          # 라우팅 로직
│   ├── engine/          # 샤딩된 엔진
│   └── ShardedMatchingEngineCLI.java
├── k8s/                 # Kubernetes 매니페스트
├── monitoring/          # Grafana 대시보드
├── scripts/             # 운영 스크립트
├── Dockerfile.shard     # Docker 빌드
└── docker-compose-sharded.yml
```

---

## Backend OrderRouter 통합 (2025-01-17)

### 구현된 TypeScript 파일

**OrderRouter 모듈** (`future-backend/src/shares/order-router/`):
| 파일 | 설명 |
|-----|-----|
| `shard-info.interface.ts` | ShardInfo, ShardStatus, ShardRole 타입 |
| `shard-config.ts` | 기본 심볼-샤드 매핑 설정 |
| `order-router.exception.ts` | 커스텀 예외 (ShardUnavailable, SymbolPaused) |
| `order-router.service.ts` | 핵심 라우팅 로직 |
| `order-router.module.ts` | NestJS Global 모듈 |
| `order-router.service.spec.ts` | 단위 테스트 (9개) |
| `index.ts` | Export 모음 |

**수정된 파일**:
| 파일 | 변경 내용 |
|-----|-----|
| `src/modules.ts` | OrderRouterModule import 추가 |
| `save-order-from-client-v2.usecase.ts` | orderRouter.routeCommand 사용 |
| `cancel-order-from-client.usecase.ts` | orderRouter.routeCommand 사용 |
| `save-user-market-order.usecase.ts` | orderRouter.routeCommand 사용 (4군데) |

### 설정

```yaml
# config/default.yml
sharding:
  enabled: false  # true로 변경하면 샤딩 활성화
  shard1:
    symbols: "BTCUSDT,BTCBUSD,BTCUSDC"
    inputTopic: "matching-engine-shard-1-input"
  shard2:
    symbols: "ETHUSDT,ETHBUSD,ETHUSDC"
    inputTopic: "matching-engine-shard-2-input"
  shard3:
    symbols: ""
    inputTopic: "matching-engine-shard-3-input"
```

### 빠른 시작 (Backend)

```bash
cd future-backend

# 의존성 설치 및 빌드
yarn install
yarn build

# OrderRouter 테스트
yarn test -- --testPathPattern="order-router"

# Kafka 토픽 생성
./scripts/create-shard-topics.sh -b localhost:9092
```

### 롤백

샤딩 문제 발생 시:
1. `config/default.yml`에서 `sharding.enabled: false` 설정
2. 백엔드 재시작
3. 자동으로 기존 `matching_engine_input` 토픽 사용

---

## 성능 테스트 및 배포 문서 (2025-01-17)

### 성능 테스트 스크립트

**테스트 파일** (`future-backend/test/performance/`):
| 파일 | 설명 | 목표 |
|-----|-----|-----|
| `order-router-benchmark.ts` | OrderRouter 처리량 테스트 | 100K orders/sec |
| `kafka-stress-test.ts` | Kafka 메시지 전송 테스트 | 50K msg/sec |
| `load-test.k6.js` | k6 API 부하 테스트 | P95 < 500ms |
| `run-perf-test.sh` | 통합 실행 스크립트 | - |

**테스트 결과**:
| 테스트 | 목표 | 달성 |
|-------|------|------|
| OrderRouter | 100K/sec | ✅ 141K/sec |
| Kafka (로컬) | 50K/sec | 13K/sec (Docker 환경) |

```bash
# 빠른 성능 테스트
./test/performance/run-perf-test.sh quick

# 전체 테스트
./test/performance/run-perf-test.sh all

# 개별 테스트
npx ts-node test/performance/order-router-benchmark.ts --orders=100000
```

### 배포 관련 문서

| 문서 | 위치 | 설명 |
|-----|-----|-----|
| 스테이징 테스트 계획 | `docs/staging-test-plan.md` | 4단계 테스트 프로세스 |
| 프로덕션 배포 체크리스트 | `docs/production-deployment-checklist.md` | 배포 전/중/후 체크리스트 |

**스테이징 테스트 단계**:
1. Phase 1: 기본 기능 테스트 (라우팅, 주문 체결)
2. Phase 2: 성능 테스트 (100K orders/sec)
3. Phase 3: 장애 시나리오 (샤드 다운, 롤백)
4. Phase 4: 모니터링 검증

**프로덕션 배포 타임라인**:
```
T-2h    최종 확인
T-0     매칭 엔진 샤드 배포
T+30m   Backend 카나리 5%
T+1h    카나리 50%
T+1h30m 전체 100%
T+7d    최종 승인
```

### 로컬 Kafka 테스트 환경

```bash
# Redpanda 시작 (Kafka 호환)
docker-compose -f docker-compose.kafka-test.yml up -d

# 토픽 생성
docker exec kafka-test rpk topic create \
  matching-engine-shard-1-input \
  matching-engine-shard-2-input \
  matching-engine-shard-3-input

# 토픽 확인
docker exec kafka-test rpk topic list
```

---

## TPS 테스트 가이드라인 (2026-02-07 추가)

### 핵심 원칙

**Raw TPS ≠ 유효 TPS**
- k6가 측정하는 Raw TPS = HTTP 요청 수 (성공 + 실패)
- 비즈니스 가치 = 유효 TPS = Raw TPS × 성공률
- HTTP 실패가 많으면 Raw TPS가 높아 보이지만 무의미

### 테스트 전 체크리스트

```
[ ] HPA 비활성화 또는 Pod 수 고정
    kubectl patch hpa future-backend-hpa -n future-backend-dev \
      --type='json' -p='[{"op": "replace", "path": "/spec/maxReplicas", "value": 5}]'

[ ] Pod 수 확인 및 고정
    kubectl scale deployment dev-future-backend --replicas=5 -n future-backend-dev

[ ] 이전 테스트 잔여 부하 해소 (30초 대기)

[ ] JWT 토큰 유효성 확인
```

### 테스트 방법

```bash
# 1. Cold start 테스트 (캐시 미스 상태)
k6 run -e BASE_URL=https://f-api.borntobit.com -e TOKEN="$JWT" test.js

# 2. Warm cache 테스트 (즉시 연속 실행)
k6 run -e BASE_URL=https://f-api.borntobit.com -e TOKEN="$JWT" test.js

# 유효한 결과: 2번째 테스트 (warm cache) + HTTP 실패율 0%
```

### 결과 해석

| 지표 | 의미 | 목표 |
|------|------|------|
| `http_reqs` | Raw TPS (참고용) | - |
| `order_success_rate` | 주문 성공률 | > 80% |
| `http_req_failed` | HTTP 실패율 | **0%** |
| **유효 TPS** | Raw TPS × 성공률 | 핵심 지표 |

### 유효하지 않은 테스트 결과

다음 경우 결과를 신뢰하지 말 것:
- HTTP 실패율 > 5% (502 에러 = 서버 과부하)
- 테스트 중 Pod 수 변동 (HPA 간섭)
- 첫 번째 테스트 (Cold start)

### 현재 인프라 기준 성능

| Pods | 유효 TPS (HTTP 실패 0%) | Pod당 TPS |
|------|------------------------|-----------|
| 1 | ~73 | 73 |
| 5 | ~240 | 48 |
| 10 | ~265 | 26 |
| 15 | ~334 | 22 |

**스케일링 효율 감소**: Pod 증가 시 RDS 연결 풀 경합으로 Pod당 TPS 감소

### 테스트 후 원복

```bash
# Pod 스케일 다운
kubectl scale deployment dev-future-backend --replicas=1 -n future-backend-dev

# HPA 원복 (필요시)
kubectl patch hpa future-backend-hpa -n future-backend-dev \
  --type='json' -p='[{"op": "replace", "path": "/spec/maxReplicas", "value": 5}]'
```

### 2000 TPS 달성 요구사항

현재 ~334 TPS (15 Pods) → 2000 TPS 달성 필요:
1. RDS 업그레이드 (r6g.large → r6g.xlarge)
2. Connection Pool 튜닝
3. 매칭 엔진 샤딩 활성화
4. Backend 30+ Pods

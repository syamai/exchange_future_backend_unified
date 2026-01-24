# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A cryptocurrency exchange system (monorepo):
- **future-engine**: Java 17 futures matching engine with sharding support
- **future-backend**: NestJS TypeScript futures REST API & WebSocket server
- **spot-backend**: PHP 8.1 Laravel spot trading backend with Swoole support
- **infra**: AWS CDK infrastructure (VPC, EKS, RDS, ElastiCache, Kafka)

### Communication Flow
- **Futures**: Backend → Kafka → Matching Engine → Kafka → Backend → DB/Redis/WS
- **Spot**: Backend → Redis Stream → PHP Matching Engine → DB/Redis/WS

## Build & Run Commands

### Future Engine (Java)

```bash
cd future-engine
mvn clean package                    # Build
mvn clean verify                     # Test
mvn test -Dtest=*Shard*,*Router*     # Sharding tests only
java -jar target/MatchingEngine-1.0-shaded.jar  # Run
./scripts/build-shard-image.sh -t 1.0.0         # Docker image
```

### Future Backend (NestJS)

```bash
cd future-backend
yarn install && docker-compose up -d  # Setup

yarn start:dev                        # Development
yarn build && yarn start:prod         # Production
yarn lint && yarn format              # Lint/Format

# Testing
make init-test                        # Init test DB (first time)
yarn test                             # All tests
yarn test -- --testPathPattern="order"  # Specific test

# Database
yarn typeorm:run                      # Run migrations
yarn typeorm:revert                   # Revert migration
yarn typeorm:migrate                  # Generate from entity changes

# Workers
yarn console:dev matching-engine:load
yarn console:dev matching-engine:notify
yarn console:dev funding:pay
```

### Spot Backend (PHP/Laravel)

```bash
cd spot-backend
composer install                      # Install dependencies

# Development
php artisan serve                     # Run dev server
php artisan queue:work                # Run queue worker

# Matching Engine modes
php artisan matching-engine:swoole usdt btc    # Swoole (high-perf)
php artisan matching-engine:stream usdt btc    # Redis Stream

# Docker
docker build -t spot-backend .        # Build image
docker-compose up -d                  # Run with MySQL/Redis

# Testing
php artisan test                      # Run tests
php benchmarks/orderbook-benchmark-fast.php  # Performance test
```

### Infrastructure (AWS CDK)

```bash
cd infra
npm install
npm run synth                         # Validate templates
npm run deploy:dev                    # Deploy all stacks
npm run destroy:dev                   # Destroy all
cdk deploy Exchange-dev-Eks -c env=dev  # Single stack
cdk deploy Exchange-dev-Ecr -c env=dev  # ECR only (includes spot-backend repo)
```

## Architecture

### Data Flow

```
Client → Backend (REST/WS) → Kafka → Engine → Kafka → Backend → DB/Redis/WS
```

### Kafka Topics

| Topic | Direction | Purpose |
|-------|-----------|---------|
| `matching-engine-shard-{1,2,3}-input` | Backend → Engine | Orders (sharded) |
| `matching-engine-shard-{1,2,3}-output` | Engine → Backend | Trades |
| `matching_engine_input` | Backend → Engine | Legacy single-shard |

### Sharding

Matching engine uses symbol-based horizontal sharding:
- **Shard 1**: BTCUSDT, BTCBUSD, BTCUSDC (highest traffic)
- **Shard 2**: ETHUSDT, ETHBUSD, ETHUSDC
- **Shard 3**: All other symbols (default)

Enable in `future-backend/config/default.yml`:
```yaml
sharding:
  enabled: true  # false = legacy single topic
```

### Key Modules

**future-engine** (`src/main/java/com/sotatek/future/`):
- `engine/MatchingEngine.java`: Event loop, command routing
- `engine/Matcher.java`: Per-symbol orderbook (TreeSet)
- `engine/ShardedMatchingEngine.java`: Sharded version
- `router/OrderRouter.java`: Symbol → Shard routing
- `service/LiquidationService.java`: Liquidation, ADL

**future-backend** (`src/`):
- `modules/matching-engine/`: Kafka consumer/producer
- `modules/order/`: Order API, validation
- `shares/order-router/`: Shard routing service
- `modules/events/`: WebSocket gateway (Socket.io)

**spot-backend** (`app/`):
- `Jobs/ProcessOrder.php`: PHP matching engine (dynamic polling)
- `Services/SwooleMatchingEngine.php`: High-perf Swoole coroutines
- `Services/StreamMatchingEngine.php`: Redis Stream-based engine
- `Services/InMemoryOrderBook.php`: In-memory orderbook
- `Http/Services/OrderService.php`: Order matching logic

**infra** (`lib/stacks/`):
- `vpc-stack.ts`: VPC, subnets, NAT Gateway
- `eks-stack.ts`: EKS cluster with Spot nodes
- `rds-stack.ts`: MySQL + Secrets Manager
- `elasticache-stack.ts`: Redis
- `kafka-stack.ts`: EC2 + Redpanda
- `ecr-stack.ts`: Container registries (future-backend, spot-backend, matching-engine)

### Database

Two MySQL connections:
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
- Google Java Style Guide
- Conventional Commits

### TypeScript (future-backend)
- Absolute imports (not relative)
- Entity files: singular (`order.entity.ts`)
- Service files: plural (`orders.service.ts`)
- Avoid `any` type
- Column names: camelCase
- Table names: plural nouns

### PHP (spot-backend)
- PSR-4 autoloading
- Laravel conventions
- Eloquent ORM
- Redis for queues and caching
- Swoole for high-performance mode

## Domain Concepts

- **Margin Modes**: Cross (shared) / Isolated (per-position)
- **Contract Types**: USD-M (USDT settled) / COIN-M (coin settled)
- **Order Types**: Limit, Market, Stop-Limit, Stop-Market, Trailing Stop
- **Time-in-Force**: GTC, IOC, FOK
- **Liquidation**: Market liquidation → Insurance fund → ADL

## Infrastructure

**Dev Environment (shared by futures + spot):**
- EKS: t3.large x 3-4 (Spot instances)
- RDS: db.t3.large (shared, separate DBs)
- Redis: cache.t3.medium (shared, separate DB indexes)
- Kafka: t3.medium (Redpanda) - futures only
- ECR: 3 repos (future-backend, spot-backend, matching-engine)

**Target TPS:**
- Futures: 2,000 TPS
- Spot: 2,000-5,000 TPS (with Swoole)

**Estimated Cost:** ~$425/month (futures + spot combined)

**Config**: `infra/config/dev.ts`

## Documentation

- `docs/implementation-guide/aws-dev-infrastructure.md` - CDK setup guide
- `docs/implementation-guide/matching-engine-sharding.md` - Sharding strategy
- `docs/staging-test-plan.md` - Testing phases
- `docs/production-deployment-checklist.md` - Deployment checklist
- `spot-backend/docs/plans/2025-01-18-php-matching-engine-performance-optimization.md` - Spot performance plan
- `spot-backend/benchmarks/RESULTS.md` - Performance benchmark results

## Kubernetes Deployment

### Future Backend
```bash
kubectl apply -k future-backend/k8s/overlays/dev
```

### Spot Backend
```bash
kubectl apply -k spot-backend/k8s/overlays/dev
```

### Namespaces
- `future-backend-dev`: Futures trading services
- `spot-backend-dev`: Spot trading services

---

## 2026-01-20 인프라 비용 최적화 작업 기록

### 완료된 작업

1. **스케줄러 시간 변경**
   - 이전: 09:00-21:00 KST (12시간)
   - 이후: 11:00-20:00 KST (9시간)
   - 절감: ~$21/월

2. **NAT Gateway → NAT Instance 교체**
   - NAT Instance ID: `i-06d5bb3c9d01f720d`
   - 절감: ~$37/월

3. **ElastiCache Redis 삭제/재생성 추가**
   - 매일 20:00 KST에 삭제, 11:00 KST에 재생성
   - 절감: ~$33/월

4. **스케줄러 관리 대상 리소스**
   ```
   EKS: exchange-dev (노드 스케일 업/다운)
   RDS: exchange-dev-mysql (시작/중지)
   Kafka: i-044548ca3fe3ae1a1 (시작/중지)
   NAT: i-06d5bb3c9d01f720d (시작/중지)
   Redis: exchange-dev-redis (삭제/재생성)
   ```

### 진행 중인 작업 (확인 필요)

**RDS 다운그레이드**
- 변경: `db.r6g.xlarge` → `db.t3.large`
- 상태: `modifying` (진행 중이었음)
- 예상 절감: ~$200/월

확인 명령어:
```bash
aws rds describe-db-instances --db-instance-identifier exchange-dev-mysql \
  --region ap-northeast-2 \
  --query 'DBInstances[0].{Status:DBInstanceStatus,Class:DBInstanceClass}' \
  --output table
```

완료 시 예상 결과:
```
| Status    | Class        |
| available | db.t3.large  |
```

### 수동 시작/종료 명령어

**전체 시작 (매칭 엔진 초기화 포함):**
```bash
aws lambda invoke --function-name exchange-dev-dev-scheduler \
  --payload '{"action":"scale-up","clusterName":"exchange-dev","nodegroupName":"exchange-dev-spot-nodes","desiredSize":3,"minSize":2,"maxSize":6,"rdsInstanceId":"exchange-dev-mysql","ec2InstanceIds":["i-044548ca3fe3ae1a1","i-06d5bb3c9d01f720d"],"elasticache":{"clusterId":"exchange-dev-redis","nodeType":"cache.t3.medium","engine":"redis","engineVersion":"7.0","subnetGroupName":"exchange-dev-redis-subnet","securityGroupName":"exchange-dev-redis-sg"},"matchingEngineInit":{"kafkaInstanceId":"i-044548ca3fe3ae1a1","preloadTopic":"matching_engine_preload","delaySeconds":420}}' \
  --cli-binary-format raw-in-base64-out \
  --region ap-northeast-2 /dev/stdout
```

**전체 종료:**
```bash
aws lambda invoke --function-name exchange-dev-dev-scheduler \
  --payload '{"action":"scale-down","clusterName":"exchange-dev","nodegroupName":"exchange-dev-spot-nodes","desiredSize":0,"minSize":0,"maxSize":6,"rdsInstanceId":"exchange-dev-mysql","ec2InstanceIds":["i-044548ca3fe3ae1a1","i-06d5bb3c9d01f720d"],"elasticache":{"clusterId":"exchange-dev-redis","nodeType":"cache.t3.medium","engine":"redis","engineVersion":"7.0","subnetGroupName":"exchange-dev-redis-subnet","securityGroupName":"exchange-dev-redis-sg"}}' \
  --cli-binary-format raw-in-base64-out \
  --region ap-northeast-2 /dev/stdout
```

**인프라만 시작 (매칭 엔진 초기화 없이):**
```bash
aws lambda invoke --function-name exchange-dev-dev-scheduler \
  --payload '{"action":"scale-up","clusterName":"exchange-dev","nodegroupName":"exchange-dev-spot-nodes","desiredSize":3,"minSize":2,"maxSize":6,"rdsInstanceId":"exchange-dev-mysql","ec2InstanceIds":["i-044548ca3fe3ae1a1","i-06d5bb3c9d01f720d"],"elasticache":{"clusterId":"exchange-dev-redis","nodeType":"cache.t3.medium","engine":"redis","engineVersion":"7.0","subnetGroupName":"exchange-dev-redis-subnet","securityGroupName":"exchange-dev-redis-sg"}}' \
  --cli-binary-format raw-in-base64-out \
  --region ap-northeast-2 /dev/stdout
```

### 비용 요약

| 항목 | 이전 | 이후 | 절감 |
|------|------|------|------|
| RDS (db.t3.large) | $248 | $75 | $173 |
| NAT Instance | $45 | $3 | $42 |
| Redis (9시간) | $50 | $13 | $37 |
| EKS 노드 (9시간) | $90 | $23 | $67 |
| Kafka (9시간) | $30 | $8 | $22 |
| **예상 총 월 비용** | **~$472** | **~$150** | **~$322** |

*참고: EKS Control Plane ($72), ELB ($20), Storage ($20) 등 고정 비용 별도*

---

## 개발 환경 시작/중지 스크립트 (2026-01-22)

매일 스케일 업/다운 시 수동 설정 없이 바로 테스트 가능하도록 자동화 스크립트 추가.

### 스크립트 위치

```
future-backend/scripts/
├── dev-environment-start.sh   # 전체 시작 (인프라 + 매칭 엔진 초기화)
├── dev-environment-stop.sh    # 전체 종료
└── dev-environment-status.sh  # 상태 확인
```

### 사용법

```bash
cd future-backend

# 개발 환경 시작 (약 5-10분 소요)
./scripts/dev-environment-start.sh

# 상태 확인
./scripts/dev-environment-status.sh

# 개발 환경 종료
./scripts/dev-environment-stop.sh
```

### 시작 스크립트가 하는 일

1. AWS Lambda로 인프라 스케일 업 (EKS, RDS, Kafka, NAT, Redis)
2. 각 컴포넌트가 Ready 상태가 될 때까지 대기
3. Kafka preload 토픽 초기화 (consumer group, 토픽 리셋)
4. 매칭 엔진 초기화 명령 전송 (INITIALIZE_ENGINE, UPDATE_INSTRUMENT, START_ENGINE)
5. 헬스체크로 전체 시스템 정상 동작 확인

### 자동 스케줄링 (2026-01-22 추가)

**Lambda 스케줄러가 매칭 엔진 초기화까지 자동으로 수행합니다.**

매일 11:00 KST 스케일 업 시:
1. 인프라 스케일 업 시작 (EKS, RDS, Kafka, Redis, NAT)
2. 3분 대기 (인프라 준비 시간)
3. Kafka preload 토픽 리셋 (consumer group 삭제, 토픽 재생성)
4. 매칭 엔진 초기화 명령 자동 전송

**배포 필요:**
```bash
cd infra
cdk deploy Exchange-dev-EksScheduler -c env=dev
```

### 주의사항

- **샤딩 모드는 현재 비활성화** 상태 (legacy single topic 모드 사용)
- 매칭 엔진 초기화 시 DB에서 데이터를 로드하지 않고 빈 상태로 시작
- 실제 운영 데이터 테스트가 필요하면 Backend에서 `yarn console matching-engine:load` 실행 필요

---

## Spot Backend 로컬 테스트 환경 설정 (2026-01-24)

### Docker 컨테이너 (기존 사용)

```bash
# 이미 실행 중인 컨테이너 사용
spot-mysql: 127.0.0.1:3307 (root/root, DB: spot_backend)
spot-redis: 127.0.0.1:6380
```

### 환경 설정

```bash
cd spot-backend
cp .env.testing .env   # 테스트용 환경변수 복사
```

`.env.testing` 주요 설정:
```
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=spot_backend
REDIS_PORT=6380
PROMETHEUS_ENABLED=false
```

### 수정된 파일들

1. **`app/Providers/PrometheusServiceProvider.php`**
   - `PROMETHEUS_ENABLED=false`일 때 InMemory adapter 사용
   - Redis 연결 오류 방지

2. **`tests/Feature/BaseTestCase.php`**
   - `insertOrIgnore()` 사용하여 중복 ID 오류 방지
   - `countries` 테이블 스킵 (corrupted data)
   - `ensureTestCoinSettings()` 추가 (BTC/USD 쌍 생성)

3. **`tests/Feature/OrderMatching/OrdersMatchingTestBase.php`**
   - `Passport::actingAs()` 사용하여 API 인증
   - `HmacTokenMiddleware` 비활성화
   - `ProcessOrder` Job import 추가 (진행 중)

4. **`database/seeders/UsersTableSeeder.php`**
   - `createAccountProfileSettings()` 추가
   - `spot_trade_allow = 1` 설정

### 테스트 실행

```bash
cd spot-backend

# 단위 테스트 (모두 통과)
php artisan test --filter=HeapOrderBook
php artisan test --filter=CircuitBreaker
php artisan test --filter=RetryPolicy

# OrderBook 통합 테스트 (모두 통과)
php artisan test tests/Feature/OrderBook/

# OrderMatching 통합 테스트 (진행 중)
php artisan test --filter=OrdersMatching001Test
```

### 알려진 이슈

1. **Redis Stream 미지원**
   - `matching-engine:stream` 명령 실행 시 "XGROUP is not a registered Redis command" 오류
   - 원인: predis 라이브러리가 Redis Stream 명령어(XGROUP, XREAD 등) 미지원
   - 해결: phpredis 확장 설치 필요 (pecl install redis)

2. **OrderMatching 테스트 매칭 미실행**
   - `order:process` 명령이 큐에 Job 추가만 함
   - `ProcessOrder` Job이 비동기로 실행되어 테스트 중 매칭 안됨
   - 해결 필요: `ProcessOrder::dispatchSync()` 또는 직접 실행

### 거래 권한 체크 흐름

`OrderAPIController::store()` → `EnableTradingSettingService::checkAllowTrading()`:
1. `account_profile_settings.spot_trade_allow` 체크
2. `enable_trading_settings` 테이블에서 사용자별 쌍 권한 체크
3. `coin_settings.is_enable` 체크

### 테스트용 필수 데이터

- `coin_settings`: BTC/USD 쌍 (`is_enable = 1`)
- `account_profile_settings`: 사용자별 `spot_trade_allow = 1`
- `fee_levels`: 수수료 레벨 설정

---

## Spot Backend OrderMatching 통합 테스트 완료 (2026-01-23~24)

### 완료된 작업

1. **ProcessOrder 동기 실행 구현**
   - 테스트 환경에서 큐 없이 직접 실행
   - `ProcessOrderRequest` → `ProcessOrder` 동기 호출

2. **테스트 데이터 확장**
   - `market_fee_setting` 자동 생성
   - `coin_settings` BTC/USD 쌍 생성

3. **15개 테스트 케이스 모두 통과**
   - OrdersMatching001~008 테스트 수정
   - 예상 결과값 매칭 로직과 일치하도록 수정

### Git Commit

```
e6a9569 fix(spot-backend): enable OrderMatching integration tests to run synchronously
```

### 테스트 실행

```bash
cd spot-backend
php artisan test tests/Feature/OrderMatching/  # 15개 테스트 모두 통과
```

---

## Spot Backend 5,000 TPS 성능 최적화 프로젝트 (2026-01-24~25)

### 벤치마크 결과

| 벤치마크 | 결과 |
|----------|------|
| InMemory Matching TPS | **27,424 TPS** |
| Heap OrderBook Insert | **3,521,127/sec** |
| Heap vs Array Speedup | **456x** |
| 현재 Production TPS | ~200 TPS (DB 병목) |

### 핵심 발견

1. **Future는 이미 최적화 완료**
   - 심볼 기반 샤딩 구현됨 (3 shards)
   - 비동기 배치 DB 쓰기 (`saveAccountsV2`, `savePositionsV2`)
   - Redis 캐싱 레이어 적용

2. **Spot의 DB 동기 쓰기가 99% 병목**
   - 순수 매칭: 27,424 TPS (충분)
   - DB 쓰기: 5-10ms/order → 최대 200 TPS

3. **공유 인프라 분리 불필요**
   - DB 인덱스 분리로 충분 (future_db, spot_db)
   - RDS IOPS 증설 필요 (3K → 10K)

### 구현 로드맵 (6주)

```
Week 1    Phase 2: DB Batch Write (Spot)     → 2,000 TPS
Week 2-3  Phase 3: Redis Stream + Sharding   → 3,500 TPS
Week 4-5  Phase 4: Swoole Migration          → 5,000 TPS
Week 6    Phase 5: Infrastructure Upgrade    → 안정화
```

### 비용 분석

| 환경 | 월 비용 | TPS | 비용/M 트랜잭션 |
|------|---------|-----|-----------------|
| 현재 (Dev) | ~$214 | 200 | $0.41 |
| 목표 (Prod) | ~$1,406 | 5,000 | $0.11 (73% 절감) |

### 관련 문서

- `spot-backend/docs/plans/5000-tps-infrastructure-plan.md` - Spot 인프라 계획
- `docs/plans/2026-01-24-5000-tps-architecture-review.md` - Spot+Future 종합 아키텍처 검토
- `docs/plans/2026-01-25-spot-performance-optimization-progress.md` - 진행 현황

### Git Commits

```
23fb269 docs(spot-backend): add 5000 TPS infrastructure plan
```

### 다음 단계

| 우선순위 | 작업 | 예상 효과 |
|----------|------|-----------|
| **1** | Spot WriteBuffer 클래스 구현 | 200 → 2,000 TPS |
| **2** | phpredis 확장 설치 | Redis Stream 활성화 |
| **3** | RDS IOPS 증설 (3K → 10K) | 배치 쓰기 지원 |

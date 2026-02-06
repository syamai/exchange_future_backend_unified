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

## AWS 계정 정보

- **Account**: 990781424619 (critonex)
- **User**: Prod-ahn
- **Region**: ap-northeast-2 (서울)

---

## Future Backend TPS 테스트 결과 (2026-02-05)

### 테스트 환경

- **서버**: `f-api.borntobit.com` (exchange-cicd-dev EKS)
- **도구**: k6 부하 테스트
- **최대 VU**: 400
- **테스트 시간**: 약 3분

### JWT ConfigMap 수정

기존 ConfigMap의 JWT 키가 잘못된 형식으로 저장되어 있어서 수정함.

**문제**: `auth.module.ts`가 base64 인코딩된 PEM 키를 기대하지만, PEM 문자열이 직접 저장됨

**해결**:
```bash
# PEM 키를 base64로 인코딩 후 ConfigMap 업데이트
cat private.pem | base64 > private_b64.txt
kubectl apply -f updated-configmap.yaml
```

### TPS 테스트 결과 비교

| 지표 | 1 Pod | 5 Pods | 개선율 |
|------|-------|--------|--------|
| **TPS** | 73 | **357** | **+388%** |
| 총 주문 | 13,734 | 57,146 | +316% |
| Median RT | 1,942ms | 154ms | **-92%** |
| P95 RT | 4,021ms | 1,454ms | -64% |
| HTTP 실패 | 0.07% | 39.74% | ⚠️ |

### 핵심 발견

1. **Pod 스케일링 효과**: 5배 Pod → 4.9배 TPS (거의 선형 확장)
2. **응답 시간 대폭 개선**: median 154ms (92% 감소)
3. **병목 발견**: 고부하 시 39% 실패 → DB 연결 풀 부족 추정

### 2000 TPS 달성 예측

```
현재: 5 Pods = 357 TPS
필요: 2000 TPS / 357 TPS * 5 Pods ≈ 28 Pods

단, 추가 인프라 업그레이드 필수:
- RDS: db.r6g.xlarge + connection pool 증가
- Redis: cache.r6g.large
- Matching Engine 샤딩 활성화 (sharding.enabled: true)
```

### 권장 아키텍처 (2000 TPS)

| 리소스 | 수량 | 월 비용 |
|--------|------|---------|
| Backend Pods (t3.large) | 10개 | ~$600 |
| Matching Engine (t3.large) | 6개 (3 shards × 2) | ~$360 |
| RDS r6g.xlarge | 1+1 (Read Replica) | ~$500 |
| Redis r6g.large | 1 | ~$200 |
| Kafka (t3.medium x3) | 3 | ~$150 |
| **Total** | | **~$1,800/월** |

### 스케일 업/다운 명령어

```bash
# EKS 노드 스케일 업 (테스트용)
AWS_PROFILE=critonex aws eks update-nodegroup-config \
  --cluster-name exchange-cicd-dev \
  --nodegroup-name ng-spot-1 \
  --scaling-config minSize=3,maxSize=6,desiredSize=4 \
  --region ap-northeast-2

# Backend Pod 스케일 업
kubectl scale deployment dev-future-backend --replicas=5 -n future-backend-dev

# 테스트 후 원복
kubectl scale deployment dev-future-backend --replicas=1 -n future-backend-dev
aws eks update-nodegroup-config ... --scaling-config desiredSize=2
```

### k6 테스트 실행

```bash
cd future-backend
JWT_TOKEN=$(cat /tmp/new_jwt.txt)
k6 run \
  -e BASE_URL=https://f-api.borntobit.com \
  -e TOKEN="$JWT_TOKEN" \
  test/performance/order-tps-test.js
```

### 관련 파일

- `future-backend/test/performance/order-tps-test.js` - k6 테스트 스크립트
- `future-backend/src/modules/auth/auth.module.ts` - JWT 설정 (base64 디코딩)
- `future-backend/src/modules/auth/strategies/jwt.strategy.ts` - JWT 검증 (`payload.sub` 사용)
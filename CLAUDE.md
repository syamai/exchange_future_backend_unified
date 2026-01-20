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

**전체 시작:**
```bash
aws lambda invoke --function-name exchange-dev-dev-scheduler \
  --payload '{"action":"scale-up","clusterName":"exchange-dev","nodegroupName":"exchange-dev-spot-nodes","desiredSize":3,"minSize":2,"maxSize":6,"rdsInstanceId":"exchange-dev-mysql","ec2InstanceIds":["i-044548ca3fe3ae1a1","i-06d5bb3c9d01f720d"],"elasticache":{"clusterId":"exchange-dev-redis","nodeType":"cache.t3.medium","engine":"redis","engineVersion":"7.0","subnetGroupName":"exchange-dev-redis-subnet","securityGroupName":"exchange-dev-redis-sg"}}' \
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

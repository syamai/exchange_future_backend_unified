# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A cryptocurrency futures exchange system (monorepo):
- **future-engine**: Java 17 order matching engine with sharding support
- **future-backend**: NestJS TypeScript REST API & WebSocket server
- **infra**: AWS CDK infrastructure (VPC, EKS, RDS, ElastiCache, Kafka)

Communication: Backend → Kafka → Matching Engine → Kafka → Backend → DB/Redis/WebSocket

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

### Infrastructure (AWS CDK)

```bash
cd infra
npm install
npm run synth                         # Validate templates
npm run deploy:dev                    # Deploy all stacks
npm run destroy:dev                   # Destroy all
cdk deploy Exchange-dev-Eks -c env=dev  # Single stack
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

**infra** (`lib/stacks/`):
- `vpc-stack.ts`: VPC, subnets, NAT Gateway
- `eks-stack.ts`: EKS cluster with Spot nodes
- `rds-stack.ts`: MySQL + Secrets Manager
- `elasticache-stack.ts`: Redis
- `kafka-stack.ts`: EC2 + Redpanda

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

## Domain Concepts

- **Margin Modes**: Cross (shared) / Isolated (per-position)
- **Contract Types**: USD-M (USDT settled) / COIN-M (coin settled)
- **Order Types**: Limit, Market, Stop-Limit, Stop-Market, Trailing Stop
- **Time-in-Force**: GTC, IOC, FOK
- **Liquidation**: Market liquidation → Insurance fund → ADL

## Infrastructure

**Dev Environment (2000 TPS target):**
- EKS: t3.large x 3 (Spot)
- RDS: db.t3.large
- Redis: cache.t3.medium
- Kafka: t3.medium (Redpanda)
- Cost: ~$375/month

**Config**: `infra/config/dev.ts`

## Documentation

- `docs/implementation-guide/aws-dev-infrastructure.md` - CDK setup guide
- `docs/implementation-guide/matching-engine-sharding.md` - Sharding strategy
- `docs/staging-test-plan.md` - Testing phases
- `docs/production-deployment-checklist.md` - Deployment checklist

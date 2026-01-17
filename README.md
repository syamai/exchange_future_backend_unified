# Exchange - Cryptocurrency Futures Trading Platform

A high-performance cryptocurrency futures exchange system with symbol-based sharding support.

## Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────────────────────┐
│   Clients   │────▶│   Backend   │────▶│     Matching Engine         │
│  (REST/WS)  │     │  (NestJS)   │     │  (Java, 3 Shards)           │
└─────────────┘     └──────┬──────┘     └──────────────┬──────────────┘
                           │                           │
                    ┌──────▼──────┐              ┌─────▼─────┐
                    │    Kafka    │◀─────────────│  Kafka    │
                    │   (Input)   │              │  (Output) │
                    └─────────────┘              └───────────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
         ┌────────┐  ┌────────┐  ┌────────┐
         │ MySQL  │  │ Redis  │  │  S3    │
         │(Master)│  │ Cache  │  │ Logs   │
         └────────┘  └────────┘  └────────┘
```

## Components

| Component | Technology | Description |
|-----------|------------|-------------|
| **future-backend** | NestJS (TypeScript) | REST API, WebSocket, Kafka consumer/producer |
| **future-engine** | Java 17 | High-performance order matching with sharding |
| **infra** | AWS CDK (TypeScript) | VPC, EKS, RDS, ElastiCache, Kafka |

## Quick Start

### Prerequisites

- Node.js 18+
- Java 17
- Maven 3.8+
- Docker & Docker Compose

### Local Development

```bash
# 1. Start infrastructure (MySQL, Redis, Kafka)
cd future-backend
docker-compose up -d

# 2. Run Backend
yarn install
yarn start:dev

# 3. Run Matching Engine (in another terminal)
cd future-engine
mvn clean package
java -jar target/MatchingEngine-1.0-shaded.jar
```

### AWS Deployment

```bash
# 1. Deploy infrastructure
cd infra
npm install
cdk bootstrap aws://ACCOUNT_ID/ap-northeast-2
npm run deploy:dev

# 2. Build and push Docker images
./future-engine/scripts/build-shard-image.sh -t 1.0.0
# Push to ECR...

# 3. Deploy to EKS
kubectl apply -k future-engine/k8s/overlays/dev
```

## Key Features

- **Symbol-based Sharding**: Horizontal scaling of matching engine
  - Shard 1: BTC pairs (highest traffic)
  - Shard 2: ETH pairs
  - Shard 3: All other symbols
- **High Performance**: 100K+ orders/sec per shard
- **Low Latency**: P99 < 10ms order processing
- **Fault Tolerance**: Primary/Standby per shard, auto-failover

## Project Structure

```
exchange/
├── future-backend/          # NestJS API Server
│   ├── src/
│   │   ├── modules/         # Feature modules (order, position, etc.)
│   │   ├── shares/          # Shared modules (kafka, redis, order-router)
│   │   └── models/          # Entities and repositories
│   ├── config/              # Environment configs
│   └── k8s/                 # Kubernetes manifests
│
├── future-engine/           # Java Matching Engine
│   ├── src/main/java/
│   │   └── com/sotatek/future/
│   │       ├── engine/      # MatchingEngine, Matcher, Trigger
│   │       ├── router/      # OrderRouter, ShardInfo
│   │       └── service/     # Liquidation, Margin calculators
│   ├── k8s/                 # Kubernetes manifests
│   └── monitoring/          # Grafana dashboards
│
├── infra/                   # AWS CDK Infrastructure
│   ├── lib/stacks/          # CDK Stack definitions
│   └── config/              # Environment configs
│
└── docs/                    # Documentation
    └── implementation-guide/
```

## Configuration

### Backend Sharding

```yaml
# future-backend/config/default.yml
sharding:
  enabled: true              # Enable sharded routing
  shard1:
    symbols: "BTCUSDT,BTCBUSD,BTCUSDC"
    inputTopic: "matching-engine-shard-1-input"
  shard2:
    symbols: "ETHUSDT,ETHBUSD,ETHUSDC"
    inputTopic: "matching-engine-shard-2-input"
  shard3:
    symbols: ""              # Default shard for all others
    inputTopic: "matching-engine-shard-3-input"
```

### Infrastructure

```typescript
// infra/config/dev.ts
export const devConfig = {
  eksNodeInstanceType: 't3.large',
  eksNodeDesiredSize: 3,
  rdsInstanceClass: 'db.t3.large',    // 2000 TPS target
  redisNodeType: 'cache.t3.medium',
  kafkaInstanceType: 't3.medium',
};
```

## Commands Reference

### Backend

```bash
yarn start:dev                    # Development server
yarn test                         # Run tests
yarn typeorm:run                  # Run migrations
yarn console:dev matching-engine:load   # Initialize engine
```

### Engine

```bash
mvn clean verify                  # Build and test
mvn test -Dtest=*Shard*          # Sharding tests only
./scripts/build-shard-image.sh    # Build Docker image
```

### Infrastructure

```bash
npm run synth                     # Validate CDK
npm run deploy:dev                # Deploy all stacks
npm run destroy:dev               # Destroy all
```

## Documentation

- [AWS Infrastructure Guide](docs/implementation-guide/aws-dev-infrastructure.md)
- [Matching Engine Sharding](docs/implementation-guide/matching-engine-sharding.md)
- [Performance Optimization](docs/implementation-guide/performance-optimization.md)
- [Disaster Recovery](docs/implementation-guide/disaster-recovery.md)

## Tech Stack

| Category | Technology |
|----------|------------|
| Backend | NestJS, TypeScript, Socket.io |
| Engine | Java 17, Maven |
| Database | MySQL 8.x, Redis 7.x |
| Message Queue | Apache Kafka (Redpanda) |
| Container | Docker, Kubernetes |
| Cloud | AWS (EKS, RDS, ElastiCache) |
| IaC | AWS CDK (TypeScript) |
| Monitoring | Prometheus, Grafana |

## License

Proprietary - All rights reserved

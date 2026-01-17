# 마이그레이션 로드맵

## 개요

현재 시스템에서 최적 아키텍처로의 단계별 마이그레이션 계획입니다. 무중단 서비스를 유지하면서 점진적으로 개선합니다.

## 현재 상태 분석

```
┌─────────────────────────────────────────────────────────┐
│                    현재 아키텍처                         │
│                                                          │
│  ┌─────────────┐        ┌─────────────┐                 │
│  │   Backend   │◄──────►│   MySQL     │                 │
│  │  (NestJS)   │        │ Master/Rep  │                 │
│  └──────┬──────┘        └─────────────┘                 │
│         │                                                │
│         │ Kafka                                          │
│         │                                                │
│  ┌──────▼──────┐        ┌─────────────┐                 │
│  │  Matching   │◄──────►│   Redis     │                 │
│  │   Engine    │        │  (Single)   │                 │
│  │   (Java)    │        └─────────────┘                 │
│  └─────────────┘                                         │
│                                                          │
│  문제점:                                                 │
│  - 단일 매칭 엔진 (SPOF)                                │
│  - MySQL 확장성 한계                                     │
│  - 장애 복구 시스템 부재                                │
│  - 이벤트 소싱 미적용                                   │
└─────────────────────────────────────────────────────────┘
```

## 마이그레이션 전략

### 원칙

1. **무중단 배포**: 서비스 중단 최소화
2. **롤백 가능**: 각 단계별 롤백 계획 수립
3. **점진적 전환**: Big Bang 방식 지양
4. **검증 우선**: 각 단계 완료 후 충분한 테스트

### 전체 타임라인

```
Phase 1 (1-2개월)     Phase 2 (2-3개월)     Phase 3 (2-3개월)     Phase 4 (지속)
┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐
│  기반 강화       │   │  CQRS 도입      │   │  샤딩 + HA      │   │  성능 최적화    │
│                 │   │                 │   │                 │   │                 │
│ - PostgreSQL    │   │ - Event Store   │   │ - 엔진 샤딩     │   │ - 핫경로 최적화 │
│ - Redis Cluster │   │ - Command/Query │   │ - Hot Standby   │   │ - 프로토콜 개선 │
│ - 모니터링      │   │ - Projection    │   │ - DR 구축       │   │ - 캐시 고도화   │
│ - Health Check  │   │ - TimescaleDB   │   │ - Auto Failover │   │                 │
└─────────────────┘   └─────────────────┘   └─────────────────┘   └─────────────────┘
```

---

## Phase 1: 기반 강화 (1-2개월)

### 목표
- 데이터베이스 현대화
- 모니터링 체계 구축
- 기본적인 고가용성 확보

### 1.1 PostgreSQL 마이그레이션

#### 단계

```
Week 1-2: 준비
├── PostgreSQL 클러스터 구축
├── 스키마 변환 스크립트 작성
├── 데이터 마이그레이션 도구 준비
└── 롤백 계획 수립

Week 3-4: 마이그레이션
├── 읽기 트래픽 PostgreSQL로 전환
├── 데이터 동기화 검증
├── 쓰기 트래픽 전환
└── MySQL 비활성화
```

#### 마이그레이션 스크립트

```typescript
// migration/mysql-to-postgres.ts

import { MigrationInterface, QueryRunner } from 'typeorm';

export class MysqlToPostgres implements MigrationInterface {

    async up(queryRunner: QueryRunner): Promise<void> {
        // 1. 스키마 생성 (PostgreSQL)
        await this.createSchemas(queryRunner);

        // 2. 데이터 마이그레이션
        await this.migrateUsers(queryRunner);
        await this.migrateAccounts(queryRunner);
        await this.migrateOrders(queryRunner);
        await this.migratePositions(queryRunner);
        await this.migrateTrades(queryRunner);

        // 3. 인덱스 생성
        await this.createIndexes(queryRunner);

        // 4. 제약조건 활성화
        await this.enableConstraints(queryRunner);
    }

    async down(queryRunner: QueryRunner): Promise<void> {
        // 롤백: PostgreSQL → MySQL로 복원
        await this.rollbackToMysql(queryRunner);
    }

    private async migrateUsers(queryRunner: QueryRunner) {
        // 배치 단위로 마이그레이션
        const batchSize = 10000;
        let offset = 0;

        while (true) {
            const users = await this.mysqlConnection.query(
                `SELECT * FROM users LIMIT ${batchSize} OFFSET ${offset}`
            );

            if (users.length === 0) break;

            await queryRunner.query(
                `INSERT INTO users (id, uid, email, role, status, created_at, updated_at)
                 SELECT * FROM jsonb_to_recordset($1::jsonb)
                 AS t(id bigint, uid text, email text, role text, status text,
                      created_at timestamptz, updated_at timestamptz)`,
                [JSON.stringify(users)]
            );

            offset += batchSize;
            console.log(`Migrated ${offset} users...`);
        }
    }
}
```

#### 듀얼 라이트 전략

```typescript
// 전환 기간 동안 MySQL과 PostgreSQL 동시 쓰기

@Injectable()
export class DualWriteService {
    constructor(
        @InjectRepository(UserEntity, 'mysql')
        private mysqlRepo: Repository<UserEntity>,
        @InjectRepository(UserEntity, 'postgres')
        private postgresRepo: Repository<UserEntity>,
    ) {}

    async save(user: UserEntity): Promise<UserEntity> {
        // MySQL에 먼저 쓰기 (기존 로직)
        const mysqlResult = await this.mysqlRepo.save(user);

        // PostgreSQL에 비동기 쓰기
        this.postgresRepo.save(user).catch(err => {
            // 불일치 기록 (나중에 동기화)
            this.recordDiscrepancy('users', user.id, err);
        });

        return mysqlResult;
    }
}
```

### 1.2 Redis Cluster 구축

```yaml
# redis-cluster-migration.yaml

# 1단계: 새 Redis Cluster 구축 (기존 Redis 유지)
# 2단계: 애플리케이션이 양쪽에 쓰기
# 3단계: 읽기를 Redis Cluster로 전환
# 4단계: 기존 Redis 비활성화

services:
  redis-cluster-1:
    image: redis:7-alpine
    command: >
      redis-server
      --cluster-enabled yes
      --cluster-config-file nodes.conf
      --cluster-node-timeout 5000
      --appendonly yes
    ports:
      - "6380:6379"

  redis-cluster-2:
    image: redis:7-alpine
    command: >
      redis-server
      --cluster-enabled yes
      --cluster-config-file nodes.conf
      --cluster-node-timeout 5000
      --appendonly yes
      --port 6380
    ports:
      - "6381:6380"

  redis-cluster-3:
    image: redis:7-alpine
    command: >
      redis-server
      --cluster-enabled yes
      --cluster-config-file nodes.conf
      --cluster-node-timeout 5000
      --appendonly yes
      --port 6381
    ports:
      - "6382:6381"
```

### 1.3 모니터링 구축

```yaml
# monitoring-stack.yaml

services:
  prometheus:
    image: prom/prometheus:latest
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    ports:
      - "9090:9090"

  grafana:
    image: grafana/grafana:latest
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
    volumes:
      - ./grafana/dashboards:/var/lib/grafana/dashboards
    ports:
      - "3001:3000"

  alertmanager:
    image: prom/alertmanager:latest
    volumes:
      - ./alertmanager.yml:/etc/alertmanager/alertmanager.yml
    ports:
      - "9093:9093"

  node-exporter:
    image: prom/node-exporter:latest
    ports:
      - "9100:9100"
```

### Phase 1 체크리스트

- [ ] PostgreSQL 클러스터 구축
- [ ] 데이터 마이그레이션 스크립트 작성
- [ ] 듀얼 라이트 구현
- [ ] 데이터 정합성 검증 도구
- [ ] Redis Cluster 구축
- [ ] 기존 Redis → Cluster 마이그레이션
- [ ] Prometheus + Grafana 설정
- [ ] 알림 규칙 설정
- [ ] 헬스체크 엔드포인트 구현
- [ ] 롤백 테스트 완료

---

## Phase 2: CQRS + Event Sourcing (2-3개월)

### 목표
- 명령/쿼리 분리
- 이벤트 소싱 도입
- 감사 추적 및 상태 복구 능력 확보

### 2.1 Event Store 구축

```sql
-- Event Store 테이블 생성
CREATE TABLE event_store (
    id BIGSERIAL PRIMARY KEY,
    event_id UUID NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    aggregate_type VARCHAR(100) NOT NULL,
    aggregate_id VARCHAR(100) NOT NULL,
    version INTEGER NOT NULL,
    payload JSONB NOT NULL,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(aggregate_type, aggregate_id, version)
);

CREATE INDEX idx_event_store_aggregate
ON event_store(aggregate_type, aggregate_id, version);

CREATE INDEX idx_event_store_type
ON event_store(event_type);
```

### 2.2 점진적 CQRS 도입

```typescript
// 1단계: 기존 서비스에 이벤트 발행 추가

@Injectable()
export class OrderService {
    constructor(
        private readonly orderRepo: OrderRepository,
        private readonly eventStore: EventStore,  // 새로 추가
    ) {}

    async createOrder(dto: CreateOrderDto): Promise<Order> {
        // 기존 로직
        const order = await this.orderRepo.save(dto);

        // 이벤트 발행 (새로 추가)
        await this.eventStore.append({
            eventType: 'OrderPlaced',
            aggregateType: 'Order',
            aggregateId: order.id.toString(),
            payload: order,
        });

        return order;
    }
}

// 2단계: Read Model 분리
@Injectable()
export class OrderQueryService {
    constructor(
        @InjectRepository(OrderReadModel, 'replica')
        private readonly readModelRepo: Repository<OrderReadModel>,
    ) {}

    async getActiveOrders(userId: number): Promise<OrderReadModel[]> {
        // Read 전용 모델에서 조회
        return this.readModelRepo.find({
            where: { userId, status: 'ACTIVE' },
        });
    }
}

// 3단계: Aggregate로 전환
@Injectable()
export class OrderCommandHandler {
    async handle(command: PlaceOrderCommand): Promise<void> {
        // Aggregate 로드
        const aggregate = await this.loadAggregate(command.orderId);

        // 명령 실행 (이벤트 생성)
        aggregate.placeOrder(command);

        // 이벤트 저장
        await this.eventStore.append(aggregate.getUncommittedEvents());
    }
}
```

### 2.3 TimescaleDB 도입

```sql
-- 기존 MySQL 캔들 데이터 → TimescaleDB 마이그레이션

-- 1. TimescaleDB에 테이블 생성
CREATE TABLE ohlcv_1m (
    time TIMESTAMPTZ NOT NULL,
    symbol TEXT NOT NULL,
    open DECIMAL(36, 18),
    high DECIMAL(36, 18),
    low DECIMAL(36, 18),
    close DECIMAL(36, 18),
    volume DECIMAL(36, 18)
);

SELECT create_hypertable('ohlcv_1m', 'time');

-- 2. 데이터 마이그레이션 (외부 스크립트로)
-- mysql_to_timescale_migration.py

-- 3. 연속 집계 생성
CREATE MATERIALIZED VIEW ohlcv_1h
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('1 hour', time) AS bucket,
    symbol,
    first(open, time) AS open,
    max(high) AS high,
    min(low) AS low,
    last(close, time) AS close,
    sum(volume) AS volume
FROM ohlcv_1m
GROUP BY bucket, symbol;
```

### Phase 2 체크리스트

- [ ] Event Store 테이블 생성
- [ ] 이벤트 발행 로직 추가
- [ ] Projection 서비스 구현
- [ ] Read Model 분리
- [ ] Aggregate 패턴 적용
- [ ] TimescaleDB 설치
- [ ] 캔들 데이터 마이그레이션
- [ ] 연속 집계 설정
- [ ] API 엔드포인트 전환

---

## Phase 3: 샤딩 + HA (2-3개월)

### 목표
- 매칭 엔진 수평 확장
- 장애 복구 시스템 구축
- 무중단 서비스 달성

### 3.1 매칭 엔진 샤딩

```
전환 전략:
1. 신규 샤드 인프라 구축 (기존 엔진 유지)
2. 저볼륨 심볼부터 새 샤드로 전환
3. 고볼륨 심볼 (BTC, ETH) 마지막 전환
4. 기존 엔진 비활성화

┌─────────────────────────────────────────────────────────────┐
│  Week 1-2: 인프라 구축                                       │
│  ├── Shard-1 (BTC) Primary + Standby 배포                   │
│  ├── Shard-2 (ETH) Primary + Standby 배포                   │
│  └── Shard-3 (Others) Primary + Standby 배포                │
├─────────────────────────────────────────────────────────────┤
│  Week 3-4: 저볼륨 심볼 전환                                   │
│  ├── SOL, XRP, ADA 등을 Shard-3으로 라우팅                  │
│  ├── 모니터링 및 검증                                        │
│  └── 문제 시 롤백                                            │
├─────────────────────────────────────────────────────────────┤
│  Week 5-6: ETH 전환                                          │
│  ├── ETH를 Shard-2로 라우팅                                 │
│  ├── 모니터링 및 검증                                        │
│  └── 문제 시 롤백                                            │
├─────────────────────────────────────────────────────────────┤
│  Week 7-8: BTC 전환 및 기존 엔진 비활성화                     │
│  ├── BTC를 Shard-1로 라우팅                                 │
│  ├── 기존 단일 엔진 비활성화                                 │
│  └── 최종 검증                                               │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Canary 배포

```typescript
// Order Router: Canary 배포 지원

@Injectable()
export class OrderRouter {
    private canaryConfig: CanaryConfig;

    async routeOrder(order: Order): Promise<void> {
        const symbol = order.symbol;

        // Canary 설정 확인
        if (this.isCanaryEnabled(symbol)) {
            const canaryPercentage = this.canaryConfig.getPercentage(symbol);

            // 일부 트래픽만 새 샤드로
            if (Math.random() * 100 < canaryPercentage) {
                await this.routeToNewShard(order);
                return;
            }
        }

        // 기존 엔진으로 라우팅
        await this.routeToLegacyEngine(order);
    }

    // Canary 비율 점진적 증가
    async increaseCanaryTraffic(symbol: string, percentage: number) {
        this.canaryConfig.setPercentage(symbol, percentage);

        // 메트릭 모니터링
        await this.monitorCanaryHealth(symbol);
    }
}
```

### 3.3 DR 구축

```yaml
# dr-setup.yaml

# Primary Region (ap-northeast-2)
primary:
  matching-engine:
    replicas: 1
    role: primary
  postgres:
    mode: primary
    synchronous_standby: dr-replica
  redis:
    mode: master
  kafka:
    replication-factor: 3

# DR Region (ap-southeast-1)
dr:
  matching-engine:
    replicas: 1
    role: standby
    sync-source: primary
  postgres:
    mode: replica
    primary-host: primary-postgres
  redis:
    mode: slave
    master-host: primary-redis
  kafka:
    mirror-maker:
      source: primary-kafka
      topics: ["*"]
```

### Phase 3 체크리스트

- [ ] 샤드별 인프라 구축
- [ ] Order Router 구현
- [ ] Canary 배포 설정
- [ ] 저볼륨 심볼 전환
- [ ] 고볼륨 심볼 전환
- [ ] Standby 동기화 구현
- [ ] Failover Manager 구현
- [ ] DR Region 구축
- [ ] 페일오버 테스트
- [ ] 기존 엔진 비활성화

---

## Phase 4: 성능 최적화 (지속)

### 목표
- 지속적인 성능 개선
- 비용 최적화
- 새로운 기술 도입

### 4.1 핫 경로 최적화

```java
// 객체 풀링, GC 최적화 적용

public class OptimizedMatcher {
    // 사전 할당된 객체 풀
    private final ObjectPool<Trade> tradePool = new ObjectPool<>(10000);

    public Trade match(Order taker, Order maker) {
        // new Trade() 대신 풀에서 획득
        Trade trade = tradePool.acquire();
        // ... 매칭 로직
        return trade;
    }
}
```

### 4.2 프로토콜 최적화

```
JSON → Protocol Buffers 전환

성능 비교:
┌──────────────┬─────────┬─────────┐
│              │  JSON   │ Protobuf│
├──────────────┼─────────┼─────────┤
│ 직렬화 속도  │  100%   │   30%   │
│ 메시지 크기  │  100%   │   40%   │
│ CPU 사용량   │  100%   │   50%   │
└──────────────┴─────────┴─────────┘
```

### Phase 4 체크리스트

- [ ] Object Pool 구현
- [ ] Hot/Cold Path 분리
- [ ] JVM 튜닝
- [ ] Protocol Buffers 적용
- [ ] 캐시 계층 최적화
- [ ] 성능 벤치마크 자동화

---

## 롤백 계획

### Phase 1 롤백

```bash
# PostgreSQL → MySQL 롤백
1. 듀얼 라이트 모드에서 MySQL 우선으로 전환
2. PostgreSQL 연결 비활성화
3. 데이터 정합성 검증

# Redis Cluster → 단일 Redis 롤백
1. 애플리케이션 설정을 단일 Redis로 변경
2. 클러스터 연결 해제
```

### Phase 2 롤백

```bash
# CQRS 롤백
1. Read Model 대신 기존 Repository 사용
2. Event Store 비활성화 (데이터는 보존)
3. Projection 서비스 중지
```

### Phase 3 롤백

```bash
# 샤딩 롤백
1. Order Router에서 모든 트래픽을 기존 엔진으로
2. 새 샤드 비활성화
3. 기존 엔진 용량 확보 확인
```

---

## 리스크 관리

### 식별된 리스크

| 리스크 | 영향 | 확률 | 완화 전략 |
|--------|------|------|----------|
| 데이터 손실 | 높음 | 낮음 | 듀얼 라이트, 백업 검증 |
| 서비스 중단 | 높음 | 중간 | Canary 배포, 빠른 롤백 |
| 성능 저하 | 중간 | 중간 | 점진적 전환, 모니터링 |
| 일정 지연 | 낮음 | 높음 | 버퍼 기간, 우선순위 조정 |

### 모니터링 지표

```yaml
# 마이그레이션 기간 중점 모니터링 항목

critical:
  - error_rate > 0.1%
  - latency_p99 > 100ms
  - data_discrepancy > 0

warning:
  - error_rate > 0.01%
  - latency_p99 > 50ms
  - cpu_usage > 80%
  - memory_usage > 85%
```

---

## 최종 목표 아키텍처

```
┌─────────────────────────────────────────────────────────────────┐
│                    Target Architecture                           │
│                                                                  │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐                         │
│  │ Client  │  │ Client  │  │ Client  │                         │
│  └────┬────┘  └────┬────┘  └────┬────┘                         │
│       └────────────┼────────────┘                               │
│                    │                                             │
│           ┌────────▼────────┐                                   │
│           │   API Gateway   │                                   │
│           │  Rate Limiting  │                                   │
│           └────────┬────────┘                                   │
│                    │                                             │
│    ┌───────────────┼───────────────┐                           │
│    │               │               │                            │
│ ┌──▼───┐       ┌───▼──┐       ┌───▼──┐                        │
│ │ REST │       │  WS  │       │Admin │                         │
│ │ API  │       │ GW   │       │ API  │                         │
│ └──┬───┘       └───┬──┘       └───┬──┘                         │
│    │               │               │                            │
│    └───────────────┼───────────────┘                           │
│                    │                                             │
│           ┌────────▼────────┐                                   │
│           │  Kafka Cluster  │                                   │
│           │    (KRaft)      │                                   │
│           └────────┬────────┘                                   │
│                    │                                             │
│    ┌───────────────┼───────────────┐                           │
│    │               │               │                            │
│ ┌──▼───┐       ┌───▼──┐       ┌───▼──┐                        │
│ │Shard │       │Shard │       │Shard │                         │
│ │  1   │       │  2   │       │  3   │                         │
│ │ BTC  │       │ ETH  │       │Others│                         │
│ └──┬───┘       └───┬──┘       └───┬──┘                         │
│    │               │               │                            │
│    └───────────────┼───────────────┘                           │
│                    │                                             │
│  ┌─────────┬───────┼───────┬─────────┐                         │
│  │         │       │       │         │                          │
│ ┌▼──────┐ ┌▼─────┐┌▼─────┐┌▼───────┐┌▼────────┐               │
│ │Postgre│ │Redis ││Time- ││ Event  ││   DR    │               │
│ │  SQL  │ │Clust.││scale ││ Store  ││ Region  │               │
│ └───────┘ └──────┘└──────┘└────────┘└─────────┘               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

달성 목표:
✅ 매칭 지연시간: <5ms
✅ 처리량: 100K+ 주문/초
✅ 가용성: 99.99%
✅ RTO: <30초
✅ RPO: 0
```

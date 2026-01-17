# 데이터베이스 아키텍처 가이드

## 개요

암호화폐 선물 거래소는 다양한 데이터 특성에 맞는 최적화된 데이터베이스 전략이 필요합니다. 이 문서에서는 PostgreSQL, Redis Cluster, TimescaleDB를 활용한 멀티 데이터베이스 아키텍처를 다룹니다.

## 아키텍처 개요

```
┌─────────────────────────────────────────────────────────────────────┐
│                        APPLICATION LAYER                             │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐            │
│   │   Command   │    │    Query    │    │   Market    │            │
│   │   Service   │    │   Service   │    │   Data      │            │
│   └──────┬──────┘    └──────┬──────┘    └──────┬──────┘            │
└──────────┼──────────────────┼──────────────────┼────────────────────┘
           │                  │                  │
           ▼                  ▼                  ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   PostgreSQL    │  │  Redis Cluster  │  │  TimescaleDB    │
│   (Write DB)    │  │   (Cache/RT)    │  │  (Time-Series)  │
│                 │  │                 │  │                 │
│ • Transactions  │  │ • Order Book    │  │ • OHLCV Candles │
│ • Orders        │  │ • Sessions      │  │ • Trade History │
│ • Positions     │  │ • Rate Limits   │  │ • Analytics     │
│ • Accounts      │  │ • Pub/Sub       │  │ • Metrics       │
└────────┬────────┘  └─────────────────┘  └─────────────────┘
         │
         ▼
┌─────────────────┐
│   PostgreSQL    │
│   (Read Replica)│
│                 │
│ • Reports       │
│ • Analytics     │
│ • Backoffice    │
└─────────────────┘
```

## 1. PostgreSQL 아키텍처

### 1.1 스키마 설계

```sql
-- 사용자 및 계정
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    kyc_level INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id),
    currency VARCHAR(20) NOT NULL,
    balance DECIMAL(36, 18) DEFAULT 0,
    available_balance DECIMAL(36, 18) DEFAULT 0,
    locked_balance DECIMAL(36, 18) DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    version BIGINT DEFAULT 0,  -- Optimistic locking
    UNIQUE(user_id, currency)
);

-- 주문
CREATE TABLE orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id),
    symbol VARCHAR(20) NOT NULL,
    side VARCHAR(4) NOT NULL,  -- BUY, SELL
    type VARCHAR(20) NOT NULL,  -- LIMIT, MARKET, STOP_LIMIT
    status VARCHAR(20) DEFAULT 'NEW',
    price DECIMAL(36, 18),
    quantity DECIMAL(36, 18) NOT NULL,
    filled_quantity DECIMAL(36, 18) DEFAULT 0,
    remaining_quantity DECIMAL(36, 18),
    average_price DECIMAL(36, 18),
    leverage INTEGER DEFAULT 1,
    margin_type VARCHAR(20),  -- CROSS, ISOLATED
    reduce_only BOOLEAN DEFAULT FALSE,
    time_in_force VARCHAR(10) DEFAULT 'GTC',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 포지션
CREATE TABLE positions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id),
    symbol VARCHAR(20) NOT NULL,
    side VARCHAR(5) NOT NULL,  -- LONG, SHORT
    size DECIMAL(36, 18) DEFAULT 0,
    entry_price DECIMAL(36, 18),
    mark_price DECIMAL(36, 18),
    liquidation_price DECIMAL(36, 18),
    margin DECIMAL(36, 18) DEFAULT 0,
    unrealized_pnl DECIMAL(36, 18) DEFAULT 0,
    realized_pnl DECIMAL(36, 18) DEFAULT 0,
    leverage INTEGER DEFAULT 1,
    margin_type VARCHAR(20) DEFAULT 'CROSS',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    version BIGINT DEFAULT 0,
    UNIQUE(user_id, symbol, side)
);

-- 체결
CREATE TABLE trades (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    symbol VARCHAR(20) NOT NULL,
    buyer_order_id UUID REFERENCES orders(id),
    seller_order_id UUID REFERENCES orders(id),
    price DECIMAL(36, 18) NOT NULL,
    quantity DECIMAL(36, 18) NOT NULL,
    buyer_fee DECIMAL(36, 18) DEFAULT 0,
    seller_fee DECIMAL(36, 18) DEFAULT 0,
    is_buyer_maker BOOLEAN NOT NULL,
    trade_time TIMESTAMPTZ DEFAULT NOW()
);

-- 자금 이체 기록
CREATE TABLE transfers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id),
    type VARCHAR(20) NOT NULL,  -- DEPOSIT, WITHDRAW, TRANSFER
    currency VARCHAR(20) NOT NULL,
    amount DECIMAL(36, 18) NOT NULL,
    fee DECIMAL(36, 18) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'PENDING',
    tx_hash VARCHAR(255),
    address VARCHAR(255),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 이벤트 스토어
CREATE TABLE events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    aggregate_type VARCHAR(50) NOT NULL,
    aggregate_id UUID NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_data JSONB NOT NULL,
    metadata JSONB DEFAULT '{}',
    version BIGINT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(aggregate_id, version)
);
```

### 1.2 인덱스 전략

```sql
-- 주문 조회 최적화
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_orders_symbol_status ON orders(symbol, status);
CREATE INDEX idx_orders_created_at ON orders(created_at DESC);

-- 포지션 조회 최적화
CREATE INDEX idx_positions_user ON positions(user_id);
CREATE INDEX idx_positions_symbol ON positions(symbol);

-- 체결 조회 최적화
CREATE INDEX idx_trades_symbol_time ON trades(symbol, trade_time DESC);
CREATE INDEX idx_trades_buyer_order ON trades(buyer_order_id);
CREATE INDEX idx_trades_seller_order ON trades(seller_order_id);

-- 이벤트 스토어 최적화
CREATE INDEX idx_events_aggregate ON events(aggregate_type, aggregate_id, version);
CREATE INDEX idx_events_type_time ON events(event_type, created_at);

-- 파티셔닝용 (체결 테이블)
-- 월별 파티셔닝으로 대용량 데이터 관리
CREATE TABLE trades_partitioned (
    LIKE trades INCLUDING ALL
) PARTITION BY RANGE (trade_time);

CREATE TABLE trades_2024_01 PARTITION OF trades_partitioned
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
CREATE TABLE trades_2024_02 PARTITION OF trades_partitioned
    FOR VALUES FROM ('2024-02-01') TO ('2024-03-01');
-- ... 월별 계속
```

### 1.3 연결 풀링 (PgBouncer)

```ini
; pgbouncer.ini
[databases]
exchange_write = host=pg-primary port=5432 dbname=exchange
exchange_read = host=pg-replica port=5432 dbname=exchange

[pgbouncer]
listen_addr = 0.0.0.0
listen_port = 6432
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt

; 트랜잭션 모드로 연결 효율성 극대화
pool_mode = transaction

; 풀 사이즈 설정
default_pool_size = 100
min_pool_size = 10
reserve_pool_size = 20

; 타임아웃 설정
server_lifetime = 3600
server_idle_timeout = 600
query_timeout = 30
query_wait_timeout = 120

; 보안
server_tls_sslmode = require
```

### 1.4 TypeScript Repository 패턴

```typescript
// src/database/repositories/order.repository.ts
import { Pool, PoolClient } from 'pg';
import { Order, OrderStatus, OrderSide, OrderType } from '../entities/order.entity';

export class OrderRepository {
  constructor(
    private readonly writePool: Pool,
    private readonly readPool: Pool
  ) {}

  async create(order: Partial<Order>, client?: PoolClient): Promise<Order> {
    const conn = client || this.writePool;
    const query = `
      INSERT INTO orders (
        user_id, symbol, side, type, status, price, quantity,
        remaining_quantity, leverage, margin_type, reduce_only, time_in_force
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
      RETURNING *
    `;

    const values = [
      order.userId,
      order.symbol,
      order.side,
      order.type,
      OrderStatus.NEW,
      order.price,
      order.quantity,
      order.quantity, // remaining = quantity initially
      order.leverage || 1,
      order.marginType || 'CROSS',
      order.reduceOnly || false,
      order.timeInForce || 'GTC'
    ];

    const result = await conn.query(query, values);
    return this.mapToEntity(result.rows[0]);
  }

  async findById(id: string): Promise<Order | null> {
    const query = 'SELECT * FROM orders WHERE id = $1';
    const result = await this.readPool.query(query, [id]);
    return result.rows[0] ? this.mapToEntity(result.rows[0]) : null;
  }

  async findByUserAndStatus(
    userId: string,
    statuses: OrderStatus[],
    limit: number = 100
  ): Promise<Order[]> {
    const query = `
      SELECT * FROM orders
      WHERE user_id = $1 AND status = ANY($2)
      ORDER BY created_at DESC
      LIMIT $3
    `;
    const result = await this.readPool.query(query, [userId, statuses, limit]);
    return result.rows.map(row => this.mapToEntity(row));
  }

  async updateStatus(
    id: string,
    status: OrderStatus,
    filledQuantity?: string,
    averagePrice?: string,
    client?: PoolClient
  ): Promise<Order | null> {
    const conn = client || this.writePool;
    const query = `
      UPDATE orders SET
        status = $2,
        filled_quantity = COALESCE($3, filled_quantity),
        remaining_quantity = quantity - COALESCE($3, filled_quantity),
        average_price = COALESCE($4, average_price),
        updated_at = NOW()
      WHERE id = $1
      RETURNING *
    `;

    const result = await conn.query(query, [id, status, filledQuantity, averagePrice]);
    return result.rows[0] ? this.mapToEntity(result.rows[0]) : null;
  }

  // 트랜잭션 헬퍼
  async withTransaction<T>(
    callback: (client: PoolClient) => Promise<T>
  ): Promise<T> {
    const client = await this.writePool.connect();
    try {
      await client.query('BEGIN');
      const result = await callback(client);
      await client.query('COMMIT');
      return result;
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }

  private mapToEntity(row: any): Order {
    return {
      id: row.id,
      userId: row.user_id,
      symbol: row.symbol,
      side: row.side as OrderSide,
      type: row.type as OrderType,
      status: row.status as OrderStatus,
      price: row.price,
      quantity: row.quantity,
      filledQuantity: row.filled_quantity,
      remainingQuantity: row.remaining_quantity,
      averagePrice: row.average_price,
      leverage: row.leverage,
      marginType: row.margin_type,
      reduceOnly: row.reduce_only,
      timeInForce: row.time_in_force,
      createdAt: row.created_at,
      updatedAt: row.updated_at
    };
  }
}
```

## 2. Redis Cluster 아키텍처

### 2.1 클러스터 구성

```yaml
# docker-compose.redis-cluster.yml
version: '3.8'

services:
  redis-node-1:
    image: redis:7-alpine
    command: redis-server /usr/local/etc/redis/redis.conf
    ports:
      - "7001:7001"
      - "17001:17001"
    volumes:
      - ./redis-cluster/node1.conf:/usr/local/etc/redis/redis.conf
      - redis-data-1:/data

  redis-node-2:
    image: redis:7-alpine
    command: redis-server /usr/local/etc/redis/redis.conf
    ports:
      - "7002:7002"
      - "17002:17002"
    volumes:
      - ./redis-cluster/node2.conf:/usr/local/etc/redis/redis.conf
      - redis-data-2:/data

  redis-node-3:
    image: redis:7-alpine
    command: redis-server /usr/local/etc/redis/redis.conf
    ports:
      - "7003:7003"
      - "17003:17003"
    volumes:
      - ./redis-cluster/node3.conf:/usr/local/etc/redis/redis.conf
      - redis-data-3:/data

  redis-node-4:
    image: redis:7-alpine
    command: redis-server /usr/local/etc/redis/redis.conf
    ports:
      - "7004:7004"
      - "17004:17004"
    volumes:
      - ./redis-cluster/node4.conf:/usr/local/etc/redis/redis.conf
      - redis-data-4:/data

  redis-node-5:
    image: redis:7-alpine
    command: redis-server /usr/local/etc/redis/redis.conf
    ports:
      - "7005:7005"
      - "17005:17005"
    volumes:
      - ./redis-cluster/node5.conf:/usr/local/etc/redis/redis.conf
      - redis-data-5:/data

  redis-node-6:
    image: redis:7-alpine
    command: redis-server /usr/local/etc/redis/redis.conf
    ports:
      - "7006:7006"
      - "17006:17006"
    volumes:
      - ./redis-cluster/node6.conf:/usr/local/etc/redis/redis.conf
      - redis-data-6:/data

volumes:
  redis-data-1:
  redis-data-2:
  redis-data-3:
  redis-data-4:
  redis-data-5:
  redis-data-6:
```

### 2.2 노드 설정

```conf
# redis-cluster/node1.conf
port 7001
cluster-enabled yes
cluster-config-file nodes-7001.conf
cluster-node-timeout 5000
appendonly yes
appendfsync everysec

# 메모리 설정
maxmemory 2gb
maxmemory-policy volatile-lru

# 보안
requirepass your-redis-password
masterauth your-redis-password

# 클러스터 버스 포트
cluster-announce-port 7001
cluster-announce-bus-port 17001

# 성능 튜닝
tcp-backlog 511
tcp-keepalive 300
timeout 0

# 스냅샷
save 900 1
save 300 10
save 60 10000
```

### 2.3 오더북 캐시 구현

```typescript
// src/cache/orderbook.cache.ts
import Redis from 'ioredis';

export interface OrderBookLevel {
  price: string;
  quantity: string;
  orderCount: number;
}

export interface OrderBookSnapshot {
  symbol: string;
  bids: OrderBookLevel[];
  asks: OrderBookLevel[];
  lastUpdateId: number;
  timestamp: number;
}

export class OrderBookCache {
  private cluster: Redis.Cluster;
  private readonly ORDERBOOK_TTL = 60; // 60초

  constructor(nodes: { host: string; port: number }[]) {
    this.cluster = new Redis.Cluster(nodes, {
      redisOptions: {
        password: process.env.REDIS_PASSWORD,
      },
      scaleReads: 'slave',
      enableReadyCheck: true,
      maxRedirections: 16,
    });
  }

  // 오더북 스냅샷 저장
  async saveSnapshot(snapshot: OrderBookSnapshot): Promise<void> {
    const key = `orderbook:${snapshot.symbol}`;
    const pipeline = this.cluster.pipeline();

    // 기존 데이터 삭제
    pipeline.del(`${key}:bids`);
    pipeline.del(`${key}:asks`);

    // Sorted Set으로 bids 저장 (가격 내림차순)
    for (const level of snapshot.bids) {
      pipeline.zadd(
        `${key}:bids`,
        parseFloat(level.price),
        JSON.stringify(level)
      );
    }

    // Sorted Set으로 asks 저장 (가격 오름차순)
    for (const level of snapshot.asks) {
      pipeline.zadd(
        `${key}:asks`,
        parseFloat(level.price),
        JSON.stringify(level)
      );
    }

    // 메타데이터 저장
    pipeline.hset(key, {
      lastUpdateId: snapshot.lastUpdateId.toString(),
      timestamp: snapshot.timestamp.toString(),
    });

    // TTL 설정
    pipeline.expire(`${key}:bids`, this.ORDERBOOK_TTL);
    pipeline.expire(`${key}:asks`, this.ORDERBOOK_TTL);
    pipeline.expire(key, this.ORDERBOOK_TTL);

    await pipeline.exec();
  }

  // 오더북 스냅샷 조회
  async getSnapshot(symbol: string, depth: number = 20): Promise<OrderBookSnapshot | null> {
    const key = `orderbook:${symbol}`;

    const pipeline = this.cluster.pipeline();
    pipeline.hgetall(key);
    pipeline.zrevrange(`${key}:bids`, 0, depth - 1);  // 높은 가격부터
    pipeline.zrange(`${key}:asks`, 0, depth - 1);     // 낮은 가격부터

    const results = await pipeline.exec();
    if (!results) return null;

    const [metaResult, bidsResult, asksResult] = results;
    const meta = metaResult?.[1] as Record<string, string>;

    if (!meta || !meta.lastUpdateId) return null;

    return {
      symbol,
      bids: (bidsResult?.[1] as string[] || []).map(s => JSON.parse(s)),
      asks: (asksResult?.[1] as string[] || []).map(s => JSON.parse(s)),
      lastUpdateId: parseInt(meta.lastUpdateId),
      timestamp: parseInt(meta.timestamp),
    };
  }

  // 개별 가격 레벨 업데이트
  async updateLevel(
    symbol: string,
    side: 'bid' | 'ask',
    level: OrderBookLevel
  ): Promise<void> {
    const key = `orderbook:${symbol}:${side}s`;
    const price = parseFloat(level.price);

    if (parseFloat(level.quantity) === 0) {
      // 수량이 0이면 삭제
      await this.cluster.zremrangebyscore(key, price, price);
    } else {
      // 업데이트 또는 추가
      await this.cluster.zadd(key, price, JSON.stringify(level));
    }
  }

  // Best Bid/Ask 조회
  async getBestBidAsk(symbol: string): Promise<{ bid: string; ask: string } | null> {
    const key = `orderbook:${symbol}`;

    const pipeline = this.cluster.pipeline();
    pipeline.zrevrange(`${key}:bids`, 0, 0);  // 최고 매수가
    pipeline.zrange(`${key}:asks`, 0, 0);      // 최저 매도가

    const results = await pipeline.exec();
    if (!results) return null;

    const [bidResult, askResult] = results;
    const bidData = bidResult?.[1] as string[];
    const askData = askResult?.[1] as string[];

    if (!bidData?.length || !askData?.length) return null;

    return {
      bid: JSON.parse(bidData[0]).price,
      ask: JSON.parse(askData[0]).price,
    };
  }
}
```

### 2.4 세션 및 Rate Limiting

```typescript
// src/cache/session.cache.ts
import Redis from 'ioredis';

export class SessionCache {
  private cluster: Redis.Cluster;
  private readonly SESSION_TTL = 86400; // 24시간
  private readonly RATE_LIMIT_WINDOW = 60; // 60초

  constructor(cluster: Redis.Cluster) {
    this.cluster = cluster;
  }

  // 세션 저장
  async setSession(sessionId: string, userId: string, data: object): Promise<void> {
    const key = `session:${sessionId}`;
    await this.cluster.hset(key, {
      userId,
      data: JSON.stringify(data),
      createdAt: Date.now().toString(),
    });
    await this.cluster.expire(key, this.SESSION_TTL);
  }

  // 세션 조회
  async getSession(sessionId: string): Promise<{ userId: string; data: object } | null> {
    const key = `session:${sessionId}`;
    const result = await this.cluster.hgetall(key);

    if (!result || !result.userId) return null;

    // TTL 갱신
    await this.cluster.expire(key, this.SESSION_TTL);

    return {
      userId: result.userId,
      data: JSON.parse(result.data || '{}'),
    };
  }

  // 세션 삭제
  async deleteSession(sessionId: string): Promise<void> {
    await this.cluster.del(`session:${sessionId}`);
  }

  // Rate Limiting (Sliding Window)
  async checkRateLimit(
    identifier: string,
    limit: number,
    windowSeconds: number = this.RATE_LIMIT_WINDOW
  ): Promise<{ allowed: boolean; remaining: number; resetAt: number }> {
    const key = `ratelimit:${identifier}`;
    const now = Date.now();
    const windowStart = now - (windowSeconds * 1000);

    const pipeline = this.cluster.pipeline();

    // 윈도우 밖의 오래된 요청 제거
    pipeline.zremrangebyscore(key, 0, windowStart);

    // 현재 윈도우의 요청 수 조회
    pipeline.zcard(key);

    // 새 요청 추가
    pipeline.zadd(key, now, `${now}:${Math.random()}`);

    // TTL 설정
    pipeline.expire(key, windowSeconds);

    const results = await pipeline.exec();
    const currentCount = (results?.[1]?.[1] as number) || 0;

    const allowed = currentCount < limit;
    const remaining = Math.max(0, limit - currentCount - 1);
    const resetAt = now + (windowSeconds * 1000);

    return { allowed, remaining, resetAt };
  }

  // 사용자별 활성 주문 수 관리
  async incrementActiveOrders(userId: string): Promise<number> {
    const key = `user:${userId}:active_orders`;
    return await this.cluster.incr(key);
  }

  async decrementActiveOrders(userId: string): Promise<number> {
    const key = `user:${userId}:active_orders`;
    const count = await this.cluster.decr(key);
    return Math.max(0, count);
  }

  async getActiveOrderCount(userId: string): Promise<number> {
    const key = `user:${userId}:active_orders`;
    const count = await this.cluster.get(key);
    return parseInt(count || '0');
  }
}
```

## 3. TimescaleDB 아키텍처

### 3.1 시계열 테이블 설계

```sql
-- TimescaleDB 확장 설치
CREATE EXTENSION IF NOT EXISTS timescaledb;

-- OHLCV 캔들 데이터
CREATE TABLE candles (
    time TIMESTAMPTZ NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    interval VARCHAR(10) NOT NULL,  -- 1m, 5m, 15m, 1h, 4h, 1d
    open DECIMAL(36, 18) NOT NULL,
    high DECIMAL(36, 18) NOT NULL,
    low DECIMAL(36, 18) NOT NULL,
    close DECIMAL(36, 18) NOT NULL,
    volume DECIMAL(36, 18) NOT NULL,
    quote_volume DECIMAL(36, 18) NOT NULL,
    trade_count INTEGER NOT NULL,
    PRIMARY KEY (symbol, interval, time)
);

-- Hypertable로 변환 (자동 파티셔닝)
SELECT create_hypertable('candles', 'time',
    chunk_time_interval => INTERVAL '1 day',
    if_not_exists => TRUE
);

-- 압축 정책 (7일 이후 데이터 압축)
ALTER TABLE candles SET (
    timescaledb.compress,
    timescaledb.compress_segmentby = 'symbol, interval'
);

SELECT add_compression_policy('candles', INTERVAL '7 days');

-- 체결 기록 (상세)
CREATE TABLE trade_history (
    time TIMESTAMPTZ NOT NULL,
    trade_id UUID NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    price DECIMAL(36, 18) NOT NULL,
    quantity DECIMAL(36, 18) NOT NULL,
    is_buyer_maker BOOLEAN NOT NULL,
    PRIMARY KEY (symbol, time, trade_id)
);

SELECT create_hypertable('trade_history', 'time',
    chunk_time_interval => INTERVAL '1 hour',
    if_not_exists => TRUE
);

-- 보존 정책 (90일 후 삭제)
SELECT add_retention_policy('trade_history', INTERVAL '90 days');

-- 시스템 메트릭
CREATE TABLE system_metrics (
    time TIMESTAMPTZ NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    value DOUBLE PRECISION NOT NULL,
    tags JSONB DEFAULT '{}'
);

SELECT create_hypertable('system_metrics', 'time',
    chunk_time_interval => INTERVAL '1 hour',
    if_not_exists => TRUE
);

-- 인덱스
CREATE INDEX idx_candles_symbol_interval ON candles (symbol, interval, time DESC);
CREATE INDEX idx_trade_history_symbol ON trade_history (symbol, time DESC);
CREATE INDEX idx_metrics_name ON system_metrics (metric_name, time DESC);
```

### 3.2 연속 집계 (Continuous Aggregates)

```sql
-- 1분 캔들을 5분 캔들로 집계
CREATE MATERIALIZED VIEW candles_5m
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('5 minutes', time) AS time,
    symbol,
    '5m' AS interval,
    first(open, time) AS open,
    max(high) AS high,
    min(low) AS low,
    last(close, time) AS close,
    sum(volume) AS volume,
    sum(quote_volume) AS quote_volume,
    sum(trade_count) AS trade_count
FROM candles
WHERE interval = '1m'
GROUP BY time_bucket('5 minutes', time), symbol;

-- 자동 새로고침 정책
SELECT add_continuous_aggregate_policy('candles_5m',
    start_offset => INTERVAL '1 hour',
    end_offset => INTERVAL '1 minute',
    schedule_interval => INTERVAL '1 minute');

-- 1시간 캔들 집계
CREATE MATERIALIZED VIEW candles_1h
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('1 hour', time) AS time,
    symbol,
    '1h' AS interval,
    first(open, time) AS open,
    max(high) AS high,
    min(low) AS low,
    last(close, time) AS close,
    sum(volume) AS volume,
    sum(quote_volume) AS quote_volume,
    sum(trade_count) AS trade_count
FROM candles
WHERE interval = '1m'
GROUP BY time_bucket('1 hour', time), symbol;

SELECT add_continuous_aggregate_policy('candles_1h',
    start_offset => INTERVAL '3 hours',
    end_offset => INTERVAL '1 hour',
    schedule_interval => INTERVAL '1 hour');

-- 일별 거래 통계
CREATE MATERIALIZED VIEW daily_trading_stats
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('1 day', time) AS date,
    symbol,
    count(*) AS trade_count,
    sum(quantity) AS total_volume,
    sum(price * quantity) AS total_quote_volume,
    avg(price) AS avg_price,
    max(price) AS high_price,
    min(price) AS low_price
FROM trade_history
GROUP BY time_bucket('1 day', time), symbol;

SELECT add_continuous_aggregate_policy('daily_trading_stats',
    start_offset => INTERVAL '2 days',
    end_offset => INTERVAL '1 day',
    schedule_interval => INTERVAL '1 day');
```

### 3.3 TypeScript 서비스

```typescript
// src/services/market-data.service.ts
import { Pool } from 'pg';

export interface Candle {
  time: Date;
  symbol: string;
  interval: string;
  open: string;
  high: string;
  low: string;
  close: string;
  volume: string;
  quoteVolume: string;
  tradeCount: number;
}

export class MarketDataService {
  constructor(private readonly timescalePool: Pool) {}

  // 캔들 데이터 조회
  async getCandles(
    symbol: string,
    interval: string,
    startTime: Date,
    endTime: Date,
    limit: number = 500
  ): Promise<Candle[]> {
    const query = `
      SELECT
        time, symbol, interval, open, high, low, close,
        volume, quote_volume, trade_count
      FROM candles
      WHERE symbol = $1 AND interval = $2
        AND time >= $3 AND time <= $4
      ORDER BY time ASC
      LIMIT $5
    `;

    const result = await this.timescalePool.query(query, [
      symbol, interval, startTime, endTime, limit
    ]);

    return result.rows.map(this.mapToCandle);
  }

  // 최신 캔들 조회
  async getLatestCandle(symbol: string, interval: string): Promise<Candle | null> {
    const query = `
      SELECT
        time, symbol, interval, open, high, low, close,
        volume, quote_volume, trade_count
      FROM candles
      WHERE symbol = $1 AND interval = $2
      ORDER BY time DESC
      LIMIT 1
    `;

    const result = await this.timescalePool.query(query, [symbol, interval]);
    return result.rows[0] ? this.mapToCandle(result.rows[0]) : null;
  }

  // 새 캔들 저장 또는 업데이트
  async upsertCandle(candle: Candle): Promise<void> {
    const query = `
      INSERT INTO candles (
        time, symbol, interval, open, high, low, close,
        volume, quote_volume, trade_count
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
      ON CONFLICT (symbol, interval, time)
      DO UPDATE SET
        high = GREATEST(candles.high, EXCLUDED.high),
        low = LEAST(candles.low, EXCLUDED.low),
        close = EXCLUDED.close,
        volume = candles.volume + EXCLUDED.volume,
        quote_volume = candles.quote_volume + EXCLUDED.quote_volume,
        trade_count = candles.trade_count + EXCLUDED.trade_count
    `;

    await this.timescalePool.query(query, [
      candle.time,
      candle.symbol,
      candle.interval,
      candle.open,
      candle.high,
      candle.low,
      candle.close,
      candle.volume,
      candle.quoteVolume,
      candle.tradeCount
    ]);
  }

  // 체결로부터 캔들 업데이트
  async updateCandleFromTrade(
    symbol: string,
    price: string,
    quantity: string,
    tradeTime: Date
  ): Promise<void> {
    // 1분 캔들 버킷 계산
    const bucketTime = new Date(
      Math.floor(tradeTime.getTime() / 60000) * 60000
    );

    const quoteVolume = (parseFloat(price) * parseFloat(quantity)).toString();

    const query = `
      INSERT INTO candles (
        time, symbol, interval, open, high, low, close,
        volume, quote_volume, trade_count
      ) VALUES ($1, $2, '1m', $3, $3, $3, $3, $4, $5, 1)
      ON CONFLICT (symbol, interval, time)
      DO UPDATE SET
        high = GREATEST(candles.high, $3::decimal),
        low = LEAST(candles.low, $3::decimal),
        close = $3,
        volume = candles.volume + $4::decimal,
        quote_volume = candles.quote_volume + $5::decimal,
        trade_count = candles.trade_count + 1
    `;

    await this.timescalePool.query(query, [
      bucketTime, symbol, price, quantity, quoteVolume
    ]);
  }

  // 24시간 통계
  async get24hStats(symbol: string): Promise<{
    priceChange: string;
    priceChangePercent: string;
    high: string;
    low: string;
    volume: string;
    quoteVolume: string;
    tradeCount: number;
  }> {
    const query = `
      WITH stats AS (
        SELECT
          first(open, time) AS first_open,
          last(close, time) AS last_close,
          max(high) AS high_24h,
          min(low) AS low_24h,
          sum(volume) AS volume_24h,
          sum(quote_volume) AS quote_volume_24h,
          sum(trade_count) AS trade_count_24h
        FROM candles
        WHERE symbol = $1 AND interval = '1m'
          AND time >= NOW() - INTERVAL '24 hours'
      )
      SELECT
        (last_close - first_open) AS price_change,
        ((last_close - first_open) / first_open * 100) AS price_change_percent,
        high_24h,
        low_24h,
        volume_24h,
        quote_volume_24h,
        trade_count_24h
      FROM stats
    `;

    const result = await this.timescalePool.query(query, [symbol]);
    const row = result.rows[0];

    return {
      priceChange: row?.price_change?.toString() || '0',
      priceChangePercent: row?.price_change_percent?.toString() || '0',
      high: row?.high_24h?.toString() || '0',
      low: row?.low_24h?.toString() || '0',
      volume: row?.volume_24h?.toString() || '0',
      quoteVolume: row?.quote_volume_24h?.toString() || '0',
      tradeCount: parseInt(row?.trade_count_24h || '0')
    };
  }

  private mapToCandle(row: any): Candle {
    return {
      time: row.time,
      symbol: row.symbol,
      interval: row.interval,
      open: row.open.toString(),
      high: row.high.toString(),
      low: row.low.toString(),
      close: row.close.toString(),
      volume: row.volume.toString(),
      quoteVolume: row.quote_volume.toString(),
      tradeCount: parseInt(row.trade_count)
    };
  }
}
```

## 4. 데이터베이스 연결 관리

### 4.1 통합 데이터베이스 매니저

```typescript
// src/database/database-manager.ts
import { Pool } from 'pg';
import Redis from 'ioredis';

export interface DatabaseConfig {
  postgres: {
    write: {
      host: string;
      port: number;
      database: string;
      user: string;
      password: string;
      max: number;
    };
    read: {
      host: string;
      port: number;
      database: string;
      user: string;
      password: string;
      max: number;
    };
  };
  timescale: {
    host: string;
    port: number;
    database: string;
    user: string;
    password: string;
    max: number;
  };
  redis: {
    nodes: { host: string; port: number }[];
    password: string;
  };
}

export class DatabaseManager {
  private pgWritePool: Pool;
  private pgReadPool: Pool;
  private timescalePool: Pool;
  private redisCluster: Redis.Cluster;
  private isInitialized = false;

  constructor(private config: DatabaseConfig) {}

  async initialize(): Promise<void> {
    if (this.isInitialized) return;

    // PostgreSQL Write Pool
    this.pgWritePool = new Pool({
      ...this.config.postgres.write,
      idleTimeoutMillis: 30000,
      connectionTimeoutMillis: 5000,
    });

    // PostgreSQL Read Pool
    this.pgReadPool = new Pool({
      ...this.config.postgres.read,
      idleTimeoutMillis: 30000,
      connectionTimeoutMillis: 5000,
    });

    // TimescaleDB Pool
    this.timescalePool = new Pool({
      ...this.config.timescale,
      idleTimeoutMillis: 30000,
      connectionTimeoutMillis: 5000,
    });

    // Redis Cluster
    this.redisCluster = new Redis.Cluster(this.config.redis.nodes, {
      redisOptions: {
        password: this.config.redis.password,
      },
      scaleReads: 'slave',
      enableReadyCheck: true,
    });

    // 연결 테스트
    await Promise.all([
      this.pgWritePool.query('SELECT 1'),
      this.pgReadPool.query('SELECT 1'),
      this.timescalePool.query('SELECT 1'),
      this.redisCluster.ping(),
    ]);

    this.isInitialized = true;
    console.log('All database connections established');
  }

  get writeDb(): Pool {
    return this.pgWritePool;
  }

  get readDb(): Pool {
    return this.pgReadPool;
  }

  get timescale(): Pool {
    return this.timescalePool;
  }

  get redis(): Redis.Cluster {
    return this.redisCluster;
  }

  async healthCheck(): Promise<{
    postgres: { write: boolean; read: boolean };
    timescale: boolean;
    redis: boolean;
  }> {
    const results = await Promise.allSettled([
      this.pgWritePool.query('SELECT 1'),
      this.pgReadPool.query('SELECT 1'),
      this.timescalePool.query('SELECT 1'),
      this.redisCluster.ping(),
    ]);

    return {
      postgres: {
        write: results[0].status === 'fulfilled',
        read: results[1].status === 'fulfilled',
      },
      timescale: results[2].status === 'fulfilled',
      redis: results[3].status === 'fulfilled',
    };
  }

  async shutdown(): Promise<void> {
    await Promise.all([
      this.pgWritePool.end(),
      this.pgReadPool.end(),
      this.timescalePool.end(),
      this.redisCluster.quit(),
    ]);
    this.isInitialized = false;
    console.log('All database connections closed');
  }
}

// 싱글톤 인스턴스
let dbManager: DatabaseManager | null = null;

export function getDatabaseManager(config?: DatabaseConfig): DatabaseManager {
  if (!dbManager && config) {
    dbManager = new DatabaseManager(config);
  }
  if (!dbManager) {
    throw new Error('DatabaseManager not initialized');
  }
  return dbManager;
}
```

## 5. 모니터링 및 알림

### 5.1 Prometheus 메트릭

```typescript
// src/monitoring/database-metrics.ts
import { Registry, Gauge, Histogram, Counter } from 'prom-client';

export class DatabaseMetrics {
  private registry: Registry;

  // PostgreSQL 메트릭
  public pgConnectionsTotal: Gauge;
  public pgConnectionsIdle: Gauge;
  public pgConnectionsWaiting: Gauge;
  public pgQueryDuration: Histogram;
  public pgQueryErrors: Counter;

  // Redis 메트릭
  public redisConnectionsTotal: Gauge;
  public redisCommandDuration: Histogram;
  public redisMemoryUsage: Gauge;
  public redisCacheHits: Counter;
  public redisCacheMisses: Counter;

  // TimescaleDB 메트릭
  public tsChunkCount: Gauge;
  public tsCompressionRatio: Gauge;
  public tsInsertRate: Counter;

  constructor(registry: Registry) {
    this.registry = registry;
    this.initializeMetrics();
  }

  private initializeMetrics(): void {
    // PostgreSQL
    this.pgConnectionsTotal = new Gauge({
      name: 'pg_connections_total',
      help: 'Total PostgreSQL connections',
      labelNames: ['pool'],
      registers: [this.registry],
    });

    this.pgConnectionsIdle = new Gauge({
      name: 'pg_connections_idle',
      help: 'Idle PostgreSQL connections',
      labelNames: ['pool'],
      registers: [this.registry],
    });

    this.pgQueryDuration = new Histogram({
      name: 'pg_query_duration_seconds',
      help: 'PostgreSQL query duration',
      labelNames: ['operation', 'table'],
      buckets: [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1],
      registers: [this.registry],
    });

    this.pgQueryErrors = new Counter({
      name: 'pg_query_errors_total',
      help: 'Total PostgreSQL query errors',
      labelNames: ['operation', 'error_type'],
      registers: [this.registry],
    });

    // Redis
    this.redisCommandDuration = new Histogram({
      name: 'redis_command_duration_seconds',
      help: 'Redis command duration',
      labelNames: ['command'],
      buckets: [0.0001, 0.0005, 0.001, 0.005, 0.01, 0.05],
      registers: [this.registry],
    });

    this.redisMemoryUsage = new Gauge({
      name: 'redis_memory_usage_bytes',
      help: 'Redis memory usage',
      labelNames: ['node'],
      registers: [this.registry],
    });

    this.redisCacheHits = new Counter({
      name: 'redis_cache_hits_total',
      help: 'Redis cache hits',
      labelNames: ['cache_type'],
      registers: [this.registry],
    });

    this.redisCacheMisses = new Counter({
      name: 'redis_cache_misses_total',
      help: 'Redis cache misses',
      labelNames: ['cache_type'],
      registers: [this.registry],
    });

    // TimescaleDB
    this.tsChunkCount = new Gauge({
      name: 'timescale_chunk_count',
      help: 'Number of TimescaleDB chunks',
      labelNames: ['hypertable'],
      registers: [this.registry],
    });

    this.tsInsertRate = new Counter({
      name: 'timescale_inserts_total',
      help: 'Total TimescaleDB inserts',
      labelNames: ['hypertable'],
      registers: [this.registry],
    });
  }
}
```

## 6. 백업 및 복구 전략

### 6.1 백업 스크립트

```bash
#!/bin/bash
# backup-databases.sh

set -e

BACKUP_DIR="/backups/$(date +%Y%m%d)"
mkdir -p "$BACKUP_DIR"

# PostgreSQL 백업
echo "Backing up PostgreSQL..."
pg_dump -h pg-primary -U postgres -Fc exchange > "$BACKUP_DIR/exchange.dump"

# TimescaleDB 백업
echo "Backing up TimescaleDB..."
pg_dump -h timescale -U postgres -Fc market_data > "$BACKUP_DIR/market_data.dump"

# Redis RDB 스냅샷 트리거
echo "Triggering Redis snapshot..."
redis-cli -h redis-node-1 -p 7001 -a "$REDIS_PASSWORD" BGSAVE

# S3 업로드
echo "Uploading to S3..."
aws s3 sync "$BACKUP_DIR" "s3://exchange-backups/$(date +%Y%m%d)/"

echo "Backup completed successfully"
```

### 6.2 복구 절차

```bash
#!/bin/bash
# restore-database.sh

BACKUP_DATE=$1
BACKUP_DIR="/backups/$BACKUP_DATE"

if [ -z "$BACKUP_DATE" ]; then
    echo "Usage: restore-database.sh YYYYMMDD"
    exit 1
fi

# S3에서 다운로드
echo "Downloading from S3..."
aws s3 sync "s3://exchange-backups/$BACKUP_DATE/" "$BACKUP_DIR/"

# PostgreSQL 복구
echo "Restoring PostgreSQL..."
pg_restore -h pg-primary -U postgres -d exchange -c "$BACKUP_DIR/exchange.dump"

# TimescaleDB 복구
echo "Restoring TimescaleDB..."
pg_restore -h timescale -U postgres -d market_data -c "$BACKUP_DIR/market_data.dump"

echo "Restore completed"
```

## 체크리스트

### 구현 전 확인사항

- [ ] PostgreSQL 15+ 설치 및 설정
- [ ] PgBouncer 연결 풀링 설정
- [ ] Redis Cluster 6노드 구성
- [ ] TimescaleDB 확장 설치
- [ ] 백업 스토리지 (S3) 설정
- [ ] 모니터링 시스템 (Prometheus/Grafana) 구축

### 성능 목표

| 메트릭 | 목표값 |
|--------|--------|
| PostgreSQL 쿼리 지연 | < 10ms (p99) |
| Redis 명령 지연 | < 1ms (p99) |
| 연결 풀 활용률 | < 80% |
| 캐시 히트율 | > 95% |
| 백업 완료 시간 | < 1시간 |

## 참고 자료

- [PostgreSQL 15 Documentation](https://www.postgresql.org/docs/15/)
- [Redis Cluster Specification](https://redis.io/docs/reference/cluster-spec/)
- [TimescaleDB Documentation](https://docs.timescale.com/)
- [PgBouncer Documentation](https://www.pgbouncer.org/)

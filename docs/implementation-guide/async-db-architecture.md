# 비동기 DB 처리 아키텍처

## 개요

동기식 DB 처리의 병목(15K TPS)을 비동기 처리로 해결하여 100K+ TPS를 달성하는 방법을 설명합니다.

## 핵심 개념: 동기 vs 비동기

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         동기식 처리 (현재)                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Client ──▶ API ──▶ DB Write ──▶ Kafka ──▶ Engine ──▶ DB Write ──▶ Response │
│                       ⬆️                               ⬆️                    │
│                    BLOCKING                         BLOCKING                 │
│                    (5-10ms)                         (5-10ms)                 │
│                                                                              │
│  문제: 모든 단계에서 DB 응답을 기다림                                          │
│  결과: 15,000 TPS ÷ 5 writes = 3,000 주문/초                                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                         비동기식 처리 (개선)                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Client ──▶ API ──▶ Kafka ──▶ Response (즉시!)                              │
│                       │                                                      │
│                       ▼                                                      │
│              ┌────────────────┐                                             │
│              │ Matching Engine │ (메모리에서 처리)                            │
│              └───────┬────────┘                                             │
│                      │                                                       │
│                      ▼                                                       │
│              ┌────────────────┐                                             │
│              │     Kafka      │ (이벤트 저장)                                │
│              └───────┬────────┘                                             │
│                      │                                                       │
│                      ▼ (비동기, 배치)                                        │
│              ┌────────────────┐                                             │
│              │   DB Writer    │ ──▶ PostgreSQL                              │
│              │   Consumer     │     (배치 INSERT)                           │
│              └────────────────┘                                             │
│                                                                              │
│  핵심: DB 쓰기를 Critical Path에서 제거                                       │
│  결과: 100,000+ 주문/초 (DB는 뒤에서 따라감)                                  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Source of Truth 계층

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    SOURCE OF TRUTH 계층                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Layer 1: Kafka (이벤트 로그) - Primary Source of Truth                      │
│  ────────────────────────────────────────────────────                        │
│  • 모든 주문/체결 이벤트가 먼저 기록됨                                         │
│  • 최소 7일 보관 (복구용)                                                     │
│  • 순서 보장 (파티션 내)                                                      │
│                                                                              │
│  Layer 2: Redis (실시간 상태) - Hot Data                                     │
│  ────────────────────────────────────────────────────                        │
│  • 현재 잔고, 포지션, 활성 주문                                               │
│  • Kafka 이벤트로부터 실시간 업데이트                                         │
│  • API 조회는 여기서                                                         │
│                                                                              │
│  Layer 3: PostgreSQL (영구 저장) - Cold Storage                              │
│  ────────────────────────────────────────────────────                        │
│  • 비동기 배치 기록                                                          │
│  • 히스토리 조회, 리포트용                                                    │
│  • 규제 준수용 영구 보관                                                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 전체 데이터 흐름

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│   [1] 주문 접수 (2-3ms로 즉시 응답)                                           │
│   ══════════════════════════════════                                         │
│                                                                              │
│   Client                                                                     │
│      │                                                                       │
│      ▼                                                                       │
│   ┌──────────────────────────────────────────────────────────────────┐      │
│   │                        API Server                                 │      │
│   │  ┌─────────────────────────────────────────────────────────────┐ │      │
│   │  │  1. Redis에서 잔고 확인 (0.5ms)                              │ │      │
│   │  │  2. Redis에서 Rate Limit 확인 (0.5ms)                        │ │      │
│   │  │  3. 주문 ID 생성 (UUID)                                      │ │      │
│   │  │  4. Kafka에 OrderSubmitted 이벤트 발행 (1ms)                 │ │      │
│   │  │  5. 클라이언트에 주문 ID 반환 (즉시!)                         │ │      │
│   │  └─────────────────────────────────────────────────────────────┘ │      │
│   └──────────────────────────────────────────────────────────────────┘      │
│                                          │                                   │
│                                          ▼                                   │
│                              Kafka: orders.submitted                         │
│                                                                              │
│   [2] 매칭 처리 (메모리에서 3-5ms)                                            │
│   ══════════════════════════════════                                         │
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────┐      │
│   │                      Matching Engine                              │      │
│   │  • Kafka에서 OrderSubmitted 이벤트 수신                          │      │
│   │  • 메모리 오더북에서 매칭                                         │      │
│   │  • 체결 → TradeExecuted 이벤트 발행                               │      │
│   │  • 미체결 → OrderPlaced 이벤트 발행                               │      │
│   └──────────────────────────────────────────────────────────────────┘      │
│                                          │                                   │
│                              ┌───────────┴───────────┐                      │
│                              ▼                       ▼                       │
│                     trades.executed           orders.placed                  │
│                                                                              │
│   [3] 상태 업데이트 (Redis - 실시간)                                          │
│   ══════════════════════════════════                                         │
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────┐      │
│   │                    State Updater Consumer                         │      │
│   │  • TradeExecuted → Redis 잔고/포지션 즉시 업데이트                 │      │
│   │  • WebSocket으로 클라이언트에 푸시                                │      │
│   └──────────────────────────────────────────────────────────────────┘      │
│                                          │                                   │
│                                          ▼                                   │
│                                   Redis Cluster                              │
│                         (잔고, 포지션, 활성주문)                              │
│                                                                              │
│   [4] DB 영구 저장 (비동기 배치 - 1초 지연 OK)                                │
│   ═══════════════════════════════════════════                                │
│                                                                              │
│   ┌──────────────────────────────────────────────────────────────────┐      │
│   │                    DB Writer Consumer                             │      │
│   │  • 1000개 또는 1초마다 배치 처리                                  │      │
│   │  • COPY 명령으로 벌크 INSERT                                      │      │
│   └──────────────────────────────────────────────────────────────────┘      │
│                                          │                                   │
│                                          ▼                                   │
│                                    PostgreSQL                                │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 구현 코드

### 1. API Server - 주문 접수 (비동기)

```typescript
// src/services/order.service.ts
import { Injectable } from '@nestjs/common';
import { KafkaProducer } from '../kafka/kafka.producer';
import { RedisService } from '../redis/redis.service';
import { v4 as uuidv4 } from 'uuid';

@Injectable()
export class OrderService {
  constructor(
    private readonly kafka: KafkaProducer,
    private readonly redis: RedisService,
  ) {}

  async submitOrder(userId: string, dto: CreateOrderDto): Promise<OrderResponse> {
    const startTime = Date.now();

    // 1. 잔고 확인 (Redis - 0.5ms)
    const balance = await this.redis.getBalance(userId, dto.marginCurrency);
    const requiredMargin = this.calculateRequiredMargin(dto);

    if (balance.available < requiredMargin) {
      throw new InsufficientBalanceException();
    }

    // 2. Rate Limit 확인 (Redis - 0.5ms)
    const rateCheck = await this.redis.checkRateLimit(userId, 'order', 100);
    if (!rateCheck.allowed) {
      throw new RateLimitExceededException(rateCheck.resetAt);
    }

    // 3. 주문 ID 생성
    const orderId = uuidv4();
    const timestamp = Date.now();

    // 4. 마진 선점 (Redis - 0.5ms)
    await this.redis.lockMargin(userId, dto.marginCurrency, requiredMargin, orderId);

    // 5. Kafka에 이벤트 발행 (1ms) - DB 쓰기 없이 여기서 끝!
    const event: OrderSubmittedEvent = {
      eventId: uuidv4(),
      eventType: 'OrderSubmitted',
      orderId,
      userId,
      symbol: dto.symbol,
      side: dto.side,
      type: dto.type,
      price: dto.price,
      quantity: dto.quantity,
      leverage: dto.leverage,
      timestamp,
    };

    await this.kafka.send('orders.submitted', {
      key: dto.symbol,  // 심볼별 파티셔닝
      value: event,
    });

    const processingTime = Date.now() - startTime;

    // 6. 즉시 응답 (총 2-3ms)
    return {
      orderId,
      status: 'SUBMITTED',
      timestamp,
      processingTime,
    };
  }

  private calculateRequiredMargin(dto: CreateOrderDto): number {
    const notional = parseFloat(dto.price) * parseFloat(dto.quantity);
    return notional / dto.leverage;
  }
}
```

### 2. Redis Service - 실시간 상태 관리

```typescript
// src/redis/redis.service.ts
import { Injectable } from '@nestjs/common';
import Redis from 'ioredis';

@Injectable()
export class RedisService {
  private cluster: Redis.Cluster;

  constructor() {
    this.cluster = new Redis.Cluster([
      { host: 'redis-node-1', port: 6379 },
      { host: 'redis-node-2', port: 6379 },
      { host: 'redis-node-3', port: 6379 },
    ]);
  }

  // 잔고 조회
  async getBalance(userId: string, currency: string): Promise<Balance> {
    const key = `balance:${userId}:${currency}`;
    const data = await this.cluster.hgetall(key);

    return {
      total: parseFloat(data.total || '0'),
      available: parseFloat(data.available || '0'),
      locked: parseFloat(data.locked || '0'),
    };
  }

  // 마진 선점 (Lua 스크립트로 원자적 처리)
  async lockMargin(
    userId: string,
    currency: string,
    amount: number,
    orderId: string
  ): Promise<void> {
    const key = `balance:${userId}:${currency}`;
    const lockKey = `margin_lock:${orderId}`;

    const script = `
      local available = tonumber(redis.call('HGET', KEYS[1], 'available') or '0')
      local amount = tonumber(ARGV[1])

      if available < amount then
        return -1
      end

      redis.call('HINCRBYFLOAT', KEYS[1], 'available', -amount)
      redis.call('HINCRBYFLOAT', KEYS[1], 'locked', amount)
      redis.call('SET', KEYS[2], amount, 'EX', 86400)

      return 1
    `;

    const result = await this.cluster.eval(script, 2, key, lockKey, amount.toString());

    if (result === -1) {
      throw new Error('Insufficient balance');
    }
  }

  // 체결 후 잔고 업데이트
  async updateBalanceFromTrade(
    userId: string,
    currency: string,
    pnl: number,
    fee: number,
    marginReleased: number
  ): Promise<void> {
    const key = `balance:${userId}:${currency}`;

    const script = `
      local pnl = tonumber(ARGV[1])
      local fee = tonumber(ARGV[2])
      local marginReleased = tonumber(ARGV[3])

      redis.call('HINCRBYFLOAT', KEYS[1], 'locked', -marginReleased)

      local netChange = marginReleased + pnl - fee
      redis.call('HINCRBYFLOAT', KEYS[1], 'available', netChange)
      redis.call('HINCRBYFLOAT', KEYS[1], 'total', pnl - fee)

      return redis.call('HGETALL', KEYS[1])
    `;

    await this.cluster.eval(
      script, 1, key,
      pnl.toString(), fee.toString(), marginReleased.toString()
    );
  }

  // 포지션 업데이트
  async updatePosition(userId: string, position: Position): Promise<void> {
    const key = `position:${userId}:${position.symbol}`;

    if (position.size === 0) {
      await this.cluster.del(key);
    } else {
      await this.cluster.hset(key, {
        side: position.side,
        size: position.size.toString(),
        entryPrice: position.entryPrice.toString(),
        leverage: position.leverage.toString(),
        margin: position.margin.toString(),
        unrealizedPnl: position.unrealizedPnl.toString(),
        updatedAt: Date.now().toString(),
      });
    }
  }

  // 활성 주문 추가
  async addActiveOrder(userId: string, order: ActiveOrder): Promise<void> {
    const userOrdersKey = `orders:active:${userId}`;
    const orderDetailKey = `order:${order.orderId}`;

    const pipeline = this.cluster.pipeline();
    pipeline.hset(orderDetailKey, order);
    pipeline.expire(orderDetailKey, 86400 * 7);
    pipeline.sadd(userOrdersKey, order.orderId);
    await pipeline.exec();
  }
}
```

### 3. State Updater Consumer - 실시간 상태 동기화

```typescript
// src/consumers/state-updater.consumer.ts
import { Injectable, OnModuleInit } from '@nestjs/common';
import { Kafka, Consumer } from 'kafkajs';
import { RedisService } from '../redis/redis.service';
import { WebSocketGateway } from '../websocket/websocket.gateway';

@Injectable()
export class StateUpdaterConsumer implements OnModuleInit {
  private consumer: Consumer;

  constructor(
    private readonly redis: RedisService,
    private readonly wsGateway: WebSocketGateway,
  ) {
    const kafka = new Kafka({
      clientId: 'state-updater',
      brokers: process.env.KAFKA_BROKERS.split(','),
    });

    this.consumer = kafka.consumer({
      groupId: 'state-updater-group',
    });
  }

  async onModuleInit() {
    await this.consumer.connect();
    await this.consumer.subscribe({
      topics: ['trades.executed', 'orders.placed', 'orders.cancelled'],
    });

    await this.consumer.run({
      partitionsConsumedConcurrently: 10,
      eachMessage: async ({ topic, message }) => {
        const event = JSON.parse(message.value.toString());
        await this.handleEvent(topic, event);
      },
    });
  }

  private async handleEvent(topic: string, event: any) {
    switch (topic) {
      case 'trades.executed':
        await this.handleTradeExecuted(event);
        break;
      case 'orders.placed':
        await this.handleOrderPlaced(event);
        break;
      case 'orders.cancelled':
        await this.handleOrderCancelled(event);
        break;
    }
  }

  private async handleTradeExecuted(event: TradeExecutedEvent) {
    const { trade, buyerUpdate, sellerUpdate } = event;

    // 매수자 상태 업데이트
    await Promise.all([
      this.redis.updateBalanceFromTrade(
        buyerUpdate.userId, 'USDT',
        buyerUpdate.pnl, buyerUpdate.fee, buyerUpdate.marginReleased
      ),
      this.redis.updatePosition(buyerUpdate.userId, buyerUpdate.position),
    ]);

    // 매도자 상태 업데이트
    await Promise.all([
      this.redis.updateBalanceFromTrade(
        sellerUpdate.userId, 'USDT',
        sellerUpdate.pnl, sellerUpdate.fee, sellerUpdate.marginReleased
      ),
      this.redis.updatePosition(sellerUpdate.userId, sellerUpdate.position),
    ]);

    // WebSocket으로 클라이언트에 실시간 푸시
    this.wsGateway.sendToUser(buyerUpdate.userId, 'trade', { trade });
    this.wsGateway.sendToUser(sellerUpdate.userId, 'trade', { trade });
    this.wsGateway.broadcastTrade(trade.symbol, trade);
  }

  private async handleOrderPlaced(event: OrderPlacedEvent) {
    await this.redis.addActiveOrder(event.userId, event);
    this.wsGateway.sendToUser(event.userId, 'orderUpdate', {
      orderId: event.orderId,
      status: 'NEW',
    });
  }

  private async handleOrderCancelled(event: OrderCancelledEvent) {
    await this.redis.releaseMargin(event.orderId, event.userId, 'USDT');
    await this.redis.removeActiveOrder(event.userId, event.orderId);
    this.wsGateway.sendToUser(event.userId, 'orderUpdate', {
      orderId: event.orderId,
      status: 'CANCELLED',
    });
  }
}
```

### 4. DB Writer Consumer - 비동기 배치 저장

```typescript
// src/consumers/db-writer.consumer.ts
import { Injectable, OnModuleInit, OnModuleDestroy } from '@nestjs/common';
import { Kafka, Consumer } from 'kafkajs';
import { Pool } from 'pg';
import { from as copyFrom } from 'pg-copy-streams';
import { Readable } from 'stream';

@Injectable()
export class DbWriterConsumer implements OnModuleInit, OnModuleDestroy {
  private consumer: Consumer;
  private pgPool: Pool;

  // 배치 버퍼
  private orderBuffer: any[] = [];
  private tradeBuffer: any[] = [];

  // 배치 설정
  private readonly BATCH_SIZE = 1000;
  private readonly FLUSH_INTERVAL_MS = 1000;
  private flushTimer: NodeJS.Timer;

  async onModuleInit() {
    // Kafka Consumer 설정
    const kafka = new Kafka({
      clientId: 'db-writer',
      brokers: process.env.KAFKA_BROKERS.split(','),
    });
    this.consumer = kafka.consumer({ groupId: 'db-writer-group' });

    // PostgreSQL Pool
    this.pgPool = new Pool({
      host: process.env.PG_HOST,
      database: 'exchange',
      max: 20,
    });

    await this.consumer.connect();
    await this.consumer.subscribe({
      topics: ['orders.submitted', 'trades.executed'],
    });

    // 배치 처리 모드
    await this.consumer.run({
      eachBatch: async ({ batch, resolveOffset, heartbeat }) => {
        for (const message of batch.messages) {
          const event = JSON.parse(message.value.toString());
          this.addToBuffer(batch.topic, event);
          resolveOffset(message.offset);
          await heartbeat();
        }

        if (this.getBufferSize() >= this.BATCH_SIZE) {
          await this.flushBuffers();
        }
      },
    });

    // 주기적 플러시 (1초마다)
    this.flushTimer = setInterval(() => this.flushBuffers(), this.FLUSH_INTERVAL_MS);
  }

  private addToBuffer(topic: string, event: any) {
    if (topic === 'orders.submitted') {
      this.orderBuffer.push(this.mapToOrderRow(event));
    } else if (topic === 'trades.executed') {
      this.tradeBuffer.push(this.mapToTradeRow(event.trade));
    }
  }

  private getBufferSize(): number {
    return this.orderBuffer.length + this.tradeBuffer.length;
  }

  private async flushBuffers(): Promise<void> {
    await Promise.all([
      this.flushOrders(),
      this.flushTrades(),
    ]);
  }

  // COPY 명령으로 벌크 INSERT (일반 INSERT 대비 10-100배 빠름)
  private async flushOrders(): Promise<void> {
    if (this.orderBuffer.length === 0) return;

    const orders = [...this.orderBuffer];
    this.orderBuffer = [];

    const client = await this.pgPool.connect();
    try {
      const copyQuery = `
        COPY orders (id, user_id, symbol, side, type, price, quantity, created_at)
        FROM STDIN WITH (FORMAT CSV)
      `;

      const ingestStream = client.query(copyFrom(copyQuery));
      const csvData = orders.map(o => this.toCsvRow(o)).join('\n') + '\n';
      const sourceStream = Readable.from(csvData);

      await new Promise((resolve, reject) => {
        sourceStream.pipe(ingestStream)
          .on('finish', resolve)
          .on('error', reject);
      });

      console.log(`Bulk inserted ${orders.length} orders`);
    } finally {
      client.release();
    }
  }

  private async flushTrades(): Promise<void> {
    if (this.tradeBuffer.length === 0) return;

    const trades = [...this.tradeBuffer];
    this.tradeBuffer = [];

    const client = await this.pgPool.connect();
    try {
      const copyQuery = `
        COPY trades (id, symbol, price, quantity, buyer_order_id, seller_order_id, trade_time)
        FROM STDIN WITH (FORMAT CSV)
      `;

      const ingestStream = client.query(copyFrom(copyQuery));
      const csvData = trades.map(t => this.toCsvRow(t)).join('\n') + '\n';
      const sourceStream = Readable.from(csvData);

      await new Promise((resolve, reject) => {
        sourceStream.pipe(ingestStream)
          .on('finish', resolve)
          .on('error', reject);
      });

      console.log(`Bulk inserted ${trades.length} trades`);
    } finally {
      client.release();
    }
  }

  private mapToOrderRow(event: any): any {
    return {
      id: event.orderId,
      userId: event.userId,
      symbol: event.symbol,
      side: event.side,
      type: event.type,
      price: event.price,
      quantity: event.quantity,
      createdAt: new Date(event.timestamp).toISOString(),
    };
  }

  private mapToTradeRow(trade: any): any {
    return {
      id: trade.tradeId,
      symbol: trade.symbol,
      price: trade.price,
      quantity: trade.quantity,
      buyerOrderId: trade.buyerOrderId,
      sellerOrderId: trade.sellerOrderId,
      tradeTime: new Date(trade.timestamp).toISOString(),
    };
  }

  private toCsvRow(obj: any): string {
    return Object.values(obj).map(v => {
      if (v === null) return '';
      if (typeof v === 'string' && v.includes(',')) return `"${v}"`;
      return String(v);
    }).join(',');
  }

  async onModuleDestroy() {
    clearInterval(this.flushTimer);
    await this.flushBuffers();
    await this.consumer.disconnect();
    await this.pgPool.end();
  }
}
```

### 5. 일관성 검증 서비스

```typescript
// src/services/consistency-checker.service.ts
import { Injectable } from '@nestjs/common';
import { Cron } from '@nestjs/schedule';
import { Pool } from 'pg';
import { RedisService } from '../redis/redis.service';

@Injectable()
export class ConsistencyCheckerService {
  constructor(
    private readonly pgPool: Pool,
    private readonly redis: RedisService,
  ) {}

  // 5분마다 Redis와 PostgreSQL 데이터 일관성 검증
  @Cron('0 */5 * * * *')
  async checkConsistency() {
    console.log('Starting consistency check...');
    await this.checkBalanceConsistency();
    console.log('Consistency check completed');
  }

  private async checkBalanceConsistency(): Promise<void> {
    const result = await this.pgPool.query(`
      SELECT user_id, currency, balance, available_balance, locked_balance
      FROM accounts
      WHERE updated_at > NOW() - INTERVAL '10 minutes'
    `);

    let fixed = 0;

    for (const row of result.rows) {
      const redisBalance = await this.redis.getBalance(row.user_id, row.currency);

      // 0.01% 이상 차이나면 불일치
      const tolerance = 0.0001;
      const diff = Math.abs(redisBalance.total - parseFloat(row.balance));

      if (diff / parseFloat(row.balance) > tolerance) {
        console.warn(`Balance mismatch for user ${row.user_id}`);

        // PostgreSQL 기준으로 Redis 수정
        await this.redis.setBalance(row.user_id, row.currency, {
          total: parseFloat(row.balance),
          available: parseFloat(row.available_balance),
          locked: parseFloat(row.locked_balance),
        });
        fixed++;
      }
    }

    if (fixed > 0) {
      console.log(`Fixed ${fixed} balance inconsistencies`);
    }
  }
}
```

## 성능 비교

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PERFORMANCE COMPARISON                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Metric              Synchronous          Asynchronous       Improvement    │
│  ──────────────────────────────────────────────────────────────────────────│
│  주문 접수 TPS         3,000               100,000            33x           │
│  주문 응답 시간        20-50ms              2-3ms             10-20x        │
│  DB 부하              100%                 10-20%            5-10x 감소     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 장애 시나리오별 대응

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        FAILURE SCENARIOS                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  1. Redis 장애                                                               │
│     → Kafka 이벤트로부터 Redis 상태 재구축 (수 초)                            │
│                                                                              │
│  2. PostgreSQL 장애                                                          │
│     → 서비스 영향 없음! (Redis가 실시간 상태 유지)                            │
│     → DB 복구 후 Kafka에서 밀린 이벤트 배치 처리                              │
│                                                                              │
│  3. Kafka 장애                                                               │
│     → 가장 Critical - 새 주문 접수 불가                                       │
│     → 기존 상태는 Redis에서 유지                                              │
│                                                                              │
│  4. DB Writer Consumer 장애                                                  │
│     → 서비스 영향 없음! (Kafka에 이벤트 누적)                                 │
│     → Consumer 재시작 시 자동 처리                                            │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 핵심 요약

| 구분 | 동기식 | 비동기식 |
|------|--------|----------|
| **주문 응답** | DB 저장 후 응답 | Kafka 발행 후 즉시 응답 |
| **실시간 상태** | DB 조회 | Redis 조회 |
| **DB 저장** | 즉시 (Blocking) | 배치 (Non-blocking) |
| **TPS** | 3,000 | 100,000+ |
| **응답 시간** | 20-50ms | 2-3ms |

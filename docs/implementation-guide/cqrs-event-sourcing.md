# CQRS + Event Sourcing 구현 가이드

## 개요

CQRS(Command Query Responsibility Segregation)와 Event Sourcing 패턴을 도입하여 확장성, 감사 추적, 장애 복구 능력을 향상시킵니다.

## 현재 아키텍처의 문제점

```
현재 구조:
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Client    │────▶│   Backend   │────▶│   MySQL     │
│             │◀────│   (CRUD)    │◀────│  (State)    │
└─────────────┘     └─────────────┘     └─────────────┘

문제점:
1. 현재 상태만 저장 → 과거 상태 복원 불가
2. 감사 추적 어려움
3. 읽기/쓰기 동일 모델 → 최적화 한계
4. 장애 시 상태 복구 어려움
```

## 타겟 아키텍처

```
CQRS + Event Sourcing:

  ┌─────────────┐                         ┌─────────────┐
  │   Command   │                         │    Query    │
  │   (Write)   │                         │   (Read)    │
  └──────┬──────┘                         └──────┬──────┘
         │                                       │
         ▼                                       ▼
  ┌─────────────┐                         ┌─────────────┐
  │  Aggregate  │                         │  Read Model │
  │  (Domain)   │                         │ (Optimized) │
  └──────┬──────┘                         └──────┬──────┘
         │                                       ▲
         ▼                                       │
  ┌─────────────┐                                │
  │   Event     │      Projection                │
  │   Store     │────────────────────────────────┘
  │  (Append)   │
  └─────────────┘

특징:
1. 모든 상태 변경을 이벤트로 저장 (Append-Only)
2. 현재 상태 = 이벤트 재생 결과
3. 읽기 모델은 최적화된 별도 저장소
4. 완전한 감사 추적 및 상태 복원 가능
```

## 핵심 개념

### Event Sourcing

```
전통적 방식:
┌─────────────────────────────────┐
│ Account                         │
│ balance: $1,000  (현재 상태만)   │
└─────────────────────────────────┘

Event Sourcing:
┌─────────────────────────────────┐
│ Events                          │
│ 1. AccountCreated { $1,000 }    │
│ 2. Deposited { $500 }           │
│ 3. TradeFee { -$10 }            │
│ 4. Withdrawn { -$490 }          │
│                                 │
│ Current State = Replay Events   │
│ = $1,000 + $500 - $10 - $490    │
│ = $1,000                        │
└─────────────────────────────────┘
```

### CQRS

```
Command Side (쓰기):
- 비즈니스 로직 처리
- 이벤트 생성 및 저장
- 도메인 규칙 검증

Query Side (읽기):
- 최적화된 데이터 모델
- 빠른 조회 성능
- 캐시 활용
```

## 구현 단계

### 1단계: 도메인 이벤트 정의

#### events/DomainEvent.ts

```typescript
// 기본 이벤트 인터페이스
export interface DomainEvent {
  eventId: string;
  eventType: string;
  aggregateId: string;
  aggregateType: string;
  version: number;
  timestamp: Date;
  payload: any;
  metadata?: EventMetadata;
}

export interface EventMetadata {
  userId?: number;
  correlationId?: string;
  causationId?: string;
  ipAddress?: string;
}

// 이벤트 타입 정의
export enum OrderEventType {
  ORDER_PLACED = 'OrderPlaced',
  ORDER_MATCHED = 'OrderMatched',
  ORDER_PARTIALLY_FILLED = 'OrderPartiallyFilled',
  ORDER_FILLED = 'OrderFilled',
  ORDER_CANCELLED = 'OrderCancelled',
  ORDER_EXPIRED = 'OrderExpired',
  ORDER_REJECTED = 'OrderRejected',
}

export enum PositionEventType {
  POSITION_OPENED = 'PositionOpened',
  POSITION_INCREASED = 'PositionIncreased',
  POSITION_DECREASED = 'PositionDecreased',
  POSITION_CLOSED = 'PositionClosed',
  POSITION_LIQUIDATED = 'PositionLiquidated',
  LEVERAGE_CHANGED = 'LeverageChanged',
  MARGIN_ADDED = 'MarginAdded',
  MARGIN_REMOVED = 'MarginRemoved',
}

export enum AccountEventType {
  ACCOUNT_CREATED = 'AccountCreated',
  BALANCE_DEPOSITED = 'BalanceDeposited',
  BALANCE_WITHDRAWN = 'BalanceWithdrawn',
  BALANCE_LOCKED = 'BalanceLocked',
  BALANCE_UNLOCKED = 'BalanceUnlocked',
  FEE_CHARGED = 'FeeCharged',
  PNL_REALIZED = 'PnlRealized',
  FUNDING_PAID = 'FundingPaid',
  FUNDING_RECEIVED = 'FundingReceived',
}
```

#### events/OrderEvents.ts

```typescript
import { DomainEvent, OrderEventType } from './DomainEvent';

export interface OrderPlacedEvent extends DomainEvent {
  eventType: OrderEventType.ORDER_PLACED;
  payload: {
    orderId: number;
    userId: number;
    accountId: number;
    symbol: string;
    side: 'BUY' | 'SELL';
    type: 'LIMIT' | 'MARKET';
    price: string;
    quantity: string;
    leverage: string;
    marginMode: 'CROSS' | 'ISOLATED';
    timeInForce: 'GTC' | 'IOC' | 'FOK';
    isReduceOnly: boolean;
    isPostOnly: boolean;
  };
}

export interface OrderMatchedEvent extends DomainEvent {
  eventType: OrderEventType.ORDER_MATCHED;
  payload: {
    orderId: number;
    tradeId: number;
    matchedPrice: string;
    matchedQuantity: string;
    remainingQuantity: string;
    fee: string;
    realizedPnl: string;
    isMaker: boolean;
    counterpartyOrderId: number;
  };
}

export interface OrderCancelledEvent extends DomainEvent {
  eventType: OrderEventType.ORDER_CANCELLED;
  payload: {
    orderId: number;
    reason: 'USER_REQUESTED' | 'INSUFFICIENT_BALANCE' | 'SELF_TRADE' | 'LIQUIDATION';
    cancelledQuantity: string;
  };
}

// 이벤트 생성 팩토리
export class OrderEventFactory {
  static createOrderPlaced(order: CreateOrderDto, metadata: EventMetadata): OrderPlacedEvent {
    return {
      eventId: generateUUID(),
      eventType: OrderEventType.ORDER_PLACED,
      aggregateId: order.orderId.toString(),
      aggregateType: 'Order',
      version: 1,
      timestamp: new Date(),
      payload: {
        orderId: order.orderId,
        userId: order.userId,
        accountId: order.accountId,
        symbol: order.symbol,
        side: order.side,
        type: order.type,
        price: order.price,
        quantity: order.quantity,
        leverage: order.leverage,
        marginMode: order.marginMode,
        timeInForce: order.timeInForce,
        isReduceOnly: order.isReduceOnly,
        isPostOnly: order.isPostOnly,
      },
      metadata,
    };
  }

  static createOrderMatched(trade: TradeDto, orderId: number, metadata: EventMetadata): OrderMatchedEvent {
    return {
      eventId: generateUUID(),
      eventType: OrderEventType.ORDER_MATCHED,
      aggregateId: orderId.toString(),
      aggregateType: 'Order',
      version: 0, // 실제로는 현재 버전 + 1
      timestamp: new Date(),
      payload: {
        orderId,
        tradeId: trade.id,
        matchedPrice: trade.price,
        matchedQuantity: trade.quantity,
        remainingQuantity: trade.remaining,
        fee: trade.fee,
        realizedPnl: trade.realizedPnl,
        isMaker: trade.isMaker,
        counterpartyOrderId: trade.counterpartyOrderId,
      },
      metadata,
    };
  }
}
```

### 2단계: Event Store 구현

#### eventstore/EventStore.ts

```typescript
import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Connection } from 'typeorm';
import { DomainEvent } from '../events/DomainEvent';
import { EventStoreEntity } from './EventStoreEntity';

@Injectable()
export class EventStore {
  constructor(
    @InjectRepository(EventStoreEntity)
    private readonly eventRepository: Repository<EventStoreEntity>,
    private readonly connection: Connection,
    private readonly kafkaClient: KafkaClient,
  ) {}

  /**
   * 이벤트 저장 (Append-Only)
   */
  async append(event: DomainEvent): Promise<void> {
    const queryRunner = this.connection.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();

    try {
      // 1. 낙관적 잠금 (Optimistic Locking)
      const currentVersion = await this.getCurrentVersion(
        event.aggregateType,
        event.aggregateId,
      );

      if (event.version !== currentVersion + 1) {
        throw new ConcurrencyException(
          `Expected version ${currentVersion + 1}, got ${event.version}`,
        );
      }

      // 2. 이벤트 저장
      const entity = new EventStoreEntity();
      entity.eventId = event.eventId;
      entity.eventType = event.eventType;
      entity.aggregateId = event.aggregateId;
      entity.aggregateType = event.aggregateType;
      entity.version = event.version;
      entity.payload = JSON.stringify(event.payload);
      entity.metadata = JSON.stringify(event.metadata);
      entity.timestamp = event.timestamp;

      await queryRunner.manager.save(entity);

      // 3. Kafka로 이벤트 발행 (Read Model 업데이트용)
      await this.kafkaClient.produce('domain-events', {
        key: `${event.aggregateType}:${event.aggregateId}`,
        value: JSON.stringify(event),
      });

      await queryRunner.commitTransaction();
    } catch (error) {
      await queryRunner.rollbackTransaction();
      throw error;
    } finally {
      await queryRunner.release();
    }
  }

  /**
   * 여러 이벤트 일괄 저장
   */
  async appendBatch(events: DomainEvent[]): Promise<void> {
    const queryRunner = this.connection.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();

    try {
      for (const event of events) {
        const entity = this.toEntity(event);
        await queryRunner.manager.save(entity);
      }

      // Kafka 배치 발행
      const messages = events.map((e) => ({
        key: `${e.aggregateType}:${e.aggregateId}`,
        value: JSON.stringify(e),
      }));
      await this.kafkaClient.produceBatch('domain-events', messages);

      await queryRunner.commitTransaction();
    } catch (error) {
      await queryRunner.rollbackTransaction();
      throw error;
    } finally {
      await queryRunner.release();
    }
  }

  /**
   * Aggregate의 모든 이벤트 조회
   */
  async getEvents(
    aggregateType: string,
    aggregateId: string,
    fromVersion?: number,
  ): Promise<DomainEvent[]> {
    const query = this.eventRepository
      .createQueryBuilder('event')
      .where('event.aggregateType = :aggregateType', { aggregateType })
      .andWhere('event.aggregateId = :aggregateId', { aggregateId })
      .orderBy('event.version', 'ASC');

    if (fromVersion !== undefined) {
      query.andWhere('event.version > :fromVersion', { fromVersion });
    }

    const entities = await query.getMany();
    return entities.map(this.toEvent);
  }

  /**
   * 특정 시점까지의 이벤트 조회 (Time Travel)
   */
  async getEventsUntil(
    aggregateType: string,
    aggregateId: string,
    until: Date,
  ): Promise<DomainEvent[]> {
    const entities = await this.eventRepository.find({
      where: {
        aggregateType,
        aggregateId,
        timestamp: LessThanOrEqual(until),
      },
      order: { version: 'ASC' },
    });
    return entities.map(this.toEvent);
  }

  /**
   * 전체 이벤트 스트림 조회 (Projection 재구축용)
   */
  async getAllEvents(
    fromPosition?: number,
    batchSize: number = 1000,
  ): AsyncGenerator<DomainEvent[], void, unknown> {
    let offset = fromPosition || 0;

    while (true) {
      const entities = await this.eventRepository.find({
        order: { id: 'ASC' },
        skip: offset,
        take: batchSize,
      });

      if (entities.length === 0) {
        break;
      }

      yield entities.map(this.toEvent);
      offset += entities.length;
    }
  }

  private async getCurrentVersion(
    aggregateType: string,
    aggregateId: string,
  ): Promise<number> {
    const result = await this.eventRepository
      .createQueryBuilder('event')
      .select('MAX(event.version)', 'maxVersion')
      .where('event.aggregateType = :aggregateType', { aggregateType })
      .andWhere('event.aggregateId = :aggregateId', { aggregateId })
      .getRawOne();

    return result?.maxVersion || 0;
  }

  private toEntity(event: DomainEvent): EventStoreEntity {
    const entity = new EventStoreEntity();
    entity.eventId = event.eventId;
    entity.eventType = event.eventType;
    entity.aggregateId = event.aggregateId;
    entity.aggregateType = event.aggregateType;
    entity.version = event.version;
    entity.payload = JSON.stringify(event.payload);
    entity.metadata = JSON.stringify(event.metadata);
    entity.timestamp = event.timestamp;
    return entity;
  }

  private toEvent(entity: EventStoreEntity): DomainEvent {
    return {
      eventId: entity.eventId,
      eventType: entity.eventType,
      aggregateId: entity.aggregateId,
      aggregateType: entity.aggregateType,
      version: entity.version,
      payload: JSON.parse(entity.payload),
      metadata: JSON.parse(entity.metadata),
      timestamp: entity.timestamp,
    };
  }
}
```

#### eventstore/EventStoreEntity.ts

```typescript
import { Entity, PrimaryGeneratedColumn, Column, Index, CreateDateColumn } from 'typeorm';

@Entity('event_store')
@Index(['aggregateType', 'aggregateId', 'version'], { unique: true })
@Index(['eventType'])
@Index(['timestamp'])
export class EventStoreEntity {
  @PrimaryGeneratedColumn('increment', { type: 'bigint' })
  id: number;

  @Column({ type: 'varchar', length: 36 })
  eventId: string;

  @Column({ type: 'varchar', length: 100 })
  eventType: string;

  @Column({ type: 'varchar', length: 100 })
  aggregateType: string;

  @Column({ type: 'varchar', length: 100 })
  aggregateId: string;

  @Column({ type: 'int' })
  version: number;

  @Column({ type: 'json' })
  payload: string;

  @Column({ type: 'json', nullable: true })
  metadata: string;

  @CreateDateColumn()
  timestamp: Date;
}
```

### 3단계: Aggregate 구현

#### aggregates/OrderAggregate.ts

```typescript
import { DomainEvent, OrderEventType } from '../events/DomainEvent';
import { OrderPlacedEvent, OrderMatchedEvent, OrderCancelledEvent } from '../events/OrderEvents';

export interface OrderState {
  orderId: number;
  userId: number;
  accountId: number;
  symbol: string;
  side: 'BUY' | 'SELL';
  type: 'LIMIT' | 'MARKET';
  price: string;
  quantity: string;
  remaining: string;
  executedQty: string;
  avgPrice: string;
  status: 'PENDING' | 'ACTIVE' | 'FILLED' | 'PARTIALLY_FILLED' | 'CANCELLED';
  trades: TradeInfo[];
  version: number;
}

interface TradeInfo {
  tradeId: number;
  price: string;
  quantity: string;
  fee: string;
}

export class OrderAggregate {
  private state: OrderState;
  private uncommittedEvents: DomainEvent[] = [];

  constructor(events: DomainEvent[] = []) {
    this.state = this.getInitialState();
    for (const event of events) {
      this.apply(event, false);
    }
  }

  private getInitialState(): OrderState {
    return {
      orderId: 0,
      userId: 0,
      accountId: 0,
      symbol: '',
      side: 'BUY',
      type: 'LIMIT',
      price: '0',
      quantity: '0',
      remaining: '0',
      executedQty: '0',
      avgPrice: '0',
      status: 'PENDING',
      trades: [],
      version: 0,
    };
  }

  /**
   * 이벤트 적용 (상태 변경)
   */
  private apply(event: DomainEvent, isNew: boolean = true): void {
    switch (event.eventType) {
      case OrderEventType.ORDER_PLACED:
        this.applyOrderPlaced(event as OrderPlacedEvent);
        break;
      case OrderEventType.ORDER_MATCHED:
        this.applyOrderMatched(event as OrderMatchedEvent);
        break;
      case OrderEventType.ORDER_CANCELLED:
        this.applyOrderCancelled(event as OrderCancelledEvent);
        break;
    }

    this.state.version = event.version;

    if (isNew) {
      this.uncommittedEvents.push(event);
    }
  }

  private applyOrderPlaced(event: OrderPlacedEvent): void {
    const { payload } = event;
    this.state.orderId = payload.orderId;
    this.state.userId = payload.userId;
    this.state.accountId = payload.accountId;
    this.state.symbol = payload.symbol;
    this.state.side = payload.side;
    this.state.type = payload.type;
    this.state.price = payload.price;
    this.state.quantity = payload.quantity;
    this.state.remaining = payload.quantity;
    this.state.status = 'ACTIVE';
  }

  private applyOrderMatched(event: OrderMatchedEvent): void {
    const { payload } = event;

    // 체결 수량 업데이트
    this.state.remaining = payload.remainingQuantity;
    this.state.executedQty = new BigNumber(this.state.executedQty)
      .plus(payload.matchedQuantity)
      .toString();

    // 평균가 계산
    this.state.avgPrice = this.calculateAvgPrice(
      payload.matchedPrice,
      payload.matchedQuantity,
    );

    // 거래 기록 추가
    this.state.trades.push({
      tradeId: payload.tradeId,
      price: payload.matchedPrice,
      quantity: payload.matchedQuantity,
      fee: payload.fee,
    });

    // 상태 업데이트
    if (new BigNumber(this.state.remaining).isZero()) {
      this.state.status = 'FILLED';
    } else {
      this.state.status = 'PARTIALLY_FILLED';
    }
  }

  private applyOrderCancelled(event: OrderCancelledEvent): void {
    this.state.status = 'CANCELLED';
  }

  /**
   * 명령 처리 - 주문 생성
   */
  placeOrder(command: PlaceOrderCommand): void {
    // 비즈니스 규칙 검증
    if (this.state.status !== 'PENDING') {
      throw new InvalidOrderStateException('Order already exists');
    }

    const event = OrderEventFactory.createOrderPlaced(
      command,
      { userId: command.userId },
    );
    event.version = this.state.version + 1;

    this.apply(event);
  }

  /**
   * 명령 처리 - 주문 매칭
   */
  matchOrder(trade: TradeDto): void {
    if (this.state.status !== 'ACTIVE' && this.state.status !== 'PARTIALLY_FILLED') {
      throw new InvalidOrderStateException('Order cannot be matched');
    }

    const event = OrderEventFactory.createOrderMatched(
      trade,
      this.state.orderId,
      { userId: this.state.userId },
    );
    event.version = this.state.version + 1;

    this.apply(event);
  }

  /**
   * 명령 처리 - 주문 취소
   */
  cancelOrder(reason: string): void {
    if (this.state.status !== 'ACTIVE' && this.state.status !== 'PARTIALLY_FILLED') {
      throw new InvalidOrderStateException('Order cannot be cancelled');
    }

    const event: OrderCancelledEvent = {
      eventId: generateUUID(),
      eventType: OrderEventType.ORDER_CANCELLED,
      aggregateId: this.state.orderId.toString(),
      aggregateType: 'Order',
      version: this.state.version + 1,
      timestamp: new Date(),
      payload: {
        orderId: this.state.orderId,
        reason,
        cancelledQuantity: this.state.remaining,
      },
    };

    this.apply(event);
  }

  getState(): OrderState {
    return { ...this.state };
  }

  getUncommittedEvents(): DomainEvent[] {
    return [...this.uncommittedEvents];
  }

  clearUncommittedEvents(): void {
    this.uncommittedEvents = [];
  }

  private calculateAvgPrice(newPrice: string, newQty: string): string {
    const totalValue = new BigNumber(this.state.avgPrice)
      .times(this.state.executedQty)
      .plus(new BigNumber(newPrice).times(newQty));

    const totalQty = new BigNumber(this.state.executedQty).plus(newQty);

    return totalValue.dividedBy(totalQty).toString();
  }
}
```

### 4단계: Command Handler 구현

#### handlers/OrderCommandHandler.ts

```typescript
import { Injectable } from '@nestjs/common';
import { EventStore } from '../eventstore/EventStore';
import { OrderAggregate } from '../aggregates/OrderAggregate';

@Injectable()
export class OrderCommandHandler {
  constructor(
    private readonly eventStore: EventStore,
    private readonly snapshotStore: SnapshotStore,
  ) {}

  /**
   * 주문 생성 명령 처리
   */
  async handlePlaceOrder(command: PlaceOrderCommand): Promise<OrderState> {
    // 1. Aggregate 로드 (새 주문이므로 빈 Aggregate)
    const aggregate = new OrderAggregate();

    // 2. 명령 실행
    aggregate.placeOrder(command);

    // 3. 이벤트 저장
    const events = aggregate.getUncommittedEvents();
    for (const event of events) {
      await this.eventStore.append(event);
    }

    // 4. 커밋 완료
    aggregate.clearUncommittedEvents();

    return aggregate.getState();
  }

  /**
   * 주문 취소 명령 처리
   */
  async handleCancelOrder(command: CancelOrderCommand): Promise<OrderState> {
    // 1. Aggregate 로드 (이벤트 재생)
    const aggregate = await this.loadAggregate(command.orderId);

    // 2. 명령 실행
    aggregate.cancelOrder(command.reason);

    // 3. 이벤트 저장
    const events = aggregate.getUncommittedEvents();
    for (const event of events) {
      await this.eventStore.append(event);
    }

    // 4. 스냅샷 저장 (선택적)
    if (this.shouldTakeSnapshot(aggregate)) {
      await this.snapshotStore.save(aggregate);
    }

    aggregate.clearUncommittedEvents();

    return aggregate.getState();
  }

  /**
   * Aggregate 로드 (스냅샷 + 이벤트 재생)
   */
  private async loadAggregate(orderId: number): Promise<OrderAggregate> {
    // 1. 스냅샷 조회
    const snapshot = await this.snapshotStore.get('Order', orderId.toString());

    let events: DomainEvent[];

    if (snapshot) {
      // 2a. 스냅샷 이후 이벤트만 조회
      events = await this.eventStore.getEvents(
        'Order',
        orderId.toString(),
        snapshot.version,
      );
      return new OrderAggregate([...snapshot.events, ...events]);
    } else {
      // 2b. 전체 이벤트 조회
      events = await this.eventStore.getEvents('Order', orderId.toString());
      return new OrderAggregate(events);
    }
  }

  private shouldTakeSnapshot(aggregate: OrderAggregate): boolean {
    // 100개 이벤트마다 스냅샷
    return aggregate.getState().version % 100 === 0;
  }
}
```

### 5단계: Read Model (Projection) 구현

#### projections/OrderProjection.ts

```typescript
import { Injectable, OnModuleInit } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { DomainEvent, OrderEventType } from '../events/DomainEvent';
import { OrderReadModel } from '../readmodels/OrderReadModel';

@Injectable()
export class OrderProjection implements OnModuleInit {
  constructor(
    @InjectRepository(OrderReadModel)
    private readonly orderReadModelRepo: Repository<OrderReadModel>,
    private readonly kafkaClient: KafkaClient,
    private readonly redisClient: RedisClient,
  ) {}

  async onModuleInit() {
    // 도메인 이벤트 구독
    await this.kafkaClient.subscribe(
      'domain-events',
      'order-projection-group',
      async (event: DomainEvent) => {
        if (event.aggregateType === 'Order') {
          await this.handleEvent(event);
        }
      },
    );
  }

  /**
   * 이벤트 핸들링 (Read Model 업데이트)
   */
  async handleEvent(event: DomainEvent): Promise<void> {
    switch (event.eventType) {
      case OrderEventType.ORDER_PLACED:
        await this.handleOrderPlaced(event);
        break;
      case OrderEventType.ORDER_MATCHED:
        await this.handleOrderMatched(event);
        break;
      case OrderEventType.ORDER_CANCELLED:
        await this.handleOrderCancelled(event);
        break;
    }
  }

  private async handleOrderPlaced(event: DomainEvent): Promise<void> {
    const { payload } = event;

    // 1. DB 저장
    const readModel = new OrderReadModel();
    readModel.orderId = payload.orderId;
    readModel.userId = payload.userId;
    readModel.accountId = payload.accountId;
    readModel.symbol = payload.symbol;
    readModel.side = payload.side;
    readModel.type = payload.type;
    readModel.price = payload.price;
    readModel.quantity = payload.quantity;
    readModel.remaining = payload.quantity;
    readModel.status = 'ACTIVE';
    readModel.createdAt = event.timestamp;
    readModel.updatedAt = event.timestamp;

    await this.orderReadModelRepo.save(readModel);

    // 2. Redis 캐시 업데이트
    await this.redisClient.set(
      `order:${payload.orderId}`,
      JSON.stringify(readModel),
      86400, // 24시간 TTL
    );

    // 3. 사용자별 주문 목록에 추가
    await this.redisClient.sadd(
      `user:${payload.userId}:active_orders`,
      payload.orderId.toString(),
    );
  }

  private async handleOrderMatched(event: DomainEvent): Promise<void> {
    const { payload } = event;

    // 1. DB 업데이트
    await this.orderReadModelRepo.update(
      { orderId: payload.orderId },
      {
        remaining: payload.remainingQuantity,
        executedQty: () => `executed_qty + ${payload.matchedQuantity}`,
        status: new BigNumber(payload.remainingQuantity).isZero()
          ? 'FILLED'
          : 'PARTIALLY_FILLED',
        updatedAt: event.timestamp,
      },
    );

    // 2. Redis 캐시 업데이트
    const cached = await this.redisClient.get(`order:${payload.orderId}`);
    if (cached) {
      const order = JSON.parse(cached);
      order.remaining = payload.remainingQuantity;
      order.status = new BigNumber(payload.remainingQuantity).isZero()
        ? 'FILLED'
        : 'PARTIALLY_FILLED';
      await this.redisClient.set(
        `order:${payload.orderId}`,
        JSON.stringify(order),
        86400,
      );
    }

    // 3. 완전 체결 시 활성 주문 목록에서 제거
    if (new BigNumber(payload.remainingQuantity).isZero()) {
      const order = await this.orderReadModelRepo.findOne({ orderId: payload.orderId });
      if (order) {
        await this.redisClient.srem(
          `user:${order.userId}:active_orders`,
          payload.orderId.toString(),
        );
      }
    }
  }

  private async handleOrderCancelled(event: DomainEvent): Promise<void> {
    const { payload } = event;

    // 1. DB 업데이트
    await this.orderReadModelRepo.update(
      { orderId: payload.orderId },
      {
        status: 'CANCELLED',
        updatedAt: event.timestamp,
      },
    );

    // 2. Redis 캐시 업데이트
    await this.redisClient.del(`order:${payload.orderId}`);

    // 3. 활성 주문 목록에서 제거
    const order = await this.orderReadModelRepo.findOne({ orderId: payload.orderId });
    if (order) {
      await this.redisClient.srem(
        `user:${order.userId}:active_orders`,
        payload.orderId.toString(),
      );
    }
  }

  /**
   * Read Model 재구축 (Projection Rebuild)
   */
  async rebuild(): Promise<void> {
    // 1. 기존 Read Model 삭제
    await this.orderReadModelRepo.clear();

    // 2. 모든 이벤트 재생
    const eventStore = this.eventStore;
    for await (const events of eventStore.getAllEvents()) {
      for (const event of events) {
        if (event.aggregateType === 'Order') {
          await this.handleEvent(event);
        }
      }
    }
  }
}
```

### 6단계: Query Service 구현

#### services/OrderQueryService.ts

```typescript
import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { OrderReadModel } from '../readmodels/OrderReadModel';

@Injectable()
export class OrderQueryService {
  constructor(
    @InjectRepository(OrderReadModel)
    private readonly orderReadModelRepo: Repository<OrderReadModel>,
    private readonly redisClient: RedisClient,
  ) {}

  /**
   * 주문 단건 조회 (캐시 우선)
   */
  async getOrder(orderId: number): Promise<OrderReadModel | null> {
    // 1. Redis 캐시 조회
    const cached = await this.redisClient.get(`order:${orderId}`);
    if (cached) {
      return JSON.parse(cached);
    }

    // 2. DB 조회
    const order = await this.orderReadModelRepo.findOne({ orderId });

    // 3. 캐시 저장
    if (order) {
      await this.redisClient.set(
        `order:${orderId}`,
        JSON.stringify(order),
        86400,
      );
    }

    return order;
  }

  /**
   * 사용자의 활성 주문 목록
   */
  async getActiveOrders(userId: number): Promise<OrderReadModel[]> {
    // 1. Redis Set에서 주문 ID 목록 조회
    const orderIds = await this.redisClient.smembers(`user:${userId}:active_orders`);

    if (orderIds.length === 0) {
      return [];
    }

    // 2. 다중 조회 (Pipeline)
    const orders = await Promise.all(
      orderIds.map((id) => this.getOrder(parseInt(id))),
    );

    return orders.filter((o): o is OrderReadModel => o !== null);
  }

  /**
   * 주문 이력 조회 (DB 직접 조회)
   */
  async getOrderHistory(
    userId: number,
    options: OrderHistoryOptions,
  ): Promise<PaginatedResult<OrderReadModel>> {
    const { symbol, status, startTime, endTime, page, limit } = options;

    const query = this.orderReadModelRepo
      .createQueryBuilder('order')
      .where('order.userId = :userId', { userId });

    if (symbol) {
      query.andWhere('order.symbol = :symbol', { symbol });
    }

    if (status) {
      query.andWhere('order.status = :status', { status });
    }

    if (startTime) {
      query.andWhere('order.createdAt >= :startTime', { startTime });
    }

    if (endTime) {
      query.andWhere('order.createdAt <= :endTime', { endTime });
    }

    const [orders, total] = await query
      .orderBy('order.createdAt', 'DESC')
      .skip((page - 1) * limit)
      .take(limit)
      .getManyAndCount();

    return {
      data: orders,
      total,
      page,
      limit,
      totalPages: Math.ceil(total / limit),
    };
  }
}
```

## 상태 복구 (Time Travel)

```typescript
/**
 * 특정 시점의 주문 상태 조회
 */
async getOrderStateAt(orderId: number, at: Date): Promise<OrderState> {
  const events = await this.eventStore.getEventsUntil('Order', orderId.toString(), at);
  const aggregate = new OrderAggregate(events);
  return aggregate.getState();
}

/**
 * 잘못된 이벤트 보정 (Compensating Event)
 */
async compensateWrongEvent(
  orderId: number,
  wrongEventId: string,
  correction: any,
): Promise<void> {
  // 보정 이벤트 발행
  const compensatingEvent: DomainEvent = {
    eventId: generateUUID(),
    eventType: 'OrderCorrected',
    aggregateId: orderId.toString(),
    aggregateType: 'Order',
    version: await this.getNextVersion(orderId),
    timestamp: new Date(),
    payload: {
      compensatesEventId: wrongEventId,
      correction,
    },
  };

  await this.eventStore.append(compensatingEvent);
}
```

## 장점 및 트레이드오프

### 장점

1. **완전한 감사 추적**: 모든 상태 변경 기록
2. **Time Travel**: 과거 특정 시점 상태 조회
3. **디버깅**: 문제 발생 시 이벤트 재생으로 원인 분석
4. **확장성**: 읽기/쓰기 독립 확장
5. **복원력**: 이벤트 재생으로 상태 완전 복구

### 트레이드오프

1. **복잡성 증가**: 개발 및 운영 난이도 상승
2. **저장 공간**: 이벤트 누적으로 저장소 증가
3. **쿼리 지연**: Read Model 업데이트까지 지연 발생 (Eventual Consistency)
4. **스키마 진화**: 이벤트 버전 관리 필요

## 체크리스트

- [ ] 도메인 이벤트 설계 완료
- [ ] Event Store 테이블 생성
- [ ] Aggregate 구현
- [ ] Command Handler 구현
- [ ] Projection 구현
- [ ] Query Service 구현
- [ ] 스냅샷 전략 결정
- [ ] 이벤트 버전 관리 정책
- [ ] Projection Rebuild 테스트
- [ ] 성능 테스트

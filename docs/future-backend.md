# Future Backend Analysis

NestJS 기반 선물 거래소 백엔드 API 서버 분석 문서

## 개요

**프로젝트명**: Future Backend
**언어**: TypeScript
**프레임워크**: NestJS 7.x
**목적**: REST API, WebSocket, Kafka Consumer 제공

## 프로젝트 구조

```
future-backend/
├── src/
│   ├── main.ts                    # 애플리케이션 진입점
│   ├── app.module.ts              # 루트 모듈
│   ├── modules.ts                 # 모듈 집합
│   ├── configs/
│   │   ├── database.config.ts     # MySQL 설정 (master/report)
│   │   ├── redis.config.ts        # Redis 설정
│   │   ├── kafka.ts               # Kafka 설정
│   │   └── matching.config.ts     # 매칭 엔진 설정
│   ├── modules/
│   │   ├── auth/                  # 인증/인가
│   │   ├── user/                  # 사용자 관리
│   │   ├── order/                 # 주문 관리
│   │   ├── position/              # 포지션 관리
│   │   ├── trade/                 # 거래 내역
│   │   ├── account/               # 계정/잔고
│   │   ├── matching-engine/       # 매칭 엔진 통신
│   │   ├── orderbook/             # 오더북
│   │   ├── ticker/                # 시세 정보
│   │   ├── funding/               # 자금 조달
│   │   └── events/                # WebSocket
│   ├── models/
│   │   ├── entities/              # TypeORM 엔티티
│   │   └── repositories/          # 커스텀 리포지토리
│   ├── shares/
│   │   ├── kafka-client/          # Kafka 클라이언트
│   │   ├── redis-client/          # Redis 클라이언트
│   │   ├── helpers/               # 헬퍼 함수
│   │   ├── enums/                 # 열거형
│   │   ├── middlewares/           # 미들웨어
│   │   └── guards/                # 가드
│   └── adapters/
│       └── redis.adapter.ts       # Socket.io Redis 어댑터
├── package.json
└── tsconfig.json
```

## API 엔드포인트

### 인증 (Auth)

```
POST   /api/v1/auth/login           # 로그인
POST   /api/v1/auth/refresh         # 토큰 갱신
GET    /api/v1/auth/me              # 사용자 정보
```

### 주문 (Order)

```
POST   /api/v1/order                # 주문 생성
DELETE /api/v1/order/:id            # 주문 취소
DELETE /api/v1/order/cancel-all     # 전체 취소
GET    /api/v1/order/open           # 미체결 주문
GET    /api/v1/order/history        # 주문 내역
```

### 포지션 (Position)

```
GET    /api/v1/positions            # 포지션 조회
POST   /api/v1/positions/leverage   # 레버리지 조정
POST   /api/v1/positions/margin     # 마진 조정
POST   /api/v1/positions/tp-sl      # TP/SL 설정
```

### 계정 (Account)

```
GET    /api/v1/account              # 잔고 조회
GET    /api/v1/account/history      # 잔고 내역
```

### 거래 (Trade)

```
GET    /api/v1/trade                # 체결 내역
GET    /api/v1/trade/history        # 거래 이력
```

### 시세 (Market Data)

```
GET    /api/v1/instruments          # 거래 상품 정보
GET    /api/v1/orderbook/:symbol    # 오더북
GET    /api/v1/ticker               # 시세 정보
GET    /api/v1/klines               # 캔들 차트
```

### 펀딩 (Funding)

```
GET    /api/v1/funding/rate         # 펀딩 비율
GET    /api/v1/funding/history      # 펀딩 내역
```

## 핵심 모듈

### 1. Order Module

**파일**: `src/modules/order/`

주문 생성, 취소, 조회를 담당합니다.

```typescript
// order.service.ts
@Injectable()
export class OrderService {
  async createOrderOptimizedV2(dto: CreateOrderDto, user: UserEntity) {
    // 1. 주문 검증
    await this.validateOrder(dto, user);

    // 2. 임시 주문 ID 생성
    const tmpId = this.generateTmpId();

    // 3. Kafka로 전송
    await this.kafkaClient.produce(
      KafkaTopic.SAVE_ORDER_FROM_CLIENT_V2,
      { ...dto, tmpId, userId: user.id }
    );

    // 4. 202 Accepted 반환
    return { tmpId, status: 'PENDING' };
  }

  async cancelOrder(orderId: number, user: UserEntity) {
    const order = await this.orderRepository.findOne(orderId);

    if (order.userId !== user.id) {
      throw new ForbiddenException();
    }

    await this.kafkaClient.produce(
      KafkaTopic.CANCEL_ORDER_FROM_CLIENT,
      { orderId, userId: user.id }
    );
  }
}
```

### 2. Matching Engine Module

**파일**: `src/modules/matching-engine/`

매칭 엔진과의 통신 및 데이터 동기화를 담당합니다.

```typescript
// matching-engine.console.ts
@Console()
export class MatchingEngineConsole {

  @Command({ command: 'matching-engine:load' })
  async loadMatchingEngine() {
    // 초기화 순서
    await this.initializeEngine();
    await this.loadInstruments();
    await this.loadAccounts();
    await this.loadPositions();
    await this.loadOrders();
    await this.startEngine();
  }

  @Command({ command: 'matching-engine:save-accounts-to-db' })
  async saveAccountsWorker() {
    await this.kafkaClient.consume(
      KafkaTopic.MATCHING_ENGINE_OUTPUT,
      KafkaGroup.SAVER_ACCOUNTS,
      async (message) => {
        await this.matchingEngineService.saveAccountsV2(message);
      }
    );
  }

  @Command({ command: 'matching-engine:notify' })
  async notifyWorker() {
    await this.kafkaClient.consume(
      KafkaTopic.MATCHING_ENGINE_OUTPUT,
      KafkaGroup.NOTIFIER,
      async (message) => {
        // WebSocket 알림
        SocketEmitter.getInstance().emitAccount(message.userId, message.account);
        SocketEmitter.getInstance().emitPosition(message.position, message.userId);
        SocketEmitter.getInstance().emitOrders(message.orders, message.userId);
      }
    );
  }
}
```

```typescript
// matching-engine.service.ts
@Injectable()
export class MatchingEngineService {

  async saveAccountsV2(data: any) {
    const accounts = data.accounts;

    for (const account of accounts) {
      // operationId로 순서 보장
      if (account.operationId <= existingAccount.operationId) {
        continue;
      }

      // Redis 캐시 업데이트
      await this.redisClient.set(
        `accounts:userId_${account.userId}:accountId_${account.id}`,
        JSON.stringify(account),
        'EX', 86400
      );

      // DB 저장
      await this.accountRepository.save(account);
    }
  }

  async savePositionsV2(data: any) {
    const positions = data.positions;

    for (const position of positions) {
      // Redis 캐시 업데이트
      await this.redisClient.set(
        `positions:userId_${position.userId}:positionId_${position.id}`,
        JSON.stringify(position),
        'EX', 86400
      );

      // DB 저장
      await this.positionRepository.save(position);
    }
  }

  async saveTrades(data: any) {
    const trades = data.trades;

    for (const trade of trades) {
      // 거래 저장
      await this.tradeRepository.save(trade);

      // 수수료 트랜잭션 생성
      await this.createFeeTransaction(trade);

      // 추천 보상 처리
      await this.processReferral(trade);

      // 리워드 센터 연동
      await this.processReward(trade);
    }
  }
}
```

### 3. Events Module (WebSocket)

**파일**: `src/modules/events/`

실시간 알림을 담당합니다.

```typescript
// event.gateway.ts
@WebSocketGateway({
  namespace: '/ws',
  cors: true
})
export class EventGateway implements OnGatewayConnection, OnGatewayDisconnect {

  @WebSocketServer()
  server: Server;

  async handleConnection(client: Socket) {
    try {
      const token = client.handshake.auth.token;
      const user = await this.authService.verifyToken(token);

      // 사용자별 룸 참여
      client.join(`user_${user.id}`);

    } catch (error) {
      client.disconnect();
    }
  }

  @SubscribeMessage('subscribe')
  handleSubscribe(client: Socket, payload: { channel: string }) {
    const { channel } = payload;

    // 공개 채널 구독
    if (channel.startsWith('orderbook_') || channel.startsWith('trades_')) {
      client.join(channel);
    }
  }
}
```

```typescript
// socket-emitter.ts
export class SocketEmitter {
  private static instance: SocketEmitter;
  private io: Server;

  emitAccount(userId: number, account: any) {
    this.io.to(`user_${userId}`).emit('account', account);
  }

  emitPosition(position: any, userId: number) {
    this.io.to(`user_${userId}`).emit('position', position);
  }

  emitOrders(orders: any[], userId: number) {
    this.io.to(`user_${userId}`).emit('orders', orders);
  }

  emitOrderbook(symbol: string, orderbook: any) {
    this.io.to(`orderbook_${symbol}`).emit('orderbook', orderbook);
  }

  emitTrades(symbol: string, trades: any[]) {
    this.io.to(`trades_${symbol}`).emit('trades', trades);
  }
}
```

## 엔티티 모델

### UserEntity

```typescript
@Entity('users')
export class UserEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  email: string;

  @Column()
  role: string;  // admin, user

  @Column()
  status: string;

  @Column()
  isLocked: string;

  @Column()
  userType: string;

  @Column()
  allowTrade: boolean;

  @Column()
  enableTradingFee: boolean;

  @Column()
  isMarketMaker: boolean;

  @Column()
  isBot: boolean;

  @Column()
  uid: string;
}
```

### AccountEntity

```typescript
@Entity('accounts')
export class AccountEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  asset: string;  // USDT, USD

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  balance: string;

  @Column()
  userId: number;

  @Column()
  operationId: number;  // 작업 순서 ID

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  rewardBalance: string;
}
```

### OrderEntity

```typescript
@Entity('orders')
export class OrderEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  accountId: number;

  @Column()
  userId: number;

  @Column()
  symbol: string;

  @Column({ type: 'enum', enum: OrderSide })
  side: OrderSide;  // BUY, SELL

  @Column({ type: 'enum', enum: OrderType })
  type: OrderType;  // LIMIT, MARKET

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  quantity: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  price: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  remaining: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  executedPrice: string;

  @Column({ type: 'enum', enum: OrderStatus })
  status: OrderStatus;  // ACTIVE, FILLED, CANCELED, UNTRIGGERED

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  leverage: string;

  @Column({ type: 'enum', enum: MarginMode })
  marginMode: MarginMode;  // CROSS, ISOLATED

  @Column()
  isReduceOnly: boolean;

  @Column()
  isPostOnly: boolean;
}
```

### PositionEntity

```typescript
@Entity('positions')
export class PositionEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  accountId: number;

  @Column()
  userId: number;

  @Column()
  symbol: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  currentQty: string;  // + Long, - Short

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  leverage: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  liquidationPrice: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  bankruptPrice: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  entryPrice: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  entryValue: string;

  @Column()
  isCross: boolean;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  positionMargin: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  orderCost: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  adjustMargin: string;
}
```

### TradeEntity

```typescript
@Entity('trades')
export class TradeEntity {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  buyOrderId: number;

  @Column()
  sellOrderId: number;

  @Column()
  buyAccountId: number;

  @Column()
  sellAccountId: number;

  @Column()
  buyUserId: number;

  @Column()
  sellUserId: number;

  @Column()
  symbol: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  price: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  quantity: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  buyFee: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  sellFee: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  realizedPnlOrderBuy: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  realizedPnlOrderSell: string;

  @Column()
  buyerIsTaker: boolean;

  @Column()
  note: string;  // LIQUIDATION, ADL 등
}
```

## 인증/인가

### JWT Auth Guard

**파일**: `src/modules/auth/guards/jwt-auth.guard.ts`

```typescript
@Injectable()
export class JwtAuthGuard implements CanActivate {

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();

    // API Key 인증
    if (request.headers['apikey']) {
      return this.validateApiKey(request);
    }

    // JWT 인증
    const token = this.extractToken(request);
    const payload = await this.jwtService.verify(token, {
      algorithms: ['RS256']
    });

    request.user = await this.userService.findById(payload.sub);
    return true;
  }

  private async validateApiKey(request: Request): Promise<boolean> {
    const apiKey = request.headers['apikey'];
    const signature = request.headers['signature'];
    const timestamp = request.headers['timestamp'];

    // 타임스탬프 검증 (60초)
    const now = Date.now();
    if (Math.abs(now - parseInt(timestamp)) > 60000) {
      throw new UnauthorizedException('Timestamp expired');
    }

    // 서명 검증
    const message = timestamp + request.method + request.path + JSON.stringify(request.body);
    const expectedSignature = createHmac('sha256', apiSecret).update(message).digest('hex');

    if (signature !== expectedSignature) {
      throw new UnauthorizedException('Invalid signature');
    }

    // 권한 확인
    const permissions = await this.getApiKeyPermissions(apiKey);
    if (!permissions.includes(request.method === 'GET' ? 'READ' : 'WRITE')) {
      throw new ForbiddenException('Insufficient permissions');
    }

    return true;
  }
}
```

## Redis 캐싱

### 캐시 키 패턴

```typescript
// 계정 캐시
`accounts:userId_${userId}:accountId_${accountId}`
`accounts:userId_${userId}:asset_${asset}`

// 주문 캐시 (ACTIVE/UNTRIGGERED만)
`orders:userId_${userId}:orderId_${orderId}`
`orders:userId_${userId}:tmpId_${tmpId}`

// 포지션 캐시
`positions:userId_${userId}:accountId_${accountId}:positionId_${positionId}`

// 티커 캐시
`ticker:${symbol}`

// 오더북 캐시
`orderbook:${symbol}`
```

### Redis Client

```typescript
// redis-client.ts
@Injectable()
export class RedisClient {
  private client: Redis;

  async get<T>(key: string): Promise<T | null> {
    const value = await this.client.get(key);
    return value ? JSON.parse(value) : null;
  }

  async set(key: string, value: any, ttl?: number): Promise<void> {
    const serialized = JSON.stringify(value);
    if (ttl) {
      await this.client.set(key, serialized, 'EX', ttl);
    } else {
      await this.client.set(key, serialized);
    }
  }

  async del(key: string): Promise<void> {
    await this.client.del(key);
  }

  async keys(pattern: string): Promise<string[]> {
    return this.client.keys(pattern);
  }
}
```

## Kafka 통신

### Kafka Topics

```typescript
// kafka.enum.ts
export enum KafkaTopic {
  // Input
  MATCHING_ENGINE_PRELOAD = 'matching_engine_preload',
  MATCHING_ENGINE_INPUT = 'matching_engine_input',
  SAVE_ORDER_FROM_CLIENT_V2 = 'save_order_from_client_v2',
  CANCEL_ORDER_FROM_CLIENT = 'cancel_order_from_client',

  // Output
  MATCHING_ENGINE_OUTPUT = 'matching_engine_output',
  ORDERBOOK_OUTPUT = 'orderbook_output',
  TICKER_ENGINE_OUTPUT = 'ticker_engine_output',

  // Integration
  FUTURE_REFERRAL = 'future_referral',
  FUTURE_REWARD_CENTER = 'future_reward_center',
}

export enum KafkaGroup {
  MATCHING_ENGINE = 'matching_engine',
  SAVER_ACCOUNTS = 'matching_engine_saver_accounts',
  SAVER_POSITIONS = 'matching_engine_saver_positions',
  SAVER_ORDERS = 'matching_engine_saver_orders',
  SAVER_TRADES = 'matching_engine_saver_trades',
  NOTIFIER = 'matching_engine_notifier',
}
```

### Kafka Client

```typescript
// kafka-client.ts
@Injectable()
export class KafkaClient {
  private producer: Producer;
  private consumers: Map<string, Consumer> = new Map();

  async produce(topic: string, message: any): Promise<void> {
    await this.producer.send({
      topic,
      messages: [{ value: JSON.stringify(message) }]
    });
  }

  async consume(
    topic: string,
    groupId: string,
    handler: (message: any) => Promise<void>
  ): Promise<void> {
    const consumer = this.kafka.consumer({ groupId });
    await consumer.connect();
    await consumer.subscribe({ topic });

    await consumer.run({
      eachMessage: async ({ message }) => {
        const payload = JSON.parse(message.value.toString());
        await handler(payload);
      }
    });

    this.consumers.set(groupId, consumer);
  }
}
```

## 기술 스택

### 핵심 의존성

```json
{
  "dependencies": {
    "@nestjs/common": "^7.6.15",
    "@nestjs/core": "^7.6.15",
    "@nestjs/platform-express": "^7.6.15",
    "@nestjs/typeorm": "^7.1.5",
    "@nestjs/passport": "^7.1.5",
    "@nestjs/jwt": "^7.2.0",
    "@nestjs/websockets": "^7.6.15",
    "@nestjs/platform-socket.io": "^7.6.15",

    "typeorm": "^0.2.41",
    "mysql2": "^2.3.3",

    "ioredis": "^4.28.5",
    "cache-manager": "^3.4.3",
    "cache-manager-ioredis": "^2.1.0",

    "kafkajs": "^1.15.0",

    "socket.io": "^2.4.1",
    "socket.io-redis": "^5.4.0",

    "passport": "^0.4.1",
    "passport-jwt": "^4.0.0",
    "jsonwebtoken": "^8.5.1",
    "bcrypt": "^5.0.1",

    "bignumber.js": "^9.0.1",
    "lodash": "^4.17.21",
    "moment": "^2.29.4"
  }
}
```

## 운영 명령어

### 개발 서버

```bash
# 개발 모드
yarn start:dev

# 프로덕션 빌드
yarn build
yarn start:prod
```

### Console 명령어

```bash
# 매칭 엔진 초기화
yarn console:dev matching-engine:load

# 계정 저장 워커
yarn console:dev matching-engine:save-accounts-to-db

# 포지션 저장 워커
yarn console:dev matching-engine:save-positions

# 주문 저장 워커
yarn console:dev matching-engine:save-orders-to-db

# 체결 저장 워커
yarn console:dev matching-engine:save-trades

# 실시간 알림 워커
yarn console:dev matching-engine:notify

# 펀딩 지급
yarn console:dev funding:pay
```

### PM2 배포

```bash
# 시작
pm2 start ecosystem.config.js

# 재시작
pm2 reload ecosystem.config.js

# 로그 확인
pm2 logs
```

## 환경 변수

```bash
# 데이터베이스
DB_HOST=localhost
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=password
DB_DATABASE=future

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

# Kafka
KAFKA_BROKERS=localhost:9092

# JWT
JWT_PUBLIC_KEY=
JWT_PRIVATE_KEY=

# API
PORT=3000
NODE_ENV=development
```

## 주요 파일 참조

| 파일 | 역할 |
|------|------|
| `main.ts` | 애플리케이션 부트스트랩 |
| `app.module.ts` | 루트 모듈 |
| `modules/order/order.service.ts` | 주문 서비스 |
| `modules/matching-engine/matching-engine.service.ts` | 매칭 엔진 통신 |
| `modules/matching-engine/matching-engine.console.ts` | 워커 명령어 |
| `modules/events/event.gateway.ts` | WebSocket 게이트웨이 |
| `modules/auth/guards/jwt-auth.guard.ts` | 인증 가드 |
| `shares/kafka-client/kafka-client.ts` | Kafka 클라이언트 |
| `shares/redis-client/redis-client.ts` | Redis 클라이언트 |

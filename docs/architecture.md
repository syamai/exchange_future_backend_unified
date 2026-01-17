# System Architecture

## 전체 아키텍처

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              Client Layer                                    │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  Web App    │  │ Mobile App  │  │   Bot/API   │  │  Admin UI   │        │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘        │
└─────────┼────────────────┼────────────────┼────────────────┼────────────────┘
          │                │                │                │
          └────────────────┴────────────────┴────────────────┘
                                    │
                          REST API / WebSocket
                                    │
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Future Backend (NestJS)                            │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                         API Gateway Layer                             │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐     │   │
│  │  │   Auth     │  │   Order    │  │  Position  │  │  Account   │     │   │
│  │  │ Controller │  │ Controller │  │ Controller │  │ Controller │     │   │
│  │  └────────────┘  └────────────┘  └────────────┘  └────────────┘     │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                         Service Layer                                 │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐     │   │
│  │  │   Order    │  │  Position  │  │  Account   │  │   Trade    │     │   │
│  │  │  Service   │  │  Service   │  │  Service   │  │  Service   │     │   │
│  │  └────────────┘  └────────────┘  └────────────┘  └────────────┘     │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                     Matching Engine Module                            │   │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐      │   │
│  │  │  ME Service     │  │  ME Console     │  │  Kafka Client   │      │   │
│  │  │  (Data Sync)    │  │  (Workers)      │  │  (Producer)     │      │   │
│  │  └─────────────────┘  └─────────────────┘  └─────────────────┘      │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
          │                         │                         │
          │ MySQL                   │ Redis                   │ Kafka
          ▼                         ▼                         ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────────────────┐
│    MySQL        │    │     Redis       │    │         Kafka               │
│  ┌───────────┐  │    │  ┌───────────┐  │    │  ┌─────────────────────┐   │
│  │  Master   │  │    │  │  Cache    │  │    │  │ matching_engine_    │   │
│  │   (RW)    │  │    │  │  Layer    │  │    │  │ input/output        │   │
│  ├───────────┤  │    │  ├───────────┤  │    │  ├─────────────────────┤   │
│  │  Report   │  │    │  │  Pub/Sub  │  │    │  │ save_order_from_    │   │
│  │   (RO)    │  │    │  │  (Socket) │  │    │  │ client_v2           │   │
│  └───────────┘  │    │  └───────────┘  │    │  ├─────────────────────┤   │
└─────────────────┘    └─────────────────┘    │  │ orderbook_output    │   │
                                              │  └─────────────────────┘   │
                                              └─────────────────────────────┘
                                                            │
                                                            ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Future Engine (Java)                                 │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                      Matching Engine Core                             │   │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                   │   │
│  │  │  Command    │  │  Matching   │  │   Output    │                   │   │
│  │  │   Queue     │──│   Engine    │──│   Stream    │                   │   │
│  │  └─────────────┘  └──────┬──────┘  └─────────────┘                   │   │
│  │                          │                                            │   │
│  │  ┌─────────────┐  ┌──────┴──────┐  ┌─────────────┐                   │   │
│  │  │  Matcher    │  │  Trigger    │  │ Liquidation │                   │   │
│  │  │  (Symbol)   │  │  (TP/SL)    │  │  Service    │                   │   │
│  │  └─────────────┘  └─────────────┘  └─────────────┘                   │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                         In-Memory Store                               │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐             │   │
│  │  │ Accounts │  │ Orders   │  │Positions │  │Instruments│             │   │
│  │  └──────────┘  └──────────┘  └──────────┘  └──────────┘             │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 데이터 흐름

### 1. 주문 생성 흐름

```
Client                Backend                 Kafka                  Engine
  │                      │                      │                      │
  │  POST /api/v1/order  │                      │                      │
  │─────────────────────>│                      │                      │
  │                      │                      │                      │
  │                      │  Validate & Produce  │                      │
  │                      │─────────────────────>│                      │
  │                      │                      │                      │
  │      202 Accepted    │                      │                      │
  │<─────────────────────│                      │                      │
  │                      │                      │   Consume Order      │
  │                      │                      │─────────────────────>│
  │                      │                      │                      │
  │                      │                      │   Match & Process    │
  │                      │                      │   ┌────────────┐     │
  │                      │                      │   │  Matcher   │     │
  │                      │                      │   └────────────┘     │
  │                      │                      │                      │
  │                      │                      │   Produce Results    │
  │                      │                      │<─────────────────────│
  │                      │                      │                      │
  │                      │   Consume Results    │                      │
  │                      │<─────────────────────│                      │
  │                      │                      │                      │
  │                      │  Save to DB & Cache  │                      │
  │                      │  ┌────────────────┐  │                      │
  │                      │  │ MySQL + Redis  │  │                      │
  │                      │  └────────────────┘  │                      │
  │                      │                      │                      │
  │   WebSocket Update   │                      │                      │
  │<─────────────────────│                      │                      │
  │                      │                      │                      │
```

### 2. 청산(Liquidation) 흐름

```
Engine (Trigger Thread)          Engine (Main)                Backend
        │                              │                          │
        │  Check Liquidation Price     │                          │
        │  ┌────────────────────┐      │                          │
        │  │ Oracle Price vs    │      │                          │
        │  │ Liquidation Price  │      │                          │
        │  └────────────────────┘      │                          │
        │                              │                          │
        │  Liquidation Triggered       │                          │
        │─────────────────────────────>│                          │
        │                              │                          │
        │                              │  1. Cancel User Orders   │
        │                              │  2. Market Liquidation   │
        │                              │  3. Insurance Fund       │
        │                              │  4. ADL (if needed)      │
        │                              │                          │
        │                              │  Produce Results         │
        │                              │─────────────────────────>│
        │                              │                          │
        │                              │                          │  Save Trade
        │                              │                          │  (LIQUIDATION)
        │                              │                          │
        │                              │                          │  WebSocket
        │                              │                          │  Notification
```

### 3. 펀딩 수수료 흐름

```
Cron (8h interval)      Backend              Kafka              Engine
        │                  │                   │                   │
        │  Trigger         │                   │                   │
        │─────────────────>│                   │                   │
        │                  │                   │                   │
        │                  │  PAY_FUNDING      │                   │
        │                  │──────────────────>│                   │
        │                  │                   │                   │
        │                  │                   │  Consume          │
        │                  │                   │──────────────────>│
        │                  │                   │                   │
        │                  │                   │  Calculate        │
        │                  │                   │  Funding for      │
        │                  │                   │  all positions    │
        │                  │                   │                   │
        │                  │                   │  Produce Results  │
        │                  │                   │<──────────────────│
        │                  │                   │                   │
        │                  │  Consume          │                   │
        │                  │<──────────────────│                   │
        │                  │                   │                   │
        │                  │  Save Funding     │                   │
        │                  │  Histories        │                   │
        │                  │                   │                   │
```

## Kafka 토픽 구조

### Input Topics (Backend → Engine)

| Topic | 설명 | Producer | Consumer |
|-------|------|----------|----------|
| `matching_engine_preload` | 엔진 초기화 데이터 | Backend | Engine |
| `matching_engine_input` | 실시간 명령 (주문, 취소 등) | Backend | Engine |
| `save_order_from_client_v2` | 클라이언트 주문 | Backend | Engine |
| `cancel_order_from_client` | 주문 취소 | Backend | Engine |

### Output Topics (Engine → Backend)

| Topic | 설명 | Producer | Consumer Group |
|-------|------|----------|----------------|
| `matching_engine_output` | 매칭 결과 | Engine | `matching_engine_saver_*` |
| `orderbook_output` | 오더북 업데이트 | Engine | `orderbook_consumer` |
| `ticker_engine_output` | 시세 업데이트 | Engine | `ticker_consumer` |

### Consumer Groups

| Group | 역할 |
|-------|------|
| `matching_engine_saver_accounts` | 계정 잔고 저장 |
| `matching_engine_saver_positions` | 포지션 저장 |
| `matching_engine_saver_orders` | 주문 저장 |
| `matching_engine_saver_trades` | 체결 저장 |
| `matching_engine_notifier` | WebSocket 알림 |

## Redis 캐시 구조

### Key Patterns

```
# 계정 캐시
accounts:userId_{userId}:accountId_{accountId}
accounts:userId_{userId}:asset_{asset}

# 주문 캐시 (ACTIVE/UNTRIGGERED만)
orders:userId_{userId}:orderId_{orderId}
orders:userId_{userId}:tmpId_{tmpId}

# 포지션 캐시
positions:userId_{userId}:accountId_{accountId}:positionId_{positionId}

# 티커 캐시
ticker:{symbol}

# 오더북 캐시
orderbook:{symbol}
```

### TTL 정책

| 데이터 | TTL |
|--------|-----|
| Account | 24시간 |
| Position | 24시간 |
| Order (ACTIVE) | 24시간 |
| Ticker | 1분 |
| Orderbook | 1분 |

## 데이터베이스 구조

### Master/Report 분리

```
┌─────────────────────────────────────────────────────────┐
│                    Application                           │
│  ┌─────────────────┐       ┌─────────────────┐          │
│  │  Write Service  │       │  Read Service   │          │
│  └────────┬────────┘       └────────┬────────┘          │
└───────────┼─────────────────────────┼────────────────────┘
            │                         │
            ▼                         ▼
┌─────────────────────┐    ┌─────────────────────┐
│    Master DB        │    │    Report DB        │
│    (Read/Write)     │───>│    (Read Only)      │
│                     │    │                     │
│  - orders           │    │  - orders           │
│  - positions        │    │  - positions        │
│  - accounts         │    │  - accounts         │
│  - trades           │    │  - trades           │
│  - transactions     │    │  - transactions     │
└─────────────────────┘    └─────────────────────┘
         │
         │ Replication
         └─────────────────────────>
```

### 주요 테이블 관계

```
users
  │
  ├──< accounts (1:N)
  │       │
  │       ├──< orders (1:N)
  │       │
  │       └──< positions (1:N)
  │
  └──< trades (via buyUserId/sellUserId)

instruments
  │
  ├──< orders (1:N via symbol)
  │
  ├──< positions (1:N via symbol)
  │
  └──< trades (1:N via symbol)
```

## 보안 아키텍처

### 인증 흐름

```
┌──────────┐     ┌─────────────────┐     ┌──────────────┐
│  Client  │────>│  JWT Auth Guard │────>│  Controller  │
└──────────┘     └────────┬────────┘     └──────────────┘
                          │
              ┌───────────┴───────────┐
              │                       │
              ▼                       ▼
     ┌────────────────┐      ┌────────────────┐
     │  JWT Token     │      │  API Key       │
     │  (RS256)       │      │  (SHA256 Sig)  │
     └────────────────┘      └────────────────┘
```

### API Key 인증

```
Headers:
  - APIKEY: {api_key}
  - signature: SHA256(timestamp + method + path + body)
  - timestamp: Unix timestamp (60초 유효)
```

## 확장성 고려사항

### 수평 확장

1. **Backend**:
   - Stateless 설계로 다중 인스턴스 가능
   - Redis Adapter로 WebSocket 동기화

2. **Engine**:
   - 심볼별 파티셔닝 가능
   - 각 심볼 독립적인 Matcher 인스턴스

3. **Database**:
   - Read Replica 추가 가능
   - 샤딩 가능 (userId 기준)

### 성능 최적화

1. **배치 처리**: 최대 10개 거래 배치
2. **캐싱**: Redis 캐시로 읽기 최적화
3. **비동기**: Kafka 비동기 처리
4. **인메모리**: 엔진 전체 데이터 인메모리

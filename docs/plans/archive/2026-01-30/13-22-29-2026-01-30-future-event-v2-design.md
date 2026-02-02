# Future Event V2 모듈 설계 문서

> 작성일: 2026-01-30
> 상태: Draft
> 관련 모듈: `future-backend/src/modules/future-event-v2`

---

## 1. 개요

### 1.1 목적

기존 `future-event` 모듈과 **완전히 독립적인** 새로운 리워드 시스템을 구축합니다.
기존 모듈은 단순 리워드 지급 후 잔액에서 차감하는 방식이었다면,
새 모듈은 **원금과 증정금을 분리 관리**하여 더 정교한 리워드 정책을 지원합니다.

### 1.2 핵심 요구사항

| 요구사항 | 설명 |
|----------|------|
| **입금 시 자동 지급** | 유저 입금(DEPOSIT) 트랜잭션 발생 시 증정금 자동 지급 |
| **온/오프 기능** | 관리자가 이벤트를 비활성화하면 그 순간부터 신규 지급 중단 |
| **원금/증정금 분리** | 거래 손실, 펀딩비, 수수료는 **원금에서만** 차감. 증정금은 보존 |
| **청산 연동** | 원금이 0 이하가 되면 포지션 청산 + 증정금도 함께 삭제 |
| **비율 조정** | 관리자가 이벤트별로 증정금 비율(%) 설정 가능 |

---

## 2. 기존 모듈과의 비교

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    기존 (future-event)                                   │
├─────────────────────────────────────────────────────────────────────────┤
│  • Account.rewardBalance에서 직접 차감                                   │
│  • 단순 리워드 지급 (금액 고정)                                           │
│  • 만료일 기반 회수                                                      │
│  • 거래량 요구사항 충족 시 잠금 해제                                       │
└─────────────────────────────────────────────────────────────────────────┘

                              ↓ 변경 ↓

┌─────────────────────────────────────────────────────────────────────────┐
│                    새 모듈 (future-event-v2)                             │
├─────────────────────────────────────────────────────────────────────────┤
│  • 원금(currentPrincipal)과 증정금(bonusAmount) 분리 관리                 │
│  • 입금 시 자동 지급 (비율 기반)                                          │
│  • 원금 청산 시 즉시 회수                                                 │
│  • 이벤트 온/오프로 실시간 제어                                           │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. 데이터 흐름

### 3.1 증정금 지급 플로우

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           증정금 지급 플로우                               │
└──────────────────────────────────────────────────────────────────────────┘

  [User]                    [Backend]                    [Kafka]
    │                          │                           │
    │  입금 요청               │                           │
    │─────────────────────────>│                           │
    │                          │                           │
    │                          │  Transaction 생성          │
    │                          │  (type=DEPOSIT,           │
    │                          │   status=APPROVED)        │
    │                          │                           │
    │                          │  Kafka 발행               │
    │                          │──────────────────────────>│
    │                          │                           │
    │                          │                           │
                               │<──────────────────────────│
                               │  future-event-v2:         │
                               │  process-deposit          │
                               │                           │
                               ▼                           │
                    ┌─────────────────────┐               │
                    │ 이벤트 활성화 체크    │               │
                    │ (status = ACTIVE?)  │               │
                    └─────────────────────┘               │
                               │                           │
                               ▼                           │
                    ┌─────────────────────┐               │
                    │ 조건 체크            │               │
                    │ • 최소 입금 금액     │               │
                    │ • 이벤트 기간 내     │               │
                    │ • 최대 보너스 한도   │               │
                    └─────────────────────┘               │
                               │                           │
                               ▼                           │
                    ┌─────────────────────┐               │
                    │ 증정금 계산          │               │
                    │ 입금액 × 비율% / 100│               │
                    └─────────────────────┘               │
                               │                           │
                               ▼                           │
                    ┌─────────────────────┐               │
                    │ UserBonusV2 생성     │               │
                    │ + History 기록       │               │
                    └─────────────────────┘               │
```

### 3.2 원금 차감 플로우

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           원금 차감 플로우                                │
└──────────────────────────────────────────────────────────────────────────┘

  [Matching Engine]              [Backend]                    [DB]
        │                           │                          │
        │  거래 체결                │                          │
        │  (수수료, 손실, 펀딩비)   │                          │
        │                           │                          │
        │  Kafka 발행               │                          │
        │──────────────────────────>│                          │
        │                           │                          │
                                    │  해당 계정의              │
                                    │  UserBonusV2 조회        │
                                    │─────────────────────────>│
                                    │                          │
                                    │<─────────────────────────│
                                    │  (bonusAmount,           │
                                    │   currentPrincipal)      │
                                    │                          │
                                    ▼                          │
                         ┌─────────────────────┐              │
                         │ currentPrincipal    │              │
                         │ -= 차감금액         │              │
                         │ (증정금은 유지)     │              │
                         └─────────────────────┘              │
                                    │                          │
                                    ▼                          │
                         ┌─────────────────────┐              │
                         │ currentPrincipal    │              │
                         │ <= 0 ?              │              │
                         └─────────────────────┘              │
                              │         │                      │
                         Yes  │         │ No                   │
                              ▼         ▼                      │
                    ┌──────────────┐  ┌──────────────┐        │
                    │ 청산 트리거   │  │ 정상 업데이트 │        │
                    │ 포지션 청산   │  │ History 기록 │        │
                    │ 증정금 삭제   │  └──────────────┘        │
                    └──────────────┘                          │
```

---

## 4. 데이터베이스 스키마

### 4.1 event_setting_v2 (이벤트 설정)

이벤트/캠페인의 기본 설정을 관리합니다.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | BIGINT | PK, Auto Increment |
| `eventName` | VARCHAR(100) | 이벤트 이름 (예: "신규 입금 보너스 100%") |
| `eventCode` | VARCHAR(50) | 이벤트 코드 (Unique, 예: "DEPOSIT_BONUS_100") |
| `status` | ENUM | ACTIVE, INACTIVE |
| `bonusRatePercent` | DECIMAL(10,2) | 증정금 비율 (예: 100.00 = 100%) |
| `minDepositAmount` | DECIMAL(30,15) | 최소 입금 금액 |
| `maxBonusAmount` | DECIMAL(30,15) | 최대 증정금 한도 |
| `startDate` | DATETIME | 이벤트 시작일 |
| `endDate` | DATETIME | 이벤트 종료일 |
| `createdAt` | DATETIME | 생성일 |
| `updatedAt` | DATETIME | 수정일 |

```sql
CREATE TABLE event_setting_v2 (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  eventName VARCHAR(100) NOT NULL,
  eventCode VARCHAR(50) NOT NULL UNIQUE,
  status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'INACTIVE',
  bonusRatePercent DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  minDepositAmount DECIMAL(30,15) NOT NULL DEFAULT 0,
  maxBonusAmount DECIMAL(30,15) NOT NULL DEFAULT 0,
  startDate DATETIME NOT NULL,
  endDate DATETIME NOT NULL,
  createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_date_range (startDate, endDate)
);
```

### 4.2 user_bonus_v2 (사용자 증정금)

사용자별 증정금과 원금을 관리합니다.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | BIGINT | PK, Auto Increment |
| `userId` | BIGINT | 사용자 ID |
| `accountId` | BIGINT | 계정 ID (Account FK) |
| `eventSettingId` | BIGINT | FK → event_setting_v2 |
| `transactionId` | BIGINT | 입금 트랜잭션 ID |
| `bonusAmount` | DECIMAL(30,15) | 증정금 금액 (변하지 않음) |
| `originalDeposit` | DECIMAL(30,15) | 원금 (입금 당시 금액) |
| `currentPrincipal` | DECIMAL(30,15) | 현재 원금 잔액 |
| `status` | ENUM | ACTIVE, LIQUIDATED, EXPIRED, REVOKED |
| `grantedAt` | DATETIME | 지급일 |
| `liquidatedAt` | DATETIME | 청산일 (NULL 가능) |
| `createdAt` | DATETIME | 생성일 |
| `updatedAt` | DATETIME | 수정일 |

```sql
CREATE TABLE user_bonus_v2 (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  userId BIGINT NOT NULL,
  accountId BIGINT NOT NULL,
  eventSettingId BIGINT NOT NULL,
  transactionId BIGINT NOT NULL,
  bonusAmount DECIMAL(30,15) NOT NULL,
  originalDeposit DECIMAL(30,15) NOT NULL,
  currentPrincipal DECIMAL(30,15) NOT NULL,
  status ENUM('ACTIVE', 'LIQUIDATED', 'EXPIRED', 'REVOKED') NOT NULL DEFAULT 'ACTIVE',
  grantedAt DATETIME NOT NULL,
  liquidatedAt DATETIME NULL,
  createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
  updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_userId (userId),
  INDEX idx_accountId (accountId),
  INDEX idx_status (status),
  INDEX idx_eventSettingId (eventSettingId),

  CONSTRAINT fk_user_bonus_v2_event FOREIGN KEY (eventSettingId)
    REFERENCES event_setting_v2(id)
);
```

### 4.3 user_bonus_v2_history (증정금 변동 이력)

원금 변동 이력을 추적합니다.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| `id` | BIGINT | PK, Auto Increment |
| `userBonusId` | BIGINT | FK → user_bonus_v2 |
| `userId` | BIGINT | 사용자 ID |
| `changeType` | VARCHAR(30) | GRANT, FEE, FUNDING, PNL, LIQUIDATE |
| `changeAmount` | DECIMAL(30,15) | 변동 금액 |
| `principalBefore` | DECIMAL(30,15) | 변동 전 원금 |
| `principalAfter` | DECIMAL(30,15) | 변동 후 원금 |
| `transactionUuid` | VARCHAR(100) | 관련 트랜잭션 UUID (NULL 가능) |
| `description` | VARCHAR(200) | 설명 |
| `createdAt` | DATETIME | 생성일 |

```sql
CREATE TABLE user_bonus_v2_history (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  userBonusId BIGINT NOT NULL,
  userId BIGINT NOT NULL,
  changeType VARCHAR(30) NOT NULL,
  changeAmount DECIMAL(30,15) NOT NULL,
  principalBefore DECIMAL(30,15) NOT NULL,
  principalAfter DECIMAL(30,15) NOT NULL,
  transactionUuid VARCHAR(100) NULL,
  description VARCHAR(200) NULL,
  createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_userBonusId (userBonusId),
  INDEX idx_userId (userId),
  INDEX idx_changeType (changeType),
  INDEX idx_createdAt (createdAt),

  CONSTRAINT fk_user_bonus_v2_history FOREIGN KEY (userBonusId)
    REFERENCES user_bonus_v2(id)
);
```

---

## 5. API 엔드포인트

### 5.1 관리자 API

| Method | Endpoint | 설명 | 권한 |
|--------|----------|------|------|
| POST | `/v1/future-event-v2/admin/settings` | 이벤트 설정 생성 | Admin |
| PUT | `/v1/future-event-v2/admin/settings/:id` | 이벤트 설정 수정 | Admin |
| GET | `/v1/future-event-v2/admin/settings` | 이벤트 설정 목록 | Admin |
| GET | `/v1/future-event-v2/admin/settings/:id` | 이벤트 설정 상세 | Admin |
| PATCH | `/v1/future-event-v2/admin/settings/:id/status` | 이벤트 온/오프 토글 | Admin |
| POST | `/v1/future-event-v2/admin/grant-bonus` | 증정금 수동 지급 | Admin |
| GET | `/v1/future-event-v2/admin/bonuses` | 증정금 목록 (필터링) | Admin |
| POST | `/v1/future-event-v2/admin/revoke-bonus/:id` | 증정금 회수 | Admin |

### 5.2 사용자 API

| Method | Endpoint | 설명 | 권한 |
|--------|----------|------|------|
| GET | `/v1/future-event-v2/my-bonuses` | 내 증정금 목록 | User |
| GET | `/v1/future-event-v2/my-bonuses/:id/history` | 증정금 변동 이력 | User |
| GET | `/v1/future-event-v2/active-events` | 활성 이벤트 목록 | Public |

---

## 6. 서비스 메서드

### 6.1 FutureEventV2Service

```typescript
class FutureEventV2Service {
  // ===== 이벤트 설정 관리 =====
  async createEventSetting(dto: CreateEventSettingV2Dto): Promise<EventSettingV2Entity>;
  async updateEventSetting(id: number, dto: UpdateEventSettingV2Dto): Promise<EventSettingV2Entity>;
  async toggleEventStatus(id: number): Promise<EventSettingV2Entity>;
  async getEventSettings(filters: EventSettingQueryDto): Promise<EventSettingV2Entity[]>;
  async getActiveEventSettings(): Promise<EventSettingV2Entity[]>;
  async isEventActive(eventCode: string): Promise<boolean>;

  // ===== 입금 시 증정금 자동 지급 =====
  /**
   * Kafka Consumer에서 호출 - 입금 트랜잭션 처리
   * 1. 활성 이벤트 확인
   * 2. 조건 체크 (최소 입금액, 기간)
   * 3. 증정금 계산 및 지급
   */
  async processDeposit(transaction: TransactionEntity): Promise<UserBonusV2Entity | null>;

  async checkDepositEligibility(
    userId: number,
    depositAmount: string,
    eventSetting: EventSettingV2Entity
  ): Promise<boolean>;

  async calculateBonusAmount(
    depositAmount: string,
    eventSetting: EventSettingV2Entity
  ): string;

  // ===== 증정금 수동 지급 (관리자) =====
  async grantBonus(dto: GrantBonusV2Dto): Promise<UserBonusV2Entity>;

  // ===== 증정금 조회 =====
  async getUserBonuses(userId: number): Promise<UserBonusV2Entity[]>;
  async getActiveBonusByAccountId(accountId: number): Promise<UserBonusV2Entity | null>;
  async getBonusesWithPagination(
    filters: AdminBonusV2QueryDto,
    pagination: PaginationDto
  ): Promise<[UserBonusV2Entity[], number]>;

  // ===== 원금 차감 (핵심 로직) =====
  /**
   * 거래 손실, 수수료, 펀딩비 발생 시 호출
   * - 증정금은 유지하고 원금에서만 차감
   * - 원금 <= 0 이면 청산 트리거
   */
  async deductFromPrincipal(
    accountId: number,
    amount: string,
    changeType: string,
    transactionUuid?: string
  ): Promise<void>;

  // ===== 청산 처리 =====
  /**
   * 원금이 0 이하가 되면 호출
   * - 포지션 청산 요청
   * - 증정금 상태를 LIQUIDATED로 변경
   */
  async handleLiquidation(accountId: number): Promise<void>;
  async checkAndTriggerLiquidation(accountId: number): Promise<boolean>;

  // ===== 증정금 회수 =====
  async revokeBonus(bonusId: number, reason: string): Promise<void>;
}
```

---

## 7. Enum & Constants

### 7.1 EventStatusV2

```typescript
export enum EventStatusV2 {
  ACTIVE = 'ACTIVE',     // 활성 - 신규 지급 가능
  INACTIVE = 'INACTIVE'  // 비활성 - 신규 지급 중단
}
```

### 7.2 BonusStatusV2

```typescript
export enum BonusStatusV2 {
  ACTIVE = 'ACTIVE',         // 활성 - 사용 가능
  LIQUIDATED = 'LIQUIDATED', // 청산됨 - 원금 소진으로 청산
  EXPIRED = 'EXPIRED',       // 만료됨 - 이벤트 기간 종료
  REVOKED = 'REVOKED'        // 회수됨 - 관리자에 의해 회수
}
```

### 7.3 BonusChangeType

```typescript
export enum BonusChangeType {
  GRANT = 'GRANT',           // 지급
  TRADING_FEE = 'TRADING_FEE',     // 거래 수수료
  FUNDING_FEE = 'FUNDING_FEE',     // 펀딩비
  REALIZED_PNL = 'REALIZED_PNL',   // 실현 손익
  LIQUIDATION = 'LIQUIDATION',     // 청산
  REVOKE = 'REVOKE'          // 회수
}
```

---

## 8. 파일 구조

```
future-backend/src/
├── models/
│   ├── entities/
│   │   ├── event-setting-v2.entity.ts          # 이벤트 설정 엔티티
│   │   ├── user-bonus-v2.entity.ts             # 사용자 증정금 엔티티
│   │   └── user-bonus-v2-history.entity.ts     # 증정금 변동 이력 엔티티
│   └── repositories/
│       ├── event-setting-v2.repository.ts      # 이벤트 설정 Repository
│       ├── user-bonus-v2.repository.ts         # 사용자 증정금 Repository
│       └── user-bonus-v2-history.repository.ts # 변동 이력 Repository
│
├── modules/
│   └── future-event-v2/
│       ├── constants/
│       │   ├── event-status-v2.enum.ts         # 이벤트 상태 Enum
│       │   ├── bonus-status-v2.enum.ts         # 증정금 상태 Enum
│       │   └── bonus-change-type.enum.ts       # 변동 타입 Enum
│       ├── dto/
│       │   ├── create-event-setting-v2.dto.ts  # 이벤트 생성 DTO
│       │   ├── update-event-setting-v2.dto.ts  # 이벤트 수정 DTO
│       │   ├── grant-bonus-v2.dto.ts           # 증정금 지급 DTO
│       │   └── admin-bonus-v2-query.dto.ts     # 관리자 조회 DTO
│       ├── future-event-v2.service.ts          # 핵심 서비스
│       ├── future-event-v2.controller.ts       # REST 컨트롤러
│       ├── future-event-v2.console.ts          # Kafka Consumer
│       ├── future-event-v2.module.ts           # NestJS 모듈
│       └── index.ts                            # Export
│
└── migrations/
    └── {timestamp}-create-future-event-v2-tables.ts  # 마이그레이션
```

---

## 9. 수정 필요 파일

| 파일 | 변경 내용 |
|------|-----------|
| `src/modules.ts` | `FutureEventV2Module` import 추가 |
| `src/models/database-common.ts` | 새 Repository 3개 등록 |

---

## 10. 예시 시나리오

### 시나리오 1: 정상 입금 및 증정금 지급

```
1. 사용자 A가 10,000 USDT 입금
2. 활성 이벤트: "DEPOSIT_BONUS_100" (100% 보너스, 최대 10,000 USDT)
3. 시스템이 자동으로:
   - UserBonusV2 생성
     - bonusAmount: 10,000 USDT (입금액 × 100%)
     - originalDeposit: 10,000 USDT
     - currentPrincipal: 10,000 USDT
   - History 기록 (changeType: GRANT)
```

### 시나리오 2: 거래 손실 발생

```
1. 사용자 A의 포지션에서 2,000 USDT 손실 발생
2. 시스템이 자동으로:
   - currentPrincipal: 10,000 → 8,000 USDT (원금에서만 차감)
   - bonusAmount: 10,000 USDT (변경 없음)
   - History 기록 (changeType: REALIZED_PNL)
```

### 시나리오 3: 원금 청산

```
1. 사용자 A의 누적 손실로 currentPrincipal이 -500 USDT가 됨
2. 시스템이 자동으로:
   - 포지션 청산 트리거
   - UserBonusV2 상태: ACTIVE → LIQUIDATED
   - liquidatedAt 기록
   - History 기록 (changeType: LIQUIDATION)
```

---

## 11. 다음 단계

스켈레톤 구현 완료 후 추가 작업:

1. **Kafka Consumer 연동** - 트랜잭션 토픽 구독 및 입금/차감 처리
2. **청산 로직 연동** - LiquidationService와 통합
3. **마이그레이션 실행** - 실제 테이블 생성
4. **단위 테스트 작성** - Service, Controller 테스트
5. **E2E 테스트** - 전체 플로우 검증

---

## 12. 참고

- 기존 모듈: `src/modules/future-event/`
- 트랜잭션 타입: `src/shares/enums/transaction.enum.ts`
- Kafka 토픽: `src/shares/enums/kafka.enum.ts`

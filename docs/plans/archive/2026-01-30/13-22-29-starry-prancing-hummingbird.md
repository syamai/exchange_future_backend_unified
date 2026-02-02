# Future Event V2 모듈 스켈레톤 구현 계획

## 목표

기존 `future-event` 모듈과 완전히 독립적인 새로운 리워드 시스템 `future-event-v2` 모듈 스켈레톤 생성

## 핵심 요구사항

| 기능 | 설명 |
|------|------|
| **입금 시 자동 지급** | 유저 입금(DEPOSIT) 트랜잭션 발생 시 증정금 자동 지급 |
| **온/오프 기능** | 이벤트 비활성화 시 그 순간부터 지급 중단 |
| **원금/증정금 분리** | 손실/수수료는 원금에서만 차감, 증정금 보존 |
| **청산 연동** | 원금 ≤ 0 시 포지션 청산 + 증정금 삭제 |
| **비율 조정** | 증정금 지급 시 퍼센트 비율 설정 가능 |

### 증정금 지급 트리거 흐름

```
User Deposit → Transaction(type=DEPOSIT, status=APPROVED)
                              ↓
              Kafka Consumer (future-event-v2:process-deposit)
                              ↓
              이벤트 활성화 체크 (EventSettingV2.status = ACTIVE)
                              ↓
              조건 체크 (최소 입금 금액, 이벤트 기간 내)
                              ↓
              증정금 계산 (입금액 × bonusRatePercent / 100)
                              ↓
              UserBonusV2 생성 + History 기록
```

**지급 조건**: 이벤트 기간 내 **모든 입금**에 대해 증정금 지급
- 입금할 때마다 새로운 UserBonusV2 레코드 생성
- 각 증정금은 해당 입금의 원금과 1:1 매핑

## 기존 vs 새 모듈 비교

```
기존 (future-event)           새 모듈 (future-event-v2)
─────────────────────────────────────────────────────────
rewardBalance에서 직접 차감   →  원금/증정금 분리 관리
단순 리워드 지급              →  이벤트 온/오프 제어
만료일 기반 회수              →  원금 청산 시 즉시 회수
고정 금액 지급                →  비율 기반 지급 지원
```

---

## 구현 범위 (스켈레톤)

### 1. 엔티티 (3개)

| 엔티티 | 테이블명 | 용도 |
|--------|----------|------|
| `EventSettingV2Entity` | `event_setting_v2` | 이벤트 설정 (온/오프, 비율) |
| `UserBonusV2Entity` | `user_bonus_v2` | 사용자별 증정금 |
| `UserBonusV2HistoryEntity` | `user_bonus_v2_history` | 증정금 변동 이력 |

### 2. Repository (3개)

- `EventSettingV2Repository`
- `UserBonusV2Repository`
- `UserBonusV2HistoryRepository`

### 3. DTO (4개)

- `CreateEventSettingV2Dto`
- `UpdateEventSettingV2Dto`
- `GrantBonusV2Dto`
- `AdminBonusV2QueryDto`

### 4. Enum/Constants (2개)

- `EventStatusV2` (ACTIVE, INACTIVE)
- `BonusStatusV2` (ACTIVE, LIQUIDATED, EXPIRED, REVOKED)

### 5. Service (1개)

- `FutureEventV2Service` - 핵심 비즈니스 로직 메서드 시그니처

### 6. Controller (1개)

- `FutureEventV2Controller` - REST API 엔드포인트

### 7. Module (1개)

- `FutureEventV2Module`

### 8. Console (1개)

- `FutureEventV2Console` - Kafka consumer 스켈레톤
  - `future-event-v2:process-deposit` - 입금 트랜잭션 감지 및 증정금 자동 지급
  - `future-event-v2:process-principal-deduction` - 원금 차감 처리 (수수료, 손실 등)

---

## 파일 구조

```
src/
├── models/
│   ├── entities/
│   │   ├── event-setting-v2.entity.ts          # NEW
│   │   ├── user-bonus-v2.entity.ts             # NEW
│   │   └── user-bonus-v2-history.entity.ts     # NEW
│   └── repositories/
│       ├── event-setting-v2.repository.ts      # NEW
│       ├── user-bonus-v2.repository.ts         # NEW
│       └── user-bonus-v2-history.repository.ts # NEW
│
├── modules/
│   └── future-event-v2/                        # NEW
│       ├── constants/
│       │   ├── event-status-v2.enum.ts
│       │   └── bonus-status-v2.enum.ts
│       ├── dto/
│       │   ├── create-event-setting-v2.dto.ts
│       │   ├── update-event-setting-v2.dto.ts
│       │   ├── grant-bonus-v2.dto.ts
│       │   └── admin-bonus-v2-query.dto.ts
│       ├── future-event-v2.service.ts
│       ├── future-event-v2.controller.ts
│       ├── future-event-v2.console.ts
│       ├── future-event-v2.module.ts
│       └── index.ts
│
└── migrations/
    └── {timestamp}-create-future-event-v2-tables.ts  # NEW
```

---

## 엔티티 스키마

### EventSettingV2Entity

```typescript
{
  id: number;                    // PK
  eventName: string;             // 이벤트 이름
  eventCode: string;             // 이벤트 코드 (unique)
  status: EventStatusV2;         // ACTIVE | INACTIVE
  bonusRatePercent: string;      // 증정금 비율 (예: "100" = 100%)
  minDepositAmount: string;      // 최소 입금 금액
  maxBonusAmount: string;        // 최대 증정금 한도
  startDate: Date;               // 이벤트 시작일
  endDate: Date;                 // 이벤트 종료일
  createdAt: Date;
  updatedAt: Date;
}
```

### UserBonusV2Entity

```typescript
{
  id: number;                    // PK
  userId: number;                // 사용자 ID
  accountId: number;             // 계정 ID
  eventSettingId: number;        // FK → event_setting_v2
  bonusAmount: string;           // 증정금 금액
  originalDeposit: string;       // 원금 (입금 당시 금액)
  currentPrincipal: string;      // 현재 원금 잔액
  status: BonusStatusV2;         // ACTIVE | LIQUIDATED | EXPIRED | REVOKED
  grantedAt: Date;               // 지급일
  liquidatedAt: Date | null;     // 청산일
  createdAt: Date;
  updatedAt: Date;
}
```

### UserBonusV2HistoryEntity

```typescript
{
  id: number;                    // PK
  userBonusId: number;           // FK → user_bonus_v2
  userId: number;                // 사용자 ID
  changeType: string;            // GRANT | FEE | FUNDING | PNL | LIQUIDATE
  changeAmount: string;          // 변동 금액
  principalBefore: string;       // 변동 전 원금
  principalAfter: string;        // 변동 후 원금
  transactionUuid: string | null;// 관련 트랜잭션 UUID
  createdAt: Date;
}
```

---

## API 엔드포인트 (스켈레톤)

### 관리자 API

| Method | Endpoint | 설명 |
|--------|----------|------|
| POST | `/v1/future-event-v2/admin/settings` | 이벤트 설정 생성 |
| PUT | `/v1/future-event-v2/admin/settings/:id` | 이벤트 설정 수정 |
| GET | `/v1/future-event-v2/admin/settings` | 이벤트 설정 목록 |
| POST | `/v1/future-event-v2/admin/grant-bonus` | 증정금 수동 지급 |
| GET | `/v1/future-event-v2/admin/bonuses` | 증정금 목록 (필터링) |

### 사용자 API

| Method | Endpoint | 설명 |
|--------|----------|------|
| GET | `/v1/future-event-v2/my-bonuses` | 내 증정금 목록 |
| GET | `/v1/future-event-v2/my-bonuses/:id/history` | 증정금 변동 이력 |

---

## 서비스 메서드 시그니처

```typescript
class FutureEventV2Service {
  // 이벤트 설정
  async createEventSetting(dto: CreateEventSettingV2Dto): Promise<EventSettingV2Entity>;
  async updateEventSetting(id: number, dto: UpdateEventSettingV2Dto): Promise<EventSettingV2Entity>;
  async getActiveEventSettings(): Promise<EventSettingV2Entity[]>;
  async isEventActive(eventCode: string): Promise<boolean>;

  // 입금 시 증정금 자동 지급 (핵심 트리거)
  async processDeposit(transaction: TransactionEntity): Promise<UserBonusV2Entity | null>;
  async checkDepositEligibility(userId: number, depositAmount: string, eventSetting: EventSettingV2Entity): Promise<boolean>;
  async calculateBonusAmount(depositAmount: string, eventSetting: EventSettingV2Entity): string;

  // 증정금 수동 지급 (관리자)
  async grantBonus(dto: GrantBonusV2Dto): Promise<UserBonusV2Entity>;

  // 증정금 조회
  async getUserBonuses(userId: number): Promise<UserBonusV2Entity[]>;
  async getActiveBonusByAccountId(accountId: number): Promise<UserBonusV2Entity | null>;

  // 원금 차감 (핵심 로직 - TODO)
  async deductFromPrincipal(accountId: number, amount: string, changeType: string, transactionUuid?: string): Promise<void>;

  // 청산 처리 (핵심 로직 - TODO)
  async handleLiquidation(accountId: number): Promise<void>;
  async checkAndTriggerLiquidation(accountId: number): Promise<boolean>;
}
```

---

## 수정해야 할 기존 파일

| 파일 | 변경 내용 |
|------|-----------|
| `src/modules.ts` | `FutureEventV2Module` import 추가 |
| `src/models/database-common.ts` | 새 Repository 3개 등록 |

---

## 검증 방법

1. **빌드 테스트**
   ```bash
   cd future-backend
   yarn build
   ```

2. **타입 체크**
   ```bash
   yarn typecheck
   ```

3. **마이그레이션 생성 확인**
   ```bash
   yarn typeorm:migrate
   ```

4. **서버 시작 테스트**
   ```bash
   yarn start:dev
   # Swagger UI에서 /v1/future-event-v2/* 엔드포인트 확인
   ```

---

## 생성할 파일 목록 (총 16개)

### 엔티티 (3개)
1. `src/models/entities/event-setting-v2.entity.ts`
2. `src/models/entities/user-bonus-v2.entity.ts`
3. `src/models/entities/user-bonus-v2-history.entity.ts`

### Repository (3개)
4. `src/models/repositories/event-setting-v2.repository.ts`
5. `src/models/repositories/user-bonus-v2.repository.ts`
6. `src/models/repositories/user-bonus-v2-history.repository.ts`

### 모듈 (7개)
7. `src/modules/future-event-v2/constants/event-status-v2.enum.ts`
8. `src/modules/future-event-v2/constants/bonus-status-v2.enum.ts`
9. `src/modules/future-event-v2/dto/create-event-setting-v2.dto.ts`
10. `src/modules/future-event-v2/dto/update-event-setting-v2.dto.ts`
11. `src/modules/future-event-v2/dto/grant-bonus-v2.dto.ts`
12. `src/modules/future-event-v2/dto/admin-bonus-v2-query.dto.ts`
13. `src/modules/future-event-v2/future-event-v2.service.ts`
14. `src/modules/future-event-v2/future-event-v2.controller.ts`
15. `src/modules/future-event-v2/future-event-v2.module.ts`
16. `src/modules/future-event-v2/index.ts`

### 수정 파일 (2개)
- `src/modules.ts`
- `src/models/database-common.ts`

---

## 다음 단계 (구현 후)

스켈레톤 완성 후 실제 비즈니스 로직 구현 시 추가 작업:

1. **Kafka Consumer 연동** - 트랜잭션 발생 시 원금 차감
2. **청산 로직 연동** - LiquidationService와 연결
3. **마이그레이션 실행** - 실제 테이블 생성
4. **단위 테스트 작성**

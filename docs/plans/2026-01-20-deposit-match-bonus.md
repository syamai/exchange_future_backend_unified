# Deposit Match Bonus Event 구현 계획

## 1. 목표 및 배경

### 목표
입금 금액과 동일한 비율(설정 가능)의 보너스를 지급하고, 실제 입금 잔고가 소진되면 자동으로 보너스를 회수하는 이벤트 시스템 구현

### 요구사항
1. **보너스 지급**: 입금 금액 × 설정 비율(50%, 100%, 200% 등) 보너스 지급
2. **차감 방식**: 수수료/PnL은 `balance`에서만 차감 (`rewardBalance` 차감 제외)
3. **자동 회수 조건**: `realDepositBalance = balance - rewardBalance <= 0`
4. **회수 절차**: 강제 청산 → 보너스 회수 → 잔고 0 설정
5. **다중 입금**: 매 입금마다 보너스 지급
6. **이벤트 제어**: 수동 on/off (날짜 범위 없음)

---

## 2. 구현 방식

### 2.1 신규 모듈 구조

```
src/modules/deposit-match-bonus/
├── deposit-match-bonus.module.ts
├── deposit-match-bonus.service.ts        # 보너스 지급 로직
├── deposit-match-bonus.console.ts        # Kafka 컨슈머
├── deposit-match-revoke-checker.service.ts  # 회수 조건 체크
├── constants/
│   └── deposit-match-bonus.const.ts      # 상수 정의
├── entities/
│   └── deposit-match-bonus.entity.ts     # 입금 보너스 기록
└── dto/
    └── deposit-match-config.dto.ts       # 설정 DTO
```

### 2.2 데이터베이스 스키마

```sql
-- 새 테이블: deposit_match_bonus
CREATE TABLE deposit_match_bonus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  userId INT NOT NULL,
  accountId INT NOT NULL,
  depositTransactionId INT NOT NULL,      -- 원본 입금 트랜잭션
  depositAmount DECIMAL(36,18) NOT NULL,  -- 입금 금액
  bonusAmount DECIMAL(36,18) NOT NULL,    -- 지급된 보너스
  bonusRate DECIMAL(5,2) NOT NULL,        -- 적용된 비율 (1.0 = 100%)
  status ENUM('ACTIVE', 'REVOKED') DEFAULT 'ACTIVE',
  asset VARCHAR(20) NOT NULL,
  createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  revokedAt TIMESTAMP NULL,
  INDEX idx_userId (userId),
  INDEX idx_accountId_status (accountId, status)
);

-- 이벤트 설정 테이블
CREATE TABLE deposit_match_event_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  isEnabled BOOLEAN DEFAULT FALSE,
  bonusRate DECIMAL(5,2) DEFAULT 1.00,    -- 100% 기본
  asset VARCHAR(20) DEFAULT 'USDT',
  minDepositAmount DECIMAL(36,18) DEFAULT 0,
  maxBonusPerUser DECIMAL(36,18) NULL,    -- NULL = 무제한
  updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 2.3 핵심 서비스 구현

#### DepositMatchBonusService

```typescript
// src/modules/deposit-match-bonus/deposit-match-bonus.service.ts

@Injectable()
export class DepositMatchBonusService {

  // 입금 시 보너스 지급
  async grantBonusOnDeposit(transaction: TransactionEntity): Promise<void> {
    // 1. 이벤트 활성화 여부 확인
    const config = await this.getEventConfig();
    if (!config.isEnabled) return;

    // 2. 최소 입금액 검증
    if (new BigNumber(transaction.amount).lt(config.minDepositAmount)) return;

    // 3. 보너스 금액 계산
    const bonusAmount = new BigNumber(transaction.amount)
      .multipliedBy(config.bonusRate)
      .toString();

    // 4. 보너스 지급 기록 저장
    const bonus = new DepositMatchBonusEntity();
    bonus.userId = transaction.userId;
    bonus.accountId = transaction.accountId;
    bonus.depositTransactionId = transaction.id;
    bonus.depositAmount = transaction.amount;
    bonus.bonusAmount = bonusAmount;
    bonus.bonusRate = config.bonusRate;
    bonus.asset = transaction.asset;
    await this.bonusRepo.save(bonus);

    // 5. rewardBalance 증가
    await this.accountRepoMaster.createQueryBuilder()
      .update(AccountEntity)
      .set({ rewardBalance: () => `rewardBalance + ${bonusAmount}` })
      .where("id = :accountId", { accountId: transaction.accountId })
      .execute();

    // 6. UserRewardFutureEvent 기록 (기존 시스템 호환)
    const reward = new UserRewardFutureEventEntity();
    reward.userId = transaction.userId;
    reward.amount = bonusAmount;
    reward.remaining = bonusAmount;
    reward.status = RewardStatus.IN_USE;
    reward.eventName = 'DEPOSIT_MATCH_BONUS';
    reward.refId = bonus.id.toString();
    await this.rewardRepo.save(reward);
  }
}
```

#### DepositMatchRevokeCheckerService

```typescript
// src/modules/deposit-match-bonus/deposit-match-revoke-checker.service.ts

@Injectable()
export class DepositMatchRevokeCheckerService {

  // 매 트랜잭션 후 회수 조건 체크
  async checkAndRevokeIfNeeded(accountId: number): Promise<void> {
    const account = await this.accountRepo.findOne({ id: accountId });

    // 회수 조건: balance - rewardBalance <= 0
    const realDepositBalance = new BigNumber(account.balance)
      .minus(account.rewardBalance);

    if (realDepositBalance.lte(0) && new BigNumber(account.rewardBalance).gt(0)) {
      await this.executeRevoke(account);
    }
  }

  private async executeRevoke(account: AccountEntity): Promise<void> {
    // 1. 강제 청산 실행
    await this.forceLiquidateAllPositions(account.userId);
    await this.cancelAllOrders(account.userId);

    // 2. 보너스 회수 및 잔고 0으로 설정
    const revokeAmount = account.rewardBalance;

    await this.accountRepoMaster.createQueryBuilder()
      .update(AccountEntity)
      .set({
        balance: '0',
        rewardBalance: '0'
      })
      .where("id = :accountId", { accountId: account.id })
      .execute();

    // 3. 보너스 기록 상태 업데이트
    await this.bonusRepo.update(
      { accountId: account.id, status: 'ACTIVE' },
      { status: 'REVOKED', revokedAt: new Date() }
    );

    // 4. 리워드 상태 업데이트
    await this.rewardRepo.update(
      { userId: account.userId, eventName: 'DEPOSIT_MATCH_BONUS', status: RewardStatus.IN_USE },
      { status: RewardStatus.REVOKED, isRevoke: true }
    );

    // 5. Matching Engine에 잔고 동기화
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.SYNC_ACCOUNT_BALANCE,
      data: { accountId: account.id, balance: '0', rewardBalance: '0' }
    });
  }

  private async forceLiquidateAllPositions(userId: number): Promise<void> {
    const positions = await this.positionRepo.find({
      where: { userId, status: PositionStatus.OPEN }
    });

    for (const position of positions) {
      await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.FORCE_LIQUIDATE,
        data: { positionId: position.id, userId }
      });
    }
  }
}
```

### 2.4 Kafka 컨슈머

```typescript
// src/modules/deposit-match-bonus/deposit-match-bonus.console.ts

@Console()
@Injectable()
export class DepositMatchBonusConsole {

  @Command({
    command: "deposit-match-bonus:process",
    description: "Process deposit match bonus on successful deposits"
  })
  async processDepositBonus(): Promise<void> {
    // matching_engine_output에서 DEPOSIT 완료 이벤트 감지
    await this.kafkaClient.consume(
      KafkaTopics.matching_engine_output,
      KafkaGroups.deposit_match_bonus,
      async (data: MatchingEngineOutputEvent) => {
        if (data.code === CommandCode.DEPOSIT && data.status === 'CONFIRMED') {
          await this.bonusService.grantBonusOnDeposit(data.transaction);
        }
      }
    );
    return new Promise(() => {});
  }

  @Command({
    command: "deposit-match-bonus:revoke-check",
    description: "Check revoke condition after transactions"
  })
  async checkRevokeCondition(): Promise<void> {
    // 잔고 변경 이벤트 감지
    await this.kafkaClient.consume(
      KafkaTopics.matching_engine_output,
      KafkaGroups.deposit_match_revoke_check,
      async (data: MatchingEngineOutputEvent) => {
        const affectBalanceTypes = [
          CommandCode.TRADING_FEE,
          CommandCode.FUNDING_FEE,
          CommandCode.REALIZED_PNL,
          CommandCode.LIQUIDATION_CLEARANCE
        ];

        if (affectBalanceTypes.includes(data.code)) {
          await this.revokeChecker.checkAndRevokeIfNeeded(data.accountId);
        }
      }
    );
    return new Promise(() => {});
  }
}
```

### 2.5 기존 코드 수정

#### future-event.service.ts 수정

```typescript
// 수정: updateUserUsedRewardBalance에서 DEPOSIT_MATCH_BONUS 제외

public async updateUserUsedRewardBalance(
  transaction: TransactionEntity,
): Promise<void> {
  // DEPOSIT_MATCH_BONUS 이벤트는 rewardBalance 차감 제외
  const depositMatchReward = await this.rewardRepo.findOne({
    where: {
      userId: transaction.userId,
      eventName: 'DEPOSIT_MATCH_BONUS',
      status: RewardStatus.IN_USE
    }
  });

  if (depositMatchReward) {
    // DEPOSIT_MATCH_BONUS 사용자는 rewardBalance 차감 건너뛰기
    return;
  }

  // 기존 차감 로직 유지...
}
```

### 2.6 설정 파일

```yaml
# config/default.yml

depositMatchBonus:
  enabled: false
  defaultBonusRate: 1.0        # 100%
  defaultAsset: 'USDT'
  minDepositAmount: '10'       # 최소 입금액
  maxBonusPerUser: null        # null = 무제한

kafka:
  groups:
    deposit_match_bonus: 'deposit-match-bonus-group'
    deposit_match_revoke_check: 'deposit-match-revoke-check-group'
```

---

## 3. 영향 범위

### 수정 파일
| 파일 | 수정 내용 |
|------|----------|
| `future-event.service.ts` | DEPOSIT_MATCH_BONUS 사용자 rewardBalance 차감 제외 |
| `kafka.enum.ts` | 새 consumer group 추가 |
| `app.module.ts` | DepositMatchBonusModule import |
| `matching-engine.const.ts` | SYNC_ACCOUNT_BALANCE, FORCE_LIQUIDATE 코드 추가 (필요시) |

### 신규 파일
| 파일 | 설명 |
|------|------|
| `deposit-match-bonus.module.ts` | 모듈 정의 |
| `deposit-match-bonus.service.ts` | 보너스 지급 서비스 |
| `deposit-match-bonus.console.ts` | Kafka 컨슈머 |
| `deposit-match-revoke-checker.service.ts` | 회수 조건 체커 |
| `deposit-match-bonus.entity.ts` | 보너스 기록 엔티티 |
| `deposit-match-event-config.entity.ts` | 이벤트 설정 엔티티 |
| Migration 파일 | 테이블 생성 |

### 영향받는 시스템
- Matching Engine: `SYNC_ACCOUNT_BALANCE`, `FORCE_LIQUIDATE` 커맨드 처리 필요
- Kafka: 새 consumer group 2개 추가
- Database: 테이블 2개 추가

---

## 4. 구현 체크리스트

### Phase 1: 기반 구조
- [ ] Migration 파일 생성 (deposit_match_bonus, deposit_match_event_config 테이블)
- [ ] Entity 파일 생성
- [ ] Repository 파일 생성
- [ ] Module 파일 생성

### Phase 2: 핵심 서비스
- [ ] DepositMatchBonusService 구현
- [ ] DepositMatchRevokeCheckerService 구현
- [ ] Kafka consumer (deposit-match-bonus:process)
- [ ] Kafka consumer (deposit-match-bonus:revoke-check)

### Phase 3: 기존 코드 수정
- [ ] future-event.service.ts 수정 (DEPOSIT_MATCH 차감 제외)
- [ ] kafka.enum.ts에 consumer group 추가
- [ ] app.module.ts에 모듈 등록

### Phase 4: 관리 API (선택)
- [ ] 이벤트 on/off API
- [ ] 보너스 비율 설정 API
- [ ] 보너스 현황 조회 API

### Phase 5: 테스트
- [ ] 단위 테스트 작성
- [ ] 통합 테스트 (입금 → 보너스 지급 플로우)
- [ ] 회수 조건 테스트

---

## 5. 흐름도

```
[사용자 입금]
     ↓
[Kafka: matching_engine_input] → [Matching Engine]
     ↓
[Kafka: matching_engine_output] (DEPOSIT CONFIRMED)
     ↓
[deposit-match-bonus:process 컨슈머]
     ↓
[이벤트 활성화?] → No → 종료
     ↓ Yes
[보너스 금액 계산: 입금액 × 비율]
     ↓
[DB: deposit_match_bonus 기록]
     ↓
[DB: account.rewardBalance += bonusAmount]
     ↓
[DB: user_reward_future_event 기록]


[트레이딩 발생 (수수료/PnL)]
     ↓
[Kafka: matching_engine_output]
     ↓
[deposit-match-bonus:revoke-check 컨슈머]
     ↓
[회수 조건 체크: balance - rewardBalance <= 0?]
     ↓ Yes
[강제 청산: 포지션/주문]
     ↓
[잔고 초기화: balance=0, rewardBalance=0]
     ↓
[상태 업데이트: REVOKED]
     ↓
[Matching Engine 동기화]
```

---

## 6. 주의사항

1. **동시성**: 잔고 업데이트 시 race condition 방지를 위해 트랜잭션 + 락 사용
2. **Matching Engine 동기화**: 강제 청산 및 잔고 초기화 후 반드시 ME에 동기화
3. **기존 리워드 시스템과 분리**: DEPOSIT_MATCH_BONUS는 별도 처리 흐름
4. **로깅**: 모든 보너스 지급/회수 이벤트 상세 로깅 필수

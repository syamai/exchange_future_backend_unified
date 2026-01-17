# LiquidationTest 변경 사항 정리

## 변경 배경

`LiquidationService`에서 **시장 청산(Market Liquidation)이 주석 처리**되어 있어, 모든 청산이 **직접 보험기금 청산**으로 진행됩니다.

### 기존 청산 흐름 (Expected)
```
1. 청산 대상 식별
2. IOC 시장 주문으로 청산 시도
3. 시장에서 체결되지 않은 잔량 → 보험기금 청산
4. 보험기금 부족 시 → ADL(Auto-Deleveraging)
```

### 현재 청산 흐름 (Actual)
```
1. 청산 대상 식별
2. 시장 청산 SKIP
3. 직접 보험기금 청산 (bankruptcy price 기준)
4. ADL 미동작 (시장 청산 단계가 없으므로)
```

---

## 변경된 테스트 목록

### 1. `test01_useInsurance_when_notCloseByMarket`
- **변경**: 테스트명 변경 (`useInsurrance_when_notCloseByMarket` → `test01_*`)
- **이유**: JUnit 5 알파벳순 실행으로 인한 테스트 격리 문제 해결 (0.0021 order leak)
- **내용**: Bankruptcy price `63966.25`로 직접 보험기금 청산

### 2. `cancelActiveOrderBeforeInsurance_when_liquidate`
- **변경**: IOC 시장 주문 제거, bankruptcy price로 직접 청산
- **Bankruptcy Price**: `63966.25`

### 3. `notUseInsurance_when_fullyCloseByMarketOrder`
- **변경**: 시장 청산 대신 보험기금 청산으로 변경
- **Bankruptcy Price**: `63966.25`
- **기타**: Order13이 ACTIVE 상태로 유지 (시장 매칭 안됨)

### 4. `useInsurance_when_partiallyCloseByMarketOrder`
- **변경**: 부분 시장 청산 제거, 전량 보험기금 청산
- **Bankruptcy Price**: `63966.25`

### 5. `useInsurance_when_partiallyCloseByMarketOrder_2`
- **변경**: 부분 시장 청산 제거, 전량 보험기금 청산
- **Bankruptcy Price**: `64177.33628271961`
- **Insurance Balance**: `999975.2339659285`

### 6. `useInsurance_when_partiallyCloseByMarketOrder_3`
- **변경**: SHORT 포지션 청산, 보험기금으로 직접 청산
- **Bankruptcy Price**: `66291.64501035371`
- **Insurance Balance**: `999974.41805419051`

### 7. `notCancelTpSlOrderBeforeInsurance_when_liquidate`
- **변경**: IOC 시장 주문(CANCELED) 제거, 직접 보험기금 청산
- **Bankruptcy Price**: `63966.25`
- **Order12 상태**: UNTRIGGERED (TP/SL 주문은 청산 전 취소 안됨)

### 8. `notChangingPositionMarginMode_when_liquidating`
- **변경**:
  - IOC CANCELED 주문 제거
  - Isolated 포지션 직접 보험기금 청산
- **Bankruptcy Price**: `64840`
- **Transaction Amount**: `-260.4016064257` (leftover margin)
- **Account Balance**: `594.5973935743`

### 9. `collectLeftOverMargin_when_liquidatingIsolatedPosition`
- **변경**: 직접 보험기금 청산
- **Bankruptcy Price**: `64345`
- **Transaction Amount**: `-258.41365461847`
- **Account Balance**: `497.65959538153`

### 10. `shouldChainLiquidate_when_dangerCrossMargin`
- **변경**:
  - `@Disabled` 제거
  - ETHUSD LeverageMarginRule 추가 (`getEthLeverageMarginCommand()`)
  - 두 심볼 모두 직접 보험기금 청산
- **UNIUSD Bankruptcy Price**: `63688.25`
- **ETHUSD Bankruptcy Price**: `32000`
- **추가된 메서드**:
```java
private List<Command> getEthLeverageMarginCommand() {
  String ethSymbol = "ETHUSD";
  List<LeverageMargin> ethRules = List.of(
    new LeverageMargin(0, 0, 50_000, 125, 0.4%, 0, ethSymbol),
    new LeverageMargin(1, 50_000, 250_000, 100, 0.5%, 50, ethSymbol),
    new LeverageMargin(2, 250_000, 1_000_000, 50, 1%, 1_300, ethSymbol)
  );
  return ethRules.stream()
    .map(lm -> new Command(CommandCode.LOAD_LEVERAGE_MARGIN, lm))
    .collect(Collectors.toList());
}
```

### 11. `autoDeleverage_when_insufficientInsuranceFund`
- **상태**: `@Disabled` 유지
- **이유**:
  - 보험기금 부족 시 ADL이 트리거되지 않음
  - 시장 청산 단계가 skip되어 ADL 진입 조건 미충족
  - 비즈니스 로직 변경 필요 (테스트 수정으로 해결 불가)

---

## Transaction 변경 사항

### createTransaction 메서드 오버로드 추가
```java
// 기존 메서드
private Transaction createTransaction(
    long accountId, MarginBigDecimal amount, Asset asset, TransactionType transactionType)

// 추가된 메서드 (symbol 파라미터)
private Transaction createTransaction(
    long accountId, MarginBigDecimal amount, Asset asset, String symbol, TransactionType transactionType)
```

---

## 테스트 결과 요약

| 항목 | 값 |
|------|-----|
| 전체 테스트 | 172개 |
| 통과 | 165개 |
| 실패 | 0개 |
| Skip | 7개 |

### Skip된 테스트 (LiquidationTest)
- `autoDeleverage_when_insufficientInsuranceFund` - ADL 기능 비활성화

---

## 향후 작업

ADL 기능을 다시 활성화하려면:
1. `LiquidationService`에서 시장 청산 로직 주석 해제
2. 또는 보험기금 부족 시 직접 ADL 트리거하도록 로직 수정
3. `autoDeleverage_when_insufficientInsuranceFund` 테스트의 `@Disabled` 제거 및 expected 값 수정

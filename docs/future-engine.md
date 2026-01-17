# Future Engine Analysis

Java 기반 고성능 선물 거래 매칭 엔진 분석 문서

## 개요

**프로젝트명**: Lagom Matching Engine
**언어**: Java 17
**빌드 도구**: Maven
**목적**: 암호화폐 선물 거래의 실시간 주문 매칭

## 프로젝트 구조

```
future-engine/
├── src/main/java/com/sotatek/future/
│   ├── MatchingEngineCLI.java       # 매칭 엔진 진입점
│   ├── TickerEngineCLI.java         # 티커 엔진 진입점
│   ├── TestEngineCLI.java           # 테스트 진입점
│   ├── engine/
│   │   ├── MatchingEngine.java      # 메인 엔진 (싱글톤)
│   │   ├── Matcher.java             # 심볼별 매칭 로직
│   │   ├── MatchingEngineConfig.java
│   │   ├── OrderComparators.java    # 주문 정렬 비교자
│   │   └── Trigger.java             # 트리거 주문 관리
│   ├── entity/
│   │   ├── Account.java
│   │   ├── Order.java
│   │   ├── Position.java
│   │   ├── Trade.java
│   │   ├── Instrument.java
│   │   └── ... (기타 엔티티)
│   ├── service/
│   │   ├── AccountService.java
│   │   ├── OrderService.java
│   │   ├── PositionService.java
│   │   ├── LiquidationService.java
│   │   ├── MarginCalculator.java
│   │   ├── PositionCalculator.java
│   │   └── ... (기타 서비스)
│   ├── usecase/
│   │   ├── OrderUseCase.java
│   │   ├── LiquidationUseCase.java
│   │   └── ... (기타 유스케이스)
│   └── input/output/               # Kafka 스트림
├── pom.xml
└── deploy/                         # 배포 스크립트
```

## 핵심 컴포넌트

### 1. MatchingEngine

**파일**: `src/main/java/com/sotatek/future/engine/MatchingEngine.java`

메인 이벤트 루프를 관리하는 싱글톤 컨트롤러입니다.

```java
// 핵심 로직 (간소화)
public void start() {
    while (!stopEngine) {
        Command command = commands.take();  // 블로킹 큐에서 명령 대기
        onTick();                           // 명령 처리
        commit();                           // 변경사항 커밋
    }
}

private void onTick() {
    switch (currentProcCommand.getCode()) {
        case PLACE_ORDER -> orderUseCase.placeOrder(currentProcCommand);
        case CANCEL_ORDER -> orderUseCase.cancelOrder(currentProcCommand);
        case LIQUIDATE -> liquidationUseCase.liquidate(currentProcCommand);
        case PAY_FUNDING -> fundingUseCase.payFunding(currentProcCommand);
        // ... 기타 명령
    }
}
```

**주요 기능**:
- Command Queue 기반 이벤트 처리
- 서비스 초기화 및 라이프사이클 관리
- 트랜잭션 Commit/Rollback

### 2. Matcher

**파일**: `src/main/java/com/sotatek/future/engine/Matcher.java`

심볼별 주문 매칭을 담당합니다.

```java
// 오더북 구조
private TreeSet<Order> buyOrders;   // 매수 주문 (높은 가격 우선)
private TreeSet<Order> sellOrders;  // 매도 주문 (낮은 가격 우선)

// 매칭 알고리즘
public boolean processOrder(Order order) {
    TreeSet<Order> oppositeBook = getPendingOrdersQueue(order.getOppositeSide());

    while (order.canBeMatched() && !oppositeBook.isEmpty()) {
        Order candidate = oppositeBook.first();

        if (!order.canBeMatchedWith(candidate)) break;

        try {
            commitTemporarily();
            Trade trade = matchOrders(order, candidate);
            oppositeBook.remove(candidate);
        } catch (InsufficientBalanceException e) {
            rollBackTemporarily();
            cancelOrder(insufficientOrder);
        }
    }

    // Time-In-Force 처리
    handleTimeInForce(order);
}
```

**주문 우선순위**:
1. 가격 우선 (매수: 높은 가격, 매도: 낮은 가격)
2. 시간 우선 (같은 가격일 경우)

### 3. Trigger

**파일**: `src/main/java/com/sotatek/future/engine/Trigger.java`

조건부 주문(Stop, TP/SL)을 관리합니다.

```java
// 트리거 조건
public enum OrderTrigger {
    LAST,   // 최종 거래가 기준
    ORACLE  // 마크 가격 기준
}

public enum TriggerCondition {
    GT,  // Greater Than
    LT   // Less Than
}

// 트리거 체크
public void startTrigger() {
    InstrumentExtraInformation extra = instrumentService.getExtraInfo(symbol);

    doTrigger(OrderTrigger.LAST, extra.getLastPrice());
    doTrigger(OrderTrigger.ORACLE, extra.getOraclePrice());

    // Trailing Stop 처리
    for (Order order : trailingStopOrders) {
        if (isTriggerTrailingStopOrder(order, extra)) {
            listener.onOrderTriggered(new Command(TRIGGER_ORDER, order));
        }
    }
}
```

## 서비스 레이어

### MarginCalculator

**파일**: `src/main/java/com/sotatek/future/service/MarginCalculator.java`

마진 및 손익 계산을 담당합니다.

#### 미실현 손익 (Unrealized PNL)

```java
// USD-M 계약
unrealizedPnl = size * (markPrice - entryPrice) * side;

// COIN-M 계약
unrealizedPnl = size * multiplier * (1/entryPrice - 1/markPrice) * side;
```

#### 실현 손익 (Realized PNL)

```java
// USD-M
realizedPnl = exitPrice * closeQty + closeValue;

// COIN-M
realizedPnl = closeQty * (exitPrice - entryPrice) * multiplier / (entryPrice * exitPrice);
```

#### 거래 수수료

```java
// USD-M
tradingFee = size * matchingPrice * feeRate;

// COIN-M
tradingFee = size * multiplier / matchingPrice * feeRate;
```

### PositionCalculator

**파일**: `src/main/java/com/sotatek/future/service/PositionCalculator.java`

포지션 계산을 담당합니다.

#### Cross Margin 청산가

```java
// USD-M
liquidationPrice = (WB - IPM - TMM + UPNL + cumB - side * size * entryPrice)
                   / (size * MMR - side * size);

// COIN-M
liquidationPrice = (size * MMR + side * size)
                   / [(WB + cumB) / multiplier + side * size / entryPrice];
```

### LiquidationService

**파일**: `src/main/java/com/sotatek/future/service/LiquidationService.java`

청산 로직을 처리합니다.

```java
// 청산 프로세스
public void liquidate(Position position) {
    // 1. 사용자 활성 주문 취소 (Cross 모드)
    cancelUserOrders(position.getUserId());

    // 2. 시장 청산 시도
    Order liquidationOrder = createLiquidationOrder(position);
    boolean marketLiquidated = tryMarketLiquidation(liquidationOrder);

    if (!marketLiquidated) {
        // 3. 보험 펀드 청산
        boolean insuranceLiquidated = tryInsuranceFundLiquidation(position);

        if (!insuranceLiquidated) {
            // 4. ADL (Auto-Deleveraging)
            executeADL(position);
        }
    }

    // 5. 청산 수수료 징수 (1%)
    chargeLiquidationFee(position);
}
```

**청산 조건**:
- Long: `oraclePrice < liquidationPrice`
- Short: `oraclePrice > liquidationPrice`
- Isolated 추가: `allocatedMargin <= maintenanceMargin`

## 엔티티 모델

### Order

```java
public class Order {
    private long id;
    private long accountId;
    private long userId;
    private String symbol;

    // 주문 유형
    private OrderType type;           // LIMIT, MARKET
    private OrderSide side;           // BUY, SELL
    private OrderStatus status;       // PENDING, ACTIVE, FILLED, CANCELED, UNTRIGGERED
    private TimeInForce timeInForce;  // GTC, IOC, FOK

    // 수량 및 가격
    private MarginBigDecimal price;
    private MarginBigDecimal quantity;
    private MarginBigDecimal remaining;
    private MarginBigDecimal executedPrice;

    // 마진 설정
    private MarginBigDecimal leverage;
    private MarginMode marginMode;    // CROSS, ISOLATED

    // 특수 주문
    private boolean isReduceOnly;
    private boolean isPostOnly;
    private TPSLType tpSLType;
    private MarginBigDecimal tpSLPrice;
    private long linkedOrderId;
}
```

### Position

```java
public class Position {
    private long id;
    private long accountId;
    private long userId;
    private String symbol;

    // 포지션 상태
    private MarginBigDecimal currentQty;     // + Long, - Short
    private MarginBigDecimal entryPrice;     // 평균 진입가
    private MarginBigDecimal entryValue;

    // 마진
    private MarginBigDecimal positionMargin;
    private MarginBigDecimal adjustMargin;   // 추가 마진
    private MarginBigDecimal orderCost;
    private boolean isCross;

    // 청산가
    private MarginBigDecimal liquidationPrice;
    private MarginBigDecimal bankruptPrice;

    // 레버리지
    private MarginBigDecimal leverage;
}
```

### Trade

```java
public class Trade {
    private long id;
    private String symbol;

    // 매칭된 주문
    private Order buyOrder;
    private Order sellOrder;

    // 체결 정보
    private MarginBigDecimal price;
    private MarginBigDecimal quantity;

    // 수수료
    private MarginBigDecimal buyFee;
    private MarginBigDecimal sellFee;

    // 실현 손익
    private MarginBigDecimal realizedPnlBuy;
    private MarginBigDecimal realizedPnlSell;

    private boolean buyerIsTaker;
    private String note;  // LIQUIDATION, ADL 등
}
```

## 기술 스택

### 의존성

```xml
<!-- 메시징 -->
<dependency>
    <groupId>org.apache.kafka</groupId>
    <artifactId>kafka_2.13</artifactId>
    <version>3.4.0</version>
</dependency>

<!-- JSON -->
<dependency>
    <groupId>com.google.code.gson</groupId>
    <artifactId>gson</artifactId>
    <version>2.10.1</version>
</dependency>

<!-- 유틸리티 -->
<dependency>
    <groupId>com.google.guava</groupId>
    <artifactId>guava</artifactId>
    <version>31.1-jre</version>
</dependency>

<!-- 로깅 -->
<dependency>
    <groupId>org.slf4j</groupId>
    <artifactId>slf4j-api</artifactId>
    <version>2.0.6</version>
</dependency>

<!-- 코드 생성 -->
<dependency>
    <groupId>org.projectlombok</groupId>
    <artifactId>lombok</artifactId>
    <version>1.18.26</version>
</dependency>
```

### 코드 품질

- **Spotless**: Google Java Format
- **SpotBugs**: 정적 분석
- **JaCoCo**: 코드 커버리지 (50% 이상)

## 설계 패턴

### 1. Singleton Pattern
모든 서비스 클래스에 적용

```java
public class OrderService extends BaseService<Order> {
    private static OrderService instance;

    public static OrderService getInstance() {
        if (instance == null) {
            instance = new OrderService();
        }
        return instance;
    }
}
```

### 2. Command Pattern
모든 작업을 Command 객체로 캡슐화

```java
public class Command {
    private CommandCode code;
    private Object payload;
    private String symbol;
    private long timestamp;
}
```

### 3. Factory Pattern
입출력 스트림 생성

```java
public class InputStreamFactory {
    public static InputStreamClient create(Map<String, Object> params) {
        String type = (String) params.get(INPUT_TYPE);
        return switch (type) {
            case "kafka" -> new KafkaInputStreamClient(params);
            case "rabbitmq" -> new RabbitMQInputStreamClient(params);
            default -> throw new IllegalArgumentException();
        };
    }
}
```

### 4. Observer Pattern
트리거 이벤트 리스너

```java
public interface OnOrderTriggeredListener {
    void onOrderTriggered(Command command);
}
```

## 성능 최적화

### 1. 인메모리 처리
모든 엔티티를 메모리에 보관하여 빠른 접근

```java
public abstract class BaseService<T extends BaseEntity> {
    protected Map<Long, T> entities = new HashMap<>();
}
```

### 2. 배치 처리
최대 10개 거래까지 배치로 처리

```java
private static final int TRADES_PER_MESSAGE = 10;
```

### 3. TreeSet 기반 오더북
O(log n) 복잡도로 주문 정렬

```java
private TreeSet<Order> buyOrders = new TreeSet<>(OrderComparators.BUY_COMPARATOR);
private TreeSet<Order> sellOrders = new TreeSet<>(OrderComparators.SELL_COMPARATOR);
```

### 4. 임시 커밋
롤백 가능한 임시 상태 저장

```java
public void commitTemporarily() {
    // 현재 상태 스냅샷 저장
}

public void rollBackTemporarily() {
    // 스냅샷으로 복원
}
```

## 실행 방법

### 빌드

```bash
mvn clean package -DskipTests
```

### 실행

```bash
# 매칭 엔진
java -jar target/matching-engine.jar \
  --kafka.brokers=localhost:9092 \
  --env=dev

# 티커 엔진
java -jar target/ticker-engine.jar \
  --kafka.brokers=localhost:9092
```

### Docker

```bash
# 개발 환경
docker-compose -f docker-compose-me.dev.yml up

# 프로덕션
docker-compose -f docker-compose-me.prod.yml up
```

## 주요 파일 참조

| 파일 | 역할 |
|------|------|
| `MatchingEngine.java` | 메인 엔진 컨트롤러 |
| `Matcher.java` | 주문 매칭 로직 |
| `Trigger.java` | 트리거 주문 관리 |
| `OrderService.java` | 주문 서비스 |
| `PositionService.java` | 포지션 서비스 |
| `LiquidationService.java` | 청산 서비스 |
| `MarginCalculator.java` | 마진 계산 |
| `PositionCalculator.java` | 포지션 계산 |

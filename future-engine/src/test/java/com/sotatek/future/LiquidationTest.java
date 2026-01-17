package com.sotatek.future;

import static org.assertj.core.api.Assertions.assertThat;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.AdjustMarginPosition;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.entity.Transaction;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.MarginMode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderTrigger;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TPSLType;
import com.sotatek.future.enums.TransactionStatus;
import com.sotatek.future.enums.TransactionType;
import com.sotatek.future.enums.TriggerCondition;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.stream.Collectors;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

public class LiquidationTest extends BaseTest {

  private long insuranceId;

  @Override
  @BeforeEach
  public void setUp() throws Exception {
    this.defaultSymbol = "UNIUSD";
    super.setUp();
    this.insuranceId = accountService.getInsuranceAccount(defaultAsset).getId();
  }

  @Override
  @AfterEach
  public void tearDown() throws Exception {
    super.tearDown();
  }

  /*
   Close position by insurance fund
  */
  @Test
  void useInsurrance_when_notCloseByMarket() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Order> orders = Arrays.asList(order10.deepCopy(), order11.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // However, there are no match, so it's cancelled immediately
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    // New funding order
    Order order3 =
        this.createOrder(3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    List<Order> processedOrders =
        Arrays.asList(order1, order2, order3, processedOrder10, processedOrder11);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65000", "1"),
            this.createTrade(2, order2, order3, "65000", "1"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(1, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Account insuranceAccount = this.accountService.getInsuranceAccount(position.getAsset());
    Assertions.assertEquals(MarginBigDecimal.valueOf("1000967.5"), insuranceAccount.getBalance());

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(1), insurancePosition.getCurrentQty());
  }

  /*
   Cancel user's order
   Close position by insurance fund
  */
  @Test
  void cancelActiveOrderBeforeInsurance_when_liquidate() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order12 = this.createOrder(12, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "0.1");
    List<Order> orders = Arrays.asList(order10.deepCopy(), order11.deepCopy(), order12.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);

    // Order12 is cancelled automatically when account 1 is liquidated
    Order processedOrder12 = this.cloneOrder(order12, "0.1", OrderStatus.CANCELED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // However, there are no match, so it's cancelled immediately
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    // New funding order
    Order order3 =
        this.createOrder(3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    List<Order> processedOrders =
        Arrays.asList(order1, order2, order3, processedOrder10, processedOrder11, processedOrder12);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65000", "1"),
            this.createTrade(2, order2, order3, "65000", "1"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(1, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Account insuranceAccount = this.accountService.getInsuranceAccount(position.getAsset());
    Assertions.assertEquals(MarginBigDecimal.valueOf("1000967.5"), insuranceAccount.getBalance());

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(1), insurancePosition.getCurrentQty());
  }

  /*
   Cancel user's order
   Close position in market
  */
  @Test
  void notUseInsurance_when_fullyCloseByMarketOrder() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("3000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order12 = this.createOrder(12, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "0.1");
    Order order13 = this.createOrder(13, 3, OrderSide.BUY, OrderType.LIMIT, "65100", "1");
    List<Order> orders =
        Arrays.asList(
            order10.deepCopy(), order11.deepCopy(), order12.deepCopy(), order13.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);

    // Order12 is cancelled automatically when account 1 is liquidated
    Order processedOrder12 = this.cloneOrder(order12, "0.1", OrderStatus.CANCELED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // This liquidation order is fully filled by order 13
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder13 = this.cloneOrder(order13, "0", OrderStatus.FILLED);

    List<Order> processedOrders =
        Arrays.asList(
            order1, processedOrder10, processedOrder11, processedOrder12, processedOrder13);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65000", "1"),
            this.createTrade(2, order1, order13, "65100", "1"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-1034.925"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("1034.925"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(1, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    assertThat(insurancePosition).isNull();

    Account liquidatedAccount = accountService.get(1L);
    assertThat(liquidatedAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("0"));
    Account insuranceAccount = accountService.getInsuranceAccount(position.getAsset());
    assertThat(insuranceAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("1001034.925"));
  }

  /*
   Cancel user's order
   Close position in market
   Close position by insurance fund
  */
  @Test
  void useInsurance_when_partiallyCloseByMarketOrder() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order12 = this.createOrder(12, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "0.4");
    Order order13 = this.createOrder(13, 3, OrderSide.BUY, OrderType.LIMIT, "65000", "0.5");
    List<Order> orders =
        Arrays.asList(
            order10.deepCopy(), order11.deepCopy(), order12.deepCopy(), order13.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);

    // Order12 is cancelled automatically when account 1 is liquidated
    Order processedOrder12 = this.cloneOrder(order12, "0.4", OrderStatus.CANCELED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // This liquidation order is partially filled by order 13
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order1 = this.cloneOrder(order1, "0.5", OrderStatus.CANCELED);
    Order processedOrder13 = this.cloneOrder(order13, "0", OrderStatus.FILLED);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "0.5");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    // New funding order
    Order order3 =
        this.createOrder(3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "65000", "0.5");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    List<Order> processedOrders =
        Arrays.asList(
            order1,
            order2,
            order3,
            processedOrder10,
            processedOrder11,
            processedOrder12,
            processedOrder13);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65000", "1"),
            this.createTrade(2, order1, order13, "65000", "0.5"),
            this.createTrade(3, order2, order3, "65000", "0.5"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(1, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Account insuranceAccount = this.accountService.getInsuranceAccount(position.getAsset());
    assertThat(insuranceAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("1000975.625"));
    Account liquidatedAccount = this.accountService.get(1L);
    assertThat(liquidatedAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("0"));

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf("0.5"), insurancePosition.getCurrentQty());
  }

  /*
   Cancel user's order
   Close position in market
   Close position by insurance fund
  */
  @Test
  void useInsurance_when_partiallyCloseByMarketOrder_2() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1264"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1264"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("1264"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65012.34", "1.5436");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65012.34", "1.5436");
    Order order12 = this.createOrder(12, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "0.0021");
    Order order13 = this.createOrder(13, 3, OrderSide.BUY, OrderType.LIMIT, "65027.87", "0.2987");
    List<Order> orders =
        Arrays.asList(
            order10.deepCopy(), order11.deepCopy(), order12.deepCopy(), order13.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);

    // Order12 is cancelled automatically when account 1 is liquidated
    Order processedOrder12 = this.cloneOrder(order12, "0.0021", OrderStatus.CANCELED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // This liquidation order is partially filled by order 13
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "65012.3399", "1.5436");
    order1 = this.cloneOrder(order1, "1.2449", OrderStatus.CANCELED);
    Order processedOrder13 = this.cloneOrder(order13, "0", OrderStatus.FILLED);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 =
        this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "65012.33994698", "1.2449");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    // New funding order
    Order order3 =
        this.createOrder(
            3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "65012.33994698", "1.2449");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    List<Order> processedOrders =
        Arrays.asList(
            order1,
            order2,
            order3,
            processedOrder10,
            processedOrder11,
            processedOrder12,
            processedOrder13);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65012.34", "1.5436"),
            this.createTrade(2, order1, order13, "65027.87", "0.2987"),
            this.createTrade(3, order2, order3, "65012.33994698", "1.2449"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-1238.9118"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("1238.9118"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(1, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Account insuranceAccount = this.accountService.getInsuranceAccount(position.getAsset());
    assertThat(insuranceAccount.getBalance())
        .isEqualTo(MarginBigDecimal.valueOf("1001218.67833451"));
    Account liquidatedAccount = this.accountService.get(1L);
    assertThat(liquidatedAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("0"));

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf("1.2449"), insurancePosition.getCurrentQty());
  }

  /*
   Cancel user's order
   Close position in market
   Close position by insurance fund
  */
  @Test
  void useInsurance_when_partiallyCloseByMarketOrder_3() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1264"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("2000"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("1264"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 2
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65012.34", "1.5436");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65012.34", "1.5436");
    Order order12 = this.createOrder(12, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "0.0021");
    Order order13 = this.createOrder(13, 3, OrderSide.BUY, OrderType.LIMIT, "64507.87", "0.2978");
    Order order14 = this.createOrder(14, 3, OrderSide.SELL, OrderType.LIMIT, "65007.87", "0.2987");
    List<Order> orders =
        Arrays.asList(
            order10.deepCopy(),
            order11.deepCopy(),
            order12.deepCopy(),
            order13.deepCopy(),
            order14.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 2, with oracle price rising to 66873.29
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf("66873.29"), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);
    Order processedOrder12 = this.cloneOrder(order12, "0.0021", OrderStatus.ACTIVE);
    Order processedOrder13 = this.cloneOrder(order13, "0.2978", OrderStatus.ACTIVE);

    // LiquidationService submit a liquidation IOC order,
    // to close account 2 position as much as possible
    // This liquidation order is partially filled by order 14
    Order order1 = this.createOrder(1, 2, OrderSide.BUY, OrderType.LIMIT, "65012.3399", "1.5436");
    order1 = this.cloneOrder(order1, "1.2449", OrderStatus.CANCELED);
    Order processedOrder14 = this.cloneOrder(order14, "0", OrderStatus.FILLED);

    // Since account 2 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 =
        this.createOrder(2, 2, OrderSide.BUY, OrderType.LIMIT, "65012.33994698", "1.2449");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    // New funding order
    Order order3 =
        this.createOrder(
            3, this.insuranceId, OrderSide.SELL, OrderType.LIMIT, "65012.33994698", "1.2449");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    List<Order> processedOrders =
        Arrays.asList(
            order1,
            order2,
            order3,
            processedOrder10,
            processedOrder11,
            processedOrder12,
            processedOrder13,
            processedOrder14);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65012.34", "1.5436"),
            this.createTrade(2, order1, order14, "65007.87", "0.2987"),
            this.createTrade(3, order2, order3, "65012.33994698", "1.2449"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                2,
                MarginBigDecimal.valueOf("-1924.7353"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("1924.7353"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(2, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Account insuranceAccount = this.accountService.getInsuranceAccount(position.getAsset());
    assertThat(insuranceAccount.getBalance())
        .isEqualTo(MarginBigDecimal.valueOf("1001904.50183451"));
    Account liquidatedAccount = this.accountService.get(2L);
    assertThat(liquidatedAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("0"));

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf("-1.2449"), insurancePosition.getCurrentQty());
  }

  /*
   Insufficient insurance fund
  */

  @Test
  void autoDeleverage_when_insufficientInsuranceFund() {
    System.setProperty("adl.enabled", "true");

    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(4, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(6, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("10"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order12 = this.createOrder(12, 3, OrderSide.BUY, OrderType.LIMIT, "70000", "0.5");
    Order order13 = this.createOrder(13, 4, OrderSide.SELL, OrderType.LIMIT, "70000", "0.5");
    Order order14 = this.createOrder(14, 5, OrderSide.BUY, OrderType.LIMIT, "69000", "0.3");
    Order order15 = this.createOrder(15, 6, OrderSide.SELL, OrderType.LIMIT, "69000", "0.3");
    Order order16 = this.createOrder(16, 4, OrderSide.SELL, OrderType.LIMIT, "65000", "0.01");
    Order order17 =
        this.createStopOrder(
            17,
            4,
            OrderSide.BUY,
            TPSLType.TAKE_PROFIT_MARKET,
            null,
            "0.2",
            OrderTrigger.ORACLE,
            "62000",
            TriggerCondition.LT);
    List<Order> orders =
        Arrays.asList(
            order10.deepCopy(),
            order11.deepCopy(),
            order12.deepCopy(),
            order13.deepCopy(),
            order14.deepCopy(),
            order15.deepCopy(),
            order16.deepCopy(),
            order17.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);
    Order processedOrder12 = this.cloneOrder(order12, "0", OrderStatus.FILLED);
    Order processedOrder13 = this.cloneOrder(order13, "0", OrderStatus.FILLED);
    Order processedOrder14 = this.cloneOrder(order14, "0", OrderStatus.FILLED);
    Order processedOrder15 = this.cloneOrder(order15, "0", OrderStatus.FILLED);
    Order processedOrder16 = this.cloneOrder(order16, "0.01", OrderStatus.CANCELED);
    Order processedOrder17 = this.cloneOrder(order17, "0.2", OrderStatus.CANCELED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // However, there are no match, so it's cancelled immediately
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order, this order will be cancelled because not enough insurance fund
    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order2 = this.cloneOrder(order2, "1", OrderStatus.CANCELED);
    // New insurance order, but will be cancelled due to insufficient fund
    Order order3 =
        this.createOrder(3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    order3 = this.cloneOrder(order3, "1", OrderStatus.CANCELED);
    order3.setMarginMode(MarginMode.ISOLATE);

    // ADL of position 4
    Order order4 = this.createOrder(4, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "0.5");
    order4 = this.cloneOrder(order4, "0", OrderStatus.FILLED);
    Order order5 = this.createOrder(5, 4, OrderSide.BUY, OrderType.LIMIT, "65000", "0.5");
    order5 = this.cloneOrder(order5, "0", OrderStatus.FILLED);

    // ADL of position 6
    Order order6 = this.createOrder(6, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "0.3");
    order6 = this.cloneOrder(order6, "0", OrderStatus.FILLED);
    Order order7 = this.createOrder(7, 6, OrderSide.BUY, OrderType.LIMIT, "65000", "0.3");
    order7 = this.cloneOrder(order7, "0", OrderStatus.FILLED);

    // ADL of position 2
    Order order8 = this.createOrder(8, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "0.2");
    order8 = this.cloneOrder(order8, "0", OrderStatus.FILLED);
    Order order9 = this.createOrder(9, 2, OrderSide.BUY, OrderType.LIMIT, "65000", "0.2");
    order9 = this.cloneOrder(order9, "0", OrderStatus.FILLED);

    List<Order> processedOrders =
        Arrays.asList(
            order1,
            order2,
            order3,
            order4,
            order5,
            order6,
            order7,
            order8,
            order9,
            processedOrder10,
            processedOrder11,
            processedOrder12,
            processedOrder13,
            processedOrder14,
            processedOrder15,
            processedOrder16,
            processedOrder17);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65000", "1"),
            this.createTrade(2, processedOrder12, processedOrder13, "70000", "0.5"),
            this.createTrade(3, processedOrder14, processedOrder15, "69000", "0.3"),
            this.createTrade(4, order4, order5, "65000", "0.5"),
            this.createTrade(5, order6, order7, "65000", "0.3"),
            this.createTrade(6, order8, order9, "65000", "0.2"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Account acc4 = accountService.get(4L);
    assertThat(acc4.getBalance()).isEqualTo(MarginBigDecimal.valueOf("3465.625"));
    Position position4 = positionService.get(4L, defaultSymbol);
    assertThat(position4.getCurrentQty()).isEqualTo(MarginBigDecimal.ZERO);

    Account acc6 = accountService.get(6L);
    assertThat(acc6.getBalance()).isEqualTo(MarginBigDecimal.valueOf("2179.6"));
    Position position6 = positionService.get(6L, defaultSymbol);
    assertThat(position6.getCurrentQty()).isEqualTo(MarginBigDecimal.ZERO);

    Account acc2 = accountService.get(2L);
    assertThat(acc2.getBalance()).isEqualTo(MarginBigDecimal.valueOf("948"));
    Position position2 = positionService.get(2L, defaultSymbol);
    assertThat(position2.getCurrentQty()).isEqualTo(MarginBigDecimal.valueOf("-0.8"));
  }

  /*
   Cancel user's order
   Close position in market
  */
  @Test
  void notCancelTpSlOrderBeforeInsurance_when_liquidate() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("3000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order12 = this.createOrder(12, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order12.setTpSLType(TPSLType.STOP_LIMIT);
    order12.setTpSLPrice(MarginBigDecimal.valueOf(60000));
    order12.setTrigger(OrderTrigger.ORACLE);
    order12.setStopCondition(TriggerCondition.LT);
    Order order13 = this.createOrder(13, 3, OrderSide.BUY, OrderType.LIMIT, "65000", "1");

    List<Order> orders =
        Arrays.asList(
            order10.deepCopy(), order11.deepCopy(), order12.deepCopy(), order13.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);
    // Order12 is cancelled due to liquidation
    Order processedOrder12 = this.cloneOrder(order12, "1", OrderStatus.UNTRIGGERED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // This liquidation order is fully filled by order 13
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder13 = this.cloneOrder(order13, "0", OrderStatus.FILLED);

    List<Order> processedOrders =
        Arrays.asList(
            order1, processedOrder10, processedOrder11, processedOrder12, processedOrder13);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65000", "1"),
            this.createTrade(2, order1, order13, "65000", "1"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("983.75"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(1, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Account insuranceAccount = this.accountService.getInsuranceAccount(position.getAsset());
    assertThat(insuranceAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("1000983.75"));

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    assertThat(insurancePosition).isNull();
  }

  @Test
  void notChangingPositionMarginMode_when_liquidating() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65500", "0.2");
    order10.setMarginMode(MarginMode.ISOLATE);
    order10.setLeverage(MarginBigDecimal.valueOf(100));
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "0.2");
    Order order12 = this.createOrder(12, 3, OrderSide.BUY, OrderType.LIMIT, "64500", "0.5");
    List<Order> orders = Arrays.asList(order10.deepCopy(), order11.deepCopy(), order12.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    commands.add(
        new Command(
            CommandCode.ADJUST_MARGIN_POSITION,
            new AdjustMarginPosition(1L, 1L, defaultSymbol, MarginBigDecimal.ONE, null)));

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "64840", "0.2");
    order1 = this.cloneOrder(order1, "0.2", OrderStatus.CANCELED);
    order1.setMarginMode(MarginMode.ISOLATE);
    Order processedOrder12 = this.cloneOrder(order12, "0.5", OrderStatus.ACTIVE);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "64840", "0.2");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    order2.setMarginMode(MarginMode.ISOLATE);
    // New funding order
    Order order3 =
        this.createOrder(3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "64840", "0.2");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    List<Order> processedOrders =
        Arrays.asList(order1, order2, order3, processedOrder10, processedOrder11, processedOrder12);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65500", "0.2"),
            this.createTrade(2, order2, order3, "64840", "0.2"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-260.408"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("260.408"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Account account = accountService.get(1L);
    assertThat(account.getBalance()).isEqualTo(MarginBigDecimal.valueOf("594.591"));

    Position account1Position = positionService.get(1, defaultSymbol);
    assertThat(account1Position.isCross()).isFalse();
    assertThat(account1Position.getCurrentQty()).isEqualTo(MarginBigDecimal.ZERO);
    assertThat(account1Position.getAdjustMargin()).isEqualTo(MarginBigDecimal.ZERO);
  }

  @Test
  void collectLeftOverMargin_when_liquidatingIsolatedPosition() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65500", "0.2");
    order10.setMarginMode(MarginMode.ISOLATE);
    order10.setLeverage(MarginBigDecimal.valueOf(100));
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "0.2");
    Order order12 = this.createOrder(12, 3, OrderSide.BUY, OrderType.LIMIT, "65000", "0.1");
    List<Order> orders = Arrays.asList(order10.deepCopy(), order11.deepCopy(), order12.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    commands.add(
        new Command(
            CommandCode.ADJUST_MARGIN_POSITION,
            new AdjustMarginPosition(1L, 1L, defaultSymbol, MarginBigDecimal.valueOf(100), null)));

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    // Will be partially filled by order 12
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "64345", "0.2");
    order1 = this.cloneOrder(order1, "0.1", OrderStatus.CANCELED);
    order1.setMarginMode(MarginMode.ISOLATE);
    Order processedOrder12 = this.cloneOrder(order12, "0.0", OrderStatus.FILLED);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "64345", "0.1");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    order2.setMarginMode(MarginMode.ISOLATE);
    // New funding order
    Order order3 =
        this.createOrder(3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "64345", "0.1");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    List<Order> processedOrders =
        Arrays.asList(order1, order2, order3, processedOrder10, processedOrder11, processedOrder12);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65500", "0.2"),
            this.createTrade(2, order1, order12, "65000", "0.1"),
            this.createTrade(3, order2, order3, "64345", "0.1"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-323.928"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("323.928"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Account account = accountService.get(1L);
    assertThat(account.getBalance()).isEqualTo(MarginBigDecimal.valueOf("497.596125"));

    Position account1Position = positionService.get(1, defaultSymbol);
    assertThat(account1Position.isCross()).isFalse();
    assertThat(account1Position.getCurrentQty()).isEqualTo(MarginBigDecimal.ZERO);
    assertThat(account1Position.getAdjustMargin()).isEqualTo(MarginBigDecimal.ZERO);
  }

  @Test
  void shouldChainLiquidate_when_dangerCrossMargin() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(3, MarginBigDecimal.valueOf("1000"));
    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("1000000"));

    List<Command> commands = new ArrayList<>();

    // Set up the initial position for account 1
    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "0.75");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000", "0.75");
    Order order12 =
        this.createOrder(12, 1, OrderSide.BUY, OrderType.LIMIT, "32000", "0.5", "ETHUSD");
    Order order13 =
        this.createOrder(13, 3, OrderSide.SELL, OrderType.LIMIT, "32000", "0.5", "ETHUSD");

    List<Order> orders =
        Arrays.asList(
            order10.deepCopy(), order11.deepCopy(), order12.deepCopy(), order13.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    // Trigger a liquidation on account 1, with oracle price falling to 64000
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(64000), MarginBigDecimal.ZERO)));

    Order processedOrder10 = this.cloneOrder(order10, "0", OrderStatus.FILLED);
    Order processedOrder11 = this.cloneOrder(order11, "0", OrderStatus.FILLED);
    Order processedOrder12 = this.cloneOrder(order12, "0", OrderStatus.FILLED);
    Order processedOrder13 = this.cloneOrder(order13, "0", OrderStatus.FILLED);

    // LiquidationService submit a liquidation IOC order,
    // to close account 1 position as much as possible
    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "64968.844042", "0.75");
    order1 = this.cloneOrder(order1, "0.75", OrderStatus.CANCELED);

    // Since account 1 position is still open, now insurance will submit 2 orders to close it
    // New liquidation order
    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "64968.844042", "0.75");
    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    // New funding order
    Order order3 =
        this.createOrder(
            3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT, "64968.844042", "0.75");
    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    order3.setMarginMode(MarginMode.ISOLATE);

    // Cascading liquidation to ETHUSD position
    Order order4 =
        this.createOrder(4, 1, OrderSide.SELL, OrderType.LIMIT, "32000", "0.5", "ETHUSD");
    order4 = this.cloneOrder(order4, "0.5", OrderStatus.CANCELED);
    Order order5 =
        this.createOrder(5, 1, OrderSide.SELL, OrderType.LIMIT, "32000", "0.5", "ETHUSD");
    order5 = this.cloneOrder(order5, "0", OrderStatus.FILLED);
    Order order6 =
        this.createOrder(6, insuranceId, OrderSide.BUY, OrderType.LIMIT, "32000", "0.5", "ETHUSD");
    order6 = this.cloneOrder(order6, "0", OrderStatus.FILLED);
    order6.setMarginMode(MarginMode.ISOLATE);
    List<Order> processedOrders =
        Arrays.asList(
            order1,
            order2,
            order3,
            order4,
            order5,
            order6,
            processedOrder10,
            processedOrder11,
            processedOrder12,
            processedOrder13);

    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder11, processedOrder10, "65000", "0.75"),
            this.createTrade(2, processedOrder13, processedOrder12, "32000", "0.5"),
            this.createTrade(3, order2, order3, "64968.844042", "0.75"),
            this.createTrade(4, order5, order6, "32000", "0.5"));

    List<Transaction> transactions =
        List.of(
            createTransaction(
                1,
                MarginBigDecimal.valueOf("-960.4455315"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("960.4455315"),
                Asset.USDT,
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                1,
                MarginBigDecimal.valueOf("0"),
                Asset.USDT,
                "ETHUSD",
                TransactionType.LIQUIDATION_CLEARANCE),
            createTransaction(
                insuranceId,
                MarginBigDecimal.valueOf("0"),
                Asset.USDT,
                "ETHUSD",
                TransactionType.LIQUIDATION_CLEARANCE));
    this.testLiquidation(commands, processedOrders, trades, transactions);

    Position position = this.positionService.get(1, this.defaultSymbol);
    Assertions.assertEquals(MarginBigDecimal.valueOf(0), position.getCurrentQty());
    Assertions.assertEquals(0, position.getLiquidationProgress());

    Account insuranceAccount = this.accountService.getInsuranceAccount(position.getAsset());
    assertThat(insuranceAccount.getBalance())
        .isEqualTo(MarginBigDecimal.valueOf("1000944.26387325"));
    Account liquidatedAccount = this.accountService.get(1L);
    assertThat(liquidatedAccount.getBalance()).isEqualTo(MarginBigDecimal.valueOf("0"));

    Position insurancePosition = this.positionService.get(this.insuranceId, this.defaultSymbol);
    assertThat(insurancePosition.getCurrentQty()).isEqualTo(MarginBigDecimal.valueOf("0.75"));

    Position insuranceEthPosition = this.positionService.get(this.insuranceId, "ETHUSD");
    assertThat(insuranceEthPosition.getCurrentQty()).isEqualTo(MarginBigDecimal.valueOf("0.5"));
  }

  private Transaction createTransaction(
      long accountId, MarginBigDecimal amount, Asset asset, TransactionType transactionType) {
    return createTransaction(accountId, amount, asset, defaultSymbol, transactionType);
  }

  private Transaction createTransaction(
      long accountId,
      MarginBigDecimal amount,
      Asset asset,
      String symbol,
      TransactionType transactionType) {
    Transaction transaction = new Transaction();
    transaction.setAccountId(accountId);
    transaction.setAmount(amount);
    transaction.setSymbol(symbol);
    transaction.setType(transactionType);
    transaction.setStatus(TransactionStatus.CONFIRMED);
    transaction.setAsset(asset);
    return transaction;
  }

  /*
   Fix bugs
  */
  //  @Test
  //  void test10() {
  //    this.setUpAccount(this.insuranceId, MarginBigDecimal.valueOf("35000"));
  //    Account account = new Account();
  //    account.setId(1002L);
  //    account.setBalance(MarginBigDecimal.valueOf("22992.738364"));
  //    account.setUnrealisedPnl(MarginBigDecimal.valueOf("30054.872703"));
  //    account.setCrossBalance(MarginBigDecimal.valueOf("22247.490899"));
  //    account.setCrossEquity(MarginBigDecimal.valueOf("52302.363602"));
  //    account.setCrossMargin(MarginBigDecimal.valueOf("188.725805"));
  //    account.setOrderMargin(MarginBigDecimal.valueOf("745.247465"));
  //    account.setAvailableBalance(MarginBigDecimal.valueOf("22058.765094"));
  //    account.setMaxAvailableBalance(MarginBigDecimal.valueOf("22058.765094"));
  //    this.accountService.update(account);
  //
  //    Position position = new Position();
  //    position.setId(4L);
  //    position.setAccountId(1002L);
  //    position.setSymbol(this.defaultSymbol);
  //    position.setLeverage(MarginBigDecimal.valueOf("50"));
  //    position.setUnrealisedPnl(MarginBigDecimal.valueOf("-53990.99231"));
  //    position.setCurrentQty(MarginBigDecimal.valueOf("-20.005"));
  //    position.setRiskLimit(MarginBigDecimal.valueOf("100000000"));
  //    position.setRiskValue(MarginBigDecimal.valueOf("0"));
  //    position.setInitMargin(MarginBigDecimal.valueOf("65.244962"));
  //    position.setMaintainMargin(MarginBigDecimal.valueOf("32.95187"));
  //    position.setExtraMargin(MarginBigDecimal.valueOf("0"));
  //    position.setRequiredInitMarginPercent(MarginBigDecimal.valueOf("0.02"));
  //    position.setRequiredMaintainMarginPercent(MarginBigDecimal.valueOf("0.01"));
  //    position.setLiquidationPrice(MarginBigDecimal.valueOf("1268.706472"));
  //    position.setBankruptPrice(MarginBigDecimal.valueOf("1268.706472"));
  //    position.setEntryPrice(MarginBigDecimal.valueOf("161.425103"));
  //    position.setEntryValue(MarginBigDecimal.valueOf("-3229.30919"));
  //    position.setOpenOrderBuyQty(MarginBigDecimal.valueOf("29.412"));
  //    position.setOpenOrderSellQty(MarginBigDecimal.valueOf("7.754"));
  //    position.setOpenOrderBuyValue(MarginBigDecimal.valueOf("52414.49716"));
  //    position.setOpenOrderSellValue(MarginBigDecimal.valueOf("22204.97335"));
  //    position.setOpenOrderValue(MarginBigDecimal.valueOf("25150.749353"));
  //    position.setOpenOrderMargin(MarginBigDecimal.valueOf("513.175889"));
  //    position.setLiquidationProgress(0);
  //    position.setMaxLiquidationBalance(MarginBigDecimal.valueOf("22992.738364"));
  //    position.setMultiplier(MarginBigDecimal.valueOf("50"));
  //    position.setRealisedPnl(MarginBigDecimal.valueOf("0"));
  //    position.setLatestRealisedPnl(MarginBigDecimal.valueOf("0"));
  //    position.setNetFunding(MarginBigDecimal.valueOf("0"));
  //    position.setPnlRanking(MarginBigDecimal.valueOf("0"));
  //    this.positionService.update(position);
  //    this.positionService.commit();
  //
  //    List<Command> commands = new ArrayList<>();
  //    commands.add(new Command(CommandCode.LIQUIDATE,
  //        new InstrumentExtraInformation(this.defaultSymbol, MarginBigDecimal.valueOf("1266.39"),
  //            MarginBigDecimal.ZERO)));
  //
  //    // close position in market
  //    Order order1 = this.createOrder(1, 1, OrderSide.SELL, OrderType.LIMIT, "64064.3", "1");
  //    order1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
  //
  //    // close position by insurance fund
  //    Order order2 = this.createOrder(2, 1, OrderSide.SELL, OrderType.LIMIT, "64064.3", "1");
  //    order2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
  //
  //    // insurance fund order
  //    Order order3 = this.createOrder(3, this.insuranceId, OrderSide.BUY, OrderType.LIMIT,
  // "64064.3", "1");
  //    order3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
  //    List<Order> processedOrders = Arrays.asList(order1, order2, order3);
  //
  //    List<Trade> trades = Arrays.asList();
  //
  //    this.testLiquidation(commands, processedOrders, trades);
  //  }
}

package com.sotatek.future;

import static org.junit.jupiter.api.Assertions.assertEquals;

import com.sotatek.future.entity.AdjustLeverage;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.entity.Transaction;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.enums.MarginMode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TransactionStatus;
import com.sotatek.future.output.ListOutputStream;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.stream.Collectors;
import org.junit.jupiter.api.Test;

class MatchingEngineLimitTest extends BaseMatchingEngineTest {

  @Test
  void notFillOrder_when_notMatchPrice() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "66000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "66000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderFully_when_matchPriceAndQuantity() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "1838.24", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "1838.24", "1");
    order1.setMarginMode(MarginMode.ISOLATE);
    order2.setMarginMode(MarginMode.ISOLATE);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades =
        Arrays.asList(this.createTrade(1, processedOrder2, processedOrder1, "1838.24", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "1838.24", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "1838.24", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillBuyOrderPartially_when_matchPriceNotQuantity() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "2");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades =
        Arrays.asList(this.createTrade(1, processedOrder2, processedOrder1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "2"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillSellOrderPartially_when_matchPriceNotQuantity() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "2");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades =
        Arrays.asList(this.createTrade(1, processedOrder2, processedOrder1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillMultipleOrder_when_priceMatch_given_largerOrderArriveFirst() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "3");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder2, processedOrder1, "65000", "1"),
            this.createTrade(2, processedOrder3, processedOrder1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "3"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillMultipleOrder_when_priceMatch_given_smallOrderArriveFirst() {
    Order order1 = this.createOrder(1, 10, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 11, OrderSide.BUY, OrderType.LIMIT, "65000", "3");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder3, processedOrder1, "65000", "1"),
            this.createTrade(2, processedOrder3, processedOrder2, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderByDescendingPrice_when_matchMultipleBuyOrder() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.BUY, OrderType.LIMIT, "66000", "1");
    Order order3 = this.createOrder(3, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades =
        Arrays.asList(this.createTrade(1, processedOrder3, processedOrder2, "66000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderByAscendingPrice_when_matchMultipleSellOrder() {
    Order order1 = this.createOrder(1, 10, OrderSide.SELL, OrderType.LIMIT, "66000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 11, OrderSide.BUY, OrderType.LIMIT, "66000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades =
        Arrays.asList(this.createTrade(1, processedOrder3, processedOrder2, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.SELL, "66000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderByProcessingOrder_when_matchMultipleSellOrderWithSamePrice() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "3");
    Order order2 = this.createOrder(2, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "3");
    Order order3 = this.createOrder(3, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order4 = this.createOrder(4, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Order> orders =
        Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy(), order4.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "3", OrderStatus.ACTIVE);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    Order processedOrder4 = this.cloneOrder(order4, "0", OrderStatus.FILLED);
    List<Order> processedOrders =
        Arrays.asList(processedOrder1, processedOrder2, processedOrder3, processedOrder4);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder3, processedOrder1, "65000", "1"),
            this.createTrade(2, processedOrder4, processedOrder1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "3"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "3"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderFromMultipleAccount_when_priceMatch() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 12, OrderSide.SELL, OrderType.LIMIT, "65000", "3");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder3, processedOrder1, "65000", "1"),
            this.createTrade(2, processedOrder3, processedOrder2, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void cancelOrder_when_notEnoughBalance() {
    int accountId = 10;
    this.setUpAccount(accountId, MarginBigDecimal.ONE);
    Order order1 = this.createOrder(1, accountId, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs = Arrays.asList();

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderCalcPositionMarginAfterChangeLeverageUsdM() {
    Order order1 = this.createOrder(1, 10, OrderSide.SELL, OrderType.LIMIT, "25000", "0.5");
    Order order2 = this.createOrder(2, 11, OrderSide.BUY, OrderType.LIMIT, "25000", "0.5");
    order1.setMarginMode(MarginMode.ISOLATE);
    order2.setMarginMode(MarginMode.ISOLATE);
    order1.setLeverage(MarginBigDecimal.valueOf("10"));

    List<Command> commands = new ArrayList<>();
    commands.add(new Command(CommandCode.PLACE_ORDER, order1.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order2.deepCopy()));

    AdjustLeverage adjustLeverageCommand = new AdjustLeverage();
    adjustLeverageCommand.setAccountId(10l);
    adjustLeverageCommand.setLeverage(MarginBigDecimal.valueOf("20"));
    adjustLeverageCommand.setOldLeverage(MarginBigDecimal.valueOf("10"));
    adjustLeverageCommand.setSymbol(defaultSymbol);

    adjustLeverageCommand.setLeverage(MarginBigDecimal.valueOf("20"));
    commands.add(
        new Command(CommandCode.ADJUST_LEVERAGE, adjustLeverageCommand)
    );

    Order order3 = this.createOrder(3, 10, OrderSide.SELL, OrderType.LIMIT, "26000", "1.5");
    Order order4 = this.createOrder(4, 11, OrderSide.BUY, OrderType.LIMIT, "26000", "1.5");
    order3.setMarginMode(MarginMode.ISOLATE);
    order4.setMarginMode(MarginMode.ISOLATE);
    order3.setLeverage(MarginBigDecimal.valueOf("20"));
    commands.add(new Command(CommandCode.PLACE_ORDER, order3.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order4.deepCopy()));

    Order order5 = this.createOrder(5, 10, OrderSide.BUY, OrderType.LIMIT, "27000", "0.3");
    Order order6 = this.createOrder(6, 11, OrderSide.SELL, OrderType.LIMIT, "27000", "0.3");
    order5.setMarginMode(MarginMode.ISOLATE);
    order6.setMarginMode(MarginMode.ISOLATE);
    order5.setLeverage(MarginBigDecimal.valueOf("20"));
    commands.add(new Command(CommandCode.PLACE_ORDER, order5.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order6.deepCopy()));

    Order order7 = this.createOrder(7, 10, OrderSide.BUY, OrderType.LIMIT, "27100", "2.7");
    Order order8 = this.createOrder(8, 11, OrderSide.SELL, OrderType.LIMIT, "21000", "2.7");
    order7.setMarginMode(MarginMode.ISOLATE);
    order8.setMarginMode(MarginMode.ISOLATE);
    order7.setLeverage(MarginBigDecimal.valueOf("20"));
    commands.add(new Command(CommandCode.PLACE_ORDER, order7.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order8.deepCopy()));


    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);

    Position position = commandOutputStream.getData().get(4).getPositions().stream().filter(p -> p.getAccountId() == 10).collect(
        Collectors.toList()).get(0);
    assertEquals(MarginBigDecimal.valueOf("3200"), position.getPositionMargin(), "position margin increase size");

    position = commandOutputStream.getData().get(6).getPositions().stream().filter(p -> p.getAccountId() == 10).collect(
        Collectors.toList()).get(0);
    assertEquals(MarginBigDecimal.valueOf("2720"), position.getPositionMargin(), "position margin decrease size (not change side)");

    position = commandOutputStream.getData().get(8).getPositions().stream().filter(p -> p.getAccountId() == 10).collect(
        Collectors.toList()).get(0);
    assertEquals(MarginBigDecimal.valueOf("1355"), position.getPositionMargin(), "position margin decrease size (change side)");

  }

  @Test
  void fillOrderCalcPositionMarginAfterChangeLeverageCoinM() {
    Order order1 = this.createOrder(1, 10, OrderSide.SELL, OrderType.LIMIT, "1345", "1", defaultSymbolCoinM);
    Order order2 = this.createOrder(2, 11, OrderSide.BUY, OrderType.LIMIT, "1345", "1", defaultSymbolCoinM);
    order1.setMarginMode(MarginMode.ISOLATE);
    order2.setMarginMode(MarginMode.ISOLATE);
    order1.setContractType(ContractType.COIN_M);
    order2.setContractType(ContractType.COIN_M);
    order1.setLeverage(MarginBigDecimal.valueOf("10"));

    List<Command> commands = new ArrayList<>();
    commands.add(new Command(CommandCode.PLACE_ORDER, order1.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order2.deepCopy()));

    AdjustLeverage adjustLeverageCommand = new AdjustLeverage();
    adjustLeverageCommand.setAccountId(10l);
    adjustLeverageCommand.setLeverage(MarginBigDecimal.valueOf("20"));
    adjustLeverageCommand.setOldLeverage(MarginBigDecimal.valueOf("10"));
    adjustLeverageCommand.setSymbol(defaultSymbolCoinM);

    adjustLeverageCommand.setLeverage(MarginBigDecimal.valueOf("20"));
    commands.add(
        new Command(CommandCode.ADJUST_LEVERAGE, adjustLeverageCommand)
    );

    Order order3 = this.createOrder(3, 10, OrderSide.SELL, OrderType.LIMIT, "1350", "2", defaultSymbolCoinM);
    Order order4 = this.createOrder(4, 11, OrderSide.BUY, OrderType.LIMIT, "1350", "2", defaultSymbolCoinM);
    order3.setMarginMode(MarginMode.ISOLATE);
    order4.setMarginMode(MarginMode.ISOLATE);
    order3.setContractType(ContractType.COIN_M);
    order4.setContractType(ContractType.COIN_M);
    order3.setLeverage(MarginBigDecimal.valueOf("20"));
    commands.add(new Command(CommandCode.PLACE_ORDER, order3.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order4.deepCopy()));


    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);

    Position position = commandOutputStream.getData().get(4).getPositions().stream().filter(p -> p.getAccountId() == 10).collect(
        Collectors.toList()).get(0);
    assertEquals(MarginBigDecimal.valueOf("0.0148423"), position.getPositionMargin());
  }

  @Test
  public void testCrossDepositChangeLiquidatePrice() {
    Order order1 = this.createOrder(1, 10, OrderSide.SELL, OrderType.LIMIT, "25000", "0.5", defaultSymbolCoinM);
    Order order2 = this.createOrder(2, 11, OrderSide.BUY, OrderType.LIMIT, "25000", "0.5", defaultSymbolCoinM);
    order1.setContractType(ContractType.COIN_M);
    order2.setContractType(ContractType.COIN_M);

    List<Command> commands = new ArrayList<>();
    commands.add(new Command(CommandCode.PLACE_ORDER, order1.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order2.deepCopy()));

    //deposit
    Transaction transaction = new Transaction();
    transaction.setAccountId(10l);
    transaction.setAmount(MarginBigDecimal.valueOf("100"));
    transaction.setStatus(TransactionStatus.PENDING);
    commands.add(new Command(CommandCode.DEPOSIT, transaction));

    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);

    System.out.println();
  }

  @Test
  public void testCrossWithdrawChangeLiquidatePrice() {
    Order order1 = this.createOrder(1, 10, OrderSide.SELL, OrderType.LIMIT, "25000", "0.5", defaultSymbolCoinM);
    Order order2 = this.createOrder(2, 11, OrderSide.BUY, OrderType.LIMIT, "25000", "0.5", defaultSymbolCoinM);
    order1.setContractType(ContractType.COIN_M);
    order2.setContractType(ContractType.COIN_M);

    List<Command> commands = new ArrayList<>();
    commands.add(new Command(CommandCode.PLACE_ORDER, order1.deepCopy()));
    commands.add(new Command(CommandCode.PLACE_ORDER, order2.deepCopy()));

    //deposit
    Transaction transaction = new Transaction();
    transaction.setAccountId(10l);
    transaction.setAmount(MarginBigDecimal.valueOf("100"));
    transaction.setStatus(TransactionStatus.PENDING);
    commands.add(new Command(CommandCode.WITHDRAW, transaction));

    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);

    System.out.println();
  }
}

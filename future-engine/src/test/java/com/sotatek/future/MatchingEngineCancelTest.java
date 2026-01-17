package com.sotatek.future;

import static org.junit.jupiter.api.Assertions.assertEquals;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderTrigger;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TPSLType;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Arrays;
import java.util.Collections;
import java.util.List;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

public class MatchingEngineCancelTest extends BaseMatchingEngineTest {

  @Test
  void notFillOrder_when_cancel() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order1.deepCopy()),
            new Command(CommandCode.PLACE_ORDER, order2.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);

    Account account = AccountService.getInstance().get(order1.getAccountId());
    //    assertEquals(this.defaultBalance, account.getUsdAvailableBalance());
  }

  @Test
  void fillOrder_when_notCancel() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "64000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 10, OrderSide.BUY, OrderType.LIMIT, "66000", "1");
    Order order4 = this.createOrder(4, 11, OrderSide.SELL, OrderType.MARKET, null, "4");
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1.deepCopy()),
            new Command(CommandCode.PLACE_ORDER, order2.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order2.deepCopy()),
            new Command(CommandCode.PLACE_ORDER, order3.deepCopy()),
            new Command(CommandCode.PLACE_ORDER, order4.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.CANCELED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    Order processedOrder4 = this.cloneOrder(order4, "2", OrderStatus.CANCELED);
    List<Order> processedOrders =
        Arrays.asList(processedOrder1, processedOrder2, processedOrder3, processedOrder4);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder4, processedOrder3, "66000", "1"),
            this.createTrade(2, processedOrder4, processedOrder1, "64000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "64000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "-1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void ignoreAndCancelUnknownOrder_when_cancel() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "64000", "1");
    List<Command> commands =
        Arrays.asList(new Command(CommandCode.CANCEL_ORDER, order1.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs = Collections.emptyList();

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void ignoreAlreadyCancelOrder_when_manualCancel() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "64000", "1");
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order1.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order1.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "64000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "-1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void ignoreAlreadyCancelOrder_when_autoCancel() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.MARKET, null, "1");
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order1.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs = Collections.emptyList();

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void notCancelOrder_when_alreadyFill() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1.deepCopy()),
            new Command(CommandCode.PLACE_ORDER, order2.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order1.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList(this.createTrade(1, order2, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  @Disabled
  @Test
  void updateBalance_when_cancel() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "2");
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1.deepCopy()),
            new Command(CommandCode.PLACE_ORDER, order2.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order2.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList(this.createTrade(1, order2, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "-1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);

    Account account = AccountService.getInstance().get(order2.getAccountId());
    // Fee is charged to USDT balance by default
    //    assertEquals(MarginBigDecimal.valueOf("99252.0125"), account.getUsdtAvailableBalance());
  }

  @Disabled
  @Test
  void updateBalance_when_cancel_2() {
    Order order1 = this.createOrder(1, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 2, OrderSide.SELL, OrderType.MARKET, null, "2");
    order2.setTpSLType(TPSLType.STOP_MARKET);
    order2.setTrigger(OrderTrigger.ORACLE);
    order2.setTpSLPrice(MarginBigDecimal.valueOf("65000"));
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1.deepCopy()),
            new Command(CommandCode.PLACE_ORDER, order2.deepCopy()),
            new Command(CommandCode.CANCEL_ORDER, order2.deepCopy()));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "2", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.BUY, "65000", "1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);

    Account account = AccountService.getInstance().get(order2.getAccountId());

    // Fee is charged to USDT balance by default
    assertEquals(MarginBigDecimal.valueOf("0"), account.getBalance());
  }
}

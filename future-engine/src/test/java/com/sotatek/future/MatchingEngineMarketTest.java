package com.sotatek.future;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Arrays;
import java.util.List;
import org.junit.jupiter.api.Test;

public class MatchingEngineMarketTest extends BaseMatchingEngineTest {

  @Test
  void cancelMarketSellOrder_when_notFillImmediately() {
    int accountId = 10;
    Order order1 = this.createOrder(1, accountId++, OrderSide.SELL, OrderType.MARKET, null, "1");
    Order order2 = this.createOrder(2, accountId++, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.BUY, "65000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrder_when_matchMultipleOrders() {
    int accountId = 10;
    Order order1 = this.createOrder(1, accountId++, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, accountId++, OrderSide.BUY, OrderType.LIMIT, "66000", "2");
    Order order3 = this.createOrder(3, accountId, OrderSide.SELL, OrderType.MARKET, null, "3");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder3, processedOrder2, "66000", "2"),
            this.createTrade(2, processedOrder3, processedOrder1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "2"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "-2"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderByDescendingPrice_when_matchMultipleBuyOrders() {
    int accountId = 10;
    Order order1 = this.createOrder(1, accountId++, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, accountId++, OrderSide.BUY, OrderType.LIMIT, "66000", "2");
    Order order3 = this.createOrder(3, accountId++, OrderSide.BUY, OrderType.LIMIT, "64000", "3");
    Order order4 = this.createOrder(4, accountId, OrderSide.SELL, OrderType.MARKET, null, "4");
    List<Order> orders =
        Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy(), order4.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "2", OrderStatus.ACTIVE);
    Order processedOrder4 = this.cloneOrder(order4, "0", OrderStatus.FILLED);
    List<Order> processedOrders =
        Arrays.asList(processedOrder1, processedOrder2, processedOrder3, processedOrder4);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder4, processedOrder2, "66000", "2"),
            this.createTrade(2, processedOrder4, processedOrder1, "65000", "1"),
            this.createTrade(3, processedOrder4, processedOrder3, "64000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "2"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "3"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "-2"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrderByAscendingPrice_when_matchMultipleSellOrders() {
    int accountId = 10;
    Order order1 = this.createOrder(1, accountId++, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, accountId++, OrderSide.SELL, OrderType.LIMIT, "66000", "2");
    Order order3 = this.createOrder(3, accountId++, OrderSide.SELL, OrderType.LIMIT, "64000", "3");
    Order order4 = this.createOrder(4, accountId++, OrderSide.BUY, OrderType.LIMIT, "60000", "3");
    Order order5 = this.createOrder(5, accountId, OrderSide.BUY, OrderType.MARKET, null, "7");
    List<Order> orders =
        Arrays.asList(
            order1.deepCopy(),
            order2.deepCopy(),
            order3.deepCopy(),
            order4.deepCopy(),
            order5.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    Order processedOrder4 = this.cloneOrder(order4, "3", OrderStatus.ACTIVE);
    Order processedOrder5 = this.cloneOrder(order5, "1", OrderStatus.CANCELED);
    List<Order> processedOrders =
        Arrays.asList(
            processedOrder1, processedOrder2, processedOrder3, processedOrder4, processedOrder5);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder5, processedOrder3, "64000", "3"),
            this.createTrade(2, processedOrder5, processedOrder1, "65000", "1"),
            this.createTrade(3, processedOrder5, processedOrder2, "66000", "2"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "66000", "2"),
            this.createOrderbookOutput(OrderSide.SELL, "64000", "3"),
            this.createOrderbookOutput(OrderSide.BUY, "60000", "3"),
            this.createOrderbookOutput(OrderSide.SELL, "64000", "-3"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "66000", "-2"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrder_when_sellDecimalQuantity() {
    int accountId = 10;
    Order order1 = this.createOrder(1, accountId++, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, accountId, OrderSide.SELL, OrderType.MARKET, null, "0.0133");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0.9867", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades =
        Arrays.asList(this.createTrade(1, processedOrder2, processedOrder1, "65000", "0.0133"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-0.0133"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillOrder_when_buyDecimalQuantity() {
    int accountId = 10;
    Order order1 = this.createOrder(1, accountId++, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, accountId, OrderSide.BUY, OrderType.MARKET, null, "0.1336");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0.8664", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades =
        Arrays.asList(this.createTrade(1, processedOrder2, processedOrder1, "65000", "0.1336"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "-0.1336"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void fillMultipleOrder_when_sellDecimalQuantity() {
    int accountId = 10;
    Order order1 = this.createOrder(1, accountId++, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, accountId++, OrderSide.BUY, OrderType.LIMIT, "66000", "1");
    Order order3 = this.createOrder(3, accountId, OrderSide.SELL, OrderType.MARKET, null, "1.3166");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0.6834", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, processedOrder3, processedOrder2, "66000", "1"),
            this.createTrade(2, processedOrder3, processedOrder1, "65000", "0.3166"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-0.3166"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void cancelSellOrder_when_insufficientBalanceWithMargin() {
    // Price should be high enough in order to allow opening of margin
    String price = "70000";
    String quantity = "2";
    tryCancelSellOrder(price, quantity);
  }

  @Test
  void cancelSellOrder_when_unableToOpenMargin() {
    // Price should be low enough in order to prevent opening of margin
    String price = "25000";
    String quantity = "3.5";
    tryCancelSellOrder(price, quantity);
  }

  private void tryCancelSellOrder(String price, String quantity) {
    int accountId = 10;
    Order order1 =
        this.createOrder(1, accountId++, OrderSide.BUY, OrderType.LIMIT, price, quantity);
    // Reduce account balance, so that it'll not be able to place a SELL order
    this.setUpAccount(accountId, MarginBigDecimal.valueOf(1));
    Order order2 = this.createOrder(2, accountId, OrderSide.SELL, OrderType.MARKET, null, quantity);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, quantity, OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, quantity, OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.BUY, price, quantity));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void cancelBuyOrder_when_insufficientBalanceWithMargin() {
    // Price should be low enough in order to allow opening of margin
    String price = "35000";
    String quantity = "2.1";
    tryCancelBuyOrder(quantity, price);
  }

  @Test
  void cancelBuyOrder_when_unableToOpenMargin() {
    // Price should be high enough in order to prevent opening of margin
    String price = "80000";
    String quantity = "1.1";
    tryCancelBuyOrder(quantity, price);
  }

  private void tryCancelBuyOrder(String quantity, String price) {
    long accountId = 10L;
    Order order1 =
        this.createOrder(1, accountId++, OrderSide.SELL, OrderType.LIMIT, price, quantity);
    this.setUpAccount(accountId, MarginBigDecimal.valueOf(1));
    Order order2 = this.createOrder(2, accountId, OrderSide.BUY, OrderType.MARKET, null, quantity);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, quantity, OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, quantity, OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.SELL, price, quantity));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);

    Account account = this.accountService.get(accountId);
  }
}

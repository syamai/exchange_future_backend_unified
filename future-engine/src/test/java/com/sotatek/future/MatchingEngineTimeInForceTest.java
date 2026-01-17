package com.sotatek.future;

import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TimeInForce;
import java.util.Arrays;
import java.util.List;
import org.junit.jupiter.api.Test;

public class MatchingEngineTimeInForceTest extends BaseMatchingEngineTest {

  @Test
  void shouldCancelBuyLimit_when_notFillImmediately() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    order1.setTimeInForce(TimeInForce.FOK);
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "66000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.SELL, "66000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void shouldFillSellLimit_when_orderMatch() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order2.setTimeInForce(TimeInForce.FOK);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList(this.createTrade(1, order2, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void shouldFillSellLimit_when_multiOrderMatch() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.BUY, OrderType.LIMIT, "64000", "1");
    Order order3 = this.createOrder(3, 10, OrderSide.BUY, OrderType.LIMIT, "66000", "1");
    Order order4 = this.createOrder(4, 11, OrderSide.SELL, OrderType.LIMIT, "63000", "2");
    order4.setTimeInForce(TimeInForce.FOK);
    List<Order> orders =
        Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy(), order4.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    Order processedOrder4 = this.cloneOrder(order4, "0", OrderStatus.FILLED);
    List<Order> processedOrders =
        Arrays.asList(processedOrder1, processedOrder2, processedOrder3, processedOrder4);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, order4, order3, "66000", "1"),
            this.createTrade(2, order4, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void shouldFillSellLimitByDescendingPrice_when_orderMatch() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.BUY, OrderType.LIMIT, "64000", "1");
    Order order3 = this.createOrder(3, 10, OrderSide.BUY, OrderType.LIMIT, "66000", "1");
    Order order4 = this.createOrder(4, 11, OrderSide.SELL, OrderType.LIMIT, "63000", "3");
    order4.setTimeInForce(TimeInForce.FOK);
    List<Order> orders =
        Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy(), order4.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    Order processedOrder4 = this.cloneOrder(order4, "0", OrderStatus.FILLED);
    List<Order> processedOrders =
        Arrays.asList(processedOrder1, processedOrder2, processedOrder3, processedOrder4);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, order4, order3, "66000", "1"),
            this.createTrade(2, order4, order1, "65000", "1"),
            this.createTrade(3, order4, order2, "64000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void shouldFullyCancelSellLimit_when_orderNotFullyMatch() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.BUY, OrderType.LIMIT, "64000", "1");
    Order order3 = this.createOrder(3, 10, OrderSide.BUY, OrderType.LIMIT, "66000", "1");
    Order order4 = this.createOrder(4, 11, OrderSide.SELL, OrderType.LIMIT, "63000", "4");
    order4.setTimeInForce(TimeInForce.FOK);
    List<Order> orders =
        Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy(), order4.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.ACTIVE);
    Order processedOrder4 = this.cloneOrder(order4, "4", OrderStatus.CANCELED);
    List<Order> processedOrders =
        Arrays.asList(processedOrder1, processedOrder2, processedOrder3, processedOrder4);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "64000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "66000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }
}

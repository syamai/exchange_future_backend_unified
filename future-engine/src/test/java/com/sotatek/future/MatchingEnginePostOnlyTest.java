package com.sotatek.future;

import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import java.util.Arrays;
import java.util.List;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

public class MatchingEnginePostOnlyTest extends BaseMatchingEngineTest {

  @Test
  void notFillOrder_when_notMatchPrice() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "66000", "1");
    order2.setPostOnly(true);
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
  void cancelOrder_when_matchPriceWithActiveOrder() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order2.setPostOnly(true);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.BUY, "65000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Disabled("Disable until after merging code cleanup branch")
  @Test
  void activePostOnlyOrder_when_matchWithOrderFromSameAccount() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 10, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    order2.setPostOnly(true);
    Order order3 = this.createOrder(3, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    Order processedOrder3 = this.cloneOrder(order3, "0", OrderStatus.FILLED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades = Arrays.asList(this.createTrade(1, order3, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }
}

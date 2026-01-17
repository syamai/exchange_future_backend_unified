package com.sotatek.future;

import static org.junit.jupiter.api.Assertions.assertEquals;

import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.OrderNote;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Arrays;
import java.util.List;
import org.junit.jupiter.api.Test;

public class ReduceOnlyTest extends BaseMatchingEngineTest {

  @Test
  void preventAllOrder_when_noPosition() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    order1.setReduceOnly(true);
    Order order2 = this.createOrder(2, 10, OrderSide.SELL, OrderType.LIMIT, "66000", "1");
    order2.setReduceOnly(true);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Arrays.asList();
    List<OrderBookOutput> orderbookOutputs = Arrays.asList();

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void preventSellOrder_when_short() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 11, OrderSide.SELL, OrderType.LIMIT, "66000", "1");
    order3.setReduceOnly(true);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades = Arrays.asList(this.createTrade(1, order2, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void allowBuyOrder_when_short() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 11, OrderSide.BUY, OrderType.LIMIT, "65500", "1");
    order3.setReduceOnly(true);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades = Arrays.asList(this.createTrade(1, order2, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65500", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void reduceBuyOrder_when_short() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order3 = this.createOrder(3, 11, OrderSide.BUY, OrderType.LIMIT, "65500", "4");
    order3.setReduceOnly(true);
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy(), order3.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.ACTIVE);
    processedOrder3.setQuantity(MarginBigDecimal.ONE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2, processedOrder3);
    List<Trade> trades = Arrays.asList(this.createTrade(1, order2, order1, "65000", "1"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65500", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void cancelActiveOrder_when_positionConflict() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "5");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "5");
    Order order3 = this.createOrder(3, 11, OrderSide.BUY, OrderType.LIMIT, "65500", "4");
    order3.setReduceOnly(true);
    Order order4 = this.createOrder(4, 10, OrderSide.SELL, OrderType.LIMIT, "65500", "3");
    Order order5 = this.createOrder(5, 11, OrderSide.BUY, OrderType.LIMIT, "65600", "2");
    Order order6 = this.createOrder(6, 10, OrderSide.SELL, OrderType.LIMIT, "65500", "4");
    List<Order> orders =
        Arrays.asList(
            order1.deepCopy(),
            order2.deepCopy(),
            order3.deepCopy(),
            order4.deepCopy(),
            order5.deepCopy(),
            order6.deepCopy());

    //  order2 was filled completely by order1
    //  ->  acc 11 position became SHORT(quantity=-5)
    //  order3 was partially filled by order4, and remains active with value Buy(remaining=1)
    //  ->  acc 11 position became SHORT(quantity=-2)
    //  order5 was filled by order6
    //  ->  acc 11 position is now neutral(quantity=0),
    //      which is incompatible with reduce-only order3 BUY(remaining=1),
    //      so order3 will be cancelled
    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.CANCELED);
    Order processedOrder4 = this.cloneOrder(order4, "0", OrderStatus.FILLED);
    Order processedOrder5 = this.cloneOrder(order5, "0", OrderStatus.FILLED);
    Order processedOrder6 = this.cloneOrder(order6, "2", OrderStatus.ACTIVE);
    List<Order> processedOrders =
        Arrays.asList(
            processedOrder1,
            processedOrder2,
            processedOrder3,
            processedOrder4,
            processedOrder5,
            processedOrder6);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, order2, order1, "65000", "5"),
            this.createTrade(2, order4, order3, "65500", "3"),
            this.createTrade(3, order6, order5, "65600", "2"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "5"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-5"),
            this.createOrderbookOutput(OrderSide.BUY, "65500", "4"),
            this.createOrderbookOutput(OrderSide.BUY, "65500", "-3"),
            this.createOrderbookOutput(OrderSide.BUY, "65600", "2"),
            this.createOrderbookOutput(OrderSide.BUY, "65600", "-2"),
            this.createOrderbookOutput(OrderSide.BUY, "65500", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "65500", "2"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);

    assertEquals(this.orderService.get(3L).getNote(), OrderNote.REDUCE_ONLY_CANCELED);
  }

  @Test
  void fillOrderToNeutralPositionThenCancel_when_positionConflict() {
    Order order1 = this.createOrder(1, 10, OrderSide.BUY, OrderType.LIMIT, "65000", "5");
    Order order2 = this.createOrder(2, 11, OrderSide.SELL, OrderType.LIMIT, "65000", "5");
    Order order3 = this.createOrder(3, 10, OrderSide.SELL, OrderType.LIMIT, "65500", "5");
    order3.setReduceOnly(true);
    Order order4 = this.createOrder(4, 10, OrderSide.SELL, OrderType.LIMIT, "65000", "1");
    Order order5 = this.createOrder(5, 11, OrderSide.BUY, OrderType.LIMIT, "65500", "6");

    List<Order> orders =
        Arrays.asList(
            order1.deepCopy(),
            order2.deepCopy(),
            order3.deepCopy(),
            order4.deepCopy(),
            order5.deepCopy());

    //  order2 was completely filled by order1
    //  ->  acc 10 position became LONG(quantity=-5)
    //  order4 was partially filled by order5
    //  ->  acc 10 position became SHORT(quantity=-4)
    //  order3 was partially filled by order5
    //  ->  acc 11 position is now neutral(quantity=0),
    //      which is incompatible with reduce-only order3 BUY(remaining=1),
    //      so order3 will be cancelled
    //  order5 remain active BUY(remaining=1)
    Order processedOrder1 = this.cloneOrder(order1, "0", OrderStatus.FILLED);
    Order processedOrder2 = this.cloneOrder(order2, "0", OrderStatus.FILLED);
    Order processedOrder3 = this.cloneOrder(order3, "1", OrderStatus.CANCELED);
    Order processedOrder4 = this.cloneOrder(order4, "0", OrderStatus.FILLED);
    Order processedOrder5 = this.cloneOrder(order5, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders =
        Arrays.asList(
            processedOrder1, processedOrder2, processedOrder3, processedOrder4, processedOrder5);
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, order2, order1, "65000", "5"),
            this.createTrade(2, order5, order4, "65000", "1"),
            this.createTrade(3, order5, order3, "65500", "4"));
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "5"),
            this.createOrderbookOutput(OrderSide.BUY, "65000", "-5"),
            this.createOrderbookOutput(OrderSide.SELL, "65500", "5"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "1"),
            this.createOrderbookOutput(OrderSide.SELL, "65000", "-1"),
            this.createOrderbookOutput(OrderSide.SELL, "65500", "-4"),
            this.createOrderbookOutput(OrderSide.SELL, "65500", "-1"),
            this.createOrderbookOutput(OrderSide.BUY, "65500", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);

    assertEquals(this.orderService.get(3L).getNote(), OrderNote.REDUCE_ONLY_CANCELED);
  }
}

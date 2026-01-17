package com.sotatek.future;

import static org.junit.jupiter.api.Assertions.assertEquals;

import com.sotatek.future.engine.Trigger;
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
import com.sotatek.future.service.ServiceFactory;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collections;
import java.util.List;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

@Disabled("Disable until after merging code cleanup branch")
public class StopOrderTest extends BaseMatchingEngineTest {

  static long priority = 0;

  @Override
  @BeforeEach
  public void setUp() throws Exception {
    super.setUp();
    ServiceFactory.initialize();
  }

  @Override
  @AfterEach
  public void tearDown() throws Exception {
    super.tearDown();
  }

  @Test
  void notTriggerBuyStop_when_lastPriceUnderStopPrice() {
    List<Order> triggeredOrders = new ArrayList<>();
    Order order1 =
        this.createStopOrder(
            1, OrderSide.BUY, TPSLType.STOP_LIMIT, "65000", "1", OrderTrigger.LAST, "60000");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    this.setLastPrice("58000");
    //    trigger.doTrigger();

    assertEquals(0, triggeredOrders.size(), "Number of trigger orders");
  }

  @Test
  void triggerBuyStop_when_lastPriceAboveStopPrice() {
    List<Order> triggeredOrders = new ArrayList<>();
    Order order1 =
        this.createStopOrder(
            1, OrderSide.BUY, TPSLType.STOP_LIMIT, "65000", "1", OrderTrigger.LAST, "60000");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    this.setLastPrice("70000");
    //    trigger.doTrigger();

    assertEquals(Arrays.asList(order1), triggeredOrders);
  }

  @Test
  void triggerBuyStop_when_lastPriceAboveStopPrice_MultipleOrder() {
    List<Order> triggeredOrders = new ArrayList<>();
    Order order1 =
        this.createStopOrder(
            1, OrderSide.BUY, TPSLType.STOP_LIMIT, "65000", "1", OrderTrigger.LAST, "60000");
    Order order2 =
        this.createStopOrder(
            2, OrderSide.BUY, TPSLType.STOP_LIMIT, "65000", "1", OrderTrigger.LAST, "62000");
    Order order3 =
        this.createStopOrder(
            3, OrderSide.BUY, TPSLType.STOP_LIMIT, "65000", "1", OrderTrigger.LAST, "61000");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    trigger.processOrder(order2);
    trigger.processOrder(order3);

    this.setLastPrice("61000");
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order1, order3), triggeredOrders);

    this.setLastPrice("70000");
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order1, order3, order2), triggeredOrders);
  }

  @Test
  void triggerSellStop_when_oraclePriceBelowStopPrice() {
    List<Order> triggeredOrders = new ArrayList<>();
    Order order1 =
        this.createStopOrder(
            1, OrderSide.SELL, TPSLType.STOP_MARKET, null, "1", OrderTrigger.ORACLE, "60000");
    Order order2 =
        this.createStopOrder(
            2, OrderSide.SELL, TPSLType.STOP_MARKET, null, "1", OrderTrigger.ORACLE, "62000");
    Order order3 =
        this.createStopOrder(
            3, OrderSide.SELL, TPSLType.STOP_MARKET, null, "1", OrderTrigger.ORACLE, "61000");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    trigger.processOrder(order2);
    trigger.processOrder(order3);

    this.setOraclePrice("61000");
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order2, order3), triggeredOrders);

    this.setOraclePrice("50000");
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order2, order3, order1), triggeredOrders);
  }

  @Test
  void triggerBuyTakeProfit_when_oraclePriceBelowStopPrice() {
    List<Order> triggeredOrders = new ArrayList<>();
    Order order1 =
        this.createStopOrder(
            1,
            OrderSide.BUY,
            TPSLType.TAKE_PROFIT_LIMIT,
            "60000",
            "1",
            OrderTrigger.ORACLE,
            "60000");
    Order order2 =
        this.createStopOrder(
            2, OrderSide.BUY, TPSLType.TAKE_PROFIT_MARKET, null, "1", OrderTrigger.ORACLE, "62000");
    Order order3 =
        this.createStopOrder(
            3,
            OrderSide.BUY,
            TPSLType.TAKE_PROFIT_LIMIT,
            "60000",
            "1",
            OrderTrigger.ORACLE,
            "61000");
    Order order4 =
        this.createStopOrder(
            4,
            OrderSide.BUY,
            TPSLType.TAKE_PROFIT_LIMIT,
            "60000",
            "1",
            OrderTrigger.ORACLE,
            "61000");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    trigger.processOrder(order2);
    trigger.processOrder(order3);
    trigger.processOrder(order4);

    this.setOraclePrice("61000");
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order2, order3, order4), triggeredOrders);

    this.setOraclePrice("50000");
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order2, order3, order4, order1), triggeredOrders);
  }

  @Test
  void notTriggerBuyTakeProfit_when_cancelBeforeTrigger() {
    List<Order> triggeredOrders = new ArrayList<>();
    Order order1 =
        this.createStopOrder(
            1,
            OrderSide.BUY,
            TPSLType.TAKE_PROFIT_LIMIT,
            "60000",
            "1",
            OrderTrigger.ORACLE,
            "60000");
    Order order2 =
        this.createStopOrder(
            2, OrderSide.BUY, TPSLType.TAKE_PROFIT_MARKET, null, "1", OrderTrigger.ORACLE, "62000");
    Order order3 =
        this.createStopOrder(
            3,
            OrderSide.BUY,
            TPSLType.TAKE_PROFIT_LIMIT,
            "60000",
            "1",
            OrderTrigger.ORACLE,
            "61000");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    trigger.processOrder(order2);
    trigger.processOrder(order3);

    this.setOraclePrice("61000");
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order2, order3), triggeredOrders);

    this.setOraclePrice("50000");
    trigger.cancelOrder(order1);
    //    trigger.doTrigger();
    assertEquals(Arrays.asList(order2, order3), triggeredOrders);
  }

  @Test
  void triggerBuyTrailStop_when_oraclePricePassLimit() {
    List<Order> triggeredOrders = new ArrayList<>();
    this.setOraclePrice("60000");
    Order order1 =
        this.createTrailingStopOrder(
            1, OrderSide.BUY, null, "1", OrderTrigger.ORACLE, "60000", "100");
    Order order2 =
        this.createTrailingStopOrder(
            2, OrderSide.BUY, null, "1", OrderTrigger.ORACLE, "60000", "-100");
    Order order3 =
        this.createTrailingStopOrder(
            3, OrderSide.BUY, null, "1", OrderTrigger.ORACLE, "60000", "200");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    trigger.processOrder(order2);
    trigger.processOrder(order3);

    this.setOraclePrice("60100");
    //    trigger.doTrigger();
    //  order 1 trailing is positive,
    //    -> oraclePrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 60100 (= oraclePrice) -> activate order 1
    //  order 2 trailing is negative,
    //    -> oraclePrice > vertexPrice -> increase vertexPrice to 60100 (= oraclePrice)
    //    -> vertexPrice + trailing = 60000 (< oraclePrice) -> do nothing
    //  order 3 trailing is positive,
    //    -> oraclePrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 60200 (> oraclePrice) -> do nothing
    assertEquals(Arrays.asList(order1), triggeredOrders);

    this.setOraclePrice("60050");
    //    trigger.doTrigger();
    //  order 2 trailing is negative,
    //    -> oraclePrice < vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 60000 (< oraclePrice) -> do nothing
    //  order 3 trailing is positive,
    //    -> oraclePrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 60200 (> oraclePrice) -> do nothing
    assertEquals(Arrays.asList(order1), triggeredOrders);

    this.setOraclePrice("60000");
    //    trigger.doTrigger();
    //  order 2 trailing is negative,
    //    -> oraclePrice < vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 60000 (= oraclePrice) -> activate order 2
    //  order 3 trailing is positive,
    //    -> oraclePrice = vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 60200 (> oraclePrice) -> do nothing
    assertEquals(Arrays.asList(order1, order2), triggeredOrders);

    this.setOraclePrice("59700");
    //    trigger.doTrigger();
    //  order 3 trailing is positive,
    //    -> oraclePrice < vertexPrice -> reduce vertexPrice to 59700 (= oraclePrice)
    //    -> vertexPrice + trailing  = 59900 (> oraclePrice) -> do nothing
    assertEquals(Arrays.asList(order1, order2), triggeredOrders);

    this.setOraclePrice("60000");
    //    trigger.doTrigger();
    //  order 3 trailing is positive,
    //    -> oraclePrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing  = 59900 (< oraclePrice) -> activate order3
    assertEquals(Arrays.asList(order1, order2, order3), triggeredOrders);
  }

  @Test
  void triggerBuyTrailStop_when_lastPricePassLimit() {
    List<Order> triggeredOrders = new ArrayList<>();
    this.setLastPrice("60000");
    Order order1 =
        this.createTrailingStopOrder(
            1, OrderSide.BUY, null, "1", OrderTrigger.LAST, "60000", "100");
    Order order2 =
        this.createTrailingStopOrder(
            2, OrderSide.BUY, null, "1", OrderTrigger.LAST, "60000", "-100");
    Order order3 =
        this.createTrailingStopOrder(
            3, OrderSide.BUY, null, "1", OrderTrigger.LAST, "60000", "200");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    trigger.processOrder(order2);
    trigger.processOrder(order3);

    this.setLastPrice("50000");
    //    trigger.doTrigger();
    //  order 1 trailing is positive,
    //    -> lastPrice < vertexPrice -> decrease vertexPrice to 50000
    //    -> vertexPrice + trailing = 50100 (> lastPrice) -> do nothing
    //  order 2 trailing is negative,
    //    -> lastPrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 59000 (> lastPrice) -> activate order 2
    //  order 3 trailing is positive,
    //    -> lastPrice < vertexPrice -> decrease vertexPrice to 50000
    //    -> vertexPrice + trailing = 50200 (> lastPrice) -> do nothing
    assertEquals(Arrays.asList(order2), triggeredOrders);

    this.setLastPrice("50150");
    trigger.startTrigger();
    //  order 1 trailing is positive
    //    -> lastPrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 50100 (< lastPrice) -> activate order 1
    //  order 3 trailing is positive
    //    -> lastPrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 50200 (> lastPrice) -> do nothing
    assertEquals(Arrays.asList(order2, order1), triggeredOrders);
  }

  @Test
  void notTriggerBuyTrailStop_when_cancelBeforeTrigger() {
    List<Order> triggeredOrders = new ArrayList<>();
    this.setLastPrice("60000");
    Order order1 =
        this.createTrailingStopOrder(
            1, OrderSide.BUY, null, "1", OrderTrigger.LAST, "60000", "100");
    Order order2 =
        this.createTrailingStopOrder(
            2, OrderSide.BUY, null, "1", OrderTrigger.LAST, "60000", "-100");
    Order order3 =
        this.createTrailingStopOrder(
            3, OrderSide.BUY, null, "1", OrderTrigger.LAST, "60000", "200");
    Trigger trigger =
        new Trigger(this.defaultSymbol, command -> triggeredOrders.add(command.getOrder()));
    trigger.processOrder(order1);
    trigger.processOrder(order2);
    trigger.processOrder(order3);

    this.setLastPrice("50000");
    //    trigger.doTrigger();
    //  order 1 trailing is positive,
    //    -> lastPrice < vertexPrice -> decrease vertexPrice to 50000
    //    -> vertexPrice + trailing = 50100 (> lastPrice) -> do nothing
    //  order 2 trailing is negative,
    //    -> lastPrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 59000 (> lastPrice) -> activate order 2
    //  order 3 trailing is positive,
    //    -> lastPrice < vertexPrice -> decrease vertexPrice to 50000
    //    -> vertexPrice + trailing = 50200 (> lastPrice) -> do nothing
    assertEquals(Arrays.asList(order2), triggeredOrders);

    this.setLastPrice("60000");
    trigger.cancelOrder(order1);
    //    trigger.doTrigger();
    //  order 1 is cancelled -> do nothing
    //  order 3 trailing is positive
    //    -> lastPrice > vertexPrice -> do nothing
    //    -> vertexPrice + trailing = 50200 (< lastPrice) -> activate order 3
    assertEquals(Arrays.asList(order2, order3), triggeredOrders);
  }

  @Test
  void notActivateBuyStop_when_notTrigger() {
    Order order1 = this.createOrder(1, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 =
        this.createStopOrder(
            2, OrderSide.BUY, TPSLType.STOP_LIMIT, "60000", "1", OrderTrigger.ORACLE, "61000");
    List<Order> orders = Arrays.asList(order1.deepCopy(), order2.deepCopy());

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.UNTRIGGERED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.BUY, "65000", "1"));

    this.testMatching(orders, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void activateBuyStop_when_trigger() {
    Order order1 = this.createOrder(1, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 =
        this.createStopOrder(
            2, OrderSide.BUY, TPSLType.STOP_LIMIT, "60000", "1", OrderTrigger.ORACLE, "61000");
    order2.setStatus(OrderStatus.UNTRIGGERED);
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1),
            new Command(CommandCode.TRIGGER_ORDER, order2));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.ACTIVE);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(
            this.createOrderbookOutput(OrderSide.BUY, "65000", "1"),
            this.createOrderbookOutput(OrderSide.BUY, "60000", "1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void cancelBuyStop_when_cancelBeforeTrigger() {
    Order order1 = this.createOrder(1, 1, OrderSide.BUY, OrderType.LIMIT, "65000", "1");
    Order order2 =
        this.createStopOrder(
            2, OrderSide.BUY, TPSLType.STOP_LIMIT, "60000", "1", OrderTrigger.ORACLE, "61000");
    List<Command> commands =
        Arrays.asList(
            new Command(CommandCode.PLACE_ORDER, order1),
            new Command(CommandCode.PLACE_ORDER, order2),
            new Command(CommandCode.CANCEL_ORDER, order2));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.ACTIVE);
    Order processedOrder2 = this.cloneOrder(order2, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1, processedOrder2);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs =
        Arrays.asList(this.createOrderbookOutput(OrderSide.BUY, "65000", "1"));

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  @Test
  void cancelBuyStopMarket_when_notAvailableMarketPrice() {
    Order order1 =
        this.createStopOrder(
            1, OrderSide.BUY, TPSLType.STOP_MARKET, null, "1", OrderTrigger.LAST, "65000");
    List<Command> commands = Arrays.asList(new Command(CommandCode.PLACE_ORDER, order1));

    Order processedOrder1 = this.cloneOrder(order1, "1", OrderStatus.CANCELED);
    List<Order> processedOrders = Arrays.asList(processedOrder1);
    List<Trade> trades = Collections.emptyList();
    List<OrderBookOutput> orderbookOutputs = Collections.emptyList();

    this.testEngine(commands, processedOrders, trades, orderbookOutputs);
  }

  protected Order createTrailingStopOrder(
      long id,
      OrderSide side,
      String price,
      String quantity,
      OrderTrigger trigger,
      String vertexPrice,
      String trailValue) {
    OrderType orderType = OrderType.MARKET;
    Order order = this.createOrder(id, 1, side, orderType, price, quantity);
    order.setTpSLType(TPSLType.TRAILING_STOP);
    order.setTrigger(trigger);
    //    order.setVertexPrice(MarginBigDecimal.valueOf(vertexPrice));
    order.setTrailPrice(MarginBigDecimal.valueOf(trailValue));
    order.setPriority(StopOrderTest.priority++);
    return order;
  }
}

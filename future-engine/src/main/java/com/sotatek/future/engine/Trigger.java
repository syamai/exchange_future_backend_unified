package com.sotatek.future.engine;

import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Order;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderTrigger;
import com.sotatek.future.enums.TPSLType;
import com.sotatek.future.enums.TriggerCondition;
import com.sotatek.future.service.InstrumentService;
import com.sotatek.future.service.OrderService;
import com.sotatek.future.util.FastDeletePriorityQueue;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;

@Slf4j
public class Trigger {
  private final String symbol;
  private final OnOrderTriggeredListener listener;
  private final InstrumentService instrumentService;
  private final OrderService orderService;
  private final List<Order> trailingStopOrders = new ArrayList<>();
  private final Map<String, FastDeletePriorityQueue<Order>> queues = new HashMap<>();

  public Trigger(String symbol, OnOrderTriggeredListener listener) {
    this.symbol = symbol;
    this.listener = listener;
    this.instrumentService = InstrumentService.getInstance();
    this.orderService = OrderService.getInstance();

    this.queues.put(OrderTrigger.LAST.toString() + TriggerCondition.LT, this.createLtQueue());
    this.queues.put(OrderTrigger.LAST.toString() + TriggerCondition.GT, this.createGtQueue());
    this.queues.put(OrderTrigger.ORACLE.toString() + TriggerCondition.LT, this.createLtQueue());
    this.queues.put(OrderTrigger.ORACLE.toString() + TriggerCondition.GT, this.createGtQueue());
  }

  private FastDeletePriorityQueue<Order> createLtQueue() {
    return new FastDeletePriorityQueue<>(
        OrderComparators.HighTpslPriceComparator.thenComparing(
            OrderComparators.LowPriorityComparator));
  }

  private FastDeletePriorityQueue<Order> createGtQueue() {
    return new FastDeletePriorityQueue<>(
        OrderComparators.LowTpslPriceComparator.thenComparing(
            OrderComparators.LowPriorityComparator));
  }

  /**
   * process for stop order
   * (STOP_LIMIT/STOP_MARKET/TRAILING_STOP/TAKE_PROFIT_LIMIT/TAKE_PROFIT_MARKET)
   *
   * @param stopOrder stop order to process
   */
  public void processOrder(Order stopOrder) {
    log.debug("Process stop order {}", stopOrder);
    if (stopOrder.isClosed()) {
      log.debug("Stop order is closed");
      return;
    }
    if (stopOrder.getTpSLType() == TPSLType.TRAILING_STOP) {
      trailingStopOrders.add(stopOrder);
    } else {
      FastDeletePriorityQueue<Order> queue =
          queues.get(stopOrder.getTrigger().toString() + stopOrder.getStopCondition().toString());
      queue.add(stopOrder);
    }
  }

  /**
   * Cancel for stop order
   * (STOP_LIMIT/STOP_MARKET/TRAILING_STOP/TAKE_PROFIT_LIMIT/TAKE_PROFIT_MARKET)
   *
   * @param stopOrder stop order to cancel
   */
  public void cancelOrder(Order stopOrder) {
    log.debug("Cancel stop order {}", stopOrder);
    if (stopOrder.getTpSLType() == TPSLType.TRAILING_STOP) {
      trailingStopOrders.remove(stopOrder);
    } else {
      FastDeletePriorityQueue<Order> queue =
          queues.get(stopOrder.getTrigger().toString() + stopOrder.getStopCondition().toString());
      queue.remove(stopOrder);
    }
  }

  public void startTrigger() {
    List<Order> triggeredOrders = new ArrayList<>();
    InstrumentExtraInformation instrumentExtra = instrumentService.getExtraInfo(symbol);
    if (instrumentExtra == null) {
      log.error("Trigger can not load instrument extra for symbol {}", symbol);
      return;
    }

    // trigger all stop order by trigger (LAST/MARK)
//    if (instrumentExtra.getLastPrice() != null) {
//      doTrigger(OrderTrigger.LAST, instrumentExtra.getLastPrice());
//    }
    doTrigger(OrderTrigger.LAST, instrumentExtra.getOraclePrice());
    doTrigger(OrderTrigger.ORACLE, instrumentExtra.getOraclePrice());

    // handle for trailing stop order
    for (Order trailingOrder : trailingStopOrders) {
      if (isTriggerTrailingStopOrder(trailingOrder, instrumentExtra)) {
        triggeredOrders.add(trailingOrder);
        log.debug("Trigger trailing stop order {}", trailingOrder);
        trailingOrder.setTriggered(true);
        listener.onOrderTriggered(new Command(CommandCode.TRIGGER_ORDER, trailingOrder));
      }
    }
    triggeredOrders.forEach(trailingStopOrders::remove);
  }

  private void doTrigger(OrderTrigger trigger, MarginBigDecimal triggerPrice) {
    doTriggerGT(trigger, triggerPrice);
    doTriggerLT(trigger, triggerPrice);
  }

  private void doTriggerLT(OrderTrigger trigger, MarginBigDecimal triggerPrice) {
    List<Order> orderQueueLtUndo = new ArrayList<>();
    FastDeletePriorityQueue<Order> orderQueueLT =
        queues.get(trigger.toString() + TriggerCondition.LT);
    while (!orderQueueLT.isEmpty()) {
      Order order = orderQueueLT.poll();
      // if order not match condition to trigger then skip to next order on queue
      if (isShouldTriggerOrder(order)) {
        if (triggerPrice.gt(order.getTpSLPrice())) {
          // if first order on queue not match condition then skip all
          orderQueueLtUndo.add(order);
          break;
        }
        // trigger order and continue loop on queue
        log.debug("Trigger order {}", order);
        // set mark order is triggered to calculate cost when active
        order.setTriggered(true);
        // trigger new order command
        listener.onOrderTriggered(new Command(CommandCode.TRIGGER_ORDER, order));
      } else {
        orderQueueLtUndo.add(order);
      }
    }
    // Put back orders that were taken from the queue and not trigger
    for (Order o : orderQueueLtUndo) {
      orderQueueLT.add(o);
    }
  }

  private void doTriggerGT(OrderTrigger trigger, MarginBigDecimal triggerPrice) {
    List<Order> orderQueueGtUndo = new ArrayList<>();
    FastDeletePriorityQueue<Order> orderQueueGT =
        this.queues.get(trigger.toString() + TriggerCondition.GT);
    while (!orderQueueGT.isEmpty()) {
      Order order = orderQueueGT.poll();
      if (isShouldTriggerOrder(order)) {
        if (triggerPrice.lt(order.getTpSLPrice())) {
          // if first order on queue not match condition then skip all
          orderQueueGtUndo.add(order);
          break;
        }
        // trigger order and continue loop on queue
        log.debug("Trigger order {}", order);
        // set mark order is triggered to calculate cost when active
        order.setTriggered(true);
        // trigger new order command
        listener.onOrderTriggered(new Command(CommandCode.TRIGGER_ORDER, order));
      } else {
        orderQueueGtUndo.add(order);
      }
    }
    // Put back orders that were taken from the queue and not trigger
    for (Order o : orderQueueGtUndo) {
      orderQueueGT.add(o);
    }
  }

  /**
   * check should we trigger order or not
   *
   * @param order order to check
   * @return is should trigger that order or not
   */
  private boolean isShouldTriggerOrder(Order order) {
    // Check trigger order when
    // It is not a tp/sl order
    if (!order.isTpSlOrder()) {
      return true;
    }
    // It is a tp/sl order, and it's parent order is matched
    Long parentOrderId = order.getParentOrderId();
    if (ObjectUtils.isNotEmpty(parentOrderId)) {
      Order parentOrder = orderService.get(parentOrderId);
      // parent order is matched then quantity != remaining
      return ObjectUtils.isNotEmpty(parentOrder)
          && !parentOrder.getQuantity().eq(parentOrder.getRemaining());
    }
    return false;
  }

  /**
   * Update trailing price when price moving (LAST/MARK) was changed
   *
   * @param oldInstrumentExtra old instrument
   * @param newInstrumentExtra new instrument
   */
  public void updateTrailingPrice(
      InstrumentExtraInformation oldInstrumentExtra,
      InstrumentExtraInformation newInstrumentExtra) {
    for (Order trailingOrder : trailingStopOrders) {
      // get old + new trigger price to compare
      MarginBigDecimal oldTriggerPrice =
          getTriggerPrice(oldInstrumentExtra, trailingOrder.getTrigger());
      MarginBigDecimal newTriggerPrice =
          getTriggerPrice(newInstrumentExtra, trailingOrder.getTrigger());
      if (ObjectUtils.isEmpty(newTriggerPrice) || ObjectUtils.isEmpty(oldTriggerPrice)) {
        return;
      }

      if (newTriggerPrice.eq(oldTriggerPrice)) {
        // not change price do not update
        return;
      }
      // update trailing price for long position => order trailing stop is SELL
      // only update trailing price when
      //   + trailPrice is has value  or (trigger price >= activation price)
      //   + trigger price is increment ->  newTriggerPrice > oldTriggerPrice
      // [trailing price = triggerPrice * (1- callback rate)]
      MarginBigDecimal activatePrice = trailingOrder.getActivationPrice();
      OrderSide orderSide = trailingOrder.getSide();
      TriggerCondition triggerCondition = trailingOrder.getStopCondition();
      boolean isTrigger =
          TriggerCondition.GT.equals(triggerCondition)
              ? newTriggerPrice.gte(activatePrice)
              : newTriggerPrice.lte(activatePrice);
      // if activation price meet condition and not set value yet
      if (isTrigger && !trailingOrder.isActivated()) {
        trailingOrder.setActivated(true);
      }

      MarginBigDecimal callBackRatePercent =
          trailingOrder.getCallbackRate().divide(MarginBigDecimal.valueOf(100));
      MarginBigDecimal temPrice = MarginBigDecimal.valueOf(1).subtract(callBackRatePercent);
      log.debug(
          "updateTrailingPrice with orderId {} temPrice {}", trailingOrder.getKey(), temPrice);
      log.debug(
          "updateTrailingPrice with orderId {} oldTriggerPrice {}  newTriggerPrice {} activatePrice"
              + " {}",
          trailingOrder.getKey(),
          oldTriggerPrice,
          newTriggerPrice,
          activatePrice);
      log.debug(
          "updateTrailingPrice with orderId {} triggerCondition {} trailingPrice {} isTrigger {}",
          trailingOrder.getKey(),
          triggerCondition,
          trailingOrder.getTrailPrice(),
          trailingOrder.isActivated());
      // old trigger price maybe null at the firstTime run or restart service
      if (OrderSide.SELL.equals(orderSide)
          && (ObjectUtils.isEmpty(oldTriggerPrice) || newTriggerPrice.gt(oldTriggerPrice))) {
        trailingOrder.setTrailPrice(newTriggerPrice.multiply(temPrice));
      }
      // update trailing price for short position => order trailing stop is BUY
      // only update trailing price when
      // + trailPrice is has value  or (trigger price <= activation price)
      // + trigger price is decrement -> newTriggerPrice < oldTriggerPrice
      // [trailing price = triggerPrice * (1+ callback rate)]
      temPrice = MarginBigDecimal.valueOf(1).add(callBackRatePercent);
      // old trigger price maybe null at the firstTime run or restart service
      if (OrderSide.BUY.equals(orderSide)
          && (ObjectUtils.isEmpty(oldTriggerPrice) || newTriggerPrice.lt(oldTriggerPrice))) {
        trailingOrder.setTrailPrice(newTriggerPrice.multiply(temPrice));
      }
    }
  }

  /**
   * Check should we trigger a trailing stop order
   *
   * @param trailingOrder trailing stop order to check
   * @param instrumentExtra instrument to get trigger price
   * @return is should trigger trailing order or not
   */
  private boolean isTriggerTrailingStopOrder(
      Order trailingOrder, InstrumentExtraInformation instrumentExtra) {
    MarginBigDecimal triggerPrice = getTriggerPrice(instrumentExtra, trailingOrder.getTrigger());
    if (triggerPrice == null) {
      return false;
    }
    MarginBigDecimal trailingPrice = trailingOrder.getTrailPrice();
    OrderSide orderSide = trailingOrder.getSide();
    // For long position => order trailing stop is SELL then active trailing stop order when
    //  + Order has trailing price => we calculate trailing price when trigger price start reach to
    // activation price
    //  + The trigger price ( LAST/MARK ) <= trailing price
    if (OrderSide.SELL.equals(orderSide)
        && trailingOrder.isActivated()
        && ObjectUtils.isNotEmpty(trailingPrice)
        && triggerPrice.lte(trailingPrice)) {
      return true;
    }
    // For short position => order trailing stop is BUY then active trailing stop order when
    //  + Order has trailing price => we calculate trailing price when trigger price start reach to
    // activation price
    //  + The trigger price ( LAST/MARK ) >= trailing price
    return OrderSide.BUY.equals(orderSide)
        && trailingOrder.isActivated()
        && ObjectUtils.isNotEmpty(trailingPrice)
        && triggerPrice.gte(trailingPrice);
  }

  /**
   * get trigger price from Instrument
   *
   * @param instrumentExtra instrument to get trigger price
   * @param trigger type of trigger (LAST/MARK)
   * @return return trigger price
   */
  private MarginBigDecimal getTriggerPrice(
      InstrumentExtraInformation instrumentExtra, OrderTrigger trigger) {
    return switch (trigger) {
      case LAST -> instrumentExtra.getLastPrice();
      case ORACLE -> instrumentExtra.getOraclePrice();
    };
  }

  /**
   * Update isHidden for those tp/sl orders which it's parent matched
   *
   * @param queueKey key to get right queue
   * @param updateOrderId if of order need to reset isHidden
   */
  public void updateIsHidden(String queueKey, Long updateOrderId) {
    // get queue hold that order
    FastDeletePriorityQueue<Order> orderQueue = queues.get(queueKey);
    // reset isHidden field
    orderQueue.forEach(
        o -> {
          if (o.getId().equals(updateOrderId)) {
            o.setHidden(false);
          }
        });
  }

  /**
   * Update tp/sl price on trigger queue
   *
   * @param queueKey
   * @param order
   */
  public void updateTpSlPrice(String queueKey, boolean isTriggerChange, Order order) {
    FastDeletePriorityQueue<Order> orderQueue = queues.get(queueKey);
    if (isTriggerChange) {
      // remove from old queue
      orderQueue.remove(order);
      // get new queue
      FastDeletePriorityQueue<Order> newQueue =
          queues.get(order.getTrigger().toString() + order.getStopCondition().toString());
      newQueue.add(order);
    } else {
      // get queue hold that order
      // update
      orderQueue.add(order);
    }
  }

  public interface OnOrderTriggeredListener {

    void onOrderTriggered(Command command);
  }
}

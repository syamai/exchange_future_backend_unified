package com.sotatek.future.engine;

import static com.sotatek.future.engine.MatchingEngine.errors;
import static com.sotatek.future.engine.MatchingEngine.triggers;
import static com.sotatek.future.enums.ErrorResponse.INSUFFICIENT_BALANCE;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.CommandError;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.OrderNote;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.exception.InsufficientBalanceException;
import com.sotatek.future.exception.InsufficientQuantityException;
import com.sotatek.future.exception.InvalidTimeInForceException;
import com.sotatek.future.exception.PostOnlyOrderException;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.OrderBookService;
import com.sotatek.future.service.OrderService;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.service.TradeService;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.List;
import java.util.TreeSet;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;
import org.apache.commons.lang3.tuple.Pair;

@Slf4j
public class Matcher {

  private final String symbol;

  // Buy Order queue, where orders with higher price appear first
  private final TreeSet<Order> buyOrders =
      new TreeSet<>(
          OrderComparators.HighPriceComparator.thenComparing(
              OrderComparators.LowPriorityComparator));
  // Sell Order queue, where orders with a lower price appear first
  private final TreeSet<Order> sellOrders =
      new TreeSet<>(
          OrderComparators.LowPriceComparator.thenComparing(
              OrderComparators.LowPriorityComparator));

  private final List<Order> processingEntities = new ArrayList<>();
  private final OrderBookService orderbookService = OrderBookService.getInstance();
  private final OrderService orderService = OrderService.getInstance();
  private final TradeService tradeService = TradeService.getInstance();
  private final PositionService positionService = PositionService.getInstance();
  private final AccountService accountService = AccountService.getInstance();

  public Matcher(String symbol) {
    this.symbol = symbol;
  }

  public void cancelOrder(Order cancelOrder) {
    log.debug("Cancel order: {}", cancelOrder);
    MarginBigDecimal orderBookQuantity = cancelOrder.getOrderBookQuantity();
    TreeSet<Order> orderQueue = getPendingOrdersQueue(cancelOrder.getSide());
    // initial variable for update order book or not
    boolean updateOrderBook = false;
    // remove order limit from order queue
    if (cancelOrder.isLimitOrder()) {
      // we only update order book when
      // + order is limit order
      // + order existed on order queue of Matcher => this mean order is active and do not cancel
      // yet
      updateOrderBook = orderQueue.remove(cancelOrder);
      if (!updateOrderBook) {
        log.debug(
            "Cancel order not update order book due to orderQueue not contain order {}",
            cancelOrder.getId());
      }
    }
    // execute cancel order
    orderService.cancel(cancelOrder);
    // update order-book with a quantity negate (subtract) with the same other order's price
    if (updateOrderBook) {
      updateOrderBookOutput(
          cancelOrder.getSide(), cancelOrder.getPrice(), orderBookQuantity.negate());
    }
  }

  public boolean processOrder(Order order) {
    log.atDebug().log("Process order: {}", order.getId());
    // get order book on opposite side
    TreeSet<Order> orderBookOpposite = getPendingOrdersQueue(order.getOppositeSide());
    int tradeCount = 0;
    Order candidate;
    while (order.canBeMatched() && !orderBookOpposite.isEmpty()) {
      candidate = orderBookOpposite.first().deepCopy();
      if (!order.canBeMatchedWith(candidate)) {
        log.atDebug().log("Candidate {} cannot be matched", candidate.getId());
        break;
      }

      if (tradeCount >= MatchingEngineConfig.TRADES_PER_MESSAGE) {
        // check of batch is full load 10 orders to handle
        // then exit and push to output stream and handle for next batch
        return true;
      }
      log.atDebug().log("Candidate {} can be matched", candidate.getId());
      if (order.isPostOnly()) {
        throw new PostOnlyOrderException(order.getId());
      }
      Order originCandidate = candidate.deepCopy();
      processingEntities.add(originCandidate);

      Trade trade = null;
      // copy a state of order before each matching
      Order processOrderBefore = order.deepCopy();
      boolean isSufficientBalance = false;
      try {
        // trade successful then commit a version of processing entity to temporary
        commitTemporarily();
        trade = matchOrders(order, candidate);
        // remove candidate from queue if match order is successful
        orderBookOpposite.remove(candidate);
      } catch (InsufficientBalanceException e) {
        log.atError()
            .setCause(e)
            .addKeyValue("accId", order.getAccountId())
            .addKeyValue("orderId", order.getId())
            .addKeyValue("candidateId", candidate.getId())
            .addKeyValue("candidateAccId", candidate.getAccountId())
            .log(
                "Insufficient balance. [account={}, availableBalance={}]",
                e.getAccount(),
                e.getAvailable());
        // set to true to not update order book
        isSufficientBalance = true;
        // roll back all state of service
        rollBackTemporarily();
        // roll back order with previous state
        order = processOrderBefore.deepCopy();
        // handle for logic when account is insufficient balance
        // get account which has insufficient balance from exception
        Account insufficientAccount = e.getAccount();
        // create new error object to push back BE for notify message
        CommandError error =
            CommandError.builder()
                .code(INSUFFICIENT_BALANCE.getCode())
                .messages(INSUFFICIENT_BALANCE.getMessages())
                .build();
        if (insufficientAccount.getId().equals(candidate.getAccountId())) {
          // if insufficientAccount is candidate then cancel from order book
          error.withUserId(candidate.getUserId());
          error.withAccountId(candidate.getAccountId());
          candidate = originCandidate;
          cancelOrder(candidate);
          // remove candidate from order queue
          orderBookOpposite.remove(candidate);
        } else {
          error.withUserId(order.getUserId());
          error.withAccountId(order.getAccountId());
          cancelOrder(order);
          orderBookOpposite.add(originCandidate);
        }
        errors.add(error);
      }

      // trade = null if owner of market order doesn't have enough balance to match minimum
      // quantity
      if (trade != null) {
        // with an order which has large amount can match with many other orders then we need
        // divide to batch for handle
        // trade count to 10 orders to put on a batch for handle
        tradeCount++;
      }
      // do not update order book if isSufficientBalance true
      if (!isSufficientBalance
          && !candidate.getOrderBookQuantity().eq(originCandidate.getOrderBookQuantity())) {
        updateOrderBookOutput(
            candidate.getSide(),
            candidate.getPrice(),
            candidate.getOrderBookQuantity().subtract(originCandidate.getOrderBookQuantity()));
      }
      if (OrderNote.REDUCE_ONLY_CANCELED.equals(candidate.getNote())) {
        // add back candidate order to queue before cancel
        getPendingOrdersQueue(candidate.getSide()).add(candidate);
        cancelOrder(candidate);
      } else if (candidate.canBeMatched() && trade != null) {
        getPendingOrdersQueue(candidate.getSide()).add(candidate);
      }
    }

    if (order.canBeMatched()) {
      switch (order.getTimeInForce()) {
        case GTC -> {
          getPendingOrdersQueue(order.getSide()).add(order);
          if (order.getOrderBookQuantity().gt(MarginBigDecimal.ZERO)) {
            updateOrderBookOutput(order.getSide(), order.getPrice(), order.getOrderBookQuantity());
          }
        }
        case IOC -> cancelOrder(order);
        case FOK -> throw new InsufficientQuantityException(order.getId());
        default -> throw new InvalidTimeInForceException(order.getTimeInForce());
      }
    }
    return false;
  }

  private void commitTemporarily() {
    positionService.commitTemporarily();
    orderService.commitTemporarily();
    tradeService.commitTemporarily();
    accountService.commitTemporarily();
  }

  private void rollBackTemporarily() {
    positionService.rollbackTemporary();
    orderService.rollbackTemporary();
    tradeService.rollbackTemporary();
    accountService.rollbackTemporary();
  }

  public TreeSet<Order> getPendingOrdersQueue(OrderSide side) {
    return (OrderSide.BUY == side) ? buyOrders : sellOrders;
  }

  public void activateOrder(Order order) {
    log.debug("activateOrder");
    if (order.canBeActivated()) {
      // calculate lock price and active order
      TreeSet<Order> orderQueue = getPendingOrdersQueue(order.getOppositeSide());
      MarginBigDecimal lockPrice = orderService.calculateLockPrice(order, orderQueue);
      order.setLockPrice(lockPrice);
      orderService.activateOrder(order);
    } else {
      orderService.update(order);
    }
  }

  public void reactivateOrder(Order order) {
    log.debug("reactivateOrder {}", order);
    TreeSet<Order> orderQueue = getPendingOrdersQueue(order.getOppositeSide());
    MarginBigDecimal newLockPrice = orderService.calculateLockPrice(order, orderQueue);
    orderService.reactivateOrder(order, newLockPrice);
  }

  private Trade matchOrders(Order taker, Order maker) {
    Pair<Order, Trade> result = orderService.matchOrders(taker, maker);
    // handle show tp/sl order of both two order matching
    handleTpSlOrder(taker);
    handleTpSlOrder(maker);
    Trade trade = result.getRight();
    log.debug("Trade: {}", trade);
    return trade;
  }

  private void handleTpSlOrder(Order targetOrder) {
    Long tpOrderId = targetOrder.getTakeProfitOrderId();
    Long slOrderId = targetOrder.getStopLossOrderId();
    Long linkedOrderId = targetOrder.getLinkedOrderId();
    Trigger trigger = triggers.get(targetOrder.getSymbol());
    if (ObjectUtils.isNotEmpty(tpOrderId)) {
      Order tpOrder = orderService.get(tpOrderId);
      if (ObjectUtils.isNotEmpty(tpOrder)) {
        tpOrder.setHidden(false);
        orderService.update(tpOrder);
        // update status on trigger queue
        String queueKey = tpOrder.getTrigger().toString() + tpOrder.getStopCondition().toString();
        trigger.updateIsHidden(queueKey, tpOrderId);
      }
    }
    if (ObjectUtils.isNotEmpty(slOrderId)) {
      Order slOrder = orderService.get(slOrderId);
      if (ObjectUtils.isNotEmpty(slOrder)) {
        slOrder.setHidden(false);
        orderService.update(slOrder);
        // update status on trigger queue
        String queueKey = slOrder.getTrigger().toString() + slOrder.getStopCondition().toString();
        trigger.updateIsHidden(queueKey, slOrderId);
      }
    }
    if (ObjectUtils.isNotEmpty(linkedOrderId)) {
      // handle cancel linked Order
      Order linkedOrder = orderService.get(linkedOrderId);
      if (ObjectUtils.isNotEmpty(linkedOrder)) {
        cancelOrder(linkedOrder);
      }
    }

    // set isTpSlTriggered of parent order to true
    if (ObjectUtils.isNotEmpty(targetOrder.getParentOrderId())) {
      Order parentOrder = orderService.get(targetOrder.getParentOrderId());
      if (ObjectUtils.isNotEmpty(parentOrder)) {
        parentOrder.setTpSlTriggered(true);
        orderService.updateWithoutUpdateAt(parentOrder);
      }
    }
  }

  private void updateOrderBookOutput(
      OrderSide side, MarginBigDecimal price, MarginBigDecimal quantity) {
    orderbookService.update(new OrderBookOutput(side, price, quantity, symbol));
  }

  public void commit() {
    processingEntities.clear();
  }

  public void rollback() {
    if (processingEntities.isEmpty()) {
      return;
    }
    TreeSet<Order> queue = getPendingOrdersQueue(processingEntities.get(0).getSide());
    processingEntities.forEach(queue::add);
  }

  private void log(String message, Object... params) {
    log.info(message, params);
  }
}

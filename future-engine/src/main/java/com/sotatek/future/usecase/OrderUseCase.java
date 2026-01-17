package com.sotatek.future.usecase;

import static com.sotatek.future.engine.MatchingEngine.errors;
import static com.sotatek.future.engine.MatchingEngine.matchers;
import static com.sotatek.future.engine.MatchingEngine.triggers;
import static com.sotatek.future.enums.ErrorResponse.CROSS_BANKRUPT_PRICE;
import static com.sotatek.future.enums.ErrorResponse.CROSS_LIQUIDATION_PRICE;
import static com.sotatek.future.enums.ErrorResponse.EXCEED_RISK_LIMIT;
import static com.sotatek.future.enums.ErrorResponse.INSUFFICIENT_BALANCE;
import static com.sotatek.future.enums.ErrorResponse.INSUFFICIENT_QUANTITY;
import static com.sotatek.future.enums.ErrorResponse.LOCK_PRICE;
import static com.sotatek.future.enums.ErrorResponse.POST_ONLY;
import static com.sotatek.future.enums.ErrorResponse.REDUCE_ONLY;

import com.sotatek.future.engine.Matcher;
import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.engine.Trigger;
import com.sotatek.future.entity.*;
import com.sotatek.future.enums.*;
import com.sotatek.future.exception.AdjustMarginException;
import com.sotatek.future.exception.CrossBankruptPriceException;
import com.sotatek.future.exception.CrossLiquidationPriceException;
import com.sotatek.future.exception.ExceedRiskLimitException;
import com.sotatek.future.exception.InsufficientBalanceException;
import com.sotatek.future.exception.InsufficientQuantityException;
import com.sotatek.future.exception.InvalidateOrderStatusException;
import com.sotatek.future.exception.LockPriceException;
import com.sotatek.future.exception.PositionNotFoundException;
import com.sotatek.future.exception.PostOnlyOrderException;
import com.sotatek.future.exception.ReduceOnlyException;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.MarginHistoryService;
import com.sotatek.future.service.OrderService;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;
import org.apache.commons.lang3.StringUtils;
import org.apache.commons.lang3.tuple.Pair;

@RequiredArgsConstructor
@Slf4j
public class OrderUseCase {

  private final OrderService orderService;

  private final PositionService positionService;

  private final AccountService accountService;

  private final MarginHistoryService marginHistoryService;

  private final MatchingEngine matchingEngine;

  private Matcher matcher;

  private Trigger trigger;

  /**
   * Method handles for edit tp/sl price of open order
   *
   * @param command
   */
  public void updateTpSlPrice(Command command) {
    log.debug("updateTpSlPrice with {}", command);
    AdjustTpSlPrice adjustTpSlPrice = command.getAdjustTpSlPrice();
    try {
      Long tpOrderId = adjustTpSlPrice.getTpOrderId();
      if (ObjectUtils.isNotEmpty(tpOrderId)) {
        // update price for tpOrder
        Order tpOrder = orderService.get(tpOrderId);
        log.debug("updateTpSlPrice with tpOrder {}", tpOrder);
        if (ObjectUtils.isNotEmpty(tpOrder) && !tpOrder.isClosed()) {
          tpOrder.setTpSLPrice(adjustTpSlPrice.getTpOrderChangePrice());
          boolean isTriggerChange = false;
          // old queue key
          String queueKey = tpOrder.getTrigger().toString() + tpOrder.getStopCondition().toString();
          if (adjustTpSlPrice.getTpOrderTrigger() != null) {
            tpOrder.setTrigger(adjustTpSlPrice.getTpOrderTrigger());
            isTriggerChange = true;
          }
          orderService.update(tpOrder);
          // update on trigger queue
          if (tpOrder.isUntriggered()) {
            // update status on trigger queue
            trigger.updateTpSlPrice(queueKey, isTriggerChange, tpOrder);
          }
        }
      }
      Long slOrderId = adjustTpSlPrice.getSlOrderId();
      if (ObjectUtils.isNotEmpty(slOrderId)) {
        // update price for slOrder
        Order slOrder = orderService.get(slOrderId);
        log.debug("updateTpSlPrice with slOrder {}", slOrder);
        if (ObjectUtils.isNotEmpty(slOrder) && !slOrder.isClosed()) {
          slOrder.setTpSLPrice(adjustTpSlPrice.getSlOrderChangePrice());
          boolean isTriggerChange = false;
          // old queue key
          String queueKey = slOrder.getTrigger().toString() + slOrder.getStopCondition().toString();
          if (adjustTpSlPrice.getSlOrderTrigger() != null) {
            slOrder.setTrigger(adjustTpSlPrice.getSlOrderTrigger());
            isTriggerChange = true;
          }
          orderService.update(slOrder);
          // update on trigger queue
          if (slOrder.isUntriggered()) {
            // update status on trigger queue
            trigger.updateTpSlPrice(queueKey, isTriggerChange, slOrder);
          }
        }
      }
    } catch (Exception e) {
      log.error(e.getMessage(), e);
      orderService.rollback();
    }
    matchingEngine.commit();
  }

  /**
   * Method to handle update leverage of order/position of user
   *
   * @param command
   */
  public void updateLeverage(Command command) {
    AdjustLeverage adjustLeverage = command.getAdjustLeverage();
    if (adjustLeverage.getLeverage() != null
        && adjustLeverage.getLeverage().eq(adjustLeverage.getOldLeverage())) {
      log.info("Not change leverage");
      // commit to output stream
      adjustLeverage.setStatus(LeverageUpdateStatus.SUCCESS);
      matchingEngine.commit();
      return;
    }
    try {
      // get position with accountId and symbol
      Position position =
          positionService.get(adjustLeverage.getAccountId(), adjustLeverage.getSymbol());
      if (position == null) {
        throw new PositionNotFoundException("Position not found");
      }
      if (adjustLeverage.getLeverage().lt(position.getLeverage())
          && position.isIsolated()
          && !position.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
        log.debug(
            "account {} having an isolated position can't be adjust leverage",
            position.getAccountId());
        throw new AdjustMarginException(position.getId());
      }
      // update new leverage
      position.setLeverage(adjustLeverage.getLeverage());
      positionService.update(position);
      // update leverage of all order belong to account and symbol
      orderService.getUserActiveOrders(adjustLeverage.getAccountId()).stream()
          .filter(e -> e.getSymbol().equals(adjustLeverage.getSymbol()))
          .forEach(
              e -> {
                // order cost after adjust leverage is calculate by:
                // new originCost = old originCost * (old leverage/new leverage)
                MarginBigDecimal newOriginCost =
                    e.getOriginalCost()
                        .multiplyThenDivide(e.getLeverage(), adjustLeverage.getLeverage())
                        .add(MarginBigDecimal.valueOf("0.00000001"));
                // new orderCost = old orderCost * (remaining/quantity)
                MarginBigDecimal newOrderCost =
                    newOriginCost.multiplyThenDivide(e.getRemaining(), e.getQuantity());
                // subtract old order cost from position
                position.setOrderCost(position.getOrderCost().subtract(e.getCost()));
                // add a small adder to new cost for round number when calc
                e.setOriginalCost(newOriginCost);
                e.setCost(newOrderCost);
                // add new order cost to position
                position.setOrderCost(position.getOrderCost().add(e.getCost()));

                MarginBigDecimal newOriginOrderMargin =
                    e.getOriginalOrderMargin()
                        .multiply(e.getLeverage())
                        .divide(adjustLeverage.getLeverage())
                        .add(MarginBigDecimal.valueOf("0.00000001"));
                MarginBigDecimal newOrderMargin =
                    newOriginOrderMargin.multiplyThenDivide(e.getRemaining(), e.getQuantity());
                // subtract old order margin from position
                if (e.isBuyOrder()) {
                  position.setMarBuy(position.getMarBuy().subtract(e.getOrderMargin()));
                } else {
                  position.setMarSel(position.getMarSel().subtract(e.getOrderMargin()));
                }
                // add a small adder to new margin for round number when calc
                e.setOriginalOrderMargin(newOriginOrderMargin);
                e.setOrderMargin(newOrderMargin);
                // add new order margin to position
                if (e.isBuyOrder()) {
                  position.setMarBuy(position.getMarBuy().add(e.getOrderMargin()));
                } else {
                  position.setMarSel(position.getMarSel().add(e.getOrderMargin()));
                }
                // update leverage for order
                e.setLeverage(adjustLeverage.getLeverage());
                // set to processing queue
                orderService.update(e);
              });
      // re-update position
      positionService.update(position);
      // change update status
      adjustLeverage.setStatus(LeverageUpdateStatus.SUCCESS);
      // Note just get account to push to output stream for backend
      Account account = accountService.get(adjustLeverage.getAccountId());
      // validate available balance
      accountService.validateAccount(account);
      accountService.update(account);
    } catch (AdjustMarginException ignored) {
      orderService.rollback();
      positionService.rollback();
      adjustLeverage.setStatus(LeverageUpdateStatus.FAILED);
    } catch (Exception e) {
      log.error(e.getMessage(), e);
      orderService.rollback();
      positionService.rollback();
      adjustLeverage.setStatus(LeverageUpdateStatus.FAILED);
    }
    // commit to output stream
    matchingEngine.commit();
  }

  /**
   * Method to handle cancel order logic of user
   *
   * @param command
   */
  public void cancelOrder(Command command) {
    log.debug("cancelOrder");
    Order cancelOrder = command.getOrder();
    if (ObjectUtils.isNotEmpty(cancelOrder.getTakeProfitOrderId())
        || ObjectUtils.isNotEmpty(cancelOrder.getStopLossOrderId())) {
      // cancel order is a parent order then cancel its child order (tp/sl order)
      Order tpOrder = orderService.get(cancelOrder.getTakeProfitOrderId());
      Order slOrder = orderService.get(cancelOrder.getStopLossOrderId());
      if (ObjectUtils.isNotEmpty(tpOrder)) {
        cancelOrder(tpOrder);
      }
      if (ObjectUtils.isNotEmpty(slOrder)) {
        cancelOrder(slOrder);
      }
    }
    Order updatedOrder = orderService.get(cancelOrder.getKey());
    if (updatedOrder == null) {
      if (!cancelOrder.canBeActivated()) {
        log.debug(
            "Cannot find Order ({}, {}) to cancel. Ignore command.",
            cancelOrder.getId(),
            cancelOrder.getStatus());
        return;
      }
    } else {
      if (updatedOrder.canBeActivated()
          || updatedOrder.canBeMatched()
          || updatedOrder.isUntriggered()) {
        cancelOrder = updatedOrder;
        // cancel below
      } else {
        log.debug(
            "Cannot cancel Order ({}, {}). Origin Order ({}, {})",
            updatedOrder.getId(),
            updatedOrder.getStatus(),
            cancelOrder.getId(),
            cancelOrder.getStatus());
        return;
      }
    }
    Matcher matcher = matchers.get(cancelOrder.getSymbol());
    matcher.cancelOrder(cancelOrder);
    matcher.commit();
    matchingEngine.commit();
  }

  /**
   * Method to handle place order logic of user
   *
   * @param command
   */
  public void placeOrder(Command command) {
    Order order = command.getOrder();
    Order originOrder = order.deepCopy();
    // get right matcher corresponding to pair symbol
    matcher = matchers.get(order.getSymbol());
    try {
      if (CommandCode.TRIGGER_ORDER.equals(command.getCode())) {
        // trigger a stop order
        triggerOrder(order, command, matchingEngine);
      } else {
        // load/place order
        if (order.isStopOrder() && !order.isActive()) {
          trigger = triggers.get(order.getSymbol());
          placeStopOrder(order, command, matchingEngine);
        } else {
          placeOrder(order, command, matchingEngine);
        }
      }
    } catch (InsufficientBalanceException exception) {
      if (exception.getAccount() != null &&
              StringUtils.isNotBlank(exception.getAccount().getUserEmail()) &&
              exception.getAccount().getUserEmail().startsWith("bot")) {
        log.atDebug()
                .setCause(exception)
                .addKeyValue("orderId", originOrder.getId())
                .addKeyValue("accountId", exception.getAccount().getId())
                .log("Insufficient balance, rollback and cancel.");
      } else {
        log.atError()
                .setCause(exception)
                .addKeyValue("orderId", originOrder.getId())
                .addKeyValue("accountId", exception.getAccount().getId())
                .log("Insufficient balance, rollback and cancel.");
      }

      commitErrorHistoryIfNeeded();
      matchingEngine.rollback();
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(INSUFFICIENT_BALANCE.getCode())
              .messages(INSUFFICIENT_BALANCE.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (InsufficientQuantityException exception) {
      log.atError()
          .setCause(exception)
          .addKeyValue("orderId", originOrder.getId())
          .log("Cannot fill FOK order, rollback and cancel");
      matchingEngine.rollback();
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(INSUFFICIENT_QUANTITY.getCode())
              .messages(INSUFFICIENT_QUANTITY.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (ExceedRiskLimitException exception) {
      log.atError()
          .setCause(exception)
          .addKeyValue("orderId", originOrder.getId())
          .log("Risk limit exceed, rollback and cancel");
      matchingEngine.rollback();
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(EXCEED_RISK_LIMIT.getCode())
              .messages(EXCEED_RISK_LIMIT.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (PostOnlyOrderException exception) {
      log.atError()
          .setCause(exception)
          .addKeyValue("orderId", originOrder.getId())
          .log("Cancel post only order");
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(POST_ONLY.getCode())
              .messages(POST_ONLY.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (CrossLiquidationPriceException exception) {
      log.atError()
          .setCause(exception)
          .addKeyValue("orderId", order.getId())
          .log("Invalid order price. [price={}]", order.getPrice());
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(CROSS_LIQUIDATION_PRICE.getCode())
              .messages(CROSS_LIQUIDATION_PRICE.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (CrossBankruptPriceException exception) {
      log.atError()
          .setCause(exception)
          .addKeyValue("orderId", order.getId())
          .log("Invalid order price. [price={}]", order.getPrice());
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(CROSS_BANKRUPT_PRICE.getCode())
              .messages(CROSS_BANKRUPT_PRICE.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (ReduceOnlyException exception) {
      log.atError()
          .setCause(exception)
          .addKeyValue("orderId", originOrder.getId())
          .log("Reduce only exception for order");
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(REDUCE_ONLY.getCode())
              .messages(REDUCE_ONLY.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (LockPriceException exception) {
      log.atError()
          .setCause(exception)
          .addKeyValue("orderId", originOrder.getId())
          .log("Cannot lock order, rollback and cancel");
      matcher.rollback();
      errors.add(
          CommandError.builder()
              .userId(originOrder.getUserId())
              .accountId(originOrder.getAccountId())
              .code(LOCK_PRICE.getCode())
              .messages(LOCK_PRICE.getMessages())
              .build());
      cancelOrder(originOrder);
    } catch (Exception e) {
      // TODO just log error to keep main thread is not stop
      log.atError()
          .setCause(e)
          .addKeyValue("orderId", originOrder.getId())
          .log("Unknown exception");
      matcher.rollback();
      matchingEngine.rollback();
    }
  }

  /**
   * Trigger order
   *
   * @param targetOrder target order to trigger
   * @param command original command
   * @param matchingEngine
   */
  private void triggerOrder(Order targetOrder, Command command, MatchingEngine matchingEngine) {
    handleReduceOnly(targetOrder);
    // after handle reduce only then order maybe cancelled
    if (!targetOrder.isCanceled()) {
      if (OrderType.MARKET.equals(targetOrder.getType())) {
        // Create an insurance order to match with target order
        Order insuranceOrder = orderService.createInsuranceLimitOrder(targetOrder);
        try {
          insuranceOrder.setLockPrice(targetOrder.getTpSLPrice());
          insuranceOrder.setPrice(targetOrder.getTpSLPrice());
          log.atDebug()
                  .addKeyValue("orderId", insuranceOrder.getId())
                  .addKeyValue("status", insuranceOrder.getStatus())
                  .log("Activating insurance order");
          orderService.activateOrder(insuranceOrder);
          log.atDebug()
                  .addKeyValue("orderId", insuranceOrder.getId())
                  .addKeyValue("status", insuranceOrder.getStatus())
                  .log("Finish activating insurance order");
          orderService.reactivateOrder(targetOrder, targetOrder.getTpSLPrice());
//        matcher.reactivateOrder(targetOrder);
          Pair<Order, Trade> result = orderService.matchOrders(targetOrder, insuranceOrder);
          Trade trade = result.getRight();
//        log.info("Liquidation trade: {}", trade);
        } catch (InsufficientBalanceException e) {
          log.atWarn()
                  .setCause(e)
                  .addKeyValue("orderId", insuranceOrder.getId())
                  .addKeyValue("status", insuranceOrder.getStatus())
                  .log("Not enough fund to liquidate with insurance fund");
          throw e;
        }
      } else {
        // reactive order
        matcher.reactivateOrder(targetOrder);
        while (matcher.processOrder(targetOrder)) {
          matchingEngine.commit();
        }
      }
    }
    matchingEngine.commit();
    matcher.commit();
  }

  /**
   * Handle a stop order
   *
   * @param targetOrder target order to handle
   */
  private void placeStopOrder(Order targetOrder, Command command, MatchingEngine matchingEngine) {
    log.debug("Place Stop Order {}", targetOrder);
    handleReduceOnly(targetOrder);
    resetHiddenTpSlOrder(targetOrder);
    // after handle reduce only then order maybe cancelled
    if (!targetOrder.isCanceled()) {
      switch (targetOrder.getStatus()) {
        case PENDING -> {
          targetOrder.setStatus(OrderStatus.UNTRIGGERED);
          orderService.update(targetOrder);
        }
        case UNTRIGGERED -> orderService.update(targetOrder);
        default -> throw new InvalidateOrderStatusException(
            "Unknown tp/sl order status: " + targetOrder.getStatus());
      }
      trigger.processOrder(targetOrder);
    }
    matchingEngine.commit();
  }

  /**
   * reset isHidden for tp/sl order when parent order is matched
   *
   * @param targetOrder tp/sl order which need to reset isHidden
   */
  private void resetHiddenTpSlOrder(Order targetOrder) {
    if (isParentOrderMatched(targetOrder)) {
      // reset isHidden
      targetOrder.setHidden(false);
    }
  }

  private boolean isParentOrderMatched(Order tpSlOrder) {
    // get parentOrderId from tpSlOrder
    Long parentOrderId = tpSlOrder.getParentOrderId();
    if (ObjectUtils.isNotEmpty(parentOrderId)) {
      // targetOrder is a tp/sl order
      Order parentOrder = orderService.get(parentOrderId);
      // parent order is matched then quantity != remaining
      if (ObjectUtils.isNotEmpty(parentOrder)
          && !parentOrder.getQuantity().eq(parentOrder.getRemaining())) {
        return true;
      }
    }
    return false;
  }

  /**
   * Handle place normal order
   *
   * @param targetOrder target order to handle
   */
  private void placeOrder(Order targetOrder, Command command, MatchingEngine matchingEngine) {
    log.debug("Place Order {}", targetOrder);
    handleReduceOnly(targetOrder);
    // after handle reduce only then order maybe cancelled
    if (!targetOrder.isCanceled()) {
      switch (targetOrder.getStatus()) {
        case PENDING -> matcher.activateOrder(targetOrder);
        case ACTIVE -> {
          orderService.update(targetOrder);
          // just need to save to service, no need to save to database
          orderService.commit();
        }
        default -> throw new InvalidateOrderStatusException(
            "Unknown order status: " + targetOrder.getStatus());
      }
      while (matcher.processOrder(targetOrder)) {
        matchingEngine.commit();
      }
    }
    matchingEngine.commit();
    matcher.commit();
  }

  /**
   * Handle reduce only order
   *
   * @param reduceOnlyOrder order to handle reduce only
   */
  private void handleReduceOnly(Order reduceOnlyOrder) {
    // check order is reduce only or skip
    if (!reduceOnlyOrder.isReduceOnly()) {
      log.debug("Skip handle reduce only with order {}", reduceOnlyOrder.getId());
      return;
    }
    // get key of position
    String key = reduceOnlyOrder.getAccountId() + reduceOnlyOrder.getSymbol();
    // get position of this account and symbol
    Position position = positionService.get(key);
    // if account hold a position
    if (ObjectUtils.isNotEmpty(position) && !position.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
      // get position quantity
      // a long position has quantity > 0
      // a short position has quantity < 0
      MarginBigDecimal positionQuantity = position.getCurrentQty();
      boolean isLongPosition = positionQuantity.gt(MarginBigDecimal.ZERO);
      boolean isOrderSameSideOfPosition =
          (isLongPosition && reduceOnlyOrder.isBuyOrder())
              || (!isLongPosition && reduceOnlyOrder.isSellOrder());
      boolean isOrderNotTpSlOrder = !reduceOnlyOrder.isTpSlOrder();
      boolean isParentOrderMatched = isParentOrderMatched(reduceOnlyOrder);
      if ((isOrderNotTpSlOrder || reduceOnlyOrder.isTriggered() || isParentOrderMatched)
          && isOrderSameSideOfPosition) {
        // reduce only order on the same side of position then reject order
        orderService.cancel(reduceOnlyOrder);
      } else {
        // reduce only order is opposite side of position then check size of order
        if (isOrderNotTpSlOrder
            && !reduceOnlyOrder.isTriggered()
            && reduceOnlyOrder.getRemaining().gt(positionQuantity.abs())) {
          // reject with order has size > position size
          orderService.cancel(reduceOnlyOrder);
        }
      }
    } else {
      log.debug("User do not hold a position");
      if (!reduceOnlyOrder.isStopOrder()) {
        // user does not hold a position then cancel normal reduce only order (order is not a stop
        // order)
        orderService.cancel(reduceOnlyOrder);
      } else {
        // if order is stop order then check parent order is cancel or filled => cancel it too
        Long parentOrderId = reduceOnlyOrder.getParentOrderId();
        if (ObjectUtils.isNotEmpty(parentOrderId)) {
          Order parentOrder = orderService.get(parentOrderId);
          if (ObjectUtils.isNotEmpty(parentOrder) && parentOrder.isClosed()) {
            orderService.cancel(reduceOnlyOrder);
          }
        }
      }
    }
  }

  private void cancelOrder(Order order) {
    Command cancelCommand = new Command(CommandCode.CANCEL_ORDER, order);
    cancelCommand.setExtraData(false);
    cancelOrder(cancelCommand);
  }

  private void commitErrorHistoryIfNeeded() {
    boolean shouldCommit = false;
    for (MarginHistory history : marginHistoryService.getProcessingEntities()) {
      if (history.isError()) {
        shouldCommit = true;
        break;
      }
    }
    if (shouldCommit) {
      marginHistoryService.commit();
    }
  }
}

package com.sotatek.future.usecase;

import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.AdjustMarginPosition;
import com.sotatek.future.entity.AdjustTpSl;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.TpSlOrder;
import com.sotatek.future.enums.AdjustMarginPositionStatus;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.TpSlAction;
import com.sotatek.future.exception.InsufficientBalanceException;
import com.sotatek.future.exception.InvalidCommandException;
import com.sotatek.future.exception.OrderStateInvalidException;
import com.sotatek.future.exception.PositionNotFoundException;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.LiquidationService;
import com.sotatek.future.service.MarginCalculator;
import com.sotatek.future.service.OrderService;
import com.sotatek.future.service.PositionCalculator;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;

@RequiredArgsConstructor
@Slf4j
public class PositionUseCase {

  private final PositionService positionService;

  private final OrderService orderService;

  private final AccountService accountService;

  private final MatchingEngine matchingEngine;

  private final PositionCalculator positionCalculator;

  private final LiquidationService liquidationService;

  public void execute(Command command) {
    Position data = command.getPosition();
    positionService.update(data);
    positionService.commit();
  }

  public void adjustMarginPosition(Command command) {
    AdjustMarginPosition param = (AdjustMarginPosition) command.getData();
    log.info("adjustMarginPosition {}", param);
    Position position = positionService.get(param.getAccountId(), param.getSymbol());
    if (position == null) {
      throw new PositionNotFoundException(
          "accountId " + param.getAccountId() + " symbol " + param.getSymbol());
    }
    if (position.isCross()) {
      throw new RuntimeException("not support for cross position");
    }
    log.debug(
        "before adjust margin position id:{} value:{}",
        position.getId(),
        position.getAdjustMargin());
    MarginBigDecimal adjustValue = param.getAssignedMarginValue();
    param.setStatus(AdjustMarginPositionStatus.SUCCESS);
    if (adjustValue.gte(MarginBigDecimal.ZERO)) {
      // add margin, max addable = available balance
      MarginBigDecimal availableBalance =
          accountService.getAccountAvailableBalance(position.getAccountId());
      log.debug("add margin {} availableBalance {}", position, availableBalance);
      if (adjustValue.gt(availableBalance)) {
        log.error(
            "Insufficient balance for adjust margin accountId {} positionId {} avaiBalance {}"
                + " addMargin {}",
            position.getAccountId(),
            position.getId(),
            availableBalance,
            adjustValue);
        param.setStatus(AdjustMarginPositionStatus.FAILED);
      }
    } else {
      MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(position.getSymbol());
      MarginBigDecimal markPrice = marginCalculator.getOraclePrice();
      MarginBigDecimal allocatedMargin = marginCalculator.calcAllocatedMargin(position);
      MarginBigDecimal maxRemovable =
          marginCalculator.getMaxRemovableAdjustMargin(allocatedMargin, markPrice, position);
      log.debug(
          "Remove margin {} markPrice {} maxRemovable {} removeValue {}",
          position,
          markPrice,
          maxRemovable,
          adjustValue);
      if (adjustValue.abs().gt(maxRemovable)) {
        log.error(
            "Remove margin exceed max removable {}, removeValue {}", maxRemovable, adjustValue);
        param.setStatus(AdjustMarginPositionStatus.FAILED);
      }
    }

    if (param.getStatus().equals(AdjustMarginPositionStatus.SUCCESS)) {
      position.setAdjustMargin(position.getAdjustMargin().add(adjustValue));
      log.debug(
          "after adjust margin position id:{} value:{}",
          position.getId(),
          position.getAdjustMargin());
      positionService.update(position);
      // check if allocated margin <= maintenance margin then liquidate this position
      MarginBigDecimal allocatedMargin =
          position.getPositionMargin().add(position.getAdjustMargin());
      MarginBigDecimal maintenanceMargin = positionCalculator.getMaintenanceMargin(position);
      if (allocatedMargin.lte(maintenanceMargin)
          && !position
              .getAccountId()
              .equals(accountService.getInsuranceAccountId(position.getAsset()))
          && !accountService.checkIsBotAccountId(position.getAccountId())) {
        // liquidate this position
        try {
          liquidationService.liquidate(position);
        } catch (InsufficientBalanceException e) {
          // set adjust margin failed when insufficient balance
          log.atError()
              .addKeyValue("account", e.getAccount())
              .log("InsufficientBalance when liquidate position");
          param.setStatus(AdjustMarginPositionStatus.FAILED);
        }
      }
      // Noted just push account to output stream for backend
      Account account = accountService.get(position.getAccountId());
      accountService.update(account);
    }
    matchingEngine.commit();
  }

  /**
   * Update tp/sl for position
   *
   * @param command
   */
  public void updateTpSl(Command command) {
    AdjustTpSl adjustTpSl = command.getAdjustTpSl();
    if (ObjectUtils.isEmpty(adjustTpSl)) {
      log.error("Invalid command data {}", command);
      return;
    }
    try {
      // handle update for position
      Position position = positionService.get(adjustTpSl.getKey());
      if (ObjectUtils.isEmpty(position)) {
        log.error("Can not update for position with id {}", adjustTpSl.getKey());
        throw new InvalidCommandException("Invalid adjust TP/SL command " + command);
      }
      // handle for tp/sl order
      if (ObjectUtils.isNotEmpty(adjustTpSl.getTpOrder())) {
        handleTpSlOrder(adjustTpSl.getTpOrder(), true, position);
      }
      if (ObjectUtils.isNotEmpty(adjustTpSl.getSlOrder())) {
        handleTpSlOrder(adjustTpSl.getSlOrder(), false, position);
      }
      positionService.update(position);
      // Noted just push account to output stream for backend
      Account account = accountService.get(position.getAccountId());
      accountService.update(account);
    } catch (Exception e) {
      matchingEngine.rollback();
    }
    matchingEngine.commit();
  }

  private void handleTpSlOrder(TpSlOrder tpSlOrder, boolean isTpOrder, Position currentPosition) {
    if (TpSlAction.PLACE.equals(tpSlOrder.getAction())) {
      // place new order
      Order oldOrder = orderService.get(tpSlOrder.getKey());
      if (ObjectUtils.isNotEmpty(oldOrder)) {
        log.error("Existed order {}", tpSlOrder);
        throw new OrderStateInvalidException("Existed order with id {}" + oldOrder.getKey());
      }
      if (isTpOrder) {
        // set tpOrderId link to position
        currentPosition.setTakeProfitOrderId(tpSlOrder.getId());
        // check if current position has old slOrder
        if (ObjectUtils.isNotEmpty(currentPosition.getStopLossOrderId())) {
          // update new tpOrderId to linkedOrderId of slOrder
          Order slOrder = orderService.get(currentPosition.getStopLossOrderId());
          if (ObjectUtils.isNotEmpty(slOrder)) {
            // link tpOrder with slOrder
            slOrder.setLinkedOrderId(tpSlOrder.getId());
            tpSlOrder.setLinkedOrderId(slOrder.getId());
            orderService.update(slOrder);
          }
        }
      } else {
        // set slOrderId to position
        currentPosition.setStopLossOrderId(tpSlOrder.getId());
        // check if current position has old tpOrder
        if (ObjectUtils.isNotEmpty(currentPosition.getTakeProfitOrderId())) {
          // update new slOrderId to linkedOrderId of tpOrder
          Order tpOrder = orderService.get(currentPosition.getTakeProfitOrderId());
          if (ObjectUtils.isNotEmpty(tpOrder)) {
            // link tpOrder with slOrder
            tpOrder.setLinkedOrderId(tpSlOrder.getId());
            tpSlOrder.setLinkedOrderId(tpOrder.getId());
            orderService.update(tpOrder);
          }
        }
      }
      // create command to place
      Order order = tpSlOrder.cloneOrder();
      Command placeCommand = new Command(CommandCode.PLACE_ORDER, order);
      matchingEngine.onReceiveCommand(placeCommand);
    }
    if (TpSlAction.CANCEL.equals(tpSlOrder.getAction())) {
      // cancel order
      Order oldOrder = orderService.get(tpSlOrder.getKey());
      if (ObjectUtils.isEmpty(oldOrder)) {
        log.error("Do not exist order to cancel {}", tpSlOrder);
        throw new OrderStateInvalidException(
            "Do not exist order to cancel id " + tpSlOrder.getKey());
      }
      if (isTpOrder) {
        // remove tpOrderId link to position
        currentPosition.setTakeProfitOrderId(null);
        // check if current position has old slOrder
        if (ObjectUtils.isNotEmpty(currentPosition.getStopLossOrderId())) {
          // remove linkedOrderId of slOrder
          Order slOrder = orderService.get(currentPosition.getStopLossOrderId());
          if (ObjectUtils.isNotEmpty(slOrder)) {
            slOrder.setLinkedOrderId(null);
            tpSlOrder.setLinkedOrderId(null);
            orderService.update(slOrder);
          }
        }
      } else {
        // cancel slOrderId link to position
        currentPosition.setStopLossOrderId(null);
        // check if current position has old tpOrder
        if (ObjectUtils.isNotEmpty(currentPosition.getTakeProfitOrderId())) {
          // remove linkedOrderId of tpOrder
          Order tpOrder = orderService.get(currentPosition.getTakeProfitOrderId());
          if (ObjectUtils.isNotEmpty(tpOrder)) {
            tpOrder.setLinkedOrderId(null);
            tpSlOrder.setLinkedOrderId(null);
            orderService.update(tpOrder);
          }
        }
      }
      // create command to cancel
      Command cancelCommand = new Command(CommandCode.CANCEL_ORDER, oldOrder);
      matchingEngine.onReceiveCommand(cancelCommand);
    }
  }
}

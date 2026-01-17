package com.sotatek.future.entity;

import com.sotatek.future.enums.MarginHistoryAction;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Date;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@ToString
@Getter
@Setter
public class MarginHistory extends BaseEntity {
  private Long accountId;
  private MarginHistoryAction action;
  private Long orderId;
  private Long tradeId;
  private String tradeUuid;
  private Long positionId;
  private MarginBigDecimal leverage;
  private MarginBigDecimal leverageAfter;
  private MarginBigDecimal entryPrice;
  private MarginBigDecimal entryPriceAfter;
  private MarginBigDecimal entryValue;
  private MarginBigDecimal entryValueAfter;
  private MarginBigDecimal currentQty;
  private MarginBigDecimal currentQtyAfter;
  private Integer liquidationProgress;
  private Integer liquidationProgressAfter;
  private MarginBigDecimal pnlRanking;
  private MarginBigDecimal pnlRankingAfter;
  private MarginBigDecimal balance;
  private MarginBigDecimal balanceAfter;
  private MarginBigDecimal orderValue;
  private MarginBigDecimal contractMargin;
  private MarginBigDecimal realizedPnl;
  private MarginBigDecimal tradePrice;
  private MarginBigDecimal fee;
  private MarginBigDecimal openFee;
  private MarginBigDecimal closeFee;

  public static MarginHistory from(
      MarginHistoryAction action,
      Position oldPosition,
      Position newPosition,
      Account oldAccount,
      Account newAccount,
      Order order) {
    MarginHistory history = from(action, oldPosition, newPosition, oldAccount, newAccount);

    if (order != null) {
      history.orderId = order.getId();
      history.orderValue = order.getOrderValue();
    }
    return history;
  }

  public static MarginHistory from(
      MarginHistoryAction action,
      Position oldPosition,
      Position newPosition,
      Account oldAccount,
      Account newAccount,
      Trade trade) {
    MarginHistory history = from(action, oldPosition, newPosition, oldAccount, newAccount);

    if (trade != null) {
      if (action == MarginHistoryAction.MATCHING_BUY) {
        history.orderId = trade.getBuyOrderId();
        history.orderValue = trade.getQuantity();
      }
      if (action == MarginHistoryAction.MATCHING_SELL) {
        history.orderId = trade.getSellOrderId();
        history.orderValue = trade.getQuantity();
      }
      history.tradeId = trade.getId();
      history.tradeUuid = trade.getUuid();
    }

    return history;
  }

  public static MarginHistory from(
      MarginHistoryAction action,
      Position oldPosition,
      Position newPosition,
      Account oldAccount,
      Account newAccount) {

    MarginHistory history = new MarginHistory();
    history.accountId = oldAccount.id;
    history.action = action;
    history.positionId = oldPosition != null ? oldPosition.id : null;

    if (oldPosition != null) {
      history.leverage = oldPosition.getLeverage();
      history.entryPrice = oldPosition.getEntryPrice();
      history.entryValue = oldPosition.getEntryValue();
      history.currentQty = oldPosition.getCurrentQty();
      history.liquidationProgress = oldPosition.getLiquidationProgress();
      history.pnlRanking = oldPosition.getPnlRanking();
    }

    if (newPosition != null) {
      history.leverageAfter = newPosition.getLeverage();
      history.entryPriceAfter = newPosition.getEntryPrice();
      history.entryValueAfter = newPosition.getEntryValue();
      history.currentQtyAfter = newPosition.getCurrentQty();
      history.liquidationProgressAfter = newPosition.getLiquidationProgress();
      history.pnlRankingAfter = newPosition.getPnlRanking();
    }

    history.balance = oldAccount.getBalance();
    history.balanceAfter = newAccount.getBalance();
    history.createdAt = new Date();
    history.updatedAt = new Date();
    return history;
  }

  public boolean isError() {
    return this.action == MarginHistoryAction.MATCHING_ERROR
        || this.action == MarginHistoryAction.CANCEL_ERROR;
  }

  @Override
  public Object getKey() {
    return this.id;
  }
}

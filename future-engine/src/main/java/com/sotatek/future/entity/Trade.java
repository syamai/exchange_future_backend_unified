package com.sotatek.future.entity;

import com.sotatek.future.enums.ContractType;
import com.sotatek.future.enums.OrderNote;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Date;
import java.util.UUID;

import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@ToString(callSuper = true)
public class Trade extends BaseEntity {
  private String symbol; /////
  // account id of buy
  private Long buyAccountId;
  // account id of sell
  private Long sellAccountId;
  private Long buyUserId;
  private Long sellUserId;
  private Order buyOrder;
  private Order sellOrder;
  // order id of buy
  private Long buyOrderId;
  // order id of sell
  private Long sellOrderId;
  private boolean buyerIsTaker; //////
  // quantity when matching two order
  private MarginBigDecimal quantity; //////
  // this is price when two order matched together
  // this is equal "exit price" when it's close position
  private MarginBigDecimal price; //////

  // fee amount of buy/sell
  private MarginBigDecimal buyFee;
  private MarginBigDecimal sellFee;
  private OrderNote note;
  // buy fee rate (percentage)
  private MarginBigDecimal buyFeeRate;
  // sell fee rate (percentage)
  private MarginBigDecimal sellFeeRate;
  // realized PNL of buy/sell order if they have
  private MarginBigDecimal realizedPnlOrderBuy;
  private MarginBigDecimal realizedPnlOrderSell;
  // to determine contract is usd-M or coin-M
  private ContractType contractType; //// usd-M
  // email of user who placed this order
  private String buyEmail;
  private String sellEmail;
  private String uuid;


  public Trade(Order taker, Order maker, MarginBigDecimal price, MarginBigDecimal quantity) {
    this.buyOrder = maker.isBuyOrder() ? maker : taker;
    this.sellOrder = maker.isSellOrder() ? maker : taker;
    updateNote(this.buyOrder.getNote(), this.sellOrder.getNote());
    this.buyOrderId = this.buyOrder.getId();
    this.sellOrderId = this.sellOrder.getId();
    this.buyerIsTaker = taker.isBuyOrder();
    this.price = price;
    this.quantity = quantity;
    this.symbol = buyOrder.getSymbol();
    this.buyAccountId = buyOrder.getAccountId();
    this.sellAccountId = sellOrder.getAccountId();
    this.buyUserId = buyOrder.getUserId();
    this.sellUserId = sellOrder.getUserId();
    this.contractType = buyOrder.getContractType();
    this.buyEmail = buyOrder.getUserEmail();
    this.sellEmail = sellOrder.getUserEmail();
    this.uuid = UUID.randomUUID().toString();
    this.createdAt = new Date(); //////
    this.updatedAt = new Date(); //////
  }

  public Trade() {

  }

  private void updateNote(OrderNote buyNote, OrderNote sellNote) {
    if (buyNote == null || sellNote == null) {
      if (buyNote != null) {
        this.note = buyNote;
      }
      if (sellNote != null) {
        this.note = sellNote;
      }
      return;
    }

    if (hasNote(OrderNote.INSURANCE_LIQUIDATION, buyNote, sellNote)) {
      this.note = OrderNote.INSURANCE_LIQUIDATION;
    } else if (hasNote(OrderNote.LIQUIDATION, buyNote, sellNote)) {
      this.note = OrderNote.LIQUIDATION;
    }
  }

  private boolean hasNote(OrderNote note, OrderNote buyNote, OrderNote sellNote) {
    return note.equals(buyNote) || note.equals(sellNote);
  }

  public boolean isCoinM() {
    return ContractType.COIN_M.equals(this.contractType);
  }

  @Override
  public Object getKey() {
    return this.id;
  }

  @Override
  public TradeValue getValue() {
    return new TradeValue(
        symbol, buyAccountId, sellAccountId, buyOrderId, sellOrderId, quantity, price);
  }

  record TradeValue(
      String symbol,
      Long buyAccountId,
      Long sellAccountId,
      Long buyOrderId,
      Long sellOrderId,
      MarginBigDecimal quantity,
      MarginBigDecimal price) {}
}

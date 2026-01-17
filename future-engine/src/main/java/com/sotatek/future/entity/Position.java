package com.sotatek.future.entity;

import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Date;
import java.util.List;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@ToString(callSuper = true)
public class Position extends BaseEntity {
  // to determine position is cross or isolated
  private boolean isCross;
  // id of user who have own this position
  private Long userId;
  // id of account who have own this position
  private Long accountId;
  // this is id of stop/take profit order when user set tp/sl for position
  private Long stopLossOrderId;
  private Long takeProfitOrderId;
  // this is pair string of position.For ex : BTCUSDT, ETHUSD...
  private String symbol;
  // asset of this order USD/USDT
  private Asset asset;
  // leverage of this position
  private MarginBigDecimal leverage;
  // size of this position: <0 short, >0 long
  private MarginBigDecimal currentQty;
  // entry average price of position
  private MarginBigDecimal entryPrice;
  // value of position = entryPrice * currentQty
  private MarginBigDecimal entryValue;
  // margin of position
  private MarginBigDecimal positionMargin;
  // to determine contract is usd-M or coin-M
  private ContractType contractType;
  // list to contain tp/sl order of position
  private List<Order> orders;
  // MarBuy = Total margin of all Buy open orders of this symbol of user
  private MarginBigDecimal marBuy;
  // MarSel = Total margin of all Sell open orders of this symbol of user
  private MarginBigDecimal marSel;
  // Sum of orderCost of all open order of this symbol of user
  private MarginBigDecimal orderCost;
  // Margin adjustment after initial
  private MarginBigDecimal adjustMargin;

  private int liquidationProgress;

  // The following 3 fields pnlRanking, liquidationPrice, bankruptPrice
  // will be updated automatically when we update the position
  // When the position is in cross-margin mode, these result can become outdated in a few situation
  // * mark price change
  // * account balance change
  // As a result, `update(position)` should be called when these events happen.
  private MarginBigDecimal pnlRanking;
  private MarginBigDecimal liquidationPrice;
  private MarginBigDecimal bankruptPrice;

  private MarginBigDecimal closeSize;
  private MarginBigDecimal avgClosePrice;

  // temperature total fee of position
  private MarginBigDecimal tmpTotalFee;

  // open time
  private Date lastOpenTime;

  public Position(boolean isCross) {
    super();
    this.currentQty = MarginBigDecimal.ZERO;
    this.entryPrice = MarginBigDecimal.ZERO;
    this.entryValue = MarginBigDecimal.ZERO;
    this.isCross = isCross;
    this.pnlRanking = MarginBigDecimal.ZERO;
    this.adjustMargin = MarginBigDecimal.ZERO;
    this.marBuy = MarginBigDecimal.ZERO;
    this.marSel = MarginBigDecimal.ZERO;
    this.orderCost = MarginBigDecimal.ZERO;
    this.positionMargin = MarginBigDecimal.ZERO;
    this.closeSize = MarginBigDecimal.ZERO;
    this.avgClosePrice = MarginBigDecimal.ZERO;
    this.tmpTotalFee = MarginBigDecimal.ZERO;
  }

  public Position() {
    this(true);
  }

  private Position(Position position) {
    super(position);
    setCross(position.isCross());
    setUserId(position.getUserId());
    setAccountId(position.getAccountId());
    setTakeProfitOrderId(position.getTakeProfitOrderId());
    setStopLossOrderId(position.getStopLossOrderId());
    setSymbol(position.getSymbol());
    setAsset(position.getAsset());
    setLeverage(position.getLeverage());
    setCurrentQty(position.getCurrentQty());
    setEntryPrice(position.getEntryPrice());
    setEntryValue(position.getEntryValue());
    setLiquidationProgress(position.getLiquidationProgress());
    setPnlRanking(position.getPnlRanking());
    setLiquidationPrice(position.getLiquidationPrice());
    setBankruptPrice(position.getBankruptPrice());
    setAdjustMargin(position.getAdjustMargin());
    setContractType(position.getContractType());
    setPositionMargin(position.getPositionMargin());
    setOrders(position.getOrders());
    setMarBuy(position.getMarBuy());
    setMarSel(position.getMarSel());
    setOrderCost(position.getOrderCost());
    setCloseSize(position.getCloseSize());
    setAvgClosePrice(position.getAvgClosePrice());
    setTmpTotalFee(position.getTmpTotalFee());
    setLastOpenTime(position.getLastOpenTime());
  }

  public static Position from(Instrument instrument) {
    Position position = new Position();
    position.setSymbol(instrument.getSymbol());
    position.setAdjustMargin(MarginBigDecimal.ZERO);
    position.setContractType(instrument.getContractType());
    position.setMarBuy(MarginBigDecimal.ZERO);
    position.setMarSel(MarginBigDecimal.ZERO);
    position.setOrderCost(MarginBigDecimal.ZERO);
    position.setPositionMargin(MarginBigDecimal.ZERO);
    position.setCloseSize(MarginBigDecimal.ZERO);
    position.setAvgClosePrice(MarginBigDecimal.ZERO);
    position.createdAt = new Date();
    position.updatedAt = new Date();
    return position;
  }

  public static String getKey(long accountId, String symbol) {
    return accountId + symbol;
  }

  @Override
  public String getKey() {
    return accountId + symbol;
  }

  @Override
  public Position deepCopy() {
    return new Position(this);
  }

  public boolean isCoinM() {
    return ContractType.COIN_M.equals(contractType);
  }

  public boolean isIsolated() {
    return !isCross;
  }
}

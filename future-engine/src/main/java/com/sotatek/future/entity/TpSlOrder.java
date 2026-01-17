package com.sotatek.future.entity;

import com.sotatek.future.enums.*;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.util.json.Exclude;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@ToString(callSuper = true)
public class TpSlOrder extends BaseEntity {
  private Long userId;
  // id of account who has placed this order
  private Long accountId;
  // Pair name, for ex : BTCUSDT, ETHUSDT...
  private String symbol;
  // asset of this order USD/USDT
  private Asset asset;
  // Order side BUY/SELL
  private OrderSide side;
  private OrderType type;
  // The price which user place for limit order type
  private MarginBigDecimal price;
  // amount of this order
  private MarginBigDecimal quantity;
  // remaining amount of this order on case matching a part
  // remaining = quantity - "matched quantity"
  private MarginBigDecimal remaining;
  // lock price to calculate other value to lock for this order
  private MarginBigDecimal lockPrice;
  // total value of this order when order being ACTIVE
  // it uses for save history
  // order value = lock price * multiplier * quantity
  private MarginBigDecimal orderValue;
  private MarginBigDecimal executedPrice;
  private TPSLType tpSLType;
  // price when order is stop order
  private MarginBigDecimal tpSLPrice;
  // Trigger type LAST/MARK
  private OrderTrigger trigger;
  // GTC/IOC/FOK
  private TimeInForce timeInForce;
  // trail price to tracking for trailing stop order
  private MarginBigDecimal trailPrice;
  // callback rate for order trailing stop
  private MarginBigDecimal callbackRate;
  // activation price for order trailing stop
  private MarginBigDecimal activationPrice;
  // order status
  private OrderStatus status;
  // stop condition to trigger stop order (GT|LT)
  private TriggerCondition stopCondition;
  // when order is reduce only
  private boolean isReduceOnly;
  // when order is post only
  private boolean isPostOnly;
  // Order is close for position or not
  private boolean isClosePositionOrder;
  private OrderNote note;
  // leverage of order
  private MarginBigDecimal leverage;
  // CROSS/ISOLATED
  private MarginMode marginMode;
  // to active hidden or show order ( related logic show/hidden for tp/sl order )
  private boolean isHidden;
  // to save linkedOrder ( for two order tp/sl save for other id )
  private Long linkedOrderId;
  // to save tp orderId of this order ( parent order )
  private Long takeProfitOrderId;
  // to save sl orderId of this order ( parent order )
  private Long stopLossOrderId;
  // the parent order id of two tp/sl order (child)
  private Long parentOrderId;
  // A lock amount when user place an order
  private MarginBigDecimal cost;
  // An original value cost of order before matching
  private MarginBigDecimal originalCost;
  // Order margin using to calc cost of order
  private MarginBigDecimal orderMargin;
  // An original value orderMargin of order before matching
  private MarginBigDecimal originalOrderMargin;
  // order is trigger or not
  private boolean isTriggered;
  // to determine contract is usd-M or coin-M
  private ContractType contractType;
  // email of user who placed this order
  private String userEmail;
  private boolean isTpSlOrder;

  @Exclude private long priority;

  private TpSlAction action;

  public Order cloneOrder() {
    Order order = new Order();
    order.setId(this.id);
    order.setUserId(this.userId);
    order.setAccountId(this.accountId);
    order.setSymbol(this.symbol);
    order.setAsset(this.asset);
    order.setSide(this.side);
    order.setType(this.type);
    order.setPrice(this.price);
    order.setQuantity(this.quantity);
    order.setRemaining(this.remaining);
    order.setLockPrice(this.lockPrice);
    order.setOrderValue(this.orderValue);
    order.setExecutedPrice(this.executedPrice);
    order.setTpSLType(this.tpSLType);
    order.setTpSLPrice(this.tpSLPrice);
    order.setTrigger(this.trigger);
    order.setTimeInForce(this.timeInForce);
    order.setTrailPrice(this.trailPrice);
    order.setCallbackRate(this.callbackRate);
    order.setActivationPrice(this.activationPrice);
    order.setStatus(this.status);
    order.setStopCondition(this.stopCondition);
    order.setReduceOnly(this.isReduceOnly);
    order.setPostOnly(this.isPostOnly);
    order.setClosePositionOrder(this.isClosePositionOrder);
    order.setNote(this.note);
    order.setLeverage(this.leverage);
    order.setMarginMode(this.marginMode);
    order.setPriority(this.priority);
    order.setHidden(this.isHidden);
    order.setLinkedOrderId(this.linkedOrderId);
    order.setTpSlOrder(this.isTpSlOrder);
    order.setTakeProfitOrderId(this.takeProfitOrderId);
    order.setStopLossOrderId(this.stopLossOrderId);
    order.setParentOrderId(this.parentOrderId);
    order.setCost(this.cost);
    order.setOriginalCost(this.originalCost);
    order.setOrderMargin(this.orderMargin);
    order.setOriginalOrderMargin(this.originalOrderMargin);
    order.setTriggered(this.isTriggered);
    // tp/sl order not need this field
    order.setTpSlTriggered(false);
    order.setContractType(this.contractType);
    order.setUserEmail(this.getUserEmail());
    return order;
  }

  @Override
  public Object getKey() {
    return id;
  }
}

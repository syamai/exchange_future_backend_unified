package com.sotatek.future.entity;

import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.enums.MarginMode;
import com.sotatek.future.enums.OrderNote;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderTrigger;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TPSLType;
import com.sotatek.future.enums.TimeInForce;
import com.sotatek.future.enums.TriggerCondition;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.util.json.Exclude;
import java.util.Date;
import java.util.Objects;
import java.util.StringJoiner;
import lombok.AccessLevel;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;

@Getter
@Setter
@Builder
@AllArgsConstructor(access = AccessLevel.PRIVATE)
@NoArgsConstructor
@Slf4j
public class Order extends BaseEntity {
  // id of user who has placed this order
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
  // the type of tp/sl order is the same with stop market order
  // so this field to confirm order is tp/sl
  private boolean isTpSlOrder;
  // true if linked tp/sl order is triggered
  private boolean isTpSlTriggered;
  // to determine contract is usd-M or coin-M
  private ContractType contractType;
  // email of user who placed this order
  private String userEmail;
  // tmp id sent from BE
  private String tmpId;

  @Exclude private long priority;

  // for save status when trailing stop order has meet activation price
  @Exclude private boolean isActivated;

  // Transient value used to save order pnl during liquidation for balancing
  @Exclude private MarginBigDecimal liquidationPnl = MarginBigDecimal.ZERO;
  // Transient value used to save order matching fee during liquidation
  @Exclude private MarginBigDecimal liquidationTradingFee = MarginBigDecimal.ZERO;

  public Order(
      long id, long userId, OrderSide side, OrderType type, String price, String quantity) {
    this.id = id;
    this.accountId = userId;
    this.userId = userId;
    this.side = side;
    this.type = type;
    this.price = new MarginBigDecimal(price);
    this.quantity = new MarginBigDecimal(quantity);
    this.remaining = new MarginBigDecimal(quantity);
    this.asset = Asset.USDT;
    this.contractType = ContractType.USD_M;
  }

  public Order(Order order) {
    super(order);
    setUserId(order.userId);
    setAccountId(order.accountId);
    setSymbol(order.symbol);
    setAsset(order.asset);
    setSide(order.side);
    setType(order.type);
    setPrice(order.price);
    setQuantity(order.quantity);
    setRemaining(order.remaining);
    setLockPrice(order.lockPrice);
    setOrderValue(order.orderValue);
    setExecutedPrice(order.executedPrice);
    setTpSLType(order.tpSLType);
    setTpSLPrice(order.tpSLPrice);
    setTrigger(order.trigger);
    setTimeInForce(order.timeInForce);
    setTrailPrice(order.trailPrice);
    setCallbackRate(order.callbackRate);
    setActivationPrice(order.activationPrice);
    setStatus(order.status);
    setStopCondition(order.stopCondition);
    setReduceOnly(order.isReduceOnly);
    setPostOnly(order.isPostOnly);
    setClosePositionOrder(order.isClosePositionOrder);
    setNote(order.note);
    setLeverage(order.leverage);
    setMarginMode(order.marginMode);
    setPriority(order.priority);
    setHidden(order.isHidden);
    setLinkedOrderId(order.linkedOrderId);
    setTakeProfitOrderId(order.takeProfitOrderId);
    setStopLossOrderId(order.stopLossOrderId);
    setParentOrderId(order.parentOrderId);
    setCost(order.cost);
    setOriginalCost(order.originalCost);
    setOrderMargin(order.orderMargin);
    setOriginalOrderMargin(order.originalOrderMargin);
    setTriggered(order.isTriggered);
    setTpSlTriggered(order.isTpSlTriggered);
    setTpSlOrder(order.isTpSlOrder);
    setLiquidationPnl(order.liquidationPnl);
    setContractType(order.contractType);
    setUserEmail(order.getUserEmail());
    setTmpId(order.getTmpId());
  }

  public boolean isBuyOrder() {
    return side.equals(OrderSide.BUY);
  }

  public boolean isSellOrder() {
    return side.equals(OrderSide.SELL);
  }

  public boolean isLimitOrder() {
    return type == OrderType.LIMIT;
  }

  public boolean isMarketOrder() {
    return type == OrderType.MARKET;
  }

  public boolean isUntriggered() {
    return status == OrderStatus.UNTRIGGERED;
  }

  public boolean isActive() {
    return status == OrderStatus.ACTIVE;
  }

  public boolean isCanceled() {
    return OrderStatus.CANCELED.equals(status);
  }

  public boolean isTpSlOrder() {
    // if order is a tp/sl order then it has a linkedOrderId value
    return ObjectUtils.isNotEmpty(parentOrderId);
  }

  public boolean isLiquidationOrder() {
    return note == OrderNote.LIQUIDATION;
  }

  public OrderSide getOppositeSide() {
    return isBuyOrder() ? OrderSide.SELL : OrderSide.BUY;
  }

  public boolean canBeActivated() {
    return status == OrderStatus.PENDING;
  }

  public boolean canBeMatched() {
    return status == OrderStatus.ACTIVE;
  }

  public boolean canBeCanceled() {
    return status == OrderStatus.ACTIVE
        || status == OrderStatus.UNTRIGGERED
        || status == OrderStatus.PENDING;
  }

  public void setStatus(OrderStatus status) {
    // update time when order is set to end process
    // updateAt of order will not update when order is FILLED or CANCELED
    // check override fnc in OrderService
    if (OrderStatus.FILLED.equals(status) || OrderStatus.CANCELED.equals(status)) {
      this.updatedAt = new Date();
    }
    this.status = status;
  }

  public boolean canBeMatchedWith(Order candidate) {
    if (side.equals(candidate.side)) {
      return false;
    }

    if (isMarketOrder() || candidate.isMarketOrder()) {
      return true;
    }

    if (isBuyOrder() && price.compareTo(candidate.price) >= 0) {
      return true;
    }

    return isSellOrder() && price.compareTo(candidate.price) <= 0;
  }

  public boolean isFokOrder() {
    return timeInForce == TimeInForce.FOK;
  }

  public boolean isStopOrder() {
    return tpSLType != null;
  }

  public boolean isClosed() {
    return status == OrderStatus.FILLED || status == OrderStatus.CANCELED;
  }

  public MarginBigDecimal getOrderBookQuantity() {
    if (this.isClosed()) {
      return MarginBigDecimal.ZERO;
    }
    return remaining;
  }

  public void close() {
    if (remaining.lt(quantity)) {
      if (remaining.gt(0)) {
        status = OrderStatus.CANCELED;
      } else {
        status = OrderStatus.FILLED;
      }
    } else {
      status = OrderStatus.CANCELED;
    }
  }

  @Override
  public boolean equals(Object o) {
    if (this == o) {
      return true;
    }
    if (o == null || this.getClass() != o.getClass()) {
      return false;
    }
    Order order = (Order) o;

    return Objects.equals(id, order.id);
  }

  @Override
  public int hashCode() {
    return Objects.hash(this.id);
  }

  @Override
  public Object getKey() {
    return id;
  }

  @Override
  public OrderValue getValue() {
    return new OrderValue(
        accountId, side, type, symbol, price, quantity, remaining, status, marginMode);
  }

  @Override
  public Order deepCopy() {
    return new Order(this);
  }

  public void addLiquidationPnl(MarginBigDecimal extra) {
    liquidationPnl = liquidationPnl.add(extra);
  }

  public void addLiquidationTradingFee(MarginBigDecimal extra) {
    liquidationTradingFee = liquidationTradingFee.add(extra);
  }

  @Override
  public String toString() {
    return new StringJoiner(", ", Order.class.getSimpleName() + "[", "]")
        .add("id=" + id)
        .add("userId=" + userId)
        .add("accountId=" + accountId)
        .add("symbol='" + symbol + "'")
        .add("asset=" + asset)
        .add("side=" + side)
        .add("type=" + type)
        .add("price=" + price)
        .add("quantity=" + quantity)
        .add("remaining=" + remaining)
        .add("lockPrice=" + lockPrice)
        .add("orderValue=" + orderValue)
        .add("executedPrice=" + executedPrice)
        .add("tpSLType=" + tpSLType)
        .add("tpSLPrice=" + tpSLPrice)
        .add("trigger=" + trigger)
        .add("timeInForce=" + timeInForce)
        .add("trailPrice=" + trailPrice)
        .add("callbackRate=" + callbackRate)
        .add("activationPrice=" + activationPrice)
        .add("status=" + status)
        .add("stopCondition=" + stopCondition)
        .add("isReduceOnly=" + isReduceOnly)
        .add("isPostOnly=" + isPostOnly)
        .add("isClosePositionOrder=" + isClosePositionOrder)
        .add("note=" + note)
        .add("leverage=" + leverage)
        .add("marginMode=" + marginMode)
        .add("isHidden=" + isHidden)
        .add("isTpSlOrder=" + isTpSlOrder)
        .add("linkedOrderId=" + linkedOrderId)
        .add("takeProfitOrderId=" + takeProfitOrderId)
        .add("stopLossOrderId=" + stopLossOrderId)
        .add("parentOrderId=" + parentOrderId)
        .add("priority=" + priority)
        .add("contractType=" + contractType)
        .add("userEmail=" + userEmail)
        .add("tmpId=" + tmpId)
        .add("operationId=" + operationId)
        .add("cost=" + cost)
        .add("originalCost=" + originalCost)
        .add("orderMargin=" + orderMargin)
        .add("originalOrderMargin=" + originalOrderMargin)
        .add("createdAt=" + createdAt)
        .add("updatedAt=" + updatedAt)
        .toString();
  }

  record OrderValue(
      Long accountId,
      OrderSide side,
      OrderType type,
      String symbol,
      MarginBigDecimal price,
      MarginBigDecimal quantity,
      MarginBigDecimal remaining,
      OrderStatus status,
      MarginMode marginMode) {}
}

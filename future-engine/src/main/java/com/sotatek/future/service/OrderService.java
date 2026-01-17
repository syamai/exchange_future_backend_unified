package com.sotatek.future.service;

import static com.sotatek.future.engine.MatchingEngine.matchers;
import static com.sotatek.future.engine.MatchingEngine.triggers;

import com.sotatek.future.engine.Matcher;
import com.sotatek.future.engine.Trigger;
import com.sotatek.future.entity.*;
import com.sotatek.future.enums.*;
import com.sotatek.future.exception.InsufficientBalanceException;
import com.sotatek.future.exception.InvalidateOrderStatusException;
import com.sotatek.future.exception.LockPriceException;
import com.sotatek.future.util.MarginBigDecimal;

import java.util.*;
import java.util.stream.Stream;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;
import org.apache.commons.lang3.tuple.Pair;
import org.slf4j.event.Level;

@Slf4j
public class OrderService extends BaseService<Order> {
  private static final long MAX_ID = 100000000000L;
  private static final OrderService instance = new OrderService();
  private static final String insuranceAccountOwner = "Insurance";
  private AccountService accountService;
  private InstrumentService instrumentService;
  private PositionService positionService;
  private TradeService tradeService;
  private InsuranceService insuranceService;
  private PositionHistoryService positionHistoryService;
  private MarginHistoryService marginHistoryService;
  private PositionCalculator positionCalculator;

  // Map to hold all orders of user
  // The key is accountId
  Map<Long, Map<Long, Order>> currentOrders = new HashMap<>();

  Set<Long> liquidationOrderIdPool = new LinkedHashSet<>();

  private OrderService() {
    super(true);
    log.isEnabledForLevel(Level.INFO);
  }

  public static OrderService getInstance() {
    return instance;
  }

  public void initialize(
      AccountService accountService,
      InstrumentService instrumentService,
      PositionService positionService,
      TradeService tradeService,
      InsuranceService insuranceService,
      MarginHistoryService marginHistoryService,
      PositionHistoryService positionHistoryService,
      PositionCalculator positionCalculator) {
    this.accountService = accountService;
    this.instrumentService = instrumentService;
    this.positionService = positionService;
    this.tradeService = tradeService;
    this.insuranceService = insuranceService;
    this.marginHistoryService = marginHistoryService;
    this.positionHistoryService = positionHistoryService;
    this.positionCalculator = positionCalculator;
  }

  @Override
  public void assignNewId(Order order) {
    long nextId = 0L;
    if (OrderNote.INSURANCE_LIQUIDATION.equals(order.getNote()) ||
            OrderNote.LIQUIDATION.equals(order.getNote()) ||
            OrderNote.INSURANCE_FUNDING.equals(order.getNote())) {
      Iterator<Long> iterator = this.liquidationOrderIdPool.iterator();
      nextId = iterator.next();
      iterator.remove();
    } else {
      nextId = this.getNextId();
      if (nextId > MAX_ID) {
        throw new RuntimeException(
                "The order id(" + nextId + ") is greater than MAX ID(" + MAX_ID + ").");
      }
    }

    order.setId(nextId);
  }

  @Override
  public void commit() {
    // Closed orders will be removed after 1 hour to save memory
    for (Order order : temporaryEntities.values()) {
      if (order.isClosed()) {
        removeOldEntity(order);
      }
    }
    for (Order order : processingEntities.values()) {
      if (order.isClosed()) {
        removeOldEntity(order);
        // remove from currentOrders
        currentOrders.get(order.getAccountId()).remove(order.getId());
      }
    }
    super.commit();
  }

  @Override
  public Order update(Order order) {
    order.setUpdatedAt(new Date());
    this.processingEntities.put(order.getKey(), order);
    this.addCurrentOrders(order);
    return order;
  }

  @Override
  public void rollbackTemporary() {
    // remove all process order
    processingEntities
        .values()
        .forEach(
            o -> {
              Map<Long, Order> orderMap = currentOrders.get(o.getAccountId());
              if (orderMap != null) {
                orderMap.remove(o.getId());
              }
            });
    // add back all order with old state
    temporaryEntities.values().forEach(this::addCurrentOrders);
    super.rollbackTemporary();
  }

  private void addCurrentOrders(Order order) {
    if (currentOrders.get(order.getAccountId()) == null) {
      Map<Long, Order> orderMap = new HashMap<>();
      orderMap.put(order.getId(), order);
      currentOrders.put(order.getAccountId(), orderMap);
    } else {
      currentOrders.get(order.getAccountId()).put(order.getId(), order);
    }
  }

  public Order updateWithoutUpdateAt(Order order) {
    this.processingEntities.put(order.getKey(), order);
    return order;
  }

  public List<Order> getUserActiveOrders(Long accountId) {
    return currentOrders.getOrDefault(accountId, new HashMap<>()).values().stream()
        .filter(Order::isActive)
        .toList();
  }

  public Stream<Order> getUserOpenOrders(Long accountId) {
    return currentOrders.getOrDefault(accountId, new HashMap<>()).values().stream()
        .filter(Order::canBeCanceled);
  }

  public Stream<Order> getUserOpenOrders(Long accountId, String symbol) {
    return getUserOpenOrders(accountId).filter(p -> p.getSymbol().equals(symbol));
  }

  /**
   * Cancel order
   *
   * @param cancelOrder order to process
   */
  public void cancel(Order cancelOrder) {
    // Noted just push account to output stream for notify for backend
    Account account = accountService.get(cancelOrder.getAccountId());
    accountService.update(account);
    // remove stop order from trigger
    if (cancelOrder.isStopOrder() && cancelOrder.isUntriggered()) {
      Trigger trigger = triggers.get(cancelOrder.getSymbol());
      trigger.cancelOrder(cancelOrder);
    }
    // if order is reduce only then set note for BE notification
    if (cancelOrder.isReduceOnly()) {
      cancelOrder.setNote(OrderNote.REDUCE_ONLY_CANCELED);
    }
    // check if order is tp/sl of position then remove linked position
    Position currentPosition =
        positionService.get(cancelOrder.getAccountId(), cancelOrder.getSymbol());
    log.debug("Cancel order {} with position {}", cancelOrder, currentPosition);
    if (ObjectUtils.isNotEmpty(currentPosition)) {
      if (cancelOrder.getId().equals(currentPosition.getTakeProfitOrderId())) {
        currentPosition.setTakeProfitOrderId(null);
        positionService.update(currentPosition);
      }
      if (cancelOrder.getId().equals(currentPosition.getStopLossOrderId())) {
        currentPosition.setStopLossOrderId(null);
        positionService.update(currentPosition);
      }

      if (cancelOrder.getCost() != null) {
        // remainingCost = originalCost*remaining/quantity
        MarginBigDecimal remainingCost =
                !cancelOrder.getQuantity().eq(0) ?
                  cancelOrder
                      .getOriginalCost()
                      .multiply(cancelOrder.getRemaining())
                      .divide(cancelOrder.getQuantity()):
                        MarginBigDecimal.ZERO;
        // subtract order cost from position
        currentPosition.setOrderCost(currentPosition.getOrderCost().subtract(remainingCost));
      }
      if (cancelOrder.isLimitOrder() && cancelOrder.getOrderMargin() != null) {
        // remainingOrderMargin = originalOrderMargin*remaining/quantity
        MarginBigDecimal remainingOrderMargin =
                !cancelOrder.getQuantity().eq(0) ?
                  cancelOrder
                    .getOriginalOrderMargin()
                    .multiply(cancelOrder.getRemaining())
                    .divide(cancelOrder.getQuantity()):
                        MarginBigDecimal.ZERO;
        // subtract order margin from position
        if (cancelOrder.isSellOrder()) {
          currentPosition.setMarSel(currentPosition.getMarSel().subtract(remainingOrderMargin));
        } else {
          currentPosition.setMarBuy(currentPosition.getMarBuy().subtract(remainingOrderMargin));
        }
      }
      positionService.update(currentPosition);
    }

    // reset cost and order margin for order
    cancelOrder.setCost(MarginBigDecimal.ZERO);
    cancelOrder.setOriginalCost(MarginBigDecimal.ZERO);
    cancelOrder.setOrderMargin(MarginBigDecimal.ZERO);
    cancelOrder.setOriginalOrderMargin(MarginBigDecimal.ZERO);
    // reset remaining = quantity when TIF = FOK
    if (cancelOrder.isFokOrder()) {
      cancelOrder.setRemaining(cancelOrder.getQuantity());
    }
    cancelOrder.close();
    update(cancelOrder);

    // avoid infinite loop when cancel linked order
    // then we handle this logic after update cancelOrder status

    // check if order has a linked order
    // and order is triggered then cancel linked order too
    Long linkedOrderId = cancelOrder.getLinkedOrderId();
    if (ObjectUtils.isNotEmpty(linkedOrderId) && cancelOrder.isTriggered()) {
      // get linked order
      Order linkedOrder = get(linkedOrderId);
      // cancel linked order if it is not closed
      if (ObjectUtils.isNotEmpty(linkedOrder) && !linkedOrder.isClosed()) {
        cancel(linkedOrder);
      }
    }
  }

  /**
   * Active order
   *
   * @param order order to process
   */
  public void activateOrder(Order order) {
    if (!order.canBeActivated()) {
      throw new InvalidateOrderStatusException(OrderStatus.PENDING, order.getStatus());
    }
    Position position = getOrCreatePosition(order);
    Account account = accountService.get(order.getAccountId());
    Position oldPosition = position.deepCopy();
    Account oldAccount = account.deepCopy();

    // calculate cost for normal order or stop order which has triggered
    if (!order.isStopOrder() || order.isTriggered()) {
      calcOrderCost(order);
      // update status and push to processing queue to include this order cost on calculate
      // available balance
      order.setStatus(OrderStatus.ACTIVE);
      update(order);

      if (this.shouldValidateAccountByCheckingOrder(order)) {
        // Get total order margin of processing market orders of this account
        MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(order.getSymbol());
        MarginBigDecimal orderMargin = marginCalculator.calcOrderMargin(order);
        MarginBigDecimal availBalance = accountService.getAccountAvailableBalance(account.getId());
        if (availBalance.lte(orderMargin)) {
          throw new InsufficientBalanceException(account, availBalance);
        }
//        accountService.validateAccount(account);
      }
    }

    accountService.update(account);
    marginHistoryService.log(
        MarginHistoryAction.ACTIVATE_ORDER,
        oldPosition,
        position.deepCopy(),
        oldAccount,
        account.deepCopy(),
        order);
  }

  private boolean shouldValidateAccountByCheckingOrder(Order order) {
    boolean shouldValidateAccount = true;
    // this is stop-loss (stop-market) order for position
    if (
            order.isClosePositionOrder() &&
                    OrderType.MARKET.equals(order.getType()) &&
                    TPSLType.STOP_MARKET.equals(order.getTpSLType()) &&
                    order.isReduceOnly() &&
                    order.isTriggered()
    ) {
      shouldValidateAccount = false;
    }

    // this is close-position market order
    if (
            order.isClosePositionOrder() &&
                    OrderType.MARKET.equals(order.getType())
    ) {
      shouldValidateAccount = false;
    }

    // this is liquidation order
    if (order.isLiquidationOrder()) shouldValidateAccount = false;

    return shouldValidateAccount;
  }

  /**
   * re-active order when it triggered
   *
   * @param order a stop order that need to reactive
   * @param newLockPrice
   */
  public void reactivateOrder(Order order, MarginBigDecimal newLockPrice) {
    order.setStatus(OrderStatus.PENDING);
    order.setLockPrice(newLockPrice);
    activateOrder(order);
  }

  private Position getOrCreatePosition(Order fromOrder) {
    Position position = positionService.get(fromOrder.getAccountId(), fromOrder.getSymbol());
    if (position == null) {
      Instrument instrument = instrumentService.get(fromOrder.getSymbol());
      position = Position.from(instrument);
      position.setLeverage(fromOrder.getLeverage());
      position.setCross(MarginMode.CROSS.equals(fromOrder.getMarginMode()));
      position.setAsset(fromOrder.getAsset());
      position.setAccountId(fromOrder.getAccountId());
      position.setUserId(fromOrder.getUserId());
      position = positionService.insert(position);
    }
    // update margin mode with new matching order
    if (MarginBigDecimal.ZERO.eq(position.getCurrentQty())) {
      // reset position leverage by order matching
      position.setLeverage(fromOrder.getLeverage());
      position.setCross(MarginMode.CROSS.equals(fromOrder.getMarginMode()));
    }
    position = positionService.update(position);
    return position;
  }

  private Position getOrCreateInsurancePosition(Account account, String symbol) {
    Position position = positionService.get(account.getId(), symbol);
    if (position == null) {
      Instrument instrument = instrumentService.get(symbol);
      position = Position.from(instrument);
      position.setAsset(account.getAsset());
      position.setAccountId(account.getId());
      position.setUserId(account.getUserId());
      position.setLeverage(MarginBigDecimal.valueOf(1));
      position.setAsset(account.getAsset());
      position = positionService.insert(position);
    }
    positionService.update(position);
    return position;
  }

  public Pair<Order, Trade> matchOrders(Order takerOrder, Order order) {
    Pair<Order, Trade> data = doMatchOrders(takerOrder, order);
    if (data.getRight() == null) {
      update(takerOrder);
      update(order);
      return data;
    }
    Order buyOrder;
    Order sellOrder;
    if (takerOrder.isBuyOrder()) {
      buyOrder = takerOrder;
      sellOrder = order;
    } else {
      buyOrder = order;
      sellOrder = takerOrder;
    }
    Trade trade = data.getRight();
    // update order state before trade
    update(takerOrder);
    update(order);
    executeTrade(trade, buyOrder, sellOrder, takerOrder.isBuyOrder());
    tradeService.insert(trade);
    // update order state after trade
    update(takerOrder);
    update(order);
    return data;
  }

  private Pair<Order, Trade> doMatchOrders(Order taker, Order maker) {
    MarginBigDecimal price = getMatchingPrice(taker, maker);
    MarginBigDecimal maxMakerQuantity = getMaxMatchableQuantity(maker);
    MarginBigDecimal maxTakerQuantity = getMaxMatchableQuantity(taker);
    log.debug(
        "doMatchOrders maxMakerQuantity {} maxTakerQuantity {}",
        maxMakerQuantity,
        maxTakerQuantity);
    MarginBigDecimal quantity = maxMakerQuantity.min(maxTakerQuantity);

    // Maybe market order doesn't have enough balance
    if (quantity.eq(0)) {
      updateOrderStatus(maker, taker, maxMakerQuantity, maxTakerQuantity);
      update(maker);
      update(taker);
      return Pair.of(null, null);
    }

    updateExecutedPrice(maker, taker, price, quantity);
    // update both of orders info
    maker.setRemaining(maker.getRemaining().subtract(quantity));
    taker.setRemaining(taker.getRemaining().subtract(quantity));

    updateOrderStatus(maker, taker, maxMakerQuantity, maxTakerQuantity);
    Order remaining = getRemainingOrder(maker, taker, maxMakerQuantity, maxTakerQuantity);
    Trade trade = new Trade(taker, maker, price, quantity);
    setFeeRate(trade);
    return Pair.of(remaining, trade);
  }

  protected MarginBigDecimal getMatchingPrice(Order taker, Order maker) {
    if (taker.isMarketOrder()) {
      return maker.getPrice();
    }
    if (maker.isMarketOrder()) {
      return taker.getPrice();
    }
    return maker.getPrice();
  }

  private MarginBigDecimal getMaxMatchableQuantity(Order order) {
    MarginBigDecimal maxQuantity = order.getRemaining();
    if (order.isReduceOnly()) {
      Position position = positionService.get(order.getAccountId(), order.getSymbol());
      // If remaining amount in the order is higher than the current position
      // Try to partially fill this order to read a neutral position if possible, then cancel the
      // order
      if (maxQuantity.gt(position.getCurrentQty().abs())) {
        maxQuantity = position.getCurrentQty().abs();
        order.setNote(OrderNote.REDUCE_ONLY_CANCELED);
      }
    }
    return maxQuantity;
  }

  private void updateOrderStatus(
      Order buyOrder,
      Order sellOrder,
      MarginBigDecimal maxBuyQuantity,
      MarginBigDecimal maxSellQuantity) {
    if (maxBuyQuantity.gt(maxSellQuantity)) {
      if (maxSellQuantity.gt(0)) {
        buyOrder.setStatus(OrderStatus.ACTIVE);
      }
      if (sellOrder.getRemaining().eq(MarginBigDecimal.ZERO)) {
        sellOrder.setStatus(OrderStatus.FILLED);
      }
    } else if (maxBuyQuantity.eq(maxSellQuantity)) {
      if (buyOrder.getRemaining().eq(MarginBigDecimal.ZERO)) {
        buyOrder.setStatus(OrderStatus.FILLED);
      }
      if (sellOrder.getRemaining().eq(MarginBigDecimal.ZERO)) {
        sellOrder.setStatus(OrderStatus.FILLED);
      }
    } else {
      if (maxBuyQuantity.gt(0)) {
        sellOrder.setStatus(OrderStatus.ACTIVE);
      }
      if (buyOrder.getRemaining().eq(MarginBigDecimal.ZERO)) {
        buyOrder.setStatus(OrderStatus.FILLED);
      }
    }
  }

  private void updateExecutedPrice(
      Order buyOrder, Order sellOrder, MarginBigDecimal price, MarginBigDecimal quantity) {
    buyOrder.setExecutedPrice(calculateExecutedPrice(buyOrder, price, quantity));
    sellOrder.setExecutedPrice(calculateExecutedPrice(sellOrder, price, quantity));
  }

  private MarginBigDecimal calculateExecutedPrice(
      Order order, MarginBigDecimal price, MarginBigDecimal quantity) {
    MarginBigDecimal executedQuantity = order.getQuantity().subtract(order.getRemaining());
    MarginBigDecimal executedPrice =
        order.getExecutedPrice() != null ? order.getExecutedPrice() : MarginBigDecimal.ZERO;
    MarginBigDecimal oldTotal = executedPrice.multiply(executedQuantity);
    MarginBigDecimal newTotal = price.multiply(quantity).add(oldTotal);
    MarginBigDecimal newQuantity = executedQuantity.add(quantity);
    return newTotal.divide(newQuantity);
  }

  private Order getRemainingOrder(
      Order maker,
      Order taker,
      MarginBigDecimal maxMakerQuantity,
      MarginBigDecimal maxTakerQuantity) {
    if (maxMakerQuantity.gt(maxTakerQuantity)) {
      return maker;
    } else if (maxMakerQuantity.eq(maxTakerQuantity)) {
      return null;
    } else {
      return taker;
    }
  }

  /**
   * Set fee rate for taker/maker
   *
   * @param trade
   */
  private void setFeeRate(Trade trade) {
    Instrument instrument = instrumentService.get(trade.getSymbol());
    if (trade.isBuyerIsTaker()) {
      trade.setBuyFeeRate(instrument.getTakerFee());
      trade.setSellFeeRate(instrument.getMakerFee());
    } else {
      trade.setBuyFeeRate(instrument.getMakerFee());
      trade.setSellFeeRate(instrument.getTakerFee());
    }
  }

  public Trade executeTrade(Trade trade, Order buyOrder, Order sellOrder, boolean buyerIsTaker) {
    log.debug("executeTrade with buyOrder {}", buyOrder);
    log.debug("executeTrade with sellOrder {}", sellOrder);
    Position buyPosition = getOrCreatePosition(buyOrder);
    Position sellPosition = getOrCreatePosition(sellOrder);
    Account buyAccount = accountService.get(trade.getBuyAccountId());
    Account sellAccount = accountService.get(trade.getSellAccountId());
    Account insuranceAccount = accountService.getInsuranceAccount(buyOrder.getAsset());
    Position beforeBuyPosition = buyPosition.deepCopy();
    Position beforeSellPosition = sellPosition.deepCopy();
    Account beforeBuyAccount = buyAccount.deepCopy();
    Account beforeSellAccount = sellAccount.deepCopy();
    Account beforeInsuranceAccount = insuranceAccount.deepCopy();
    MarginBigDecimal buyRealisedPnl;
    MarginBigDecimal sellRealisedPnl;
    MarginBigDecimal buyFee;
    MarginBigDecimal sellFee;
    MarginBigDecimal buyOpenPositionFee;
    MarginBigDecimal sellOpenPositionFee;
    MarginBigDecimal buyClosePositionFee;
    MarginBigDecimal sellClosePositionFee;

    // we will handle order for reduce position first then handel order increment position after
    // we only has one position linked to one account
    // so for handle two order of the same account then buyPosition is the same sellPosition
    boolean isLongPosition = buyPosition.getCurrentQty().gt(MarginBigDecimal.ZERO);
    if (isLongPosition) {
      // for long position then handle sellOrder first
      // handle matching sell order, sell account and sell position
      MatchingResult matchingResultSell =
          handleMatchingOrder(
              trade,
              sellOrder,
              sellPosition.deepCopy(),
              sellAccount.deepCopy(),
              insuranceAccount,
              !buyerIsTaker);
      sellPosition = matchingResultSell.position();
      sellAccount = matchingResultSell.account();
      sellRealisedPnl = matchingResultSell.realisedPnl();
      insuranceAccount = matchingResultSell.insuranceAccount();
      sellFee = matchingResultSell.fee();
      sellOpenPositionFee = matchingResultSell.openPositionFee();
      sellClosePositionFee = matchingResultSell.closePositionFee();
      // validate account after matching order
      log.debug("Validate account with id {} after matching order", sellAccount.getId());
      updateOrderMarginAfterMatching(sellPosition, sellOrder);
//      if (!sellOrder.isLiquidationOrder()) {
//        accountService.validateAccount(sellAccount);
//      }
      if (this.shouldValidateAccountByCheckingOrder(sellOrder)) {
        accountService.validateAccount(sellAccount);
      }

      if (Objects.equals(trade.getBuyAccountId(), trade.getSellAccountId())) {
        // handle matching two order of the same account
        // then get new position to pass to buy position
        buyAccount = matchingResultSell.account();
        buyPosition = matchingResultSell.position();
      }
      // handle matching for buy order, buy account and buy position
      MatchingResult matchingResultBuy =
          handleMatchingOrder(
              trade,
              buyOrder,
              buyPosition.deepCopy(),
              buyAccount.deepCopy(),
              insuranceAccount,
              buyerIsTaker);
      buyFee = matchingResultBuy.fee();
      buyPosition = matchingResultBuy.position();
      buyAccount = matchingResultBuy.account();
      buyRealisedPnl = matchingResultBuy.realisedPnl();
      insuranceAccount = matchingResultBuy.insuranceAccount();
      buyOpenPositionFee = matchingResultBuy.openPositionFee();
      buyClosePositionFee = matchingResultBuy.closePositionFee();
      log.debug("Validate account with id {} after matching order", buyAccount.getId());
      updateOrderMarginAfterMatching(buyPosition, buyOrder);
//      if (!buyOrder.isLiquidationOrder()) {
//        accountService.validateAccount(buyAccount);
//      }
      if (this.shouldValidateAccountByCheckingOrder(buyOrder)) {
        accountService.validateAccount(buyAccount);
      }
    } else {
      // for short position then handle buyOrder first
      // handle matching for buy order, buy account and buy position
      MatchingResult matchingResultBuy =
          handleMatchingOrder(
              trade,
              buyOrder,
              buyPosition.deepCopy(),
              buyAccount.deepCopy(),
              insuranceAccount,
              buyerIsTaker);
      buyFee = matchingResultBuy.fee();
      buyPosition = matchingResultBuy.position();
      buyAccount = matchingResultBuy.account();
      buyRealisedPnl = matchingResultBuy.realisedPnl();
      insuranceAccount = matchingResultBuy.insuranceAccount();
      buyOpenPositionFee = matchingResultBuy.openPositionFee();
      buyClosePositionFee = matchingResultBuy.closePositionFee();
      log.debug("Validate account with id {} after matching order", buyAccount.getId());
      updateOrderMarginAfterMatching(buyPosition, buyOrder);
//      if (!buyOrder.isLiquidationOrder()) {
//        accountService.validateAccount(buyAccount);
//      }
      if (this.shouldValidateAccountByCheckingOrder(buyOrder)) {
        accountService.validateAccount(buyAccount);
      }

      if (Objects.equals(trade.getBuyAccountId(), trade.getSellAccountId())) {
        // handle matching two order of the same account
        // then get new position to pass to sell position
        sellAccount = matchingResultBuy.account();
        sellPosition = matchingResultBuy.position();
      }

      MatchingResult matchingResultSell =
          handleMatchingOrder(
              trade,
              sellOrder,
              sellPosition.deepCopy(),
              sellAccount.deepCopy(),
              insuranceAccount,
              !buyerIsTaker);
      sellPosition = matchingResultSell.position();
      sellAccount = matchingResultSell.account();
      sellRealisedPnl = matchingResultSell.realisedPnl();
      insuranceAccount = matchingResultSell.insuranceAccount();
      sellFee = matchingResultSell.fee();
      sellOpenPositionFee = matchingResultSell.openPositionFee();
      sellClosePositionFee = matchingResultSell.closePositionFee();
      log.debug("Validate account with id {} after matching order", sellAccount.getId());
      updateOrderMarginAfterMatching(sellPosition, sellOrder);
//      if (!sellOrder.isLiquidationOrder()) {
//        accountService.validateAccount(sellAccount);
//      }
      if (this.shouldValidateAccountByCheckingOrder(sellOrder)) {
        accountService.validateAccount(sellAccount);
      }
    }
    // get position after matching with updated state from queue
    buyPosition = positionService.get(buyPosition.getKey());
    sellPosition = positionService.get(sellPosition.getKey());

    // handle close reduce only order when position is close
    if (buyPosition.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
      cancelReduceOnlyWhenPositionClosed(buyPosition);
    }
    // call method again if sellPosition do not belong the same account of buyPosition
    if (!Objects.equals(buyPosition.getAccountId(), sellPosition.getAccountId())
        && sellPosition.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
      cancelReduceOnlyWhenPositionClosed(sellPosition);
    }

    // side of a position change when [before quantity * after quantity < 0]
    boolean isChangeSideOfBuyPosition =
        buyPosition.getCurrentQty().multiply(beforeBuyPosition.getCurrentQty()).lt(0);
    boolean isChangeSideOfSellPosition =
        sellPosition.getCurrentQty().multiply(beforeSellPosition.getCurrentQty()).lt(0);
    log.debug(
        "executeTrade isChangeSideOfBuyPosition {},  isChangeSideOfSellPosition {}",
        isChangeSideOfBuyPosition,
        isChangeSideOfSellPosition);
    if (isChangeSideOfBuyPosition) {
      cancelReduceOnlyWhenPositionChangeSide(buyPosition);
    }
    // call method again if sellPosition do not belong the same account of buyPosition
    if (!Objects.equals(buyPosition.getAccountId(), sellPosition.getAccountId())
        && isChangeSideOfSellPosition) {
      cancelReduceOnlyWhenPositionChangeSide(sellPosition);
    }

    // Update reduce only when change position
    updateReduceOnlyWhenPositionChange(buyPosition);
    // call method again if sellPosition do not belong the same account of buyPosition
    if (!Objects.equals(buyPosition.getAccountId(), sellPosition.getAccountId())) {
      updateReduceOnlyWhenPositionChange(sellPosition);
    }

    trade.setBuyFee(buyFee);
    trade.setSellFee(sellFee);
    trade.setRealizedPnlOrderBuy(buyRealisedPnl);
    trade.setRealizedPnlOrderSell(sellRealisedPnl);
    updateTraderInformation(
        trade,
        beforeBuyPosition,
        buyPosition,
        beforeSellPosition,
        sellPosition,
        beforeInsuranceAccount,
        insuranceAccount,
        beforeBuyAccount,
        buyAccount,
        beforeSellAccount,
        sellAccount,
        buyerIsTaker,
        buyOpenPositionFee,
        buyClosePositionFee,
        sellOpenPositionFee,
        sellClosePositionFee
    );
    return trade;
  }

  /** update marBuy/marSel/orderCost on position after matching */
  private void updateOrderMarginAfterMatching(Position position, Order order) {
    log.atDebug()
        .addKeyValue("position", position)
        .addKeyValue("order", order)
        .log(
            "updating marBuy/marSel/orderCost on position {} after matching order {}",
            position.getId(),
            order.getId());
    if (order.isLimitOrder()) {
      // update marBuy/marSell
      if (order.isSellOrder()) {
        // update marSell value
        MarginBigDecimal oldMarSell = position.getMarSel();
        // remaining order margin = originalOrderMargin*remaining/quantity
        MarginBigDecimal remainingOrderMargin =
            order
                .getOriginalOrderMargin()
                .multiply(order.getRemaining())
                .divide(order.getQuantity());
        // newMarSell = oldMarSell - old orderMargin + remainingOrderMargin
        position.setMarSel(oldMarSell.subtract(order.getOrderMargin()).add(remainingOrderMargin));
        // update orderMargin
        order.setOrderMargin(remainingOrderMargin);
      } else {
        // update marBuy value
        MarginBigDecimal oldMarBuy = position.getMarBuy();
        // remaining order margin = originalOrderMargin*remaining/quantity
        MarginBigDecimal remainingOrderMargin =
            order
                .getOriginalOrderMargin()
                .multiply(order.getRemaining())
                .divide(order.getQuantity());
        // newMarSell = oldMarSell - old orderMargin + remainingOrderMargin
        position.setMarBuy(oldMarBuy.subtract(order.getOrderMargin()).add(remainingOrderMargin));
        // update orderMargin
        order.setOrderMargin(remainingOrderMargin);
      }
    }
    // update orderCost
    MarginBigDecimal oldOrderCost = position.getOrderCost();
    // remainingOrderCost = originalCost*remaining)/quantity
    MarginBigDecimal remainingOrderCost =
        order.getOriginalCost().multiply(order.getRemaining()).divide(order.getQuantity());
    // newOrderCost = oldOrderCost - orderCost + remainingOrderCost
    MarginBigDecimal newOrderCost = oldOrderCost.subtract(order.getCost()).add(remainingOrderCost);
    position.setOrderCost(newOrderCost);
    log.atDebug()
        .addKeyValue("oldOrderCost", oldOrderCost)
        .addKeyValue("orderCost", order.getCost())
        .addKeyValue("remainingOrderCost", remainingOrderCost)
        .addKeyValue("newOrderCost", newOrderCost)
        .log("update order cost for position {}", position.getId());
    // update order cost
    order.setCost(remainingOrderCost);
    // update position
    positionService.update(position);
  }

  /**
   * Handle update size of two tp/sl order when position size has changed Handle logic reduce only
   * when position change
   *
   * @param targetPosition
   */
  private void updateReduceOnlyWhenPositionChange(Position targetPosition) {
    // if position is closed then skip this process
    if (targetPosition.getCurrentQty().eq(0)) {
      log.debug("Skip this process because position is closed");
      return;
    }
    // update size of tp/sl order which linked to position
    if (targetPosition.getTakeProfitOrderId() != null) {
      Order tpOrder = get(targetPosition.getTakeProfitOrderId());
      if (ObjectUtils.isNotEmpty(tpOrder) && !tpOrder.isClosed()) {
        tpOrder.setQuantity(targetPosition.getCurrentQty().abs());
        tpOrder.setRemaining(targetPosition.getCurrentQty().abs());
        update(tpOrder);
      }
    }
    if (targetPosition.getStopLossOrderId() != null) {
      Order slOrder = get(targetPosition.getStopLossOrderId());
      if (ObjectUtils.isNotEmpty(slOrder) && !slOrder.isClosed()) {
        slOrder.setQuantity(targetPosition.getCurrentQty().abs());
        slOrder.setRemaining(targetPosition.getCurrentQty().abs());
        update(slOrder);
      }
    }

    // get current side of position
    // long position has quantity > 0
    // and short position has quantity < 0
    boolean isLongPosition = targetPosition.getCurrentQty().gt(0);
    String symbol = targetPosition.getSymbol();
    Long accountId = targetPosition.getAccountId();
    Matcher matcher = matchers.get(symbol);

    currentOrders.get(accountId).values().stream()
        // get all order reduce only which is not close and the same side of position
        .filter(
            e ->
                e.getSymbol().equals(symbol)
                    && e.isReduceOnly()
                    && !e.isClosed()
                    // filter order is the same side of position
                    && ((isLongPosition && e.isBuyOrder()) || (!isLongPosition && e.isSellOrder())))
        .forEach(
            e -> {
              log.debug("Reduce only order to cancel when position change size is {}", e.getId());
              matcher.cancelOrder(e);
            });
  }

  /**
   * Cancel all reduce only order when position is closed
   *
   * @param position
   */
  private void cancelReduceOnlyWhenPositionClosed(Position position) {
    if (!position.getCurrentQty().eq(0)) {
      return;
    }
    log.debug("handle when position closed {}", position.getId());
    // reset adjust margin
    position.setAdjustMargin(MarginBigDecimal.ZERO);
    positionService.update(position);
    String symbol = position.getSymbol();
    Long accountId = position.getAccountId();
    Matcher matcher = matchers.get(symbol);
    List<Order> cancelTpSlOrders = new ArrayList<>();
    currentOrders.get(accountId).values().stream()
        // get all order reduce only which is not close
        .filter(e -> e.getSymbol().equals(symbol) && e.isReduceOnly() && !e.isClosed())
        .forEach(cancelTpSlOrders::add);
    // get tp/sl order of position to cancel
    Long tpOrderId = position.getTakeProfitOrderId();
    if (ObjectUtils.isNotEmpty(tpOrderId)) {
      Order tpOrder = get(tpOrderId);
      if (ObjectUtils.isNotEmpty(tpOrder)) {
        if (tpOrder.isClosed()) {
          // if tpOrder is cancel or closed then remove it from position
          position.setTakeProfitOrderId(null);
          positionService.update(position);
        } else {
          // if tpOrder is active then cancel it
          cancelTpSlOrders.add(tpOrder);
        }
      }
    }
    Long slOrderId = position.getStopLossOrderId();
    if (ObjectUtils.isNotEmpty(slOrderId)) {
      Order slOrder = get(slOrderId);
      if (ObjectUtils.isNotEmpty(slOrder)) {
        if (slOrder.isClosed()) {
          // if slOrder is cancel or closed then remove it from position
          position.setStopLossOrderId(null);
          positionService.update(position);
        } else {
          // if slOrder is active then cancel it
          cancelTpSlOrders.add(slOrder);
        }
      }
    }
    // execute cancel order
    cancelTpSlOrders.forEach(
        e -> {
          log.debug("Reduce only order to cancel when position close is {}", e.getId());
          matcher.cancelOrder(e);
        });
  }

  private void cancelReduceOnlyWhenPositionChangeSide(Position position) {
    log.debug("handle when position change side {}", position.getId());
    // if position is closed then do not handle
    // we have other method to handle when position closed
    if (position.getCurrentQty().eq(0)) {
      log.debug("Position is closed, skip this process");
      return;
    }
    // reset adjust margin
    position.setAdjustMargin(MarginBigDecimal.ZERO);
    positionService.update(position);
    // get current side of position
    // long position has quantity > 0
    // and short position has quantity < 0
    boolean isLongPosition = position.getCurrentQty().gt(0);
    String symbol = position.getSymbol();
    Long accountId = position.getAccountId();
    Matcher matcher = matchers.get(symbol);
    List<Order> reduceOnlyOrders =
            new ArrayList<>(currentOrders.get(accountId).values().stream()
                    // get all order reduce only which is not close and the same side of position
                    .filter(
                            e ->
                                    e.getSymbol().equals(symbol)
                                            && e.isReduceOnly()
                                            && !e.isClosed()
                                            // filter order is the same side of position
                                            && ((isLongPosition && e.isBuyOrder())
                                            || (!isLongPosition && e.isSellOrder())))
                    .toList());
    // get tp/sl order of position to cancel
    Long tpOrderId = position.getTakeProfitOrderId();
    if (ObjectUtils.isNotEmpty(tpOrderId)) {
      Order tpOrder = get(tpOrderId);
      if (ObjectUtils.isNotEmpty(tpOrder) && !tpOrder.isClosed()) {
        reduceOnlyOrders.add(tpOrder);
      }
    }
    Long slOrderId = position.getStopLossOrderId();
    if (ObjectUtils.isNotEmpty(slOrderId)) {
      Order slOrder = get(slOrderId);
      if (ObjectUtils.isNotEmpty(slOrder) && !slOrder.isClosed()) {
        reduceOnlyOrders.add(slOrder);
      }
    }

    // execute cancel order
    reduceOnlyOrders.forEach(
        e -> {
          log.debug("Reduce only order to cancel when position change side is {}", e.getId());
          matcher.cancelOrder(e);
        });
  }

  private MatchingResult handleMatchingOrder(
      Trade trade,
      Order order,
      Position position,
      Account account,
      Account insuranceAccount,
      boolean isTaker) {
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(order.getSymbol());
    MarginBigDecimal totalFee = MarginBigDecimal.ZERO;
    MarginBigDecimal closePositionFee = MarginBigDecimal.ZERO;
    MarginBigDecimal openPositionFee = MarginBigDecimal.ZERO;
    // initial realisedPnl = null
    // so for close position realisedPnl will have value
    // and for open/add position realisedPnl still null
    MarginBigDecimal realisedPnl = null;

    // convert matching quantity is negative when sell and positive when buy
    MarginBigDecimal matchingQuantity = convertQuantity(trade.getQuantity(), order);
    MarginBigDecimal closeSize =
        marginCalculator.getCloseSize(matchingQuantity, position.getCurrentQty());
    MarginBigDecimal openSize =
        marginCalculator.getOpenSize(matchingQuantity, position.getCurrentQty());

    log.debug(
        "handleMatchingOrder with matchingQuantity {} closeSize {} openSize {}",
        matchingQuantity,
        closeSize,
        openSize);

    // Handle close position
    if (!closeSize.eq(MarginBigDecimal.ZERO)) {
      MarginBigDecimal closeQuantity = closeSize.negate();
      MarginBigDecimal closeValue = marginCalculator.getCloseValue(position, closeSize);
      realisedPnl =
          marginCalculator.getRealisedPnl(trade.getPrice(), closeQuantity, closeValue, position);

      MarginBigDecimal oldQty = position.getCurrentQty();
      position.setCurrentQty(position.getCurrentQty().add(closeSize));
      if (position.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
        position.setEntryValue(MarginBigDecimal.ZERO);
        position.setEntryPrice(MarginBigDecimal.ZERO);
        position.setLiquidationPrice(MarginBigDecimal.ZERO);
        position.setBankruptPrice(MarginBigDecimal.ZERO);
        position.setPositionMargin(MarginBigDecimal.ZERO);
        position.setCloseSize(MarginBigDecimal.ZERO);
        position.setAvgClosePrice(MarginBigDecimal.ZERO);
        position.setPnlRanking(MarginBigDecimal.ZERO);
        position.setMarBuy(MarginBigDecimal.ZERO);
        position.setMarSel(MarginBigDecimal.ZERO);
        position.setOrderCost(MarginBigDecimal.ZERO);
        position.setTmpTotalFee(MarginBigDecimal.ZERO);
      } else {
        marginCalculator.reCalcPositionMarginIsolateClose(position, oldQty);
        position.setEntryValue(position.getEntryValue().add(closeValue));
        position.setEntryPrice(
            marginCalculator.getEntryPrice(position.getEntryValue(), position.getCurrentQty()));
        position.setAvgClosePrice(
            marginCalculator.calcAvgClosePrice(position, closeSize.abs(), trade.getPrice()));
        position.setCloseSize(position.getCloseSize().add(closeSize.abs()));
      }

      if (order.getNote() == OrderNote.LIQUIDATION) {
        log.atDebug()
            .addKeyValue("orderId", order.getId())
            .addKeyValue("accId", position.getAccountId())
            .addKeyValue("symbol", position.getSymbol())
            .log("Save real realisePnl of liquidation order. [amount={}]", realisedPnl);
        order.addLiquidationPnl(realisedPnl);
        MarginBigDecimal accountBalance = account.getBalance();
        if (accountBalance.add(realisedPnl).lt(0)) {
          MarginBigDecimal newRealisedPnl;
          if (accountBalance.gte(MarginBigDecimal.ZERO)) {
            newRealisedPnl = accountBalance.negate();
          } else {
            log.atWarn()
                .addKeyValue("orderId", order.getId())
                .addKeyValue("accId", position.getAccountId())
                .addKeyValue("symbol", position.getSymbol())
                .log("Account balance already negative before adding realisePnl.");
            newRealisedPnl = MarginBigDecimal.ZERO;
          }
          log.atDebug()
              .addKeyValue("orderId", order.getId())
              .addKeyValue("accId", position.getAccountId())
              .addKeyValue("symbol", position.getSymbol())
              .log(
                  "Adjust realisePnl due to insufficient balance. [old_val={}, new_val={}]",
                  realisedPnl,
                  newRealisedPnl);
          realisedPnl = newRealisedPnl;
        }
      }

      // adding realisePnL to balance
      log.debug("adding realisePnl to balance {}", realisedPnl);
      account = accountService.addAmountToBalance(account, realisedPnl);

      MarginBigDecimal fee = marginCalculator.getFee(trade.getPrice(), closeSize, isTaker);
      totalFee = totalFee.add(fee);
      closePositionFee = closePositionFee.add(fee);

      // Update balance
      if (openSize.eq(MarginBigDecimal.ZERO)) {
        if (order.getNote() == OrderNote.LIQUIDATION) {
          log.atDebug()
              .addKeyValue("orderId", order.getId())
              .addKeyValue("accId", position.getAccountId())
              .addKeyValue("symbol", position.getSymbol())
              .log("Save real trading fee of liquidation order. [amount={}]", totalFee);
          MarginBigDecimal accountBalance = account.getBalance();
          if (position.isCross()) {
            MarginBigDecimal ipm =
                positionCalculator.getIsolatedPositionMargin(position.getAccountId());
            log.atInfo()
                .addKeyValue("accountBalance", accountBalance)
                .addKeyValue("ipm", ipm)
                .log("adjust cross balance when charge trading fee for liquidation");
            accountBalance = accountBalance.subtract(ipm);
          }
          if (accountBalance.subtract(totalFee).lt(0)) {
            MarginBigDecimal newTradingFee;
            if (accountBalance.gte(MarginBigDecimal.ZERO)) {
              newTradingFee = accountBalance;
            } else {
              log.atWarn()
                  .addKeyValue("orderId", order.getId())
                  .addKeyValue("accId", position.getAccountId())
                  .addKeyValue("symbol", position.getSymbol())
                  .log("Account balance already negative before subtracting trading fee.");
              newTradingFee = MarginBigDecimal.ZERO;
            }
            log.atDebug()
                .addKeyValue("orderId", order.getId())
                .addKeyValue("accId", position.getAccountId())
                .addKeyValue("symbol", position.getSymbol())
                .log(
                    "Adjust trading fee due to insufficient balance. [old_val={}, new_val={}]",
                    totalFee,
                    newTradingFee);
            totalFee = newTradingFee;
          }
          order.addLiquidationTradingFee(totalFee);
        }
        log.atDebug()
            .addKeyValue("accId", account.getId())
            .addKeyValue("asset", order.getAsset())
            .addKeyValue("orderId", order.getId())
            .log("Charging matching fee for close position. [value={}]", totalFee);
        account = chargeFee(account, totalFee);
      }
    }

    // Handle open position
    if (!openSize.eq(MarginBigDecimal.ZERO)) {
      // Calculate open margin
      MarginBigDecimal openValue = marginCalculator.getOpenValue(trade.getPrice(), openSize);
      log.debug(
          "handleMatchingOrder openValue={}, tradePrice={}, openSize={}",
          openValue,
          trade.getPrice(),
          openSize);
      position.setCurrentQty(position.getCurrentQty().add(openSize));
      position.setEntryValue(position.getEntryValue().add(openValue));
      position.setEntryPrice(
          marginCalculator.getEntryPrice(position.getEntryValue(), position.getCurrentQty()));
      marginCalculator.reCalcPositionMarginIsolateOpen(position, openValue);
      MarginBigDecimal fee = marginCalculator.getFee(trade.getPrice(), openSize, isTaker);
      totalFee = totalFee.add(fee);
      openPositionFee = openPositionFee.add(fee);
      position.setLastOpenTime(new Date());

      // Update balance
      log.atDebug()
          .addKeyValue("accId", account.getId())
          .addKeyValue("asset", order.getAsset())
          .addKeyValue("orderId", order.getId())
          .log("Charging matching fee for open position. [value={}]", totalFee);
      account = chargeFee(account, totalFee);
    }
    accountService.update(account);

    MarginBigDecimal feeRate = marginCalculator.getFeeRate(isTaker);
    return new MatchingResult(account, insuranceAccount, position, realisedPnl, totalFee, openPositionFee, closePositionFee, feeRate);
  }

  public void updateTraderInformation(
      Trade trade,
      Position beforeBuyPosition,
      Position buyPosition,
      Position beforeSellPosition,
      Position sellPosition,
      Account beforeInsuranceAccount,
      Account insuranceAccount,
      Account beforeBuyAccount,
      Account buyAccount,
      Account beforeSellAccount,
      Account sellAccount,
      boolean buyerIsTaker,
      MarginBigDecimal buyOpenPositionFee,
      MarginBigDecimal buyClosePositionFee,
      MarginBigDecimal sellOpenPositionFee,
      MarginBigDecimal sellClosePositionFee) {
    if (!beforeInsuranceAccount.getBalance().eq(insuranceAccount.getBalance())) {
      insuranceService.insert(insuranceAccount.getBalance());
      Position position = getOrCreateInsurancePosition(insuranceAccount, trade.getSymbol());
      marginHistoryService.log(
          MarginHistoryAction.LIQUIDATION,
          position,
          position,
          beforeInsuranceAccount,
          insuranceAccount);
    }

    if (Objects.equals(buyAccount.getId(), sellAccount.getId())) {
      positionHistoryService.log(PositionHistoryAction.MATCHING, beforeSellPosition, sellPosition);
      marginHistoryService.log(
          MarginHistoryAction.MATCHING_BUY,
          beforeBuyPosition,
          buyPosition,
          beforeBuyAccount,
          buyAccount,
          trade,
          trade.getRealizedPnlOrderBuy(),
          buyClosePositionFee,
          buyOpenPositionFee
      );
      marginHistoryService.log(
          MarginHistoryAction.MATCHING_SELL,
          buyPosition,
          sellPosition,
          buyAccount,
          sellAccount,
          trade,
          trade.getRealizedPnlOrderSell(),
          sellClosePositionFee,
          sellOpenPositionFee
      );
    } else {
      positionHistoryService.log(PositionHistoryAction.MATCHING, beforeBuyPosition, buyPosition);
      positionHistoryService.log(PositionHistoryAction.MATCHING, beforeSellPosition, sellPosition);
      marginHistoryService.log(
          MarginHistoryAction.MATCHING_BUY,
          beforeBuyPosition,
          buyPosition,
          beforeBuyAccount,
          buyAccount,
          trade,
          trade.getRealizedPnlOrderBuy(),
          buyClosePositionFee,
          buyOpenPositionFee
      );
      marginHistoryService.log(
          MarginHistoryAction.MATCHING_SELL,
          beforeSellPosition,
          sellPosition,
          beforeSellAccount,
          sellAccount,
          trade,
          trade.getRealizedPnlOrderSell(),
          sellClosePositionFee,
          sellOpenPositionFee
      );
    }

    if (buyAccount.getId().equals(accountService.getInsuranceAccountId(buyAccount.getAsset()))) {
      MarginBigDecimal availableBalance =
          accountService.getAccountAvailableBalance(buyAccount.getId());
      insuranceService.insert(availableBalance);
    }
    if (sellAccount.getId().equals(accountService.getInsuranceAccountId(sellAccount.getAsset()))) {
      MarginBigDecimal availableBalance =
          accountService.getAccountAvailableBalance(sellAccount.getId());
      insuranceService.insert(availableBalance);
    }

    // Set balance to 0 if account balances are less than 0 
    if (buyAccount.getBalance().lt(MarginBigDecimal.ZERO)) {
      buyAccount.setBalance(MarginBigDecimal.ZERO);
      accountService.update(buyAccount);
    }
    if (sellAccount.getBalance().lt(MarginBigDecimal.ZERO)) {
      sellAccount.setBalance(MarginBigDecimal.ZERO);
      accountService.update(sellAccount);
    }
  }

  private MarginBigDecimal convertQuantity(MarginBigDecimal quantity, Order order) {
    if (order.isBuyOrder()) {
      return quantity;
    } else {
      return quantity.negate();
    }
  }

  /**
   * Calculate a lock price of order
   *
   * @param order
   * @param orderQueue
   * @return
   */
  public MarginBigDecimal calculateLockPrice(Order order, TreeSet<Order> orderQueue) {
    // order is limit then lock price = input price
    if (order.isLimitOrder()) {
      return order.getPrice();
    }
    // Order is market order
    // if orderBook is empty then throw an exception can not calculate lock price
    if (orderQueue.isEmpty()) {
      throw new LockPriceException(order);
    }
    return calculateMarketLockPrice(order, orderQueue);
  }

  /**
   * Calculate a lock price when order is market order
   *
   * @param order market order to calculate
   * @param orderQueue
   * @return
   */
  public MarginBigDecimal calculateMarketLockPrice(Order order, TreeSet<Order> orderQueue) {
    Order firstOrder = orderQueue.first();
    // buy order
    if (order.isBuyOrder()) {
      // return lowest Sell price * (1 + 0.05%)
      return firstOrder.getPrice().multiply(MarginBigDecimal.valueOf("1.005"));
    }
    // sell order then return highest buy order
    return firstOrder.getPrice();
  }

  public Order createLiquidationOrder(Position position) {
    return createLiquidationOrder(position, position.getCurrentQty().abs());
  }

  public Order createLiquidationOrder(Position position, MarginBigDecimal quantity) {
    // Not a bug: Liquidation order will be made at bankrupt price to maximize liquidity
    MarginBigDecimal price = position.getBankruptPrice();
    // to trace if bankrupt price is negative
    if (price.lt(0)) {
      log.warn(
          "createLiquidationOrder position bankrupt price is negative posId={}, price={}",
          position.getId(),
          position.getBankruptPrice());
      price = price.negate();
    }

    Account positionAccount = accountService.get(position.getAccountId());

    OrderSide side = position.getCurrentQty().gt(0) ? OrderSide.SELL : OrderSide.BUY;
    Order order = createDefaultOrder();
    order.setLeverage(position.getLeverage());
    order.setAsset(position.getAsset());
    order.setUserId(position.getUserId());
    order.setAccountId(position.getAccountId());
    order.setSymbol(position.getSymbol());
    order.setSide(side);
    order.setType(OrderType.LIMIT);
    order.setQuantity(quantity);
    order.setRemaining(quantity);
    order.setPrice(price);
    order.setReduceOnly(true);
    MarginMode orderMarginMode = position.isCross() ? MarginMode.CROSS : MarginMode.ISOLATE;
    order.setMarginMode(orderMarginMode);
    order.setNote(OrderNote.LIQUIDATION);
    order.setContractType(position.getContractType());
    order.setTimeInForce(TimeInForce.IOC);
    order.setUserEmail(positionAccount.getUserEmail());
    insert(order);
    return order;
  }

  public Order createInsuranceLiquidationOrder(Order otherOrder) {
    Order order = createDefaultOrder();

    MarginBigDecimal quantity = otherOrder.getQuantity().abs();
    OrderSide side = otherOrder.getSide() == OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY;
    order.setLeverage(otherOrder.getLeverage());
    order.setAsset(otherOrder.getAsset());
    order.setUserId(AccountService.INSURANCE_USER_ID);
    order.setAccountId(accountService.getInsuranceAccountId(otherOrder.getAsset()));
    order.setUserEmail(insuranceAccountOwner);
    order.setSymbol(otherOrder.getSymbol());
    order.setSide(side);
    order.setType(OrderType.LIMIT);
    order.setQuantity(quantity);
    order.setMarginMode(MarginMode.ISOLATE);
    order.setRemaining(quantity);
    order.setPrice(otherOrder.getPrice());
    order.setNote(OrderNote.INSURANCE_LIQUIDATION);
    order.setContractType(otherOrder.getContractType());
    order.setTimeInForce(TimeInForce.IOC);
    insert(order);
    return order;
  }

  public Order createAdlOrder(Order otherOrder, Position adlPosition, MarginBigDecimal quantity) {
    Order order = createDefaultOrder();
    Account adlPostionAccount = accountService.get(adlPosition.getAccountId());

    OrderSide side = otherOrder.getSide() == OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY;
    MarginMode marginMode = adlPosition.isCross() ? MarginMode.CROSS : MarginMode.ISOLATE;
    order.setLeverage(adlPosition.getLeverage());
    order.setAsset(adlPosition.getAsset());
    order.setUserId(adlPosition.getUserId());
    order.setAccountId(adlPosition.getAccountId());
    order.setSymbol(adlPosition.getSymbol());
    order.setUserEmail(adlPostionAccount.getUserEmail());
    order.setSide(side);
    order.setType(OrderType.LIMIT);
    order.setQuantity(quantity);
    order.setMarginMode(marginMode);
    order.setRemaining(quantity);
    order.setPrice(otherOrder.getPrice());
    order.setNote(OrderNote.AUTO_DELEVERAGE);
    order.setTimeInForce(TimeInForce.IOC);
    order.setLockPrice(otherOrder.getLockPrice());
    order.setContractType(otherOrder.getContractType());
    insert(order);
    return order;
  }

  public Order createInsuranceClosePositionOrder(Position position) {
    MarginBigDecimal quantity = position.getCurrentQty().abs();
    OrderSide side = position.getCurrentQty().gt(0) ? OrderSide.SELL : OrderSide.BUY;

    MarginBigDecimal price =
        getInsuranceClosingPositionPrice(
            position, MarginBigDecimal.valueOf("0.015")); // 1.5 percent

    Order order = createDefaultOrder();
    order.setLeverage(position.getLeverage());
    order.setAsset(position.getAsset());
    order.setMarginMode(MarginMode.ISOLATE);
    order.setUserId(AccountService.INSURANCE_USER_ID);
    order.setUserEmail(insuranceAccountOwner);
    order.setAccountId(accountService.getInsuranceAccountId(position.getAsset()));
    order.setSymbol(position.getSymbol());
    order.setSide(side);
    order.setType(OrderType.LIMIT);
    order.setQuantity(quantity);
    order.setRemaining(quantity);
    order.setPrice(price);
    order.setReduceOnly(true);
    order.setNote(OrderNote.INSURANCE_FUNDING);
    order.setContractType(position.getContractType());
    order.setTimeInForce(TimeInForce.IOC);
    insert(order);
    return order;
  }

  private Order createDefaultOrder() {
    Date date = new Date();
    Order order = new Order();
    order.setStatus(OrderStatus.PENDING);
    order.setOrderMargin(MarginBigDecimal.ZERO);
    order.setCost(MarginBigDecimal.ZERO);
    order.setCreatedAt(date);
    order.setUpdatedAt(date);
    return order;
  }

  private MarginBigDecimal getInsuranceClosingPositionPrice(
      Position position, MarginBigDecimal spread) {
    MarginBigDecimal ratio =
        position.getCurrentQty().lt(0)
            ? MarginBigDecimal.ONE.subtract(spread)
            : MarginBigDecimal.ONE.add(spread);
    MarginBigDecimal entryPrice = position.getEntryPrice();
    return entryPrice.multiply(ratio);
  }

  public Order createInsuranceLimitOrder(Order otherOrder) {
    Order order = createDefaultOrder();

    MarginBigDecimal quantity = otherOrder.getQuantity().abs();
    OrderSide side = otherOrder.getSide() == OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY;
    order.setLeverage(otherOrder.getLeverage());
    order.setAsset(otherOrder.getAsset());
    order.setUserId(AccountService.INSURANCE_USER_ID);
    order.setAccountId(accountService.getInsuranceAccountId(otherOrder.getAsset()));
    order.setUserEmail(insuranceAccountOwner);
    order.setSymbol(otherOrder.getSymbol());
    order.setSide(side);
    order.setType(OrderType.LIMIT);
    order.setQuantity(quantity);
    order.setMarginMode(MarginMode.ISOLATE);
    order.setRemaining(quantity);
    order.setPrice(otherOrder.getPrice());
    order.setNote(OrderNote.INSURANCE_FUNDING);
    order.setContractType(otherOrder.getContractType());
    order.setTimeInForce(TimeInForce.IOC);
    insert(order);
    return order;
  }

  /**
   * Calculate order cost
   *
   * @param order order to calculate cost
   */
  public void calcOrderCost(Order order) {
    Long accountId = order.getAccountId();
    String symbol = order.getSymbol();
    MarginBigDecimal inputPrice = order.getLockPrice();
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(symbol);
    Position position = positionService.get(accountId, symbol);
    // the system always create new position when place a first order
    // so check position has quantity != 0 for sure
    boolean isHasPosition =
        ObjectUtils.isNotEmpty(position) && !position.getCurrentQty().eq(MarginBigDecimal.ZERO);
    // check position is long or short
    // long means current quantity > 0 and short means current quantity < 0
    boolean isLongPosition = position.getCurrentQty().gt(MarginBigDecimal.ZERO);

    // get marBuy/marSell from position
    MarginBigDecimal marBuy = position.getMarBuy();
    MarginBigDecimal marSel = position.getMarSel();

    MarginBigDecimal leverage = order.getLeverage();
    MarginBigDecimal mulBuy = marginCalculator.calcMulBuy(inputPrice, leverage);
    MarginBigDecimal mulSell = marginCalculator.calcMulSell(inputPrice, leverage);

    MarginBigDecimal orderCost;
    if (isHasPosition) {
      MarginBigDecimal positionMargin = marginCalculator.calcAllocatedMargin(position);
      MarginBigDecimal positionSize = position.getCurrentQty().abs();
      log.atDebug()
          .addKeyValue("orderId", order.getId())
          .addKeyValue("positionMargin", positionMargin)
          .addKeyValue("positionSize", position.getCurrentQty())
          .log("calcOrderCost with position.");
      orderCost =
          marginCalculator.calculateOrderCostWithPosition(
              isLongPosition,
              positionMargin,
              positionSize,
              inputPrice,
              marBuy,
              marSel,
              mulBuy,
              mulSell,
              order);
    } else {
      orderCost =
          marginCalculator.calculateOrderCostWithoutPosition(
              inputPrice, marBuy, marSel, mulBuy, mulSell, order);
    }
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("markPrice", marginCalculator.getOraclePrice())
        .addKeyValue("orderCost", orderCost)
        .addKeyValue("marBuy", marBuy)
        .addKeyValue("marSel", marSel)
        .addKeyValue("mulBuy", mulBuy)
        .addKeyValue("mulSell", mulSell)
        .log("calcOrderCost for order.");

    if (order.isLimitOrder()) {
      // add orderMargin to marBuy/marSell on position
      MarginBigDecimal orderMargin = marginCalculator.calcOrderMargin(order);
      order.setOriginalOrderMargin(orderMargin);
      order.setOrderMargin(orderMargin);
      if (order.isBuyOrder()) {
        position.setMarBuy(position.getMarBuy().add(orderMargin));
      } else {
        position.setMarSel(position.getMarSel().add(orderMargin));
      }
    }

    if (this.shouldAddOrderCostToPositionOrderCost(order)) {
      position.setOrderCost(position.getOrderCost().add(orderCost));
    }

    positionService.update(position);
    order.setOriginalCost(orderCost);
    order.setCost(orderCost);
  }

  private boolean shouldAddOrderCostToPositionOrderCost(Order order) {
    boolean shouldAddOrderCostToPositionOrderCost = true;
    // this is close-position market order
    if (
            order.isClosePositionOrder() &&
                    OrderType.MARKET.equals(order.getType()) &&
                    order.isReduceOnly()
    ) {
      shouldAddOrderCostToPositionOrderCost = false;
    }

    // this is stop-loss (stop-market) order for position
    if (
            order.isClosePositionOrder() &&
                    OrderType.MARKET.equals(order.getType()) &&
                    TPSLType.STOP_MARKET.equals(order.getTpSLType()) &&
                    order.isReduceOnly() &&
                    order.isTriggered()
    ) {
      shouldAddOrderCostToPositionOrderCost = false;
    }

    return shouldAddOrderCostToPositionOrderCost;
  }

  @Override
  public void cleanOldEntities() {
    if (removingEntities.size() == 0) {
      return;
    }
    Pair<Order, Long> oldEntity = removingEntities.peek();
    while (oldEntity != null && oldEntity.getRight() < System.currentTimeMillis()) {
      Order oldOrder = oldEntity.getLeft();
      entities.remove(oldOrder.getId());
      removingEntities.remove();
      oldEntity = removingEntities.peek();
    }
  }

  /**
   * Charge fee to asset balance of user
   *
   * @param account
   * @param fee
   * @return
   */
  public Account chargeFee(Account account, MarginBigDecimal fee) {
    return accountService.subAmountToBalance(account.deepCopy(), fee);
  }

  public void seedLiquidationOrderId(Command command) {
    EngineParams params = (EngineParams) command.getData();
    Set<Long> liqOrderIds = params.getLiquidationOrderIds();
    if (liqOrderIds == null || liqOrderIds.isEmpty()) return;
    this.liquidationOrderIdPool.addAll(liqOrderIds.stream().toList());
  }

  public boolean shouldSeedLiquidationOrderIdPool() {
      return this.liquidationOrderIdPool.size() <= 700;
  }
}

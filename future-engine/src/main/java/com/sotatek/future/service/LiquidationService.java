package com.sotatek.future.service;

import static com.sotatek.future.engine.MatchingEngine.matchers;

import com.sotatek.future.engine.Matcher;
import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.entity.Transaction;
import com.sotatek.future.enums.*;
import com.sotatek.future.exception.InsufficientBalanceException;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;
import java.util.stream.Stream;
import lombok.AccessLevel;
import lombok.NoArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.tuple.Pair;

@Slf4j
@NoArgsConstructor(access = AccessLevel.PRIVATE)
public class LiquidationService {
  private static final LiquidationService instance = new LiquidationService();

  private AccountService accountService;
  private OrderService orderService;
  private PositionService positionService;

  private TradeService tradeService;

  private final List<Position> liquidatedPositions = new ArrayList<>();

  private final List<Transaction> liquidatedTransactions = new ArrayList<>();

  private PositionCalculator positionCalculator;

  private MarginHistoryService marginHistoryService;

  public static LiquidationService getInstance() {
    return instance;
  }

  public void initialize(
      AccountService accountService,
      OrderService orderService,
      PositionService positionService,
      TradeService tradeService,
      PositionCalculator positionCalculator,
      MarginHistoryService marginHistoryService) {
    this.accountService = accountService;
    this.orderService = orderService;
    this.positionService = positionService;
    this.tradeService = tradeService;
    this.positionCalculator = positionCalculator;
    this.marginHistoryService = marginHistoryService;
  }

  public void rollback() {
    liquidatedPositions.clear();
    liquidatedTransactions.clear();
  }

  public void commit() {
    liquidatedPositions.clear();
    liquidatedTransactions.clear();
  }

  public List<Position> getLiquidatedPositions() {
    return new ArrayList<>(liquidatedPositions);
  }

  public List<Transaction> getLiquidatedTransactions() {
    return new ArrayList<>(liquidatedTransactions);
  }

  public void closeInsurancePosition(
      Position position, Matcher matcher, MatchingEngine matchingEngine) {
    log.info("Close insurance position {}", position);
    Order order = orderService.createInsuranceClosePositionOrder(position);
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("accountId", order.getAccountId())
        .addKeyValue("price", order.getPrice())
        .log("Activating insurance closing order");
    matcher.activateOrder(order);
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("status", order.getStatus())
        .log("Finish activating insurance closing order.");
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("status", order.getStatus())
        .log("Processing insurance closing order.");
    while (matcher.processOrder(order)) {
      matchingEngine.commit();
    }
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("status", order.getStatus())
        .log("Finish processing insurance closing order.");
  }

  private Position finalizingLiquidation(
      Long accId, String symbol, MarginBigDecimal clearanceFee, MarginBigDecimal insurance) {
    Position position = positionService.get(accId, symbol);
    log.atDebug().log("Final position after liquidation. [qty={}]", position.getCurrentQty());
    position.setLiquidationProgress(0);
    if (position.isIsolated()) {
      log.atDebug()
          .addKeyValue("accId", position.getAccountId())
          .addKeyValue("symbol", position.getSymbol())
          .log("Resetting added margin. [margin={}]", position.getAdjustMargin());
      position.setAdjustMargin(MarginBigDecimal.ZERO);
    }
    positionService.update(position);
    log.atDebug()
        .addKeyValue("accId", accId)
        .addKeyValue("symbol", symbol)
        .log(
            "Saving liquidation transactions. [clearance={}, insurance={}]",
            clearanceFee,
            insurance);
    logLiquidationTransactions(
        accId,
        symbol,
        clearanceFee,
        position.getAsset(),
        position.getContractType(),
        position.getUserId(),
        TransactionType.LIQUIDATION_CLEARANCE);
    // log margin insurance fee (remaining margin if liquidate at better price)
    if (!insurance.eq(MarginBigDecimal.ZERO)) {
      logLiquidationTransactions(
          accId,
          symbol,
          insurance,
          position.getAsset(),
          position.getContractType(),
          position.getUserId(),
          TransactionType.MARGIN_INSURANCE_FEE);
    }
    return position;
  }

  public Position liquidate(Position initialPosition) {
    Matcher matcher = matchers.get(initialPosition.getSymbol());
    MatchingEngine matchingEngine = MatchingEngine.getInstance();
    initialPosition.setLiquidationProgress(1);
    positionService.update(initialPosition);

    String symbol = initialPosition.getSymbol();
    Long accId = initialPosition.getAccountId();
    log.atDebug().log(
        "Start liquidate initialPosition. [qty={}, liquidationPrice={}, bankruptPrice={}]",
        initialPosition.getCurrentQty(),
        initialPosition.getLiquidationPrice(),
        initialPosition.getBankruptPrice());
    cancelUserActiveOrder(initialPosition, matcher);

    // Liquidate using the market
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(symbol);
    MarginBigDecimal positionMargin = marginCalculator.calcAllocatedMargin(initialPosition);
    MarginBigDecimal liquidationPnl = MarginBigDecimal.ZERO;
    MarginBigDecimal liquidationTradingFee = MarginBigDecimal.ZERO;
//    Position beforeMarketPosition = positionService.get(accId, symbol);
//    if (initialPosition.isCross()) {
//      // Liquidation clearance fee can change liquidation/bankrupt price of cross initialPosition
//      beforeMarketPosition = positionService.update(beforeMarketPosition);
//    }
//    Pair<Boolean, Order> closeMarketResult =
//        closePositionInMarket(beforeMarketPosition, matcher, matchingEngine);
//    liquidationPnl = liquidationPnl.add(closeMarketResult.getRight().getLiquidationPnl());
//    liquidationTradingFee =
//        liquidationTradingFee.add(closeMarketResult.getRight().getLiquidationPnl());
//    if (!closeMarketResult.getLeft()) {
      // Final liquidation step by insurance fund it not liquidated by market
      Position beforeInsurance = positionService.get(accId, symbol);
      Pair<Boolean, Order> closeInsuranceOrderResult =
          closePositionByInsuranceFund(beforeInsurance);
      if (closeInsuranceOrderResult.getLeft()) {
        Order closeInsuranceOrder = closeInsuranceOrderResult.getRight();
        liquidationPnl = liquidationPnl.add(closeInsuranceOrder.getLiquidationPnl());
        liquidationTradingFee =
            liquidationTradingFee.add(closeInsuranceOrder.getLiquidationTradingFee());
      }
//    }

    // Collect liquidation fee after adding realised Pnl
    MarginBigDecimal clearanceFee =
        collectLiquidationFee(initialPosition, initialPosition.getLiquidationPrice());

    // Take the profit from initialPosition that got liquidated at better than bankruptcy price
    MarginBigDecimal liquidationInsurance = MarginBigDecimal.ZERO;
    if (initialPosition.isIsolated()) {
      liquidationInsurance =
          collectExtraIsolatedMargin(
              accId, symbol, positionMargin, liquidationPnl, liquidationTradingFee);
    }
//    else {
//      if (closeMarketResult.getLeft()) {
//        // Only collect liquidation pnl if cross initialPosition is fully closed in the market
//        // Otherwise, when initialPosition is partially closed by insurance fund,
//        // any liquidation pnl has already been taken into account by closing price
//        liquidationInsurance = collectExtraCrossMargin(accId, symbol, liquidationPnl);
//      }
//    }

    boolean adlEnabled = Boolean.parseBoolean(System.getProperty("adl.enabled", "false"));
    if (adlEnabled) {
      Position beforeAdl = positionService.get(accId, symbol);
      if (!beforeAdl.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
        // Position still open after insurance, perform ADL
        liquidateByAdl(beforeAdl, matcher);
      }
    }
    return finalizingLiquidation(accId, symbol, clearanceFee, liquidationInsurance);
  }

  private void cancelUserActiveOrder(Position position, Matcher matcher) {
    if (position.isCross()) {
      log.atDebug()
          .addKeyValue("accId", position.getAccountId())
          .addKeyValue("symbol", position.getSymbol())
          .log("Cancel all user's orders.");
      List<Order> activeOrders = orderService.getUserActiveOrders(position.getAccountId());
      log.atDebug().addKeyValue("orderCount", activeOrders.size()).log("Start cancelling orders.");
      for (Order order : activeOrders) {
        log.atDebug().addKeyValue("orderId", order.getId()).log("Canceling order.");
        matcher.cancelOrder(order);
      }
    } else {
      log.atDebug()
          .addKeyValue("accId", position.getAccountId())
          .addKeyValue("symbol", position.getSymbol())
          .log("Not cancelling order when liquidating isolated position");
    }
  }

  private void cancelActiveAdlCandidateOrders(Position adlPosition, Matcher matcher) {
    log.atDebug()
        .addKeyValue("accId", adlPosition.getAccountId())
        .addKeyValue("symbol", adlPosition.getSymbol())
        .log("Cancel user's orders before ADL");
    Stream<Order> userOpenOrders =
        orderService.getUserOpenOrders(adlPosition.getAccountId(), adlPosition.getSymbol());
    userOpenOrders.forEach(
        order -> {
          log.atDebug()
              .addKeyValue("accId", adlPosition.getAccountId())
              .addKeyValue("symbol", adlPosition.getSymbol())
              .addKeyValue("orderId", order.getId())
              .log("Cancel order before ADL");
          matcher.cancelOrder(order);
        });
  }

  private Pair<Boolean, Order> closePositionInMarket(
      Position initialPosition, Matcher matcher, MatchingEngine matchingEngine) {
    Long accId = initialPosition.getAccountId();
    String symbol = initialPosition.getSymbol();
    final MarginBigDecimal liquidationPrice = initialPosition.getLiquidationPrice();
    final MarginBigDecimal bankruptPrice = initialPosition.getBankruptPrice();
    log.atDebug()
        .addKeyValue("positionId", initialPosition.getId())
        .log(
            "Liquidating using market. [qty={}, liquidationPrice={}," + " bankruptPrice={}]",
            initialPosition.getCurrentQty(),
            liquidationPrice,
            bankruptPrice);
    liquidatedPositions.add(initialPosition.deepCopy());
    log.debug("Close in market {}", initialPosition);
    Order order = orderService.createLiquidationOrder(initialPosition);
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("accountId", order.getAccountId())
        .addKeyValue("price", order.getPrice())
        .log("Activating liquidation order");
    matcher.activateOrder(order);
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("status", order.getStatus())
        .log("Finish activating liquidation order. ");
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("status", order.getStatus())
        .log("Processing liquidation order");
    while (matcher.processOrder(order)) {
      matchingEngine.commit();
    }
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("status", order.getStatus())
        .log("Finish processing liquidation order.");

    Position afterPosition = positionService.get(accId, symbol);
    if (initialPosition.isIsolated()) {
      // Part of the added margin need to be used to pay off any PNL incurred during liquidation,
      // therefore, we have to reduce added margin in proportion to the liquidated amount.
      // In effect, this allows the position to maintain the same liquidation and bankruptcy price
      MarginBigDecimal newAdjustMargin =
          initialPosition
              .getAdjustMargin()
              .multiply(afterPosition.getCurrentQty())
              .divide(initialPosition.getCurrentQty());
      log.atDebug()
          .addKeyValue("accId", accId)
          .addKeyValue("symbol", symbol)
          .log(
              "Adjusting added margin based. [old_val={}, new_val={}]",
              initialPosition.getAdjustMargin(),
              newAdjustMargin);
      afterPosition.setAdjustMargin(newAdjustMargin);
      positionService.update(afterPosition);
    }

    if (afterPosition.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
      log.atDebug()
          .addKeyValue("accId", accId)
          .addKeyValue("symbol", symbol)
          .log("Finish liquidation after market match.");
      return Pair.of(true, order);
    }
    return Pair.of(false, order);
  }

  private Pair<Boolean, Order> closePositionByInsuranceFund(Position initialPosition) {
    // call commit to save status of those entities on case it has handled partial liquidation with
    // order on order book
    commitTemporarily();
    final MarginBigDecimal liquidationPrice = initialPosition.getLiquidationPrice();
    final MarginBigDecimal bankruptPrice = initialPosition.getBankruptPrice();
    log.atDebug()
        .addKeyValue("positionId", initialPosition.getKey())
        .log(
            "Liquidating using insurance fund. [qty={}, liquidationPrice={}, bankruptPrice={}]",
            initialPosition.getCurrentQty(),
            liquidationPrice,
            bankruptPrice);

    log.info("Close position by insurance fund {}", initialPosition);
    Order order = orderService.createLiquidationOrder(initialPosition);
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("accountId", order.getAccountId())
        .addKeyValue("price", order.getPrice())
        .log("Activating liquidation order.");
    order.setLockPrice(order.getPrice());
    orderService.activateOrder(order);
    log.atDebug()
        .addKeyValue("orderId", order.getId())
        .addKeyValue("status", order.getStatus())
        .log("Finish activating liquidation order");
    Order insuranceOrder = orderService.createInsuranceLiquidationOrder(order);
    try {
      insuranceOrder.setLockPrice(order.getPrice());
      log.atDebug()
          .addKeyValue("orderId", insuranceOrder.getId())
          .addKeyValue("status", insuranceOrder.getStatus())
          .log("Activating insurance order");
      orderService.activateOrder(insuranceOrder);
      log.atDebug()
          .addKeyValue("orderId", insuranceOrder.getId())
          .addKeyValue("status", insuranceOrder.getStatus())
          .log("Finish activating insurance order");
      Pair<Order, Trade> result = orderService.matchOrders(order, insuranceOrder);
      Trade trade = result.getRight();
      log.info("Liquidation trade: {}", trade);
      commitTemporarily();
      return Pair.of(true, order);
    } catch (InsufficientBalanceException e) {
      log.atWarn()
          .setCause(e)
          .addKeyValue("orderId", insuranceOrder.getId())
          .addKeyValue("status", insuranceOrder.getStatus())
          .log("Not enough fund to liquidate with insurance fund");
      // Cancel liquidation order
      //      orderService.cancel(order);
      //      orderService.cancel(insuranceOrder);
      //      return Pair.of(false, null);
      rollBackTemporarily();
      throw e;
    }
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

  public void liquidateByAdl(Position initialPosition, Matcher matcher) {
    Long accId = initialPosition.getAccountId();
    String symbol = initialPosition.getSymbol();
    Position currentPosition = initialPosition;
    // Continue to perform liquidation by ADL until the position is fully closed
    while (!currentPosition.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
      final MarginBigDecimal liquidationPrice = currentPosition.getLiquidationPrice();
      final MarginBigDecimal bankruptPrice = currentPosition.getBankruptPrice();
      log.atDebug()
          .addKeyValue("accId", currentPosition.getAccountId())
          .addKeyValue("symbol", currentPosition.getSymbol())
          .log(
              "Liquidating using adl. [qty={}, liquidationPrice={}, bankruptPrice={}]",
              currentPosition.getCurrentQty(),
              liquidationPrice,
              bankruptPrice);

      log.info("Close position by ADL mechanism {}", currentPosition);

      // Pick position for ADL
      boolean longPosition = currentPosition.getCurrentQty().lt(MarginBigDecimal.ZERO);
      Optional<Position> adlCandidateOpt =
          positionService.getPositionForAdl(currentPosition.getSymbol(), longPosition);
      if (adlCandidateOpt.isEmpty()) {
        log.atWarn().log("Unable to find position for ADL");
        break;
      }
      Position candidate = adlCandidateOpt.get();
      // Cancel all active orders of the candidate before ADL
      cancelActiveAdlCandidateOrders(candidate, matcher);

      // Calculate the max amount that can be liquidated by ADL the candidate
      MarginBigDecimal liquidateSize = currentPosition.getCurrentQty().abs();
      MarginBigDecimal adlCandidateSize = candidate.getCurrentQty().abs();
      if (liquidateSize.gt(adlCandidateSize)) {
        liquidateSize = adlCandidateSize;
      }
      // Create a liquidation order at bankruptcy price
      Order order = orderService.createLiquidationOrder(currentPosition, liquidateSize);
      log.atDebug()
          .addKeyValue("orderId", order.getId())
          .addKeyValue("accountId", order.getAccountId())
          .addKeyValue("price", order.getPrice())
          .log("Activating liquidation order for ADL");
      order.setLockPrice(order.getPrice());
      orderService.activateOrder(order);
      log.atDebug()
          .addKeyValue("orderId", order.getId())
          .addKeyValue("status", order.getStatus())
          .log("Finish activating liquidation order for ADL");
      // Create an ADL order to match with the liquidation:
      // with same size, same price, but reverse polarity
      Order adlOrder = orderService.createAdlOrder(order, candidate, liquidateSize);
      log.atDebug()
          .addKeyValue("orderId", adlOrder.getId())
          .addKeyValue("status", adlOrder.getStatus())
          .log("Activating ADL order");
      orderService.activateOrder(adlOrder);
      log.atDebug()
          .addKeyValue("orderId", adlOrder.getId())
          .addKeyValue("status", adlOrder.getStatus())
          .log("Finish activating ADL order");
      // Match the liquidation order with the ADL order directly
      Pair<Order, Trade> result = orderService.matchOrders(order, adlOrder);
      Trade trade = result.getRight();
      log.info("ADL trade: {}", trade);

      // Update the liquidated position size
      currentPosition = positionService.get(accId, symbol);
    }
  }

  private MarginBigDecimal collectLiquidationFee(
      Position liquidatedPosition, MarginBigDecimal clearancePrice) {
    MarginBigDecimal liquidationClearanceFee =
        positionCalculator.getClearanceFee(
            liquidatedPosition, liquidatedPosition.getCurrentQty(), clearancePrice);
    String symbol = liquidatedPosition.getSymbol();
    Asset asset = liquidatedPosition.getAsset();
    Account liquidatedAccountBefore = accountService.get(liquidatedPosition.getAccountId()).deepCopy();
    Account liquidatedAccount = accountService.get(liquidatedPosition.getAccountId());
    MarginBigDecimal userBalance = liquidatedAccount.getBalance();
    if (userBalance.subtract(liquidationClearanceFee).lt(MarginBigDecimal.ZERO)) {
      MarginBigDecimal newClearanceFee;
      if (userBalance.gte(MarginBigDecimal.ZERO)) {
        newClearanceFee = userBalance;
      } else {
        log.atWarn()
            .addKeyValue("accId", liquidatedPosition.getAccountId())
            .addKeyValue("symbol", liquidatedPosition.getSymbol())
            .log("Account balance already negative before subtracting liquidation clearance fee.");
        newClearanceFee = MarginBigDecimal.ZERO;
      }
      log.atDebug()
          .addKeyValue("accId", liquidatedAccount.getId())
          .log(
              "Adjust clearance fee due to insufficient balance. [old_val={}, new_val={}]",
              liquidationClearanceFee,
              newClearanceFee);
      liquidationClearanceFee = newClearanceFee;
    }
    if (liquidationClearanceFee.gt(MarginBigDecimal.ZERO)) {
      log.atDebug()
          .addKeyValue("accId", liquidatedAccount.getId())
          .log("Collecting liquidation clearance fee. [value={}]", liquidationClearanceFee);
      Account liquidatedAccountUpdate =
          liquidatedAccount.subAmountToBalance(liquidationClearanceFee);
      accountService.update(liquidatedAccountUpdate);
      marginHistoryService.log(
              MarginHistoryAction.COLLECT_LIQ_CLEARANCE_FEE,
              positionService.get(liquidatedPosition.getKey()),
              positionService.get(liquidatedPosition.getKey()),
              liquidatedAccountBefore,
              liquidatedAccountUpdate);

      Account insuranceAccountBefore = accountService.getInsuranceAccount(asset).deepCopy();
      Account insuranceAccountUpdate =
          accountService.getInsuranceAccount(asset).addAmountToBalance(liquidationClearanceFee);
      accountService.update(insuranceAccountUpdate);
      marginHistoryService.log(
              MarginHistoryAction.COLLECT_LIQ_CLEARANCE_FEE,
              positionService.get(insuranceAccountUpdate.getId(), symbol),
              positionService.get(insuranceAccountUpdate.getId(), symbol),
              insuranceAccountBefore,
              insuranceAccountUpdate);
    }
    return liquidationClearanceFee;
  }

  private MarginBigDecimal collectExtraIsolatedMargin(
      Long accountId,
      String symbol,
      MarginBigDecimal positionMargin,
      MarginBigDecimal liquidationPnl,
      MarginBigDecimal liquidationTradingFee) {
    Account liquidatedAccountBefore = accountService.get(accountId).deepCopy();
    Account liquidatedAccount = accountService.get(accountId);
    MarginBigDecimal remainingMargin = positionMargin.add(liquidationPnl);
    log.atDebug()
        .addKeyValue("accId", accountId)
        .addKeyValue("symbol", symbol)
        .log(
            "Collecting left-over isolated margin. [remaining={}, margin={}, liquidationPnl={},"
                + " liquidationTradingFee={}]",
            remainingMargin,
            positionMargin,
            liquidationPnl,
            liquidationTradingFee);
    if (remainingMargin.gt(MarginBigDecimal.ZERO)) {
      MarginBigDecimal currentBalance = liquidatedAccount.getBalance();
      if (currentBalance.subtract(remainingMargin).lt(MarginBigDecimal.ZERO)) {
        MarginBigDecimal newMargin;
        if (currentBalance.gte(MarginBigDecimal.ZERO)) {
          newMargin = currentBalance;
        } else {
          log.atWarn()
              .addKeyValue("accId", accountId)
              .addKeyValue("symbol", symbol)
              .log("Account balance already negative before subtracting remaining margin.");
          newMargin = MarginBigDecimal.ZERO;
        }
        log.atDebug()
            .addKeyValue("accId", accountId)
            .addKeyValue("symbol", symbol)
            .log(
                "Adjusting isolated remaining margin due to insufficient balance. [old_val={},"
                    + " new_val={}]",
                remainingMargin,
                newMargin);
        remainingMargin = newMargin;
      }
      // Execute balancing transactions
      Account liquidatedAccountUpdate = liquidatedAccount.subAmountToBalance(remainingMargin);
      accountService.update(liquidatedAccountUpdate);
      marginHistoryService.log(
              MarginHistoryAction.COLLECT_EXTRA_ISOLATED_MARGIN,
              positionService.get(liquidatedAccountUpdate.getId(), symbol),
              positionService.get(liquidatedAccountUpdate.getId(), symbol),
              liquidatedAccountBefore,
              liquidatedAccountUpdate);

      Account insuranceAccountBefore = accountService.getInsuranceAccount(liquidatedAccountUpdate.getAsset()).deepCopy();
      Account insuranceAccountUpdate =
          accountService
              .getInsuranceAccount(liquidatedAccountUpdate.getAsset())
              .addAmountToBalance(remainingMargin);
      accountService.update(insuranceAccountUpdate);
      marginHistoryService.log(
              MarginHistoryAction.COLLECT_EXTRA_ISOLATED_MARGIN,
              positionService.get(insuranceAccountUpdate.getId(), symbol),
              positionService.get(insuranceAccountUpdate.getId(), symbol),
              insuranceAccountBefore,
              insuranceAccountUpdate);
      return remainingMargin;
    } else {
      return MarginBigDecimal.ZERO;
    }
  }

  private MarginBigDecimal collectExtraCrossMargin(
      Long accountId, String symbol, MarginBigDecimal liquidationPnl) {
    log.atDebug()
        .addKeyValue("accId", accountId)
        .addKeyValue("symbol", symbol)
        .log("Collecting extra pnl of cross margin. [liquidationPnl={}]", liquidationPnl);
    if (liquidationPnl.gt(MarginBigDecimal.ZERO)) {
      Account liquidatedAccountBefore = accountService.get(accountId).deepCopy();
      Account liquidatedAccount = accountService.get(accountId);
      MarginBigDecimal currentBalance = liquidatedAccount.getBalance();
      if (currentBalance.subtract(liquidationPnl).lt(MarginBigDecimal.ZERO)) {
        MarginBigDecimal newPnl;
        if (currentBalance.gte(MarginBigDecimal.ZERO)) {
          newPnl = currentBalance;
        } else {
          log.atWarn()
              .addKeyValue("accId", accountId)
              .addKeyValue("symbol", symbol)
              .log("Account balance already negative before subtracting extra pnl");
          newPnl = MarginBigDecimal.ZERO;
        }
        log.atDebug()
            .addKeyValue("accId", accountId)
            .addKeyValue("symbol", symbol)
            .log(
                "Adjusting cross extra pnl due to insufficient balance. [old_val={}, new_val={}]",
                liquidationPnl,
                newPnl);
        liquidationPnl = newPnl;
      }
      // Execute balancing transactions
      Account liquidatedAccountUpdate = liquidatedAccount.subAmountToBalance(liquidationPnl);
      accountService.update(liquidatedAccountUpdate);
      marginHistoryService.log(
              MarginHistoryAction.COLLECT_EXTRA_CROSS_MARGIN,
              positionService.get(liquidatedAccountUpdate.getId(), symbol),
              positionService.get(liquidatedAccountUpdate.getId(), symbol),
              liquidatedAccountBefore,
              liquidatedAccountUpdate);

      Account insuranceAccountBefore = accountService.getInsuranceAccount(liquidatedAccountUpdate.getAsset()).deepCopy();
      Account insuranceAccountUpdate =
          accountService
              .getInsuranceAccount(liquidatedAccountUpdate.getAsset())
              .addAmountToBalance(liquidationPnl);
      accountService.update(insuranceAccountUpdate);
      marginHistoryService.log(
              MarginHistoryAction.COLLECT_EXTRA_CROSS_MARGIN,
              positionService.get(insuranceAccountUpdate.getId(), symbol),
              positionService.get(insuranceAccountUpdate.getId(), symbol),
              insuranceAccountBefore,
              insuranceAccountUpdate);
      return liquidationPnl;
    } else {
      return MarginBigDecimal.ZERO;
    }
  }

  /**
   * Use insurance fund to clear negative account balance due to liquidation, not used yet
   *
   * @param accountId
   * @param asset
   */
  @Deprecated
  public void negativeAccountBalanceClearance(Long accountId, Asset asset, String symbol) {
    List<Position> accountPositions =
        positionService.getUserPositions(
            accountId, position -> !position.getCurrentQty().eq(0) && position.getAsset() == asset);
    if (!accountPositions.isEmpty()) {
      log.atDebug().log(
          "Skip negative account balance clearance because account still have open position."
              + " [pos={}]",
          accountPositions.size());
      return;
    }
    Account liquidatedAccount = accountService.get(accountId);
    MarginBigDecimal accountBalance = liquidatedAccount.getBalance();
    MarginBigDecimal adjustAmount;
    if (accountBalance.lt(MarginBigDecimal.ZERO)) {
      adjustAmount = accountBalance.negate();
      log.atDebug()
          .addKeyValue("accId", accountId)
          .log(
              "Transferring money from insurance fund to clear negative account balance."
                  + " [deficit={}]",
              adjustAmount);
      Account liquidatedAccountUpdate = liquidatedAccount.addAmountToBalance(adjustAmount);
      accountService.update(liquidatedAccountUpdate);
      Account insuranceAccountUpdate =
          accountService
              .getInsuranceAccount(liquidatedAccountUpdate.getAsset())
              .subAmountToBalance(adjustAmount);
      accountService.update(insuranceAccountUpdate);
      //      logLiquidationTransactions(
      //          accountId, "", accountBalance, asset, null, AccountService.INSURANCE_USER_ID,
      // TransactionType.NEGATIVE_ACCOUNT_CLEARANCE);
    }
  }

  private void logLiquidationTransactions(
      Long accountId,
      String symbol,
      MarginBigDecimal amount,
      Asset asset,
      ContractType contractType,
      Long userId,
      TransactionType type) {
    // Log the debit transaction from user account
    Transaction debitTransaction = new Transaction();
    debitTransaction.setAccountId(accountId);
    debitTransaction.setAmount(amount.negate());
    debitTransaction.setSymbol(symbol);
    debitTransaction.setType(type);
    debitTransaction.setStatus(TransactionStatus.CONFIRMED);
    debitTransaction.setAsset(asset);
    debitTransaction.setContractType(contractType);
    debitTransaction.setUserId(userId);
    liquidatedTransactions.add(debitTransaction);

    // Log the credit transaction to the insurance account
    Transaction creditTransaction = new Transaction();
    creditTransaction.setAccountId(accountService.getInsuranceAccountId(asset));
    creditTransaction.setAmount(amount);
    creditTransaction.setSymbol(symbol);
    creditTransaction.setType(type);
    creditTransaction.setStatus(TransactionStatus.CONFIRMED);
    creditTransaction.setAsset(asset);
    creditTransaction.setContractType(contractType);
    creditTransaction.setUserId(AccountService.INSURANCE_USER_ID);
    liquidatedTransactions.add(creditTransaction);
  }
}

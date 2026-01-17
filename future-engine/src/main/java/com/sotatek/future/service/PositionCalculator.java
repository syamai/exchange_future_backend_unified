package com.sotatek.future.service;

import com.google.common.base.Preconditions;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Position;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.value.LeverageMarginRule;
import java.util.List;
import java.util.Optional;
import lombok.extern.slf4j.Slf4j;
import org.jetbrains.annotations.NotNull;

/**
 * Calculate values related to a position such as
 *
 * <ul>
 *   <li>Liquidation price
 * </ul>
 *
 * <p>#TODO: implement more calculation
 */
@Slf4j
public class PositionCalculator {

  private static final MarginBigDecimal DEFAULT_LIQ_CLR_RATE =
      MarginBigDecimal.valueOf(1).divide(MarginBigDecimal.valueOf(100));

  private final TradingRuleService tradingRuleService;

  private final InstrumentService instrumentService;

  private final PositionService positionService;

  public PositionCalculator(
      TradingRuleService tradingRuleService,
      InstrumentService instrumentService,
      PositionService positionService) {
    this.tradingRuleService = tradingRuleService;
    this.instrumentService = instrumentService;
    this.positionService = positionService;
  }

  private MarginBigDecimal getContractMultiplier(Position position) {
    if (position.isCoinM()) {
      return this.instrumentService.get(position.getSymbol()).getMultiplier();
    }
    return MarginBigDecimal.ONE;
  }

  /**
   * Calculate liquidation price for a cross-margin position The liquidation price of a cross-margin
   * position needs to be recalculated in the following events
   *
   * <ul>
   *   <li>Wallet Balance change
   *   <li>Any isolated position margin change
   *   <li>Other cross-margin contracts change
   *   <li>Trading rule change
   * </ul>
   *
   * <p>#FIXME: Documents more events that can trigger liquidation price change
   *
   * @param position Position under check
   * @param allPositions All positions including the one under check
   * @param walletBalance
   * @param markPrice
   * @return liquidation price
   */
  public MarginBigDecimal getCrossLiquidationPrice(
      Position position,
      List<Position> allPositions,
      MarginBigDecimal walletBalance,
      MarginBigDecimal markPrice) {
    log.atDebug()
        .addKeyValue("posId", position.getId())
        .addKeyValue("accId", position.getAccountId())
        .addKeyValue("symbol", position.getSymbol())
        .addKeyValue("cross", position.isCross())
        .addKeyValue("coin_m", position.isCoinM())
        .addKeyValue("markPrice", markPrice)
        .log("Getting liquidation price for position");
    Preconditions.checkArgument(position.isCross(), "Position must be cross");

    // Getting trading rule for position
    LeverageMarginRule tradingRule = getTradingRule(position, markPrice);

    MarginBigDecimal size = position.getCurrentQty().abs();
    MarginBigDecimal cumB = tradingRule.maintenanceAmount();
    MarginBigDecimal side = MarginBigDecimal.getSign(position.getCurrentQty());
    MarginBigDecimal mmr = tradingRule.maintenanceMarginRate();
    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal ipm = null;
    MarginBigDecimal tmm = null;
    MarginBigDecimal upnl = null;
    MarginBigDecimal multiplier = null;
    MarginBigDecimal crossLiquidationPrice;

    if (position.isCoinM()) {
      multiplier = getContractMultiplier(position);
      // "Liquidation Price = (Size * MMR + Side * Size) /
      // [(WB + cumB) / Contract Multiplier + Side * Size / Entry Price]"
      MarginBigDecimal numerator = size.multiply(mmr).add(size.multiply(side));
      MarginBigDecimal denominator =
          walletBalance.add(cumB).divide(multiplier).add(size.multiplyThenDivide(side, entryPrice));
      crossLiquidationPrice = numerator.divide(denominator);
    } else {
      long positionId = position.getId();
      List<Position> otherPositions =
          allPositions.stream().filter(p -> p.getId() != positionId).toList();

      // Total isolated margin
      ipm = getIsolatedPositionMargin(otherPositions);
      tmm = getTotalMaintenanceMargin(otherPositions);
      upnl = getUnrealizedPNL(otherPositions);

      // (WB - IPM - TMM + UPNL + cumB - Side * Size * Entry Price)/ (Size * MMR - Side * Size)
      // numerator = WB - IPM -TMM + UPNL + cumB - Side * Size * EntryPrice
      MarginBigDecimal numerator =
          walletBalance
              .subtract(ipm)
              .subtract(tmm)
              .add(upnl)
              .add(cumB)
              .subtract(side.multiply(size).multiply(entryPrice));
      // denominator = (Size * MMR - Size * Side)
      MarginBigDecimal denominator = size.multiply(mmr).subtract(side.multiply(size));
      crossLiquidationPrice = numerator.divide(denominator);
    }

    log.atDebug().log(
        "Cross-margin liquidation price. [crossLiquidationPrice={}, wb={}, ipm={}, tmm={}, upnl={},"
            + " cumB={}, mmr={}, side={}, size={}, entryPrice={}, markPrice={}, multiplier={},"
            + " positionId={}]",
        crossLiquidationPrice,
        walletBalance,
        ipm,
        tmm,
        upnl,
        cumB,
        mmr,
        side,
        size,
        entryPrice,
        markPrice,
        multiplier,
        position.getId());
    return crossLiquidationPrice;
  }

  /**
   * Calculate bankrupt price for a cross-margin position
   *
   * @param position Position under check
   * @param allPositions All positions including the one under check
   * @param walletBalance
   * @param markPrice
   * @return
   */
  public MarginBigDecimal getCrossBankruptPrice(
      Position position,
      List<Position> allPositions,
      MarginBigDecimal walletBalance,
      MarginBigDecimal markPrice) {
    log.atDebug()
        .addKeyValue("posId", position.getId())
        .addKeyValue("accId", position.getAccountId())
        .addKeyValue("symbol", position.getSymbol())
        .addKeyValue("cross", position.isCross())
        .addKeyValue("coin_m", position.isCoinM())
        .addKeyValue("markPrice", markPrice)
        .log("Getting bankrupt price for cross position");
    Preconditions.checkArgument(position.isCross(), "Position must be cross");

    LeverageMarginRule tradingRule = getTradingRule(position, markPrice);

    MarginBigDecimal ipm = null;
    MarginBigDecimal upnl = null;
    MarginBigDecimal cumB = tradingRule.maintenanceAmount();
    MarginBigDecimal side = MarginBigDecimal.getSign(position.getCurrentQty());
    MarginBigDecimal size = position.getCurrentQty().abs();
    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal crossBankruptPrice;

    if (position.isCoinM()) {
      MarginBigDecimal multiplier = getContractMultiplier(position);
      // "Bankrupt Price = (Side * Size) /
      // [(WB + cumB) / Contract Multiplier + Side * Size / Entry Price]"
      MarginBigDecimal denominator =
          walletBalance.add(cumB).divide(multiplier).add(size.multiplyThenDivide(side, entryPrice));
      crossBankruptPrice = size.multiplyThenDivide(side, denominator);
    } else {
      long positionId = position.getId();
      List<Position> otherPositions =
          allPositions.stream().filter(p -> p.getId() != positionId).toList();
      ipm = getIsolatedPositionMargin(otherPositions);
      upnl = getUnrealizedPNL(otherPositions);
      // (WB - IPM + UPNL + cumB - Side * Size * Entry Price)/ ( - Side * Size)
      // numerator = (WB - IPM + UPNL + cumB - Side * Size * Entry Price)
      MarginBigDecimal numerator =
          walletBalance
              .subtract(ipm)
              .add(upnl)
              .add(cumB)
              .subtract(side.multiply(size).multiply(entryPrice));
      // denominator = ( - Side * Size)
      MarginBigDecimal denominator = side.multiply(size).negate();
      crossBankruptPrice = numerator.divide(denominator);
    }

    log.atDebug().log(
        "Cross-margin bankrupt price numerator. [crossBankruptPrice={}, wb={}, ipm={}, upnl={},"
            + " cumB={}, side={}, size={}, entryPrice={}, markPrice={}, positionId={}]",
        crossBankruptPrice,
        walletBalance,
        ipm,
        upnl,
        cumB,
        side,
        size,
        entryPrice,
        markPrice,
        position.getId());
    return crossBankruptPrice;
  }

  /**
   * Calculate liquidation price for an isolated-margin position The liquidation price of an
   * isolated-margin position needs to be recalculated in the following events
   *
   * <ul>
   *   <li>Margin change
   *   <li>MMR change
   *   <li>Position size change
   *   <li>Trading rule change
   * </ul>
   *
   * #FIXME: Documents more events that can trigger liquidation price change
   *
   * @param position
   * @param markPrice
   * @return liquidation price
   */
  public MarginBigDecimal getIsolatedLiquidationPrice(
      Position position, MarginBigDecimal markPrice) {
    log.atDebug()
        .addKeyValue("posId", position.getId())
        .addKeyValue("accId", position.getAccountId())
        .addKeyValue("symbol", position.getSymbol())
        .addKeyValue("cross", position.isCross())
        .addKeyValue("coin_m", position.isCoinM())
        .addKeyValue("markPrice", markPrice)
        .log("Getting liquidation price for position");
    Preconditions.checkArgument(!position.isCross(), "Position must be isolated");

    // Getting trading rule for position
    LeverageMarginRule tradingRule = getTradingRule(position, markPrice);

    MarginBigDecimal liquidationPrice;

    if (position.isCoinM()) {
      liquidationPrice = getIsolatedLiquidationPriceCoinM(position, tradingRule);
    } else {
      liquidationPrice = getIsolatedLiquidationPriceUsdM(position, tradingRule);
    }

    return liquidationPrice;
  }

  private MarginBigDecimal getIsolatedLiquidationPriceUsdM(
      Position position, LeverageMarginRule tradingRule) {
    Preconditions.checkArgument(
        !position.isCoinM(), "Position must be UsdM, posId: %s", position.getId());

    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal size = position.getCurrentQty().abs();
    MarginBigDecimal side = MarginBigDecimal.getSign(position.getCurrentQty());
    MarginBigDecimal addedMargin = position.getAdjustMargin();
    MarginBigDecimal positionMargin = position.getPositionMargin();
    MarginBigDecimal mmr = tradingRule.maintenanceMarginRate();
    MarginBigDecimal cumB = tradingRule.maintenanceAmount();
    MarginBigDecimal liquidationPrice;

    // Liquidation price =
    // (Allocate Position Margin + cumB - Side * Size * Entry price) / (Size * MMR - Side * Size)
    MarginBigDecimal numerator =
        positionMargin
            .add(addedMargin)
            .add(cumB)
            .subtract(side.multiply(size).multiply(entryPrice));
    MarginBigDecimal denominator = size.multiply(mmr).subtract(side.multiply(size));
    liquidationPrice = numerator.divide(denominator);
    log.atDebug()
        .addKeyValue("entryPrice", entryPrice)
        .addKeyValue("size", size)
        .addKeyValue("side", side)
        .addKeyValue("addedMargin", addedMargin)
        .addKeyValue("positionMargin", positionMargin)
        .addKeyValue("mmr", mmr)
        .addKeyValue("cumB", cumB)
        .addKeyValue("liquidationPrice", liquidationPrice)
        .addKeyValue("positionId", position.getId())
        .log("Isolated-margin Usd-M liquidation price.");
    return liquidationPrice;
  }

  private MarginBigDecimal getIsolatedLiquidationPriceCoinM(
      Position position, LeverageMarginRule tradingRule) {
    Preconditions.checkArgument(
        position.isCoinM(), "Position must be coinM, posId: %s", position.getId());

    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal leverage = position.getLeverage();
    MarginBigDecimal size = position.getCurrentQty().abs();
    MarginBigDecimal side = MarginBigDecimal.getSign(position.getCurrentQty());
    MarginBigDecimal positionMargin = position.getPositionMargin();
    MarginBigDecimal addedMargin = position.getAdjustMargin();
    MarginBigDecimal mmr = tradingRule.maintenanceMarginRate();
    MarginBigDecimal cumB = tradingRule.maintenanceAmount();
    MarginBigDecimal multiplier = getContractMultiplier(position);
    MarginBigDecimal liquidationPriceCoinM;

    // "Liquidation Price = (Size * MMR + Side * Size) /
    // [(Allocated Position Margin + cumB) / Contract Multiplier + Side * Size / Entry Price]"
    MarginBigDecimal numerator = size.multiply(mmr).add(size.multiply(side));
    MarginBigDecimal denominator =
        positionMargin
            .add(addedMargin)
            .add(cumB)
            .divide(multiplier)
            .add(size.multiplyThenDivide(side, entryPrice));
    liquidationPriceCoinM = numerator.divide(denominator);

    log.atDebug().log(
        "Isolated-margin CoinM liquidation price. [liquidationPriceCoinM={},"
            + " entryPrice={}, leverage={}, size={}, side={}, positionMargin={}, addedMargin={},"
            + " mmr={} cumB={}, multiplier={}, positionId={}]",
        liquidationPriceCoinM,
        entryPrice,
        leverage,
        size,
        side,
        positionMargin,
        addedMargin,
        mmr,
        cumB,
        multiplier,
        position.getId());

    return liquidationPriceCoinM;
  }

  /**
   * Calculate bankrupt price for an isolated position
   *
   * @param position
   * @return
   */
  public MarginBigDecimal getIsolatedBankruptPrice(Position position, MarginBigDecimal markPrice) {
    log.atDebug()
        .addKeyValue("posId", position.getId())
        .addKeyValue("accId", position.getAccountId())
        .addKeyValue("symbol", position.getSymbol())
        .addKeyValue("cross", position.isCross())
        .addKeyValue("coin_m", position.isCoinM())
        .addKeyValue("markPrice", markPrice)
        .log("Getting bankrupt price for position");
    Preconditions.checkArgument(!position.isCross(), "Position must be isolated");

    MarginBigDecimal bankruptPrice;
    LeverageMarginRule tradingRule = getTradingRule(position, markPrice);

    if (position.isCoinM()) {
      bankruptPrice = getIsolatedBankruptPriceCoinM(position, tradingRule);
    } else {
      bankruptPrice = getIsolatedBankruptPriceUsdM(position, tradingRule);
    }

    return bankruptPrice;
  }

  private MarginBigDecimal getIsolatedBankruptPriceUsdM(
      Position position, LeverageMarginRule tradingRule) {
    Preconditions.checkArgument(
        !position.isCoinM(), "Position must be UsdM, posId: %s", position.getId());

    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal size = position.getCurrentQty().abs();
    MarginBigDecimal side = MarginBigDecimal.getSign(position.getCurrentQty());
    MarginBigDecimal addedMargin = position.getAdjustMargin();
    MarginBigDecimal positionMargin = position.getPositionMargin();
    MarginBigDecimal cumB = tradingRule.maintenanceAmount();
    MarginBigDecimal bankruptPrice;

    // Bankrupt price =
    // (Allocated Position Margin + cumB - Side * Size * Entry price) / (- Side * Size)
    MarginBigDecimal numerator =
        positionMargin
            .add(addedMargin)
            .add(cumB)
            .subtract(side.multiply(size).multiply(entryPrice));
    MarginBigDecimal denominator = size.negate().multiply(side);
    bankruptPrice = numerator.divide(denominator);
    log.atDebug()
        .addKeyValue("entryPrice", entryPrice)
        .addKeyValue("size", size)
        .addKeyValue("side", side)
        .addKeyValue("addedMargin", addedMargin)
        .addKeyValue("positionMargin", positionMargin)
        .addKeyValue("cumB", cumB)
        .addKeyValue("bankruptPrice", bankruptPrice)
        .addKeyValue("positionId", position.getId())
        .log("Isolated-margin Usd-M bankrupt price.");
    return bankruptPrice;
  }

  private MarginBigDecimal getIsolatedBankruptPriceCoinM(
      Position position, LeverageMarginRule tradingRule) {
    Preconditions.checkArgument(
        position.isCoinM(), "Position must be CoinM, posId: %s", position.getId());

    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal leverage = position.getLeverage();
    MarginBigDecimal size = position.getCurrentQty().abs();
    MarginBigDecimal side = MarginBigDecimal.getSign(position.getCurrentQty());
    MarginBigDecimal addedMargin = position.getAdjustMargin();
    MarginBigDecimal positionMargin = position.getPositionMargin();
    MarginBigDecimal cumB = tradingRule.maintenanceAmount();
    MarginBigDecimal multiplier = getContractMultiplier(position);
    MarginBigDecimal bankruptPriceCoinM;

    // "Bankrupt Price = (Side * Size) /
    // [(Allocated Position Margin + cumB) / Contract Multiplier + Side * Size / Entry Price]"
    MarginBigDecimal denominator =
        positionMargin
            .add(addedMargin)
            .add(cumB)
            .divide(multiplier)
            .add(size.multiplyThenDivide(side, entryPrice));
    bankruptPriceCoinM = size.multiplyThenDivide(side, denominator);

    log.atDebug().log(
        "Isolated-margin CoinM bankrupt price. [bankruptPriceCoinM={}, entryPrice={},"
            + " leverage={}, size={}, side={}, addedMargin={}, positionMargin={}, cumB={},"
            + " multiplier={}, positionId={}]",
        bankruptPriceCoinM,
        entryPrice,
        leverage,
        size,
        side,
        addedMargin,
        positionMargin,
        cumB,
        multiplier,
        position.getId());

    return bankruptPriceCoinM;
  }

  @NotNull
  private MarginBigDecimal getIsolatedPositionMargin(List<Position> otherPositions) {
    return otherPositions.stream()
        .filter(p -> !p.isCross())
        .map(
            p -> {
              MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(p.getSymbol());
              return marginCalculator.calcAllocatedMargin(p);
            })
        .reduce(MarginBigDecimal.ZERO, (a, b) -> a.add(b));
  }

  @NotNull
  private MarginBigDecimal getUnrealizedPNL(List<Position> otherPositions) {
    return otherPositions.stream()
        .filter(Position::isCross)
        .map(this::getUnrealizedPNL)
        .reduce(MarginBigDecimal.ZERO, (a, b) -> a.add(b));
  }

  @NotNull
  private MarginBigDecimal getTotalMaintenanceMargin(List<Position> otherPositions) {
    return otherPositions.stream()
        .filter(Position::isCross)
        .map(this::getMaintenanceMargin)
        .reduce(MarginBigDecimal.ZERO, (a, b) -> a.add(b));
  }

  @NotNull
  public MarginBigDecimal getMaintenanceMargin(Position position) {
    InstrumentExtraInformation instrumentExtraInformation =
        instrumentService.getExtraInfo(position.getSymbol());
    if (instrumentExtraInformation != null) {
      MarginBigDecimal markPrice = instrumentExtraInformation.getOraclePrice();
      MarginBigDecimal multiplier = instrumentService.get(position.getSymbol()).getMultiplier();
      LeverageMarginRule tradingRule = getTradingRule(position, markPrice);
      MarginBigDecimal maintMargin;
      if (position.isCoinM()) {
        // "Maintenance Margin (of each Position)
        // = Size * (Contract Multiplier / Mark price) * Maintenance Margin rate - Maintenance
        // Amount"
        maintMargin =
            position
                .getCurrentQty()
                .abs()
                .multiply(multiplier)
                .multiply(tradingRule.maintenanceMarginRate())
                .divide(markPrice)
                .subtract(tradingRule.maintenanceAmount());
      } else {
        // "Maintenance Margin (of each Position)
        // = Size * Mark price * Maintenance Margin rate - Maintenance Amount"
        maintMargin =
            position
                .getCurrentQty()
                .abs()
                .multiply(markPrice)
                .multiply(tradingRule.maintenanceMarginRate())
                .subtract(tradingRule.maintenanceAmount());
      }

      log.atDebug()
          .addKeyValue("posId", position.getId())
          .addKeyValue("coin_m", position.isCoinM())
          .log(
              "Maintenance margin value for position. [value={}, multiplier={}, mmr={}, ma={}]",
              maintMargin,
              multiplier,
              tradingRule.maintenanceMarginRate(),
              tradingRule.maintenanceAmount());
      return maintMargin;
    } else {
      log.atWarn().addKeyValue("symbol", position.getSymbol()).log("No instrument data for symbol");
      return MarginBigDecimal.ZERO;
    }
  }

  @NotNull
  public MarginBigDecimal getUnrealizedPNL(Position position) {
    InstrumentExtraInformation instrumentExtraInformation =
        instrumentService.getExtraInfo(position.getSymbol());
    if (instrumentExtraInformation != null) {
      MarginBigDecimal markPrice = instrumentExtraInformation.getOraclePrice();
      MarginBigDecimal multiplier = getContractMultiplier(position);
      return MarginCalculator.calcUnrealisedPnl(position, markPrice, multiplier);
    } else {
      log.atWarn().addKeyValue("symbol", position.getSymbol()).log("No instrument data for symbol");
      return MarginBigDecimal.ZERO;
    }
  }

  @NotNull
  public LeverageMarginRule getTradingRule(Position position, MarginBigDecimal markPrice) {
    MarginBigDecimal multiplier = getContractMultiplier(position);
    Optional<LeverageMarginRule> tradingRuleOpt =
        tradingRuleService.getLeverageMarginRule(
            position.getSymbol(), position, markPrice, multiplier);
    LeverageMarginRule tradingRule;
    if (tradingRuleOpt.isEmpty()) {
      tradingRule = tradingRuleService.lmSymbolIndexDefault.get(position.getSymbol());
      log.atDebug()
          .addKeyValue("posId", position.getId())
          .addKeyValue("accId", position.getAccountId())
          .addKeyValue("symbol", position.getSymbol())
          .addKeyValue("cross", position.isCross())
          .addKeyValue("defaultLm", tradingRule)
          .log(
              "Unable to get trading rule for position, using default. [isCoinM={}, size={},"
                  + " markPrice={}, multiplier={}]",
              position.isCoinM(),
              position.getCurrentQty(),
              markPrice,
              multiplier);
    } else {
      tradingRule = tradingRuleOpt.get();
    }
    log.atDebug()
        .addKeyValue("posId", position.getId())
        .addKeyValue("accId", position.getAccountId())
        .addKeyValue("symbol", position.getSymbol())
        .addKeyValue("cross", position.isCross())
        .log(
            "Trading rule for position. [size={}, markPrice={}, maintAmount={}, mmr={},"
                + " multiplier={}]",
            position.getCurrentQty(),
            markPrice,
            tradingRule.maintenanceAmount(),
            tradingRule.maintenanceMarginRate(),
            multiplier);
    return tradingRule;
  }

  public MarginBigDecimal getClearanceFee(
      Position liquidatedPosition, MarginBigDecimal size, MarginBigDecimal price) {
    if (price.eq(MarginBigDecimal.ZERO)) {
      return MarginBigDecimal.ZERO;
    }
    Optional<MarginBigDecimal> clearanceRateOpt =
        tradingRuleService.getLiquidationClearanceRate(liquidatedPosition.getSymbol());
    MarginBigDecimal clearanceRate;
    MarginBigDecimal multiplier = null;
    if (clearanceRateOpt.isEmpty()) {
      log.atDebug()
          .addKeyValue("symbol", liquidatedPosition.getSymbol())
          .log(
              "Liquidation Clearance Rate not available, using fallback value. [rate={}]",
              DEFAULT_LIQ_CLR_RATE);
      clearanceRate = DEFAULT_LIQ_CLR_RATE;
    } else {
      clearanceRate = clearanceRateOpt.get();
    }

    MarginBigDecimal liquidationClearanceFee;
    if (liquidatedPosition.isCoinM()) {
      // "Clearance fee = Size / Liquidation price * Clearance rate * Contract Multiplier"
      // "= Size * Clearance rate * Contract Multiplier / Liquidation price"
      multiplier = getContractMultiplier(liquidatedPosition);
      liquidationClearanceFee =
          size.multiply(clearanceRate).multiply(multiplier).divide(price).abs();
    } else {
      // "Clearance fee = Liquidation price * Size * Clearance rate "
      liquidationClearanceFee = clearanceRate.multiply(size).multiply(price).abs();
    }

    log.atDebug()
        .addKeyValue("positionId", liquidatedPosition.getId())
        .addKeyValue("isCoinM", liquidatedPosition.isCoinM())
        .log(
            "Calculating liquidation clearance fee. [value={}, size={}, price={}, rate={},"
                + " multiplier={}]",
            liquidationClearanceFee,
            size,
            price,
            clearanceRate,
            multiplier);
    return liquidationClearanceFee;
  }

  /**
   * Ranking <br>
   * = PNL Percentage * Effective Leverage (if PNL percentage > 0) <br>
   * = PNL Percentage / Effective Leverage (if PNL percentage < 0) <br>
   * <br>
   * where
   *
   * <ul>
   *   <li>Effective Leverage = abs(Mark Value) / (Mark Value - Bankrupt Value)
   *   <li>PNL percentage = (Mark Value - Avg Entry Value) / abs(Avg Entry Value)
   * </ul>
   *
   * @param position Position under check
   * @return
   */
  @NotNull
  public MarginBigDecimal getPnlRanking(Position position) {
    MarginBigDecimal bankruptPrice = position.getBankruptPrice();
    MarginBigDecimal bankruptValue = position.getCurrentQty().multiply(bankruptPrice);

    MarginBigDecimal markPrice =
        instrumentService.getExtraInfo(position.getSymbol()).getOraclePrice();
    MarginBigDecimal markValue = position.getCurrentQty().multiply(markPrice);

    MarginBigDecimal entryPrice = position.getEntryPrice();
    MarginBigDecimal entryValue = position.getCurrentQty().multiply(entryPrice);

    log.atDebug()
        .addKeyValue("accId", position.getAccountId())
        .addKeyValue("symbol", position.getSymbol())
        .addKeyValue("coin_m", position.isCoinM())
        .log(
            "Calculate pnl ranking. [size={}, mark_price={}, mark_value={}, bankrupt_price={},"
                + " bankrupt_value={}, entry_price={}, entry_value={}]",
            position.getCurrentQty(),
            markPrice,
            markValue,
            bankruptPrice,
            bankruptValue,
            entryPrice,
            entryValue);
    MarginBigDecimal pnlRanking;
    if (markValue.gt(entryValue)) {
      // pnl percentage > 0
      // pnl ranking = PNL percentage * Effective Leverage
      // pnl ranking = ((Mark Value - Avg Entry Value) / abs(Avg Entry Value)) * (abs(Mark Value) /
      // (Mark Value - Bankrupt Value))
      // pnl ranking = (Mark Value - Avg Entry Value) * abs(Mark Value) / (abs(Avg Entry Value) *
      // (Mark Value - Bankrupt Value))
      MarginBigDecimal numerator = markValue.subtract(entryValue).multiply(markValue).abs();
      MarginBigDecimal denominator = entryValue.abs().multiply(markValue.subtract(bankruptValue));
      log.atDebug().log(
          "Calculate pnl ranking when pnl_percentage > 0 [numerator={}, denominator={}]",
          numerator,
          denominator);
      if (denominator.eq(0L)) {
        denominator = MarginBigDecimal.valueOf("0.00000001");
      }
      pnlRanking = numerator.divide(denominator);
      log.atDebug().log("pnl_ranking when pnl_percentage > 0 [ranking={}]", pnlRanking);
    } else if (markValue.lt(entryValue)) {
      // pnl percentage < 0
      // pnl ranking = PNL percentage / Effective Leverage
      // pnl ranking = ((Mark Value - Avg Entry Value) / abs(Avg Entry Value)) / (abs(Mark Value) /
      // (Mark Value - Bankrupt Value))
      // pnl ranking = ((Mark Value - Avg Entry Value) * (Mark Value - Bankrupt Value)) / (abs(Avg
      // Entry Value) * abs(Mark Value))
      MarginBigDecimal numerator =
          markValue.subtract(entryValue).multiply(markValue.subtract(bankruptValue));
      MarginBigDecimal denominator = entryValue.abs().multiply(markValue.abs());
      log.atDebug().log(
          "Calculate pnl ranking when pnl_percentage < 0 [numerator={}, denominator={}]",
          numerator,
          denominator);
      pnlRanking = numerator.divide(denominator);
      log.atDebug().log("pnl_ranking when pnl_percentage < 0 [ranking={}]", pnlRanking);
    } else {
      // lim(PNL Ranking) = 0 when PNL Percentage goes from INF_POSITIVE to 0
      // lim(PNL Ranking) = 0 when PNL Percentage goes from INF_NEGATIVE to 0
      pnlRanking = MarginBigDecimal.ZERO;
      log.atDebug().log("pnl_ranking when pnl_percentage = 0 [ranking={}]", pnlRanking);
    }
    return pnlRanking;
  }

  public MarginBigDecimal getIsolatedPositionMargin(Long accountId) {
    return positionService
        .getUserPositions(accountId, p -> !p.getCurrentQty().eq(0) && p.isIsolated())
        .stream()
        .map(p -> p.getPositionMargin().add(p.getAdjustMargin()))
        .reduce(MarginBigDecimal::add)
        .orElse(MarginBigDecimal.ZERO);
  }
}

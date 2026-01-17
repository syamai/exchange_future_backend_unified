package com.sotatek.future.service;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Position;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.service.PositionPnlRankingIndexer.PositionPnlIndexValue;
import com.sotatek.future.util.MarginBigDecimal;

import java.util.*;
import java.util.function.Consumer;
import java.util.function.Predicate;
import java.util.stream.Stream;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class PositionService extends BaseService<Position> {

  private PositionPnlRankingIndexer pnlRankingIndexer;

  private static final PositionService instance = new PositionService();

  private InstrumentService instrumentService;

  private AccountService accountService;

  private PositionCalculator positionCalculator;

  private PositionService() {
    super(true);
  }

  public static PositionService getInstance() {
    return instance;
  }

  public void initialize(
      InstrumentService instrumentService,
      AccountService accountService,
      PositionCalculator positionCalculator,
      PositionPnlRankingIndexer pnlRankingIndexer) {
    this.instrumentService = instrumentService;
    this.positionCalculator = positionCalculator;
    this.accountService = accountService;
    this.pnlRankingIndexer = pnlRankingIndexer;
  }

  @Override
  public Position update(Position entity) {
    // Update liquidation data
    updateLiquidationData(entity);
    // Persisted object
    Position updated = super.update(entity);
    pnlRankingIndexer.updatePnlRankingIndex(updated);
    return updated;
  }

  public Position get(long accountId, String symbol) {
    return get(Position.getKey(accountId, symbol));
  }

  private void updateLiquidationData(Position entity) {
    // Write pnl ranking of position, if it's open
    if (!entity.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
      try {
        MarginCalculator calculator = MarginCalculator.getCalculatorFor(entity.getSymbol());
        // call calcAllocatedMargin just to re-calc positionMargin,
        // not using return value(allocatedMargin) to assign to positionMargin
        calculator.calcAllocatedMargin(entity);
        entity.setLiquidationPrice(getLiquidationPrice(entity));

        if (entity.isIsolated()) {
          // calc liquidation fee and assign to position liquidation fee if mode is isolated
          MarginBigDecimal liquidationFee = positionCalculator.getClearanceFee(entity, entity.getCurrentQty(), entity.getLiquidationPrice());
          // calc trading fee as taker fee
          MarginBigDecimal tradingFee = calculator.getFee(entity.getLiquidationPrice(), entity.getCurrentQty(), true);
          entity.setTmpTotalFee(liquidationFee.add(tradingFee.multiply(2)));
        }

        entity.setBankruptPrice(getBankruptPrice(entity));
        entity.setPnlRanking(getPnlRanking(entity));
      } catch (Exception e) {
        log.atError()
            .setCause(e)
            .addKeyValue("accId", entity.getAccountId())
            .addKeyValue("symbol", entity.getSymbol())
            .log("Exception when updating position liquidation data");
      }
    }
  }

  public Stream<Position> getOpenPositions(String symbol) {
    return getCurrentEntities().stream()
        .filter(position -> !position.getCurrentQty().eq(0) && position.getSymbol().equals(symbol));
  }

  public Stream<Position> getPositions(String symbol) {
    return getCurrentEntities().stream().filter(position -> position.getSymbol().equals(symbol));
  }

  public List<Position> getUserPositions(Long accountId) {
    return getUserPositions(accountId, position -> !position.getCurrentQty().eq(0));
  }

  public List<Position> getUserPositions(Long accountId, Predicate<Position> predicate) {
    List<Instrument> instruments = instrumentService.getEntities();

    List<Position> positions = new ArrayList<>();
    for (Instrument instrument : instruments) {
      Position position = this.get(accountId, instrument.getSymbol());
      // get only open position
      if (position != null && predicate.test(position)) {
        positions.add(position);
      }
    }
    return positions;
  }

  public void updateUserPosition(Long accountId, Consumer<Position> consumer) {
    List<Instrument> instruments = instrumentService.getEntities();

    for (Instrument instrument : instruments) {
      Position position = this.get(accountId, instrument.getSymbol());
      // get only open position
      if (position != null && !position.getCurrentQty().eq(0)) {
        consumer.accept(position);
      }
    }
  }

  private MarginBigDecimal getLiquidationPrice(Position position) {
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(position.getSymbol());
    marginCalculator.calcAllocatedMargin(position);
    MarginBigDecimal markPrice = marginCalculator.getOraclePrice();
    if (position.isCross()) {
      Account account = accountService.get(position.getAccountId());
      // Get all other open positions
      Asset positionAsset = position.getAsset();
      MarginBigDecimal walletBalance = account.getBalance();
      List<Position> accountPositions =
          getUserPositions(
              position.getAccountId(),
              p -> !p.getCurrentQty().eq(0) && p.getAsset() == positionAsset);
      return positionCalculator.getCrossLiquidationPrice(
          position, accountPositions, walletBalance, markPrice);
    } else {
      return positionCalculator.getIsolatedLiquidationPrice(position, markPrice);
    }
  }

  private MarginBigDecimal getBankruptPrice(Position position) {
    MarginCalculator calculator = MarginCalculator.getCalculatorFor(position.getSymbol());
    MarginBigDecimal markPrice = calculator.getOraclePrice();
    calculator.calcAllocatedMargin(position);
    if (position.isCross()) {
      Account account = accountService.get(position.getAccountId());
      Asset positionAsset = position.getAsset();
      MarginBigDecimal walletBalance = account.getBalance();
      // Get all other open positions
      List<Position> accountPositions =
          getUserPositions(
              position.getAccountId(),
              p -> !p.getCurrentQty().eq(0) && p.getAsset() == positionAsset);
      return positionCalculator.getCrossBankruptPrice(
          position, accountPositions, walletBalance, markPrice);
    } else {
      return positionCalculator.getIsolatedBankruptPrice(position, markPrice);
    }
  }

  /**
   * Return PNL ranking for a position
   *
   * @param position
   * @return
   */
  private MarginBigDecimal getPnlRanking(Position position) {
    return positionCalculator.getPnlRanking(position);
  }

  public Optional<Position> getPositionForAdl(String symbol, boolean longPosition) {
    Optional<PositionPnlIndexValue> candidate = pnlRankingIndexer.poll(symbol, longPosition);
    return candidate.map(v -> get(v.getAccountId(), v.getSymbol()));
  }

  public void updateLiquidationData(String symbol) {
    getOpenPositions(symbol).forEach(this::update);
  }

  public Stream<Position> getLiquidablePositionsForSymbol(String symbol) {
    return getOpenPositions(symbol).filter(this::checkLiquidable);
  }

  public Optional<Position> getNextLiquidableCrossPositionForAccount(
      Long accId, Set<String> alreadyLiquidated) {
    List<Position> positions =
        getUserPositions(
            accId,
            position ->
                !position.getCurrentQty().eq(0)
                    && position.isCross()
                    && checkLiquidable(position)
                    && !alreadyLiquidated.contains(position.getKey()));
    return positions.stream().findFirst();
  }

  public boolean checkLiquidable(Position position) {
    Long accountId = position.getAccountId();
    String symbol = position.getSymbol();
    if (accountId.equals(accountService.getInsuranceAccountId(position.getAsset())) || accountService.checkIsBotAccountId(accountId) || accountId.equals(10481L)) {
      // Position hold by insurance account is not liquidated
      return false;
    }
    // Get the oracle price and compare to liquidation price
    InstrumentExtraInformation instrumentExtraInformation = instrumentService.getExtraInfo(symbol);
    MarginBigDecimal oraclePrice = instrumentExtraInformation.getOraclePrice();
    if (position.getLiquidationPrice() == null
        || position.getLiquidationPrice().lte(MarginBigDecimal.ZERO)) {
      // Negative liquidation price or when liquidation price is not calculated is not liquidated
      return false;
    }
    // add more logic check liquidate for isolated position
    if (position.isIsolated()) {
      MarginBigDecimal allocatedMargin =
          position.getPositionMargin().add(position.getAdjustMargin());
      MarginBigDecimal maintenanceMargin = positionCalculator.getMaintenanceMargin(position);
      // if allocated margin <= maintenance margin => position will be liquidated
      if (allocatedMargin.lte(maintenanceMargin)) {
        return true;
      }
    }
    if (position.getCurrentQty().gt(MarginBigDecimal.ZERO)) {
      // Long position
      if (oraclePrice.lt(position.getLiquidationPrice())) {
        log.atDebug()
            .addKeyValue("accId", accountId)
            .addKeyValue("asset", position.getAsset())
            .addKeyValue("symbol", position.getSymbol())
            .log(
                "Liquidating due to liquidation price > mark price. [liquidationPrice={},"
                    + " markPrice={}]",
                position.getLiquidationPrice(),
                oraclePrice);
        return true;
      }
    } else {
      // Short position
      if (oraclePrice.gt(position.getLiquidationPrice())) {
        log.atDebug()
            .addKeyValue("accId", accountId)
            .addKeyValue("asset", position.getAsset())
            .addKeyValue("symbol", position.getSymbol())
            .log(
                "Liquidating due to liquidation price < mark price. [liquidationPrice={},"
                    + " markPrice={}]",
                position.getLiquidationPrice(),
                oraclePrice);
        return true;
      }
    }
    return false;
  }
}

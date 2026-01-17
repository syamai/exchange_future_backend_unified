package com.sotatek.future.service;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.FundingHistory;
import com.sotatek.future.entity.Position;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.tuple.Pair;

@Slf4j
public class FundingService extends BaseService<FundingHistory> {
  private static final FundingService instance = new FundingService();

  private final Map<String, Boolean> paidFundings = new HashMap<>();

  private AccountService accountService;

  private PositionService positionService;

  private PositionCalculator positionCalculator;

  private PositionHistoryService positionHistoryService;

  private LiquidationService liquidationService;
  private static final long expireEntityPeriodMili = 86400 * 1000; // 1 day

  private FundingService() {
    super(true);
  }

  public static FundingService getInstance() {
    return instance;
  }

  public void initialize(
      AccountService accountService,
      PositionService positionService,
      PositionCalculator positionCalculator,
      PositionHistoryService positionHistoryService,
      LiquidationService liquidationService) {
    this.accountService = accountService;
    this.positionService = positionService;
    this.positionCalculator = positionCalculator;
    this.positionHistoryService = positionHistoryService;
    this.liquidationService = liquidationService;
  }

  public boolean isFundingPaid(String symbol, Date time) {
    Boolean paid = paidFundings.get(symbol + time.getTime());
    return paid != null && paid;
  }

  public void setFundingPaid(String symbol, Date time) {
    paidFundings.put(symbol + time.getTime(), true);
  }

  public boolean isPositionFundingPaid(Position position, Date time) {
    return this.get(FundingHistory.getKey(position.getId(), time)) != null;
  }

  private MarginBigDecimal calculateFundingFee(
      MarginBigDecimal oraclePrice, MarginBigDecimal fundingRate, Position position, Date time) {
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(position.getSymbol());
    MarginBigDecimal positionQuantity =
        positionHistoryService.getPositionHistoryQuantity(position, time);

    return marginCalculator.calcFundingFee(oraclePrice, positionQuantity, fundingRate);
  }

  public void payFunding(
      Position position, MarginBigDecimal fundingRate, MarginBigDecimal oraclePrice, Date time) {
    log.info(
        "payFunding fundingRate: {}, markPrice: {}, position: {}",
        fundingRate,
        oraclePrice,
        position);
    MarginBigDecimal fundingFee = calculateFundingFee(oraclePrice, fundingRate, position, time);
    if (fundingFee.eq(MarginBigDecimal.ZERO)) {
      log.info("payFunding not change fee for position {}", position.getId());
      return;
    }
    Account account = accountService.get(position.getAccountId());
    log.debug("payFunding fundingFee {}", fundingFee);
    MarginBigDecimal finalFee;
    if (fundingFee.lt(0)) {
      // init cross balance = wallet balance
      MarginBigDecimal crossBalance = account.getBalance();
      if (position.isCross()) {
        // get sum of all isolated allocated margin
        MarginBigDecimal isolatedMargins =
            positionCalculator.getIsolatedPositionMargin(account.getId());
        // cross balance = wallet balance - isolated allocated margin
        crossBalance = crossBalance.subtract(isolatedMargins);
      }
      // max subtract funding fee is crossBalance
      log.debug("payFunding accountBalance {} {}", account.getAsset(), crossBalance);
      fundingFee = fundingFee.abs().min(crossBalance);
      finalFee = fundingFee.negate();
    } else {
      finalFee = fundingFee;
    }

    log.debug("payFunding finalFee {}", finalFee);
    account = accountService.addAmountToBalance(account, finalFee);
    if (position.isIsolated()) {
      // Add funding fee to isolated position
      MarginBigDecimal positionMargin = position.getPositionMargin();
      position.setPositionMargin(positionMargin.add(finalFee));
      positionService.update(position);
      // exclude the position belong to insurance fund
      if (!account.getId().equals(accountService.getInsuranceAccountId(position.getAsset())) && !accountService.checkIsBotAccountId(position.getAccountId())) {
        // check if allocated margin <= maintenance margin then liquidate this position
        MarginBigDecimal allocatedMargin =
            position.getPositionMargin().add(position.getAdjustMargin());
        MarginBigDecimal maintenanceMargin = positionCalculator.getMaintenanceMargin(position);
        if (allocatedMargin.lte(maintenanceMargin)) {
          // liquidate this position
          liquidationService.liquidate(position);
        }
      }
    }
    accountService.update(account);
    createFundingHistory(position, finalFee, fundingRate, time);
  }

  private void createFundingHistory(
      Position position, MarginBigDecimal amount, MarginBigDecimal fundingRate, Date time) {
    FundingHistory history = new FundingHistory();
    history.setAsset(position.getAsset());
    history.setSymbol(position.getSymbol());
    history.setUserId(position.getUserId());
    history.setAccountId(position.getAccountId());
    history.setPositionId(position.getId());
    history.setTime(time);
    history.setAmount(amount);
    history.setFundingRate(fundingRate);
    history.setFundingQuantity(position.getCurrentQty());
    history.setContractType(position.getContractType());
    insert(history);
    this.removeOldEntity(history);
  }

  @Override
  public void removeOldEntity(FundingHistory fundingHistory) {
    removingEntities.add(
        Pair.of(fundingHistory, fundingHistory.getCreatedAt().getTime() + expireEntityPeriodMili));
  }
}

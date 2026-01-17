package com.sotatek.future.service;

import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.usecase.*;
import lombok.AccessLevel;
import lombok.NoArgsConstructor;

@NoArgsConstructor(access = AccessLevel.PRIVATE)
public class ServiceFactory {
  private static PositionUseCase positionUseCase;

  private static AccountUseCase accountUseCase;

  private static PositionHistoryUseCase positionHistoryUseCase;

  private static FundingHistoryUseCase fundingHistoryUseCase;

  private static InstrumentUseCase instrumentUseCase;

  private static FundingUseCase fundingUseCase;

  private static OrderUseCase orderUseCase;

  private static LiquidationUseCase liquidationUseCase;

  private static LeverageMarginUseCase leverageMarginUseCase;
  private static RetrieveDataUseCase retrieveDataUseCase;

  public static void initialize() {
    PositionPnlRankingIndexer pnlRankingIndexer = new PositionPnlRankingIndexer();
    // TODO use DI
    MatchingEngine matchingEngine = MatchingEngine.getInstance();
    AccountService accountService = AccountService.getInstance();
    FundingService fundingService = FundingService.getInstance();
    InstrumentService instrumentService = InstrumentService.getInstance();
    InsuranceService insuranceService = InsuranceService.getInstance();
    MarginHistoryService marginHistoryService = MarginHistoryService.getInstance();
    LiquidationService liquidationService = LiquidationService.getInstance();
    OrderBookService orderbookService = OrderBookService.getInstance();
    OrderService orderService = OrderService.getInstance();
    PositionHistoryService positionHistoryService = PositionHistoryService.getInstance();
    PositionService positionService = PositionService.getInstance();
    TradeService tradeService = TradeService.getInstance();
    TransactionService transactionService = TransactionService.getInstance();
    LeverageMarginService leverageMarginService = LeverageMarginService.getInstance();

    TradingRuleService tradingRuleService = TradingRuleService.INSTANCE;
    PositionCalculator positionCalculator =
        new PositionCalculator(tradingRuleService, instrumentService, positionService);

    accountService.initialize(marginHistoryService, transactionService, positionService, orderService);
    fundingService.initialize(
        accountService,
        positionService,
        positionCalculator,
        positionHistoryService,
        liquidationService);
    insuranceService.initialize();
    marginHistoryService.initialize(accountService, positionService);
    liquidationService.initialize(
        accountService, orderService, positionService, tradeService, positionCalculator, marginHistoryService);
    orderService.initialize(
        accountService,
        instrumentService,
        positionService,
        tradeService,
        insuranceService,
        marginHistoryService,
        positionHistoryService,
        positionCalculator);
    positionHistoryService.initialize();
    positionService.initialize(
        instrumentService, accountService, positionCalculator, pnlRankingIndexer);
    tradeService.initialize();
    transactionService.initialize();
    leverageMarginService.initialize();

    // Initialize use-case
    positionUseCase =
        new PositionUseCase(
            positionService,
            orderService,
            accountService,
            matchingEngine,
            positionCalculator,
            liquidationService);
    accountUseCase = new AccountUseCase(accountService, transactionService, matchingEngine);
    positionHistoryUseCase = new PositionHistoryUseCase(positionHistoryService);
    fundingHistoryUseCase = new FundingHistoryUseCase(fundingService);
    instrumentUseCase = new InstrumentUseCase(instrumentService, matchingEngine);
    fundingUseCase = new FundingUseCase(fundingService, positionService, matchingEngine);
    orderUseCase =
        new OrderUseCase(
            orderService, positionService, accountService, marginHistoryService, matchingEngine);
    liquidationUseCase =
        new LiquidationUseCase(
            instrumentService, liquidationService, positionService, accountService, matchingEngine);
    leverageMarginUseCase = new LeverageMarginUseCase(leverageMarginService);
    retrieveDataUseCase = new RetrieveDataUseCase(orderService, positionService, accountService, instrumentService, tradingRuleService);
  }

  public static PositionUseCase getPositionUseCase() {
    return positionUseCase;
  }

  public static AccountUseCase getAccountUseCase() {
    return accountUseCase;
  }

  public static PositionHistoryUseCase getPositionHistoryUseCase() {
    return positionHistoryUseCase;
  }

  public static FundingHistoryUseCase getFundingHistoryUseCase() {
    return fundingHistoryUseCase;
  }

  public static InstrumentUseCase getInstrumentUseCase() {
    return instrumentUseCase;
  }

  public static FundingUseCase getFundingUseCase() {
    return fundingUseCase;
  }

  public static OrderUseCase getOrderUseCase() {
    return orderUseCase;
  }

  public static LiquidationUseCase getLiquidationUseCase() {
    return liquidationUseCase;
  }

  public static LeverageMarginUseCase getLeverageMarginUseCase() {
    return leverageMarginUseCase;
  }

  public static RetrieveDataUseCase getRetrieveDataUseCase() {
    return retrieveDataUseCase;
  }
}

package com.sotatek.future;

import static org.assertj.core.api.Assertions.assertThat;
import static org.junit.jupiter.api.Assertions.assertEquals;

import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.engine.MatchingEngineConfig;
import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.BaseEntity;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.EngineParams;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.LeverageMargin;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.entity.TradingRule;
import com.sotatek.future.entity.Transaction;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.enums.InputDriver;
import com.sotatek.future.enums.MarginMode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderTrigger;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.OutputDriver;
import com.sotatek.future.enums.TPSLType;
import com.sotatek.future.enums.TimeInForce;
import com.sotatek.future.enums.TriggerCondition;
import com.sotatek.future.exception.AccountNotFoundException;
import com.sotatek.future.input.InputStream;
import com.sotatek.future.input.ListInputStream;
import com.sotatek.future.output.ListOutputStream;
import com.sotatek.future.output.OrderBookOutputStream;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.FundingService;
import com.sotatek.future.service.InstrumentService;
import com.sotatek.future.service.InsuranceService;
import com.sotatek.future.service.LiquidationService;
import com.sotatek.future.service.MarginHistoryService;
import com.sotatek.future.service.OrderBookService;
import com.sotatek.future.service.OrderService;
import com.sotatek.future.service.PositionHistoryService;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.service.ServiceFactory;
import com.sotatek.future.service.TradeService;
import com.sotatek.future.service.TransactionService;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;
import java.util.stream.Stream;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class BaseTest {

  protected String defaultSymbol = "BTCUSD";
  protected String defaultSymbolCoinM = "BTCUSDM";
  protected MarginBigDecimal defaultMultiplier = MarginBigDecimal.valueOf(100);
  protected Asset defaultAsset = Asset.USDT;
  private MarginMode defaultMarginMode = MarginMode.CROSS;
  private MarginBigDecimal defaultLeverage = MarginBigDecimal.valueOf(100);
  protected MarginBigDecimal defaultBalance = MarginBigDecimal.valueOf(100000);
  public static long defaultInsuranceAccountId = 1000000000l;

  protected AccountService accountService;
  protected FundingService fundingService;
  protected InstrumentService instrumentService;
  protected InsuranceService insuranceService;
  protected MarginHistoryService marginHistoryService;
  protected OrderBookService orderbookService;
  protected OrderService orderService;
  protected PositionHistoryService positionHistoryService;
  protected PositionService positionService;
  protected TradeService tradeService;
  protected TransactionService transactionService;

  public void setUp() throws Exception {
    this.setUpServices();
    this.setUpInstrument();
    this.setUpInsuranceAccount();
    this.defaultBalance = MarginBigDecimal.valueOf(100000);
  }

  protected void setUpServices() {
    ServiceFactory.initialize();
    this.accountService = AccountService.getInstance();
    this.fundingService = FundingService.getInstance();
    this.instrumentService = InstrumentService.getInstance();
    this.insuranceService = InsuranceService.getInstance();
    this.marginHistoryService = MarginHistoryService.getInstance();
    this.orderbookService = OrderBookService.getInstance();
    this.orderService = OrderService.getInstance();
    this.positionHistoryService = PositionHistoryService.getInstance();
    this.positionService = PositionService.getInstance();
    this.tradeService = TradeService.getInstance();
    this.transactionService = TransactionService.getInstance();
  }

  protected void setUpInstrument() {
    this.instrumentService.update(this.createInstrument(defaultSymbol, ContractType.USD_M));
    this.instrumentService.updateExtraInfo(this.createInstrumentExtraInformation());
    this.instrumentService.update(this.createInstrument("ETHUSD", ContractType.USD_M));
    this.instrumentService.updateExtraInfo(
        this.createInstrumentExtraInformation("ETHUSD", MarginBigDecimal.valueOf(32000)));
    this.instrumentService.update(this.createInstrument("BTCUSDM", ContractType.USD_M));
    this.instrumentService.updateExtraInfo(
        this.createInstrumentExtraInformation("BTCUSDM", MarginBigDecimal.valueOf(32000)));

    this.instrumentService.update(this.createInstrument(defaultSymbolCoinM, ContractType.COIN_M));
    this.instrumentService.updateExtraInfo(
        this.createInstrumentExtraInformation(defaultSymbolCoinM, MarginBigDecimal.valueOf(2310)));
    this.instrumentService.commit();
  }

  protected void setUpInsuranceAccount() {
    AccountService.INSURANCE_ACCOUNT_IDS.put(defaultAsset, defaultInsuranceAccountId);
    this.accountService.update(
        this.createAccount(
            defaultInsuranceAccountId,
            accountService.INSURANCE_USER_ID,
            MarginBigDecimal.valueOf("100000000")));
    this.accountService.commit();
  }

  protected void setUpAccount(long id, MarginBigDecimal balance) {
    this.accountService.update(this.createAccount(id, balance));
    this.accountService.commit();
  }

  public void tearDown() throws Exception {
    this.accountService.clear();
    this.fundingService.clear();
    this.instrumentService.clear();
    this.orderService.clear();
    this.positionHistoryService.clear();
    this.positionService.clear();
    this.tradeService.clear();
    this.transactionService.clear();
  }

  protected Instrument createInstrument(String symbol, ContractType contractType) {
    Instrument instrument = new Instrument();
    instrument.setSymbol(symbol);
    instrument.setRootSymbol("BTC");
    instrument.setState("Open");
    instrument.setType(0);
    instrument.setInitMargin(MarginBigDecimal.valueOf("0.01"));
    instrument.setMaintainMargin(MarginBigDecimal.valueOf("0.005"));
    instrument.setMultiplier(
        contractType.equals(ContractType.USD_M) ? MarginBigDecimal.ONE : defaultMultiplier);
    instrument.setTickSize(MarginBigDecimal.valueOf("0.01"));
    instrument.setContractSize(MarginBigDecimal.valueOf("0.000001"));
    instrument.setLotSize(MarginBigDecimal.valueOf("100"));
    instrument.setReferenceIndex("BTC");
    instrument.setFundingBaseIndex("BTCBON8H");
    instrument.setFundingQuoteIndex("USDBON8H");
    instrument.setFundingPremiumIndex("BTCUSDPI8H");
    instrument.setFundingInterval(8);
    instrument.setMaxPrice(MarginBigDecimal.valueOf(1000000));
    instrument.setMaxOrderQty(MarginBigDecimal.valueOf(1000000));
    instrument.setTakerFee(MarginBigDecimal.valueOf("0.075"));
    instrument.setMakerFee(MarginBigDecimal.valueOf("0.025"));
    instrument.setContractType(contractType);

    return instrument;
  }

  protected InstrumentExtraInformation createInstrumentExtraInformation() {
    return createInstrumentExtraInformation(defaultSymbol, MarginBigDecimal.valueOf(65000));
  }

  protected InstrumentExtraInformation createInstrumentExtraInformation(
      String symbol, MarginBigDecimal oraclePrice) {
    InstrumentExtraInformation instrumentExtraInformation = new InstrumentExtraInformation();
    instrumentExtraInformation.setSymbol(symbol);
    instrumentExtraInformation.setOraclePrice(oraclePrice);
    return instrumentExtraInformation;
  }

  protected Account createAccount(long id, MarginBigDecimal balance) {
    Account account = Account.builder().build();
    account.setId(id);
    account.setAsset(defaultAsset);
    account.setBalance(balance);
    return account;
  }

  protected Account createAccount(long id, long userId, MarginBigDecimal balance) {
    Account account = Account.builder().build();
    account.setId(id);
    account.setUserId(userId);
    account.setAsset(defaultAsset);
    account.setBalance(balance);
    return account;
  }

  protected Order createOrder(
      long id, long accountId, OrderSide side, OrderType type, String price, String quantity) {
    return this.createOrder(id, accountId, side, type, price, quantity, null, defaultSymbol);
  }

  protected Order createOrder(
      long id,
      long accountId,
      OrderSide side,
      OrderType type,
      String price,
      String quantity,
      String symbol) {
    return this.createOrder(id, accountId, side, type, price, quantity, null, symbol);
  }

  protected Order createOrder(
      long id,
      long accountId,
      OrderSide side,
      OrderType type,
      String price,
      String quantity,
      String lockPrice,
      String symbol) {
    // Ensure order is linked to a valid account
    Account acc;
    try {
      acc = accountService.get(accountId);
    } catch (AccountNotFoundException e) {
      acc = createAccount(accountId, defaultBalance);
      accountService.update(acc);
      accountService.commit();
    }
    Order order = Order.builder().build();
    order.setId(id);
    order.setAccountId(acc.getId());
    order.setSide(side);
    order.setType(type);
    if (type == OrderType.LIMIT) {
      order.setPrice(MarginBigDecimal.valueOf(price));
      order.setTimeInForce(TimeInForce.GTC);
    } else {
      order.setTimeInForce(TimeInForce.IOC);
    }
    if (lockPrice != null) {
      order.setLockPrice(MarginBigDecimal.valueOf(lockPrice));
    }
    order.setQuantity(MarginBigDecimal.valueOf(quantity));
    order.setRemaining(MarginBigDecimal.valueOf(quantity));
    order.setSymbol(symbol);
    order.setStatus(OrderStatus.PENDING);
    order.setAsset(this.defaultAsset);
    order.setMarginMode(this.defaultMarginMode);
    order.setLeverage(this.defaultLeverage);
    return order;
  }

  protected Order createStopOrder(
      long id,
      OrderSide side,
      TPSLType type,
      String price,
      String quantity,
      OrderTrigger trigger,
      String stopPrice) {
    return createStopOrder(
        id, 1L, side, type, price, quantity, trigger, stopPrice, TriggerCondition.GT);
  }

  protected Order createStopOrder(
      long id,
      long accountId,
      OrderSide side,
      TPSLType type,
      String price,
      String quantity,
      OrderTrigger trigger,
      String stopPrice,
      TriggerCondition triggerCondition) {
    OrderType orderType = OrderType.LIMIT;
    switch (type) {
      case STOP_LIMIT:
      case TAKE_PROFIT_LIMIT:
        orderType = OrderType.LIMIT;
        break;
      case STOP_MARKET:
      case TAKE_PROFIT_MARKET:
        orderType = OrderType.MARKET;
        break;
    }
    Order order = this.createOrder(id, accountId, side, orderType, price, quantity);
    order.setTpSLType(type);
    order.setTrigger(trigger);
    order.setTpSLPrice(MarginBigDecimal.valueOf(stopPrice));
    order.setPriority(StopOrderTest.priority++);
    order.setStopCondition(triggerCondition);
    return order;
  }

  protected OrderBookOutput createOrderbookOutput(OrderSide side, String price, String quantity) {
    return new OrderBookOutput(
        side, new MarginBigDecimal(price), new MarginBigDecimal(quantity), "BTCUSD");
  }

  protected Order cloneOrder(Order order, String remaining, OrderStatus status) {
    Order cloneOrder = order.deepCopy();
    cloneOrder.setRemaining(MarginBigDecimal.valueOf(remaining));
    cloneOrder.setStatus(status);
    return cloneOrder;
  }

  protected Trade createTrade(long id, Order taker, Order maker, String price, String quantity) {
    Trade trade =
        new Trade(
            taker, maker, MarginBigDecimal.valueOf(price), MarginBigDecimal.valueOf(quantity));
    trade.setId(id);
    return trade;
  }

  protected void testMatching(
      List<Order> orders,
      List<Order> processedOrders,
      List<Trade> trades,
      List<OrderBookOutput> orderbookOutputs) {

    List<Command> orderInputs =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());

    this.testEngine(orderInputs, processedOrders, trades, orderbookOutputs);
  }

  protected void testEngine(
      List<Command> commands,
      List<Order> processedOrders,
      List<Trade> trades,
      List<OrderBookOutput> orderBookOutputs) {

    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);

    validateEntitiesListKeyAndValue(processedOrders, orderService.getEntities());
    List<Trade> outputTrades =
        commandOutputStream.getData().stream()
            .flatMap((CommandOutput output) -> output.getTrades().stream())
            .collect(Collectors.toList());
    validateEntitiesListKeyAndValue(trades, outputTrades);
    assertEquals(orderBookOutputs, orderBookOutputStream.getData());
  }

  protected void testTransaction(
      List<Command> commands, List<Transaction> processedTransactions, List<Account> accounts) {

    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);

    validateEntitiesListKeyAndValue(processedTransactions, transactionService.getEntities());

    List<Account> resultAccounts = new ArrayList<>();
    for (Account account : accounts) {
      resultAccounts.add(AccountService.getInstance().get(account.getId()));
    }
    validateEntitiesListKeyAndValue(accounts, resultAccounts);
  }

  protected void testLiquidation(
      List<Command> commands,
      List<Order> processedOrders,
      List<Trade> trades,
      List<Transaction> transactions) {
    this.testLiquidation(commands, processedOrders, trades, transactions, true);
  }

  protected void testLiquidation(
      List<Command> commands,
      List<Order> processedOrders,
      List<Trade> trades,
      List<Transaction> transactions,
      boolean validateTrade) {
    // Reset liquidation service for each tests
    LiquidationService.getInstance().rollback();

    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);

    validateEntitiesListKeyAndValue(processedOrders, orderService.getEntities());

    List<Trade> outputTrades =
        commandOutputStream.getData().stream()
            .flatMap((CommandOutput output) -> output.getTrades().stream())
            .collect(Collectors.toList());
    if (validateTrade) {
      validateEntitiesListKeyAndValue(trades, outputTrades);
    }

    List<Transaction> outputTransactions =
        commandOutputStream.getData().stream().flatMap(f -> f.getTransactions().stream()).toList();
    validateEntitiesListKeyAndValue(transactions, outputTransactions);
  }

  protected void testFunding(List<Command> commands) {
    ListOutputStream<CommandOutput> commandOutputStream = new ListOutputStream<>();
    ListOutputStream orderBookOutputStream = new ListOutputStream();

    this.testEngine(commands, commandOutputStream, orderBookOutputStream);
  }

  protected void testEngine(
      List<Command> commands,
      ListOutputStream<CommandOutput> commandOutputStream,
      ListOutputStream orderBookOutputStream) {

    // Add a final command to stop the engine, so we can evaluate the result
    // We make a copy to avoid modifying the input param,
    // and because the original commands list might be immutable
    List<Command> finalCommands =
        Stream.concat(commands.stream(), Stream.of(new Command(CommandCode.STOP_ENGINE, null)))
            .toList();

    MatchingEngineConfig config = new MatchingEngineConfig();
    config.setTesting(true);
    config.setCommandInputDriver(InputDriver.JAVA_LIST);
    config.setCommandInputStream(new ListInputStream<>(finalCommands));
    config.setCommandPreloadDriver(InputDriver.JAVA_LIST);
    config.setCommandPreloadStream(this.createPreloadStream());
    config.setCommandOutputDriver(OutputDriver.JAVA_LIST);
    config.setCommandOutputStream(commandOutputStream);
    config.setOrderBookOutputDriver(OutputDriver.JAVA_LIST);
    config.setOrderBookOutputStream(orderBookOutputStream);
    Map<String, Object> params = new HashMap<>();
    params.put(OrderBookOutputStream.UPDATE_INTERVAL, 0);
    config.setOutputParameters(params);

    MatchingEngine matchingEngine = MatchingEngine.getInstance();
    matchingEngine.initialize(config);
    this.sleep(20);

    long time = System.currentTimeMillis();
    matchingEngine.start();
    this.sleep(20);
  }

  protected InputStream<Command> createPreloadStream() {
    List<Command> commands = new ArrayList<>();
    EngineParams engineParams = new EngineParams();
    engineParams.setLastOrderId(0);
    engineParams.setLastPositionId(0);
    engineParams.setLastTradeId(0);
    engineParams.setLastMarginHistoryId(0);
    engineParams.setLastPositionHistoryId(0);
    engineParams.setLastFundingHistoryId(0);
    commands.add(new Command(CommandCode.INITIALIZE_ENGINE, engineParams));
    for (Instrument instrument : InstrumentService.getInstance().getEntities()) {
      commands.add(new Command(CommandCode.UPDATE_INSTRUMENT, instrument));
      commands.add(
          new Command(
              CommandCode.UPDATE_INSTRUMENT_EXTRA,
              InstrumentService.getInstance().getExtraInfo(instrument.getSymbol())));
    }
    List<Command> leverageMarginCommand = getLeverageMarginCommand();
    commands.addAll(leverageMarginCommand);

    List<Command> tradingRuleCommand = getTradingRuleCommand();
    commands.addAll(tradingRuleCommand);

    commands.add(new Command(CommandCode.START_ENGINE, null));
    return new ListInputStream<>(commands);
  }

  protected void sleep(long t) {
    try {
      Thread.sleep(t);
    } catch (InterruptedException e) {
      e.printStackTrace();
    }
  }

  protected static <T extends BaseEntity> void validateEntitiesListKeyAndValue(
      List<T> expected, List<T> actual) {
    List<Object> expectedId =
        expected.stream().map(BaseEntity::getKey).collect(Collectors.toList());
    List<Object> expectedValue = expected.stream().map(BaseEntity::getValue).toList();
    assertThat(actual)
        .extracting(BaseEntity::getValue)
        .containsExactlyInAnyOrderElementsOf(expectedValue);
    assertThat(actual)
        .extracting(BaseEntity::getKey)
        .containsExactlyInAnyOrderElementsOf(expectedId);
  }

  protected List<Command> getLeverageMarginCommand() {
    List<LeverageMargin> binanceBTCUSTDRules =
        List.of(
            new LeverageMargin(
                0,
                MarginBigDecimal.valueOf(0),
                MarginBigDecimal.valueOf(50_000),
                125,
                MarginBigDecimal.valueOf("0.4"),
                MarginBigDecimal.valueOf(0),
                defaultSymbol),
            new LeverageMargin(
                1,
                MarginBigDecimal.valueOf(50_000),
                MarginBigDecimal.valueOf(250_000),
                100,
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf(50),
                defaultSymbol),
            new LeverageMargin(
                2,
                MarginBigDecimal.valueOf(250_000),
                MarginBigDecimal.valueOf(1_000_000),
                50,
                MarginBigDecimal.valueOf("1"),
                MarginBigDecimal.valueOf(1_300),
                defaultSymbol),
            new LeverageMargin(
                3,
                MarginBigDecimal.valueOf(1_000_000),
                MarginBigDecimal.valueOf(10_000_000),
                20,
                MarginBigDecimal.valueOf("2.5"),
                MarginBigDecimal.valueOf(16_300),
                defaultSymbol),
            new LeverageMargin(
                4,
                MarginBigDecimal.valueOf(10_000_000),
                MarginBigDecimal.valueOf(20_000_000),
                10,
                MarginBigDecimal.valueOf("5"),
                MarginBigDecimal.valueOf(266_300),
                defaultSymbol),
            new LeverageMargin(
                5,
                MarginBigDecimal.valueOf(20_000_000),
                MarginBigDecimal.valueOf(50_000_000),
                5,
                MarginBigDecimal.valueOf("10"),
                MarginBigDecimal.valueOf(1_266_300),
                defaultSymbol),
            new LeverageMargin(
                6,
                MarginBigDecimal.valueOf(50_000_000),
                MarginBigDecimal.valueOf(100_000_000),
                4,
                MarginBigDecimal.valueOf("12.5"),
                MarginBigDecimal.valueOf(2_516_300),
                defaultSymbol),
            new LeverageMargin(
                7,
                MarginBigDecimal.valueOf(100_000_000),
                MarginBigDecimal.valueOf(200_000_000),
                3,
                MarginBigDecimal.valueOf("15"),
                MarginBigDecimal.valueOf(5_016_300),
                defaultSymbol),
            new LeverageMargin(
                8,
                MarginBigDecimal.valueOf(200_000_000),
                MarginBigDecimal.valueOf(300_000_000),
                2,
                MarginBigDecimal.valueOf("25"),
                MarginBigDecimal.valueOf(25_016_300),
                defaultSymbol),
            new LeverageMargin(
                9,
                MarginBigDecimal.valueOf(300_000_000),
                MarginBigDecimal.valueOf(500_000_000),
                1,
                MarginBigDecimal.valueOf("50"),
                MarginBigDecimal.valueOf(100_016_300),
                defaultSymbol));
    return binanceBTCUSTDRules.stream()
        .map(r -> new Command(CommandCode.LOAD_LEVERAGE_MARGIN, r))
        .toList();
  }

  protected List<Command> getTradingRuleCommand() {
    TradingRule rule = new TradingRule();
    rule.setSymbol(defaultSymbol);
    rule.setLiqClearanceFee(MarginBigDecimal.valueOf(2));
    Command command = new Command(CommandCode.LOAD_TRADING_RULE, rule);
    return List.of(command);
  }
}

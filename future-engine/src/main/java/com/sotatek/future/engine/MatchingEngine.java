package com.sotatek.future.engine;

import com.sotatek.future.engine.Trigger.OnOrderTriggeredListener;
import com.sotatek.future.entity.*;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;
import com.sotatek.future.exception.MarginException;
import com.sotatek.future.input.InputStream;
import com.sotatek.future.input.InputStreamFactory;
import com.sotatek.future.input.OnNewDataListener;
import com.sotatek.future.model.*;
import com.sotatek.future.output.JsonOutputStream;
import com.sotatek.future.output.OrderBookOutputStream;
import com.sotatek.future.output.OutputStream;
import com.sotatek.future.output.OutputStreamFactory;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.FundingService;
import com.sotatek.future.service.InstrumentService;
import com.sotatek.future.service.LiquidationService;
import com.sotatek.future.service.MarginHistoryService;
import com.sotatek.future.service.OrderBookService;
import com.sotatek.future.service.OrderService;
import com.sotatek.future.service.PositionHistoryService;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.service.ServiceFactory;
import com.sotatek.future.service.TradeService;
import com.sotatek.future.service.TradingRuleService;
import com.sotatek.future.service.TransactionService;
import com.sotatek.future.thread.TriggerThread;
import com.sotatek.future.usecase.*;
import com.sotatek.future.util.OperationIdGenerator;
import com.sotatek.util.SelfExpiringConcurrentMap;
import java.io.IOException;
import java.util.*;
import java.util.concurrent.*;

import lombok.AccessLevel;
import lombok.NoArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;

@Slf4j
@NoArgsConstructor(access = AccessLevel.PROTECTED)
public class MatchingEngine implements OnNewDataListener<Command>, OnOrderTriggeredListener {
  private static final MatchingEngine instance = new MatchingEngine();
  public static final Map<String, Matcher> matchers = new HashMap<>();
  public static final Map<String, Trigger> triggers = new HashMap<>();
  public static final BlockingQueue<Command> commands = new LinkedBlockingQueue<>();
  private Command currentProcCommand;
  private final SelfExpiringConcurrentMap<Long, CommandCode> receivedOrders =
      new SelfExpiringConcurrentMap<>();
  private final OperationIdGenerator operationIdGenerator = new OperationIdGenerator();
  public static final List<CommandError> errors = new ArrayList<>();
  private final List<Object> retrievingData = new ArrayList<>();
  private MatchingEngineConfig config;
  private long currentPriority = 0;
  private AccountService accountService;
  private FundingService fundingService;
  private InstrumentService instrumentService;
  private LiquidationService liquidationService;
  private MarginHistoryService marginHistoryService;
  private OrderBookService orderbookService;
  private OrderService orderService;
  private PositionHistoryService positionHistoryService;
  private PositionService positionService;
  private TradeService tradeService;
  private TransactionService transactionService;
  private InputStream<Command> inputStream;
  private InputStream<Command> preloadStream;
  private OutputStream<CommandOutput> commandOutputStream;
  private OutputStream<OrderBookOutput> orderBookOutputStream;
  private PositionUseCase positionUseCase;
  private AccountUseCase accountUseCase;
  private PositionHistoryUseCase positionHistoryUseCase;
  private FundingHistoryUseCase fundingHistoryUseCase;
  private FundingUseCase fundingUseCase;
  private InstrumentUseCase instrumentUseCase;
  private OrderUseCase orderUseCase;
  private LiquidationUseCase liquidationUseCase;
  private TradingRuleService tradingRuleService;
  private RetrieveDataUseCase retrieveDataUseCase;

  private volatile boolean stopEngine = false;
  private boolean showProcessingTime = true;

  public static MatchingEngine getInstance() {
    return instance;
  }

  public void initialize(MatchingEngineConfig config) throws InvalidMatchingEngineConfigException {
    this.config = config;
    initializeServices();
    createPreloadStream(config);
    createCommandOutputStream(config);
    createOrderBookOutputStream(config);
    startTriggerThread();
//    new MemoryCheckingThread().start();
  }

  private void initializeServices() {
    // TODO use DI
    ServiceFactory.initialize();
    matchers.clear();
    positionUseCase = ServiceFactory.getPositionUseCase();
    accountUseCase = ServiceFactory.getAccountUseCase();
    positionHistoryUseCase = ServiceFactory.getPositionHistoryUseCase();
    fundingHistoryUseCase = ServiceFactory.getFundingHistoryUseCase();
    instrumentUseCase = ServiceFactory.getInstrumentUseCase();
    liquidationUseCase = ServiceFactory.getLiquidationUseCase();
    fundingUseCase = ServiceFactory.getFundingUseCase();
    orderUseCase = ServiceFactory.getOrderUseCase();
    retrieveDataUseCase = ServiceFactory.getRetrieveDataUseCase();
    accountService = AccountService.getInstance();
    fundingService = FundingService.getInstance();
    instrumentService = InstrumentService.getInstance();
    liquidationService = LiquidationService.getInstance();
    marginHistoryService = MarginHistoryService.getInstance();
    orderbookService = OrderBookService.getInstance();
    orderService = OrderService.getInstance();
    positionHistoryService = PositionHistoryService.getInstance();
    positionService = PositionService.getInstance();
    tradeService = TradeService.getInstance();
    transactionService = TransactionService.getInstance();
    tradingRuleService = TradingRuleService.INSTANCE;
  }

  private void createPreloadStream(MatchingEngineConfig config)
      throws InvalidMatchingEngineConfigException {
    preloadStream = InputStreamFactory.createPreloadStream(config);
    preloadStream.setOnNewDataListener(this);
    try {
      preloadStream.connect();
    } catch (IOException | TimeoutException e) {
      throw new RuntimeException(e);
    }
  }

  private void createInputStream(MatchingEngineConfig config)
      throws InvalidMatchingEngineConfigException {
    inputStream = InputStreamFactory.createInputStream(config);
    inputStream.setOnNewDataListener(this);
    try {
      inputStream.connect();
    } catch (IOException | TimeoutException e) {
      throw new RuntimeException(e);
    }
  }

  private void createCommandOutputStream(MatchingEngineConfig config)
      throws InvalidMatchingEngineConfigException {
    commandOutputStream = OutputStreamFactory.createCommandOutputStream(config);
    try {
      commandOutputStream.connect();
    } catch (IOException | TimeoutException e) {
      e.printStackTrace();
    }
  }

  private void createOrderBookOutputStream(MatchingEngineConfig config)
      throws InvalidMatchingEngineConfigException {
    orderBookOutputStream = OutputStreamFactory.createOrderBookOutputStream(config);
    try {
      orderBookOutputStream.connect();
    } catch (IOException | TimeoutException e) {
      e.printStackTrace();
    }
  }

  private void startTriggerThread() {
    new TriggerThread(triggers).start();
  }

  @Override
  public long onNewData(Command command) {
    try {
      onReceiveCommand(command);
    } catch (Exception e) {
      log.error(e.getMessage(), e);
    }
    return commands.size();
  }

  @Override
  public void onOrderTriggered(Command command) {
    log.debug("onOrderTriggered");
    try {
      onReceiveCommand(command);
    } catch (Exception e) {
      log.error(e.getMessage(), e);
    }
  }

  public static int numOfOrdersMeReceived = 0;
  public void onReceiveCommand(Command command) {
    if (!command.getCode().equals(CommandCode.LIQUIDATE)) {
      log.debug("onReceiveCommand: {}", command);
    }
    if (!command.isOrderCommand()) {
      commands.add(command);
      return;
    }
    if (ObjectUtils.isEmpty(command.getOrder())) {
      log.error("Error Command {}", command);
      return;
    }
    MatchingEngine.numOfOrdersMeReceived++;

    receivedOrders.compute(
        command.getOrder().getId(),
        (id, oldCommand) -> {
          if (oldCommand == null) {
            return processNewOrderCommand(command);
          }
          if (oldCommand.equals(CommandCode.CANCEL_ORDER)) {
            log.debug("Order is already canceled");
            return oldCommand;
          }
          if (oldCommand.equals(CommandCode.PLACE_ORDER)) {
            return processUpdatingOrderCommand(command, oldCommand);
          }
          throw new MarginException("Unknown command " + oldCommand);
        });
  }

  private CommandCode processNewOrderCommand(Command command) {
    log.debug("processNewOrderCommand");
    if (command.isPlaceOrderCommand()) {
      Order order = (Order) command.getData();
      order.setPriority(currentPriority++);
      commands.add(command);
      return CommandCode.PLACE_ORDER;
    } else if (command.isCancelOrderCommand()) {
      log.debug("Order hasn't been processed before");
      command.setExtraData(false); // added to orderbook: false
      commands.add(command);
      return CommandCode.CANCEL_ORDER;
    }
    throw new MarginException("Unknown command " + command.getCode());
  }

  private CommandCode processUpdatingOrderCommand(Command command, CommandCode oldCommand) {
    log.debug("processUpdatingOrderCommand");
    if (command.isCancelOrderCommand()) {
      Order commandOrder = (Order) command.getData();
      Order order = this.orderService.get(commandOrder.getKey());
      if (order != null && (order.isLimitOrder() || order.isStopOrder())) {
//      if (commandOrder.isLimitOrder() || commandOrder.isStopOrder()) {
        commands.add(command);
      } else {
        log.debug("Market order is canceled by matching engine automatically");
      }
      return command.getCode();
    } else if (command.isPlaceOrderCommand()) {
      if (command.isTriggerCommand()) {
        commands.add(command);
      } else {
        log.debug("Order is already processed");
      }
      return oldCommand;
    }
    throw new MarginException("Unknown command " + command.getCode());
  }

  public static long sumOfTime = 0;
  public static long startTime;
  public void start() {
    log.info("Start Matching Engine");
    // loop to get next command until we want to stop
    while (!stopEngine) {
      startTime = System.currentTimeMillis();
      String processCommandCode = null;
      Integer currSizeCommand = null;
      try {
        currentProcCommand = commands.take(); // GC activate
        processCommandCode = currentProcCommand.getCode().toString();
        currSizeCommand = commands.size();
      } catch (InterruptedException e) {
        log.error("Exception while polling for command", e);
        stopEngine = true;
      }
      if (currentProcCommand != null) {
        UUID commandId = UUID.randomUUID();
        if (!currentProcCommand.getCode().equals(CommandCode.LIQUIDATE)) {
          log.debug("Process command: [code={}, id={}]", currentProcCommand.getCode(), commandId);
        }
        if (currentProcCommand.getCode().equals(CommandCode.SHOW_PROCESSING_TIME)) {
          showProcessingTime = !showProcessingTime;
          continue;
        }
        try {
          onTick();
        } catch (Exception e) {
          // skip error for continue running
          log.error(e.getMessage(), e);
          rollback();
        }
        if (!currentProcCommand.getCode().equals(CommandCode.LIQUIDATE)) {
          log.debug("End process. [code={}, id={}]", currentProcCommand.getCode(), commandId);
        }
      }

      long processingTime = System.currentTimeMillis() - startTime;
      if (showProcessingTime && processingTime > 200) {
        System.out.println("Start process command: " + processCommandCode);
        System.out.println("Current size of commands: " + currSizeCommand);
        System.out.println("👋 Processing Time: " + processingTime);
      }
    }
    if (stopEngine) {
      log.info("Stop Matching Engine");
      // When STOP matching engine we need to save output stream
      orderBookOutputStream.flush();
      orderBookOutputStream.close();
    }
  }

  protected void onTick() {
    switch (currentProcCommand.getCode()) {
      case INITIALIZE_ENGINE:
        initializeEngine(currentProcCommand);
        break;
      case START_ENGINE:
        startEngine();
        break;
      case UPDATE_INSTRUMENT:
        instrumentUseCase.updateInstrument(currentProcCommand, orderBookOutputStream);
        break;
      case UPDATE_INSTRUMENT_EXTRA:
        instrumentUseCase.updateInstrumentExtra(currentProcCommand);
        break;
      case CREATE_ACCOUNT:
        accountUseCase.create(currentProcCommand);
        break;
      case LOAD_POSITION:
        positionUseCase.execute(currentProcCommand);
        break;
      case ADJUST_TP_SL:
        positionUseCase.updateTpSl(currentProcCommand);
        break;
      case LOAD_POSITION_HISTORY:
        positionHistoryUseCase.execute(currentProcCommand);
        break;
      case LOAD_FUNDING_HISTORY:
        fundingHistoryUseCase.execute(currentProcCommand);
        break;
      case DEPOSIT:
        accountUseCase.deposit(currentProcCommand);
        break;
      case WITHDRAW:
        accountUseCase.withdraw(currentProcCommand);
        break;
      case ADJUST_LEVERAGE:
        orderUseCase.updateLeverage(currentProcCommand);
        break;
      case LOAD_ORDER:
      case PLACE_ORDER:
      case TRIGGER_ORDER:
        orderUseCase.placeOrder(currentProcCommand);
        break;
      case CANCEL_ORDER:
        Order commandOrder = (Order) currentProcCommand.getData();
        Order order = this.orderService.get(commandOrder.getKey());
        if (order == null) {
          log.error("[onTick] Order not found. Cannot make cancel order - id=" + commandOrder.getKey());
          commit();
          break;
        }
        currentProcCommand.setData(commandOrder);
        orderUseCase.cancelOrder(currentProcCommand);
        break;
      case LIQUIDATE:
        liquidationUseCase.liquidate(currentProcCommand, triggers);
        break;
      case PAY_FUNDING:
        fundingUseCase.payFunding(currentProcCommand);
        break;
      case LOAD_LEVERAGE_MARGIN:
        tradingRuleService.loadLeverageMarginRule(currentProcCommand.getLeverageMargin());
        break;
      case STOP_ENGINE:
        stopEngine = true;
        break;
      case ADJUST_MARGIN_POSITION:
        positionUseCase.adjustMarginPosition(currentProcCommand);
        break;
      case LOAD_TRADING_RULE:
        tradingRuleService.loadTradingRule(currentProcCommand.getTradingRule());
        break;
      case CLOSE_INSURANCE:
        liquidationUseCase.closeInsurancePosition();
        break;
      case ADJUST_TP_SL_PRICE:
        orderUseCase.updateTpSlPrice(currentProcCommand);
        break;
      case LOAD_BOT_ACCOUNT:
        accountUseCase.loadBotAccount(currentProcCommand);
        break;
      case SEED_LIQUIDATION_ORDER_ID:
        orderService.seedLiquidationOrderId(currentProcCommand);
        break;

      case START_MEASURE_TPS:
        this.startMeasureTps();
        break;
      case RETRIEVE_DATA:
        this.retrievingData.addAll(this.retrieveDataUseCase.execute(currentProcCommand, orderBookOutputStream));
        commit();
        break;
      default:
        log.atError()
            .addKeyValue("command", currentProcCommand.getCode())
            .log("Ignoring unsupported command");
    }
    cleanOldEntities();
  }

  private void initializeEngine(Command command) {
    EngineParams params = (EngineParams) command.getData();
    orderService.setCurrentId(params.getLastOrderId() + 1);
    orderService.seedLiquidationOrderId(command);
    positionService.setCurrentId(params.getLastPositionId() + 1);
    tradeService.setCurrentId(params.getLastTradeId() + 1);
    marginHistoryService.setCurrentId(params.getLastMarginHistoryId() + 1);
    positionHistoryService.setCurrentId(params.getLastPositionHistoryId() + 1);
    fundingService.setCurrentId(params.getLastFundingHistoryId() + 1);
  }

  private void startEngine() {
    log.info("Start Matching Engine and Waiting Order");
    preloadStream.close();
    createInputStream(config);
  }

  public static int numOfTradesMeHandled = 0;
  public void commit() {
    CommandOutput output = new CommandOutput();
    output.setCode(currentProcCommand.getCode());
    output.setData(currentProcCommand.getData());
    output.setShouldSeedLiquidationOrderId(orderService.shouldSeedLiquidationOrderIdPool());
    output.setAccounts(accountService.getProcessingEntities());
    output.setFundingHistories(fundingService.getProcessingEntities());
    output.setMarginHistories(marginHistoryService.getProcessingEntities());
    output.setTrades(tradeService.getProcessingEntities());
    if (output.getTrades() != null) {
      MatchingEngine.numOfTradesMeHandled += output.getTrades().size();
    }
    // Re-update order from trade
//    tradeService.getProcessingEntities().forEach(trade -> {
//      if (trade.getSellOrder() != null && !OrderStatus.CANCELED.equals(trade.getSellOrder().getStatus())) orderService.update(trade.getSellOrder());
//      if (trade.getBuyOrder() != null && !OrderStatus.CANCELED.equals(trade.getBuyOrder().getStatus())) orderService.update(trade.getBuyOrder());
//    });
    output.setPositions(positionService.getProcessingEntities());
    output.setOrders(orderService.getProcessingEntities());

    // Check accounts have no open orders and positions
//    Set<Long> accountIdsHaveNoOpenOrdersAndPositionsSet = new HashSet<>();
//    output.getPositions().forEach(p -> {
//      if (!p.getCurrentQty().eq(MarginBigDecimal.ZERO)) return;
//      List<Position> userOpenPositions = this.positionService.getUserPositions(p.getAccountId());
//      List<Order> userOpenOrders = this.orderService.getUserOpenOrders(p.getAccountId()).toList();
//      if (userOpenOrders.isEmpty() && userOpenPositions.isEmpty()) {
//        accountIdsHaveNoOpenOrdersAndPositionsSet.add(p.getAccountId());
//      }
//    });
//
//    output.getOrders().forEach(o -> {
//      if (o.canBeCanceled()) return;
//      List<Position> userOpenPositions = this.positionService.getUserPositions(o.getAccountId());
//      List<Order> userOpenOrders = this.orderService.getUserOpenOrders(o.getAccountId()).toList();
//      if (userOpenOrders.isEmpty() && userOpenPositions.isEmpty()) {
//        accountIdsHaveNoOpenOrdersAndPositionsSet.add(o.getAccountId());
//      }
//    });
//
//    // Set accHasNoOpenOrdersAndPositionsList to output
//    output.setAccHasNoOpenOrdersAndPositionsList(
//            accountIdsHaveNoOpenOrdersAndPositionsSet.stream().map(
//                    accountId -> new AccHasNoOpenOrdersAndPositions(accountId, this.accountService.get(accountId).getUserId())
//            ).toList()
//    );

    output.setPositionHistories(positionHistoryService.getProcessingEntities());
    setTpSlOrderPositions();
    List<Transaction> outputTransactions = new ArrayList<>();
    outputTransactions.addAll(transactionService.getProcessingEntities());
    outputTransactions.addAll(liquidationService.getLiquidatedTransactions());
    output.setTransactions(outputTransactions);
    output.setOperationId(String.valueOf(operationIdGenerator.generateOperationId()));
    output.setErrors(errors);
    output.setLiquidatedPositions(liquidationService.getLiquidatedPositions());
    output.setAdjustLeverage(currentProcCommand.getAdjustLeverage());
    output.setRetrievingData(this.retrievingData);

    if (output.getCode().equals(CommandCode.PAY_FUNDING)
        || output.getCode().equals(CommandCode.ADJUST_MARGIN_POSITION)
        || output.getCode().equals(CommandCode.UPDATE_INSTRUMENT)
        || output.getCode().equals(CommandCode.UPDATE_INSTRUMENT_EXTRA)
        || output.hasData()) {
      commandOutputStream.write(output.deepCopy());
    }
    orderBookOutputStream.write(orderbookService.getProcessingEntities());

    accountService.commit();
    fundingService.commit();
    marginHistoryService.commit();
    orderService.commit();
    positionHistoryService.commit();
    positionService.commit();
    tradeService.commit();
    transactionService.commit();
    liquidationService.commit();
    orderbookService.commit();
    instrumentService.updateLastPrice(output.getTrades());
    errors.clear();
    this.retrievingData.clear();
  }

  /** Set tp/sl order for position */
  private void setTpSlOrderPositions() {
    positionService
        .getProcessingEntities()
        .forEach(
            p -> {
              p.setOrders(new ArrayList<>());
              if (p.getStopLossOrderId() != null) {
                Order slOrder = orderService.get(p.getStopLossOrderId());
                p.getOrders().add(slOrder);
              }
              if (p.getTakeProfitOrderId() != null) {
                Order tpOrder = orderService.get(p.getTakeProfitOrderId());
                p.getOrders().add(tpOrder);
              }
            });
  }

  public void rollback() {
    accountService.rollback();
    fundingService.rollback();
    marginHistoryService.rollback();
    orderService.rollback();
    positionHistoryService.rollback();
    positionService.rollback();
    tradeService.rollback();
    transactionService.rollback();
    liquidationService.rollback();
    orderbookService.rollback();
    errors.clear();
    this.retrievingData.clear();
  }

  private void cleanOldEntities() {
    this.orderService.cleanOldEntities();
    this.positionHistoryService.cleanOldEntities();
    this.fundingService.cleanOldEntities();
  }

  private void startMeasureTps() {
    log.atInfo().log("Start to measure tps...");
    MatchingEngine.numOfOrdersMeReceived = 0;
    MatchingEngine.numOfTradesMeHandled = 0;
    JsonOutputStream.numOfTradesSentToKafka = 0;

    ScheduledExecutorService scheduler = Executors.newScheduledThreadPool(1);
    scheduler.schedule(() -> {
      System.out.println("numOfOrdersMeReceived=" + MatchingEngine.numOfOrdersMeReceived);
      System.out.println("numOfTradesMeHandled=" + MatchingEngine.numOfTradesMeHandled);
      System.out.println("numOfTradesSentToKafka=" + JsonOutputStream.numOfTradesSentToKafka);
    }, 60, TimeUnit.SECONDS);

    scheduler.shutdown();
  }
}

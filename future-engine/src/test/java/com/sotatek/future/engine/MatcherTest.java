package com.sotatek.future.engine;

import com.sotatek.future.BaseTest;
import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Order;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TimeInForce;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.FundingService;
import com.sotatek.future.service.InstrumentService;
import com.sotatek.future.service.MarginHistoryService;
import com.sotatek.future.service.OrderService;
import com.sotatek.future.service.PositionHistoryService;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.service.ServiceFactory;
import com.sotatek.future.service.TradeService;
import com.sotatek.future.service.TransactionService;
import com.sotatek.future.util.MarginBigDecimal;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

class MatcherTest {

  private Matcher matcher = new Matcher("BTCUSDT");

  private AccountService accountService;

  private InstrumentService instrumentService;

  private OrderService orderService;

  private TradeService tradeService;

  private PositionService positionService;

  private final Asset defaultAsset = Asset.USDT;

  @BeforeEach
  void setUp() {
    // initialize for all service
    ServiceFactory.initialize();
    accountService = AccountService.getInstance();
    FundingService fundingService = FundingService.getInstance();
    instrumentService = InstrumentService.getInstance();
    MarginHistoryService marginHistoryService = MarginHistoryService.getInstance();
    orderService = OrderService.getInstance();
    PositionHistoryService positionHistoryService = PositionHistoryService.getInstance();
    positionService = PositionService.getInstance();
    tradeService = TradeService.getInstance();
    TransactionService transactionService = TransactionService.getInstance();

    // initial current Id
    accountService.setCurrentId(1);
    fundingService.setCurrentId(1);
    instrumentService.setCurrentId(1);
    marginHistoryService.setCurrentId(1);
    orderService.setCurrentId(1);
    positionService.setCurrentId(1);
    positionHistoryService.setCurrentId(1);
    tradeService.setCurrentId(1);
    transactionService.setCurrentId(1);

    // set info for default instrument
    Instrument instrument = new Instrument();
    instrument.setSymbol("BTCUSDT");
    instrument.setRootSymbol("BTC");
    instrument.setState("Open");
    instrument.setType(0);
    instrument.setInitMargin(MarginBigDecimal.valueOf("0.01"));
    instrument.setMaintainMargin(MarginBigDecimal.valueOf("0.005"));
    instrument.setMultiplier(MarginBigDecimal.ONE);
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
    instrument.setTakerFee(MarginBigDecimal.valueOf("0.00075"));
    instrument.setMakerFee(MarginBigDecimal.valueOf("0.00025"));
    instrument.setContractType(ContractType.USD_M);
    instrumentService.insert(instrument);
    InstrumentExtraInformation instrumentExtraInformation = new InstrumentExtraInformation();
    instrumentExtraInformation.setSymbol("BTCUSDT");
    instrumentExtraInformation.setOraclePrice(MarginBigDecimal.valueOf("300"));
    instrumentService.updateExtraInfo(instrumentExtraInformation);
  }

  @Test
  void processOrder_WhenOrderBookHasOrderWithInsufficientAccount() {
    Account processAccount = Account.builder().build();
    processAccount.setId(1l);
    processAccount.setAsset(this.defaultAsset);
    processAccount.setBalance(MarginBigDecimal.valueOf(50));

    Account account1 = Account.builder().build();
    account1.setId(2l);
    account1.setAsset(this.defaultAsset);
    account1.setBalance(MarginBigDecimal.valueOf(10000));

    // insufficient balance
    Account insufficientAccount = Account.builder().build();
    insufficientAccount.setId(3l);
    insufficientAccount.setAsset(this.defaultAsset);
    insufficientAccount.setBalance(MarginBigDecimal.valueOf(10000));

    Account account2 = Account.builder().build();
    account2.setId(4l);
    account2.setAsset(this.defaultAsset);
    account2.setBalance(MarginBigDecimal.valueOf(10000));

    Account insurance = Account.builder().build();
    insurance.setId(BaseTest.defaultInsuranceAccountId);
    insurance.setUserId(AccountService.INSURANCE_USER_ID);
    insurance.setAsset(this.defaultAsset);
    insurance.setBalance(MarginBigDecimal.valueOf(20000));
    AccountService.INSURANCE_ACCOUNT_IDS.put(defaultAsset, BaseTest.defaultInsuranceAccountId);

    // insert account
    accountService.insert(processAccount);
    accountService.insert(account1);
    accountService.insert(insufficientAccount);
    accountService.insert(account2);
    accountService.update(insurance);
    accountService.commit();

    /**
     * Case testing Buy order limit with size 4 and price 4 3 Sell order limit with size 1 price 1
     * size 1 price 2 => this order linked to insufficient account size 1 price 3 => logic after
     * matching Buy order size 4 (remaining 2) => ACTIVE Sell order size 1 price 1 => FILLED size 1
     * price 2 => CANCELLED size 1 price 3 => FILLED
     *
     * <p>processAccount -> subtract taker fee two time when matching with sellOrder1 and sellOrder3
     * insufficientAccount -> not change because sellOrder2 linked to insufficient account then it
     * cancelled account1 -> subtract a maker fee account2 -> subtract a maker fee
     */
    Order buyOrder =
        Order.builder()
            .accountId(processAccount.getId())
            .asset(Asset.USDT)
            .quantity(MarginBigDecimal.valueOf(4))
            .remaining(MarginBigDecimal.valueOf(4))
            .status(OrderStatus.ACTIVE)
            .side(OrderSide.BUY)
            .timeInForce(TimeInForce.GTC)
            .cost(MarginBigDecimal.valueOf(20))
            .originalCost(MarginBigDecimal.valueOf(20))
            .leverage(MarginBigDecimal.valueOf(10))
            .price(MarginBigDecimal.valueOf(4))
            .orderMargin(MarginBigDecimal.ZERO)
            .originalOrderMargin(MarginBigDecimal.ZERO)
            .symbol("BTCUSDT")
            .type(OrderType.LIMIT)
            .build();
    buyOrder.setId(1l);

    Order sellOrder1 =
        Order.builder()
            .accountId(account1.getId())
            .asset(Asset.USDT)
            .quantity(MarginBigDecimal.valueOf(5))
            .remaining(MarginBigDecimal.valueOf(1))
            .status(OrderStatus.ACTIVE)
            .cost(MarginBigDecimal.valueOf(5))
            .originalCost(MarginBigDecimal.valueOf(5))
            .leverage(MarginBigDecimal.valueOf(10))
            .price(MarginBigDecimal.valueOf(1))
            .orderMargin(MarginBigDecimal.ZERO)
            .originalOrderMargin(MarginBigDecimal.ZERO)
            .side(OrderSide.SELL)
            .timeInForce(TimeInForce.GTC)
            .symbol("BTCUSDT")
            .type(OrderType.LIMIT)
            .build();
    sellOrder1.setId(2l);

    // linked to insufficient balance
    Order sellOrder2 =
        Order.builder()
            .accountId(insufficientAccount.getId())
            .asset(Asset.USDT)
            .quantity(MarginBigDecimal.valueOf(1))
            .remaining(MarginBigDecimal.valueOf(1))
            .status(OrderStatus.ACTIVE)
            .cost(MarginBigDecimal.valueOf(5))
            .originalCost(MarginBigDecimal.valueOf(5))
            .leverage(MarginBigDecimal.valueOf(10))
            .price(MarginBigDecimal.valueOf(2))
            .orderMargin(MarginBigDecimal.ZERO)
            .originalOrderMargin(MarginBigDecimal.ZERO)
            .side(OrderSide.SELL)
            .timeInForce(TimeInForce.GTC)
            .symbol("BTCUSDT")
            .type(OrderType.LIMIT)
            .build();
    sellOrder2.setId(3l);

    Order sellOrder3 =
        Order.builder()
            .accountId(4l)
            .asset(Asset.USDT)
            .quantity(MarginBigDecimal.valueOf(1))
            .remaining(MarginBigDecimal.valueOf(1))
            .status(OrderStatus.ACTIVE)
            .cost(MarginBigDecimal.valueOf(5))
            .originalCost(MarginBigDecimal.valueOf(5))
            .leverage(MarginBigDecimal.valueOf(10))
            .price(MarginBigDecimal.valueOf(3))
            .orderMargin(MarginBigDecimal.ZERO)
            .originalOrderMargin(MarginBigDecimal.ZERO)
            .side(OrderSide.SELL)
            .timeInForce(TimeInForce.GTC)
            .symbol("BTCUSDT")
            .type(OrderType.LIMIT)
            .build();
    sellOrder3.setId(4l);

    // create sell order queue
    matcher.getPendingOrdersQueue(OrderSide.SELL).add(sellOrder1);
    matcher.getPendingOrdersQueue(OrderSide.SELL).add(sellOrder2);
    matcher.getPendingOrdersQueue(OrderSide.SELL).add(sellOrder3);

    // call process order
    matcher.processOrder(buyOrder);

    accountService.getProcessingEntities();

    // confirm result after process
    Assertions.assertEquals(4, orderService.getProcessingEntities().size());
    // confirm state of each order after process
    Assertions.assertEquals(buyOrder.getId(), orderService.getProcessingEntities().get(0).getId());
    Assertions.assertEquals(
        OrderStatus.ACTIVE, orderService.getProcessingEntities().get(0).getStatus());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(2), orderService.getProcessingEntities().get(0).getRemaining());

    Assertions.assertEquals(
        sellOrder1.getId(), orderService.getProcessingEntities().get(1).getId());
    Assertions.assertEquals(
        OrderStatus.FILLED, orderService.getProcessingEntities().get(1).getStatus());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(0), orderService.getProcessingEntities().get(1).getRemaining());

    Assertions.assertEquals(
        sellOrder2.getId(), orderService.getProcessingEntities().get(2).getId());
    Assertions.assertEquals(
        OrderStatus.CANCELED, orderService.getProcessingEntities().get(2).getStatus());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(1), orderService.getProcessingEntities().get(2).getRemaining());

    Assertions.assertEquals(
        sellOrder3.getId(), orderService.getProcessingEntities().get(3).getId());
    Assertions.assertEquals(
        OrderStatus.FILLED, orderService.getProcessingEntities().get(3).getStatus());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(0), orderService.getProcessingEntities().get(3).getRemaining());

    // Confirm trade
    Assertions.assertEquals(2, tradeService.getProcessingEntities().size());
    // confirm first trade of buyOrder with sellOrder1
    Assertions.assertEquals(
        buyOrder.getId(), tradeService.getProcessingEntities().get(0).getBuyOrderId());
    Assertions.assertEquals(
        sellOrder1.getId(), tradeService.getProcessingEntities().get(0).getSellOrderId());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(1), tradeService.getProcessingEntities().get(0).getPrice());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(1), tradeService.getProcessingEntities().get(0).getQuantity());
    // confirm second trade of buyOrder with sellOrder3
    Assertions.assertEquals(
        buyOrder.getId(), tradeService.getProcessingEntities().get(1).getBuyOrderId());
    Assertions.assertEquals(
        sellOrder3.getId(), tradeService.getProcessingEntities().get(1).getSellOrderId());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(3), tradeService.getProcessingEntities().get(1).getPrice());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(1), tradeService.getProcessingEntities().get(1).getQuantity());

    // confirm position
    Assertions.assertEquals(3, positionService.getProcessingEntities().size());
    // short position for account 1
    Assertions.assertEquals(
        sellOrder1.getAccountId(), positionService.getProcessingEntities().get(0).getAccountId());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(-1),
        positionService.getProcessingEntities().get(0).getCurrentQty());
    // short position for account 2
    Assertions.assertEquals(
        sellOrder3.getAccountId(), positionService.getProcessingEntities().get(1).getAccountId());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(-1),
        positionService.getProcessingEntities().get(1).getCurrentQty());
    // long position for processing account
    Assertions.assertEquals(
        buyOrder.getAccountId(), positionService.getProcessingEntities().get(2).getAccountId());
    Assertions.assertEquals(
        MarginBigDecimal.valueOf(2),
        positionService.getProcessingEntities().get(2).getCurrentQty());

    // confirm account

  }
}

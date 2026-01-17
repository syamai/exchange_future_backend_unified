package com.sotatek.future.usecase;

import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.entity.*;
import com.sotatek.future.model.*;
import com.sotatek.future.output.OrderBookOutputStream;
import com.sotatek.future.output.OutputStream;
import com.sotatek.future.service.*;
import com.sotatek.future.util.IntervalTree;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.value.LeverageMarginRule;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;

import java.util.ArrayList;
import java.util.List;

@RequiredArgsConstructor
@Slf4j
public class RetrieveDataUseCase {
    private final OrderService orderService;
    private final PositionService positionService;
    private final AccountService accountService;
    private final InstrumentService instrumentService;
    private final TradingRuleService tradingRuleService;

    public List<Object> execute(Command command, OutputStream<OrderBookOutput> orderBookOutputStream) {
        RetrieveData retrieveData = command.getRetrieveData();
        if (retrieveData == null || retrieveData.getType() == null) {
            log.error("retrieveData is invalid");
            return new ArrayList<>();
        }

        return switch (retrieveData.getType()) {
            case ORDER -> {
                Order order = this.getOrder(retrieveData);
                yield order != null ? List.of(order) : new ArrayList<>();
            }
            case POSITION -> {
                Position position = this.getPosition(retrieveData);
                yield position != null ? List.of(position) : new ArrayList<>();
            }
            case ACCOUNT -> {
                Account account = this.getAccount(retrieveData);
                yield account != null ? List.of(account) : new ArrayList<>();
            }
            case INSTRUMENT -> {
                Instrument instrument = this.getInstrument(retrieveData);
                yield instrument != null ? List.of(instrument) : new ArrayList<>();
            }
            case ORDERBOOK -> {
                OrderBookOutputStream.OrderBookData orderBook = this.getOrderBook(retrieveData, orderBookOutputStream);
                yield orderBook != null ? List.of(orderBook) : new ArrayList<>();
            }
            case LM_SYMBOL_INDEX -> {
                IntervalTree<MarginBigDecimal, LeverageMarginRule> lmSymbolIndex = this.getLmSymbolIndex(retrieveData);
                yield lmSymbolIndex != null ? List.of(lmSymbolIndex) : new ArrayList<>();
            }
            case LM_SYMBOL_INDEX_DEFAULT -> {
                LeverageMarginRule lmSymbolIndexDefault = this.getLmSymbolIndexDefault(retrieveData);
                yield lmSymbolIndexDefault != null ? List.of(lmSymbolIndexDefault) : new ArrayList<>();
            }
            case LIQUIDATION_CLEARANCE_RATE_INDEX -> {
                MarginBigDecimal liquidationClearanceRateIndex = this.getLiquidationClearanceRateIndex(retrieveData);
                yield liquidationClearanceRateIndex != null ? List.of(liquidationClearanceRateIndex) : new ArrayList<>();
            }
        };

    }

    private Order getOrder(RetrieveData retrieveDataInput) {
        OrderQuery orderQuery = retrieveDataInput.getOrderQuery();
        if (orderQuery == null || orderQuery.getId() == null) {
            log.error("orderQuery is invalid");
            return null;
        }

        Order order = this.orderService.get(orderQuery.getId());
        if (order == null) {
            log.error("order id=" + orderQuery.getId() + " is null or empty");
            return null;
        }
        return order;
    }

    private Position getPosition(RetrieveData retrieveDataInput) {
        PositionQuery positionQuery = retrieveDataInput.getPositionQuery();
        if (positionQuery == null || positionQuery.getAccountId() == null || positionQuery.getSymbol() == null) {
            log.error("positionQuery is invalid");
            return null;
        }

        Position position = this.positionService.get(positionQuery.getAccountId(), positionQuery.getSymbol());
        if (position == null) {
            log.error("position accountId=" + positionQuery.getAccountId() + " and symbol=" + positionQuery.getSymbol() + " is null or empty");
            return null;
        }
        return position;
    }

    private Account getAccount(RetrieveData retrieveDataInput) {
        AccountQuery accountQuery = retrieveDataInput.getAccountQuery();
        if (accountQuery == null || accountQuery.getId() == null) {
            log.error("accountQuery is invalid");
            return null;
        }

        Account account = this.accountService.get(accountQuery.getId());
        if (account == null) {
            log.error("account id=" + accountQuery.getId() + " is null or empty");
            return null;
        }
        return account;
    }

    private Instrument getInstrument(RetrieveData retrieveDataInput) {
        InstrumentQuery instrumentQuery = retrieveDataInput.getInstrumentQuery();
        if (instrumentQuery == null || instrumentQuery.getSymbol() == null) {
            log.error("instrumentQuery is invalid");
            return null;
        }

        Instrument instrument = this.instrumentService.get(instrumentQuery.getSymbol());
        if (instrument == null) {
            log.error("instrument of " + instrumentQuery.getSymbol() + " is null or empty");
            return null;
        }
        return instrument;
    }

    private OrderBookOutputStream.OrderBookData getOrderBook(RetrieveData retrieveDataInput, OutputStream<OrderBookOutput> orderBookOutputStream) {
        OrderBookQuery orderBookQuery = retrieveDataInput.getOrderBookQuery();
        if (orderBookQuery == null || orderBookQuery.getSymbol() == null) {
            log.error("orderBookQuery is invalid");
            return null;
        }
        if (orderBookOutputStream instanceof OrderBookOutputStream) {
            OrderBookOutputStream.OrderBookData orderBookData = ((OrderBookOutputStream) orderBookOutputStream).getOrderBookDataBySymbol(orderBookQuery.getSymbol());
            if (orderBookData == null) {
                log.error("orderBookData of " + orderBookQuery.getSymbol() + " is null or empty");
                return null;
            }
            return orderBookData;
        }
        return null;
    }

    private IntervalTree<MarginBigDecimal, LeverageMarginRule> getLmSymbolIndex(RetrieveData retrieveDataInput) {
        LmSymbolIndexQuery lmSymbolIndexQuery = retrieveDataInput.getLmSymbolIndexQuery();
        if (lmSymbolIndexQuery == null || lmSymbolIndexQuery.getSymbol() == null) {
            log.error("lmSymbolIndexQuery is invalid");
            return null;
        }

        IntervalTree<MarginBigDecimal, LeverageMarginRule> lmSymbolIndex = this.tradingRuleService.getLmSymbolIndexBySymbol(lmSymbolIndexQuery.getSymbol());
        if (lmSymbolIndex == null) {
            log.error("lmSymbolIndex of " + lmSymbolIndexQuery.getSymbol() + " is null or empty");
            return null;
        }
        return lmSymbolIndex;
    }

    private LeverageMarginRule getLmSymbolIndexDefault(RetrieveData retrieveDataInput) {
        LmSymbolIndexDefaultQuery lmSymbolIndexDefaultQuery = retrieveDataInput.getLmSymbolIndexDefaultQuery();
        if (lmSymbolIndexDefaultQuery == null || lmSymbolIndexDefaultQuery.getSymbol() == null) {
            log.error("lmSymbolIndexDefaultQuery is invalid");
            return null;
        }

        LeverageMarginRule lmSymbolIndexDefault = this.tradingRuleService.getLmSymbolIndexDefaultBySymbol(lmSymbolIndexDefaultQuery.getSymbol());
        if (lmSymbolIndexDefault == null) {
            log.error("lmSymbolIndexDefault of " + lmSymbolIndexDefaultQuery.getSymbol() + " is null or empty");
            return null;
        }
        return lmSymbolIndexDefault;
    }

    private MarginBigDecimal getLiquidationClearanceRateIndex(RetrieveData retrieveDataInput) {
        LiquidationClearanceRateIndexQuery liquidationClearanceRateIndexQuery = retrieveDataInput.getLiquidationClearanceRateIndexQuery();
        if (liquidationClearanceRateIndexQuery == null || liquidationClearanceRateIndexQuery.getSymbol() == null) {
            log.error("liquidationClearanceRateIndexQuery is invalid");
            return null;
        }

        MarginBigDecimal liquidationClearanceRateIndex = this.tradingRuleService.getLiquidationClearanceRateIndexBySymbol(liquidationClearanceRateIndexQuery.getSymbol());
        if (liquidationClearanceRateIndex == null) {
            log.error("liquidationClearanceRateIndex of " + liquidationClearanceRateIndexQuery.getSymbol() + " is null or empty");
            return null;
        }
        return liquidationClearanceRateIndex;

    }
}

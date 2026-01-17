package com.sotatek.future.ticker;

import com.google.gson.internal.LinkedTreeMap;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.Ticker;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.input.InputStream;
import com.sotatek.future.input.ListInputStream;
import com.sotatek.future.output.ListOutputStream;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.util.TimeUtil;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Date;
import java.util.List;
import java.util.concurrent.TimeoutException;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;

class TickerTest {

  @Test
  void test1() {
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(1, "65000", "1", TimeUtil.currentTimeSeconds())
            //            ,this.createTrade(2, "12", "1", TimeUtil.currentTimeSeconds())
            );
    Ticker ticker = new Ticker();
    ticker.symbol("BTCUSD");
    ticker.lastPrice(MarginBigDecimal.valueOf("65000"));
    ticker.highPrice(MarginBigDecimal.valueOf("65000"));
    ticker.lowPrice(MarginBigDecimal.valueOf("64000"));
    ticker.volume(MarginBigDecimal.valueOf("1"));
    ticker.quoteVolume(MarginBigDecimal.valueOf("65000"));
    ticker.priceChange(MarginBigDecimal.valueOf("1000"));
    ticker.priceChangePercent(MarginBigDecimal.valueOf("1.5625"));
    ticker.lastPriceChange(MarginBigDecimal.valueOf("1000"));
    ticker.trades(new ArrayList<>());
    List<Ticker> tickers = Arrays.asList(ticker);
    this.doTest(Arrays.asList(), trades, tickers);
  }

  @Test
  void test1b() {
    List<Trade> trades = Arrays.asList();
    Ticker ticker = new Ticker();
    ticker.symbol("BTCUSD");
    ticker.lastPrice(MarginBigDecimal.valueOf("65000"));
    ticker.highPrice(MarginBigDecimal.valueOf("65000"));
    ticker.lowPrice(MarginBigDecimal.valueOf("0"));
    ticker.volume(MarginBigDecimal.valueOf("1"));
    ticker.quoteVolume(MarginBigDecimal.valueOf("65000"));
    List<Ticker> tickers = Arrays.asList();
    this.doTest(Arrays.asList(), trades, tickers);
  }

  @Test
  void test2() {
    List<Trade> oldTrades =
        Arrays.asList(this.createTrade(1, "50000", "1", TickerProcessor.getTime24Ago() - 100));
    List<Trade> trades =
        Arrays.asList(this.createTrade(2, "65000", "1", TimeUtil.currentTimeSeconds()));
    Ticker ticker = new Ticker();
    ticker.symbol("BTCUSD");
    ticker.priceChange(MarginBigDecimal.valueOf("15000"));
    ticker.priceChangePercent(MarginBigDecimal.valueOf("30"));
    ticker.lastPrice(MarginBigDecimal.valueOf("65000"));
    ticker.lastPriceChange(MarginBigDecimal.valueOf("15000"));
    ticker.highPrice(MarginBigDecimal.valueOf("65000"));
    ticker.lowPrice(MarginBigDecimal.valueOf("50000"));
    ticker.volume(MarginBigDecimal.valueOf("1"));
    ticker.quoteVolume(MarginBigDecimal.valueOf("65000"));
    List<Ticker> tickers = Arrays.asList(ticker);

    this.doTest(oldTrades, trades, tickers);
  }

  @Test
  void test3() {
    List<Trade> oldTrades =
        Arrays.asList(
            this.createTrade(1, "50000", "1", TickerProcessor.getTime24Ago() - 100),
            this.createTrade(2, "55000", "2", TickerProcessor.getTime24Ago() + 100));
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(3, "65000", "1", TimeUtil.currentTimeSeconds() - 100),
            this.createTrade(4, "64000", "2", TimeUtil.currentTimeSeconds() - 10));
    Ticker ticker = new Ticker();
    ticker.symbol("BTCUSD");
    ticker.priceChange(MarginBigDecimal.valueOf("14000"));
    ticker.priceChangePercent(MarginBigDecimal.valueOf("28"));
    ticker.lastPrice(MarginBigDecimal.valueOf("64000"));
    ticker.lastPriceChange(MarginBigDecimal.valueOf("-1000"));
    ticker.highPrice(MarginBigDecimal.valueOf("65000"));
    ticker.lowPrice(MarginBigDecimal.valueOf("50000"));
    ticker.volume(MarginBigDecimal.valueOf("5"));
    ticker.quoteVolume(MarginBigDecimal.valueOf("303000"));
    List<Ticker> tickers = Arrays.asList(ticker);
    this.doTest(oldTrades, trades, tickers);
  }

  @Test
  void test4() {
    List<Trade> oldTrades =
        Arrays.asList(
            this.createTrade(1, "50000", "1", TickerProcessor.getTime24Ago() - 100),
            this.createTrade(2, "55000", "2", TickerProcessor.getTime24Ago() + 100));
    List<Trade> trades =
        Arrays.asList(
            this.createTrade(3, "65000", "1", TimeUtil.currentTimeSeconds() - 100),
            this.createTrade(4, "64000", "2", TimeUtil.currentTimeSeconds() - 10),
            this.createTrade("ETHUSD", 5, "4000", "2", TimeUtil.currentTimeSeconds() - 10));
    Ticker ticker = new Ticker();
    ticker.symbol("BTCUSD");
    ticker.priceChange(MarginBigDecimal.valueOf("14000"));
    ticker.priceChangePercent(MarginBigDecimal.valueOf("28"));
    ticker.lastPrice(MarginBigDecimal.valueOf("64000"));
    ticker.lastPriceChange(MarginBigDecimal.valueOf("-1000"));
    ticker.highPrice(MarginBigDecimal.valueOf("65000"));
    ticker.lowPrice(MarginBigDecimal.valueOf("50000"));
    ticker.volume(MarginBigDecimal.valueOf("5"));
    ticker.quoteVolume(MarginBigDecimal.valueOf("303000"));
    Ticker ticker2 = new Ticker();
    ticker2.symbol("ETHUSD");
    ticker2.lastPrice(MarginBigDecimal.valueOf("4000"));
    ticker2.highPrice(MarginBigDecimal.valueOf("4000"));
    ticker2.lowPrice(MarginBigDecimal.valueOf("0"));
    ticker2.volume(MarginBigDecimal.valueOf("2"));
    ticker2.quoteVolume(MarginBigDecimal.valueOf("8000"));
    List<Ticker> tickers = Arrays.asList(ticker, ticker2);
    this.doTest(oldTrades, trades, tickers);
  }

  void doTest(List<Trade> preloadTrades, List<Trade> trades, List<Ticker> tickers) {
    List<CommandOutput> preloadCommands = new ArrayList<>();
    if (preloadTrades.size() > 0) {
      CommandOutput command = new CommandOutput();
      command.setCode(CommandCode.PLACE_ORDER);
      command.setTrades(preloadTrades);
      preloadCommands.add(command);
    }
    CommandOutput startCommand = new CommandOutput();
    startCommand.setCode(CommandCode.START_ENGINE);
    preloadCommands.add(startCommand);

    LinkedTreeMap<String, Object> instrument = new LinkedTreeMap<>();
    instrument.put("symbol", "BTCUSD");
    instrument.put("multiplier", "1");
    CommandOutput updateInstrument = new CommandOutput();
    updateInstrument.setCode(CommandCode.UPDATE_INSTRUMENT);
    updateInstrument.setData(instrument);

    LinkedTreeMap<String, Object> instrumentExtraInformation = new LinkedTreeMap();
    instrumentExtraInformation.put("symbol", "BTCUSD");
    instrumentExtraInformation.put("indexPrice", "10");
    CommandOutput updateInstrumentExtra = new CommandOutput();
    updateInstrumentExtra.setCode(CommandCode.UPDATE_INSTRUMENT_EXTRA);
    updateInstrumentExtra.setData(instrumentExtraInformation);

    CommandOutput placeOrderCommand = new CommandOutput();
    placeOrderCommand.setCode(CommandCode.PLACE_ORDER);
    placeOrderCommand.setTrades(trades);

    InputStream<List<CommandOutput>> preloadStream =
        new ListInputStream<>(Arrays.asList(preloadCommands));
    InputStream<List<CommandOutput>> inputStream =
        new ListInputStream<>(
            Arrays.asList(
                Arrays.asList(updateInstrument, updateInstrumentExtra, placeOrderCommand)));
    ListOutputStream<List<Ticker>> outputStream = new ListOutputStream<>();
    TickerEngine tickerEngine = new TickerEngine(preloadStream, inputStream, outputStream);
    try {
      tickerEngine.initialize();
      tickerEngine.start();
      tickerEngine.publish();
    } catch (IOException e) {
      e.printStackTrace();
    } catch (TimeoutException e) {
      e.printStackTrace();
    }

    List<List<Ticker>> data = outputStream.getData();
    List<Ticker> actual = data.size() > 0 ? data.get(data.size() - 1) : null;
    Assertions.assertEquals(tickers, actual);
  }

  protected Trade createTrade(long id, String price, String quantity, Date createdAt) {
    return this.createTrade("BTCUSD", id, price, quantity, createdAt);
  }

  protected Trade createTrade(long id, String price, String quantity, long createdAt) {
    return this.createTrade("BTCUSD", id, price, quantity, new Date(createdAt * 1000));
  }

  protected Trade createTrade(
      String symbol, long id, String price, String quantity, long createdAt) {
    return this.createTrade(symbol, id, price, quantity, new Date(createdAt * 1000));
  }

  protected Trade createTrade(
      String symbol, long id, String price, String quantity, Date createdAt) {
    Order order = Order.builder().build();
    order.setId(id);
    order.setSide(OrderSide.BUY);
    order.setSymbol(symbol);
    // we only need price and quantity
    Trade trade =
        new Trade(
            order, order, MarginBigDecimal.valueOf(price), MarginBigDecimal.valueOf(quantity));
    trade.setId(id);
    trade.setCreatedAt(createdAt);
    return trade;
  }
}

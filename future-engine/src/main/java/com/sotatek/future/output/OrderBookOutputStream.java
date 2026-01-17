package com.sotatek.future.output;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.sotatek.future.TestEngineCLI;
import com.sotatek.future.engine.Matcher;
import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.entity.FutureBidsAsksBinanceDataResponse;
import com.sotatek.future.entity.OrderBook;
import com.sotatek.future.entity.OrderBookEvent;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.util.TimeUtil;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Comparator;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Map.Entry;
import java.util.Queue;
import java.util.Set;
import java.util.SortedMap;
import java.util.TreeMap;
import java.util.concurrent.ConcurrentLinkedQueue;
import java.util.function.Function;
import java.util.stream.Collectors;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;

@Slf4j
public abstract class OrderBookOutputStream extends BaseOutputStream<OrderBookOutput> {

  public static final String UPDATE_INTERVAL = "update_interval";
  private static final int ROW_LIMIT = 1000;

  protected final Map<String, OrderBookData> data = new HashMap<>();
  protected Queue<OrderBookOutput> queue = new ConcurrentLinkedQueue<>();
  protected int updateInterval = 450; // ms
  protected Map<String, Long> lastUpdatedAts = new HashMap<>();
  protected boolean isClosing = false;

  @Override
  public boolean connect() {
    new OrderBookOutputThread().start();
    return false;
  }

  @Override
  public void write(OrderBookOutput output) {
    this.queue.add(output);
  }

  @Override
  public void write(List<OrderBookOutput> orderBookOutputs) {
    this.queue.addAll(orderBookOutputs);
  }

  @Override
  public void close() {
    this.isClosing = true;
  }

  protected void processBatch(List<OrderBookOutput> batch) {
    Set<String> updatedSymbols = new HashSet<>();
    for (OrderBookOutput output : batch) {
      if (ObjectUtils.isEmpty(output.price())) {
        log.error("OrderBookOutput error {}", output);
        continue;
      }
      updatedSymbols.add(output.symbol());
      processOrderBookOutput(output);
    }

    for (String symbol : updatedSymbols) {
      write(symbol);
    }

//    Map<String, Matcher> matchers = MatchingEngine.matchers;
//    for (String symbol : matchers.keySet()) {
//      if (!updatedSymbols.contains(symbol)) {
//        write(symbol);
//      }
//    }
  }

  private void write(String symbol) {
    Function<Entry<MarginBigDecimal, MarginBigDecimal>, MarginBigDecimal[]> mapper =
        (Entry<MarginBigDecimal, MarginBigDecimal> entry) ->
            new MarginBigDecimal[] {entry.getKey(), entry.getValue()};

    List<MarginBigDecimal[]> newBids =
        this.getMap(symbol, OrderSide.BUY).entrySet().stream()
            .limit(ROW_LIMIT)
            .map(mapper)
            .collect(Collectors.toList());
    List<MarginBigDecimal[]> newAsks =
        this.getMap(symbol, OrderSide.SELL).entrySet().stream()
            .limit(ROW_LIMIT)
            .map(mapper)
            .collect(Collectors.toList());

    SortedMap<MarginBigDecimal, MarginBigDecimal> updatedBids =
        this.getUpdatedMap(symbol, OrderSide.BUY);
    SortedMap<MarginBigDecimal, MarginBigDecimal> updatedAsks =
        this.getUpdatedMap(symbol, OrderSide.SELL);
    List<MarginBigDecimal[]> changedBids =
        updatedBids.entrySet().stream().map(mapper).collect(Collectors.toList());
    List<MarginBigDecimal[]> changedAsks =
        updatedAsks.entrySet().stream().map(mapper).collect(Collectors.toList());
    updatedBids.clear();
    updatedAsks.clear();

    // Get data from binance
//    BidsAsksDataCombinedWithBinance bidsAsksDataCombinedWithBinance = this.combineCurrentBidsAsksWithBinanceData(symbol, newBids,  newAsks,  changedBids,  changedAsks);

    long updatedAt = System.currentTimeMillis();
    OrderBook newOrderBook = new OrderBook(newBids, newAsks, updatedAt, null);
//    OrderBook newOrderBook = new OrderBook(bidsAsksDataCombinedWithBinance.newBidsWithBinance, bidsAsksDataCombinedWithBinance.newAsksWithBinance, updatedAt, null);
//    OrderBook changedRows =
//        new OrderBook(bidsAsksDataCombinedWithBinance.changedBidsWithBinance, bidsAsksDataCombinedWithBinance.changedAsksWithBinance, updatedAt, this.getLastUpdatedAt(symbol));
    OrderBook changedRows =
        new OrderBook(changedBids, changedAsks, updatedAt, this.getLastUpdatedAt(symbol));
    this.lastUpdatedAts.put(symbol, updatedAt);
    this.publish(new OrderBookEvent(symbol, newOrderBook, changedRows));
  }

  private Map<String, FutureBidsAsksBinanceDataResponse> oldBinanceDatasBySymbol = new HashMap<>();
  private BidsAsksDataCombinedWithBinance combineCurrentBidsAsksWithBinanceData(String symbol, List<MarginBigDecimal[]> newBids, List<MarginBigDecimal[]> newAsks, List<MarginBigDecimal[]> changedBids, List<MarginBigDecimal[]> changedAsks) {
    try {
      // Kéo data từ binance về
      FutureBidsAsksBinanceDataResponse binanceData = this.getDataFromBinance(symbol);

      // Tạo ra 4 biến mới là: newBidsWithBinance, newAsksWithBinance, changedBidsWithBinance, changedAsksWithBinance
      // Những biến này là kết quả sau khi combine data binance và data của chúng ta
      List<MarginBigDecimal[]> newBidsWithBinance = new ArrayList<>(newBids);
      List<MarginBigDecimal[]> newAsksWithBinance = new ArrayList<>(newAsks);
      List<MarginBigDecimal[]> changedBidsWithBinance = new ArrayList<>(changedBids);
      List<MarginBigDecimal[]> changedAsksWithBinance = new ArrayList<>(changedAsks);

      for (MarginBigDecimal[] bid: binanceData.getBids()) {
        bid[1] = bid[1].divide(MarginBigDecimal.valueOf(10));
        newBidsWithBinance.add(bid);
      }

      for (MarginBigDecimal[] ask: binanceData.getAsks()) {
        ask[1] = ask[1].divide(MarginBigDecimal.valueOf(10));
        newAsksWithBinance.add(ask);
      }

      FutureBidsAsksBinanceDataResponse oldBinanceDataBySymbol = oldBinanceDatasBySymbol.get(symbol);
      if (oldBinanceDataBySymbol != null) {
        for (MarginBigDecimal[] newBid: oldBinanceDataBySymbol.getBids()) {
          MarginBigDecimal[] newBidWith0 = {newBid[0], MarginBigDecimal.ZERO};
          changedBidsWithBinance.add(newBidWith0);
        }

        for (MarginBigDecimal[] newAsk: oldBinanceDataBySymbol.getAsks()) {
          MarginBigDecimal[] newAskWith0 = {newAsk[0], MarginBigDecimal.ZERO};
          changedAsksWithBinance.add(newAskWith0);
        }
      }
      oldBinanceDatasBySymbol.put(symbol, binanceData);

      changedBidsWithBinance.addAll(binanceData.getBids());
      changedAsksWithBinance.addAll(binanceData.getAsks());
      return new BidsAsksDataCombinedWithBinance(newBidsWithBinance, newAsksWithBinance, changedBidsWithBinance, changedAsksWithBinance);
    } catch (Exception e) {
//      e.printStackTrace();
      return new BidsAsksDataCombinedWithBinance(newBids,  newAsks,  changedBids,  changedAsks);
    }
  }

  private FutureBidsAsksBinanceDataResponse getDataFromBinance(String symbol) throws Exception {
    URL obj = new URL("https://fapi.binance.com/fapi/v1/depth?symbol=" + symbol + "&limit=50");
    HttpURLConnection con = (HttpURLConnection) obj.openConnection();
    con.setRequestMethod("GET");
    int responseCode = con.getResponseCode();
//    System.out.println("GET Response Code :: " + responseCode);
    if (responseCode == HttpURLConnection.HTTP_OK) { // success
      BufferedReader in = new BufferedReader(new InputStreamReader(con.getInputStream()));
      String inputLine;
      StringBuffer response = new StringBuffer();
      while ((inputLine = in.readLine()) != null) {
        response.append(inputLine);
      }
      in.close();
//      System.out.println(response.toString());

      ObjectMapper objectMapper = new ObjectMapper();
      FutureBidsAsksBinanceDataResponse result = objectMapper.readValue(response.toString(), FutureBidsAsksBinanceDataResponse.class);
      return result;
    } else {
//      System.out.println("Cannot get data from Binance");
    }
    return null;
  }

  public void refreshOrderBook(String symbol) {
    OrderBook orderbook =
        new OrderBook(Arrays.asList(), Arrays.asList(), System.currentTimeMillis(), null);
    this.publish(new OrderBookEvent(symbol, orderbook, orderbook));
  }

  private long getLastUpdatedAt(String symbol) {
    Long time = this.lastUpdatedAts.get(symbol);
    return time == null ? System.currentTimeMillis() : time;
  }

  protected void processOrderBookOutput(OrderBookOutput orderbookOutput) {
    SortedMap<MarginBigDecimal, MarginBigDecimal> rows =
        this.getMap(orderbookOutput.symbol(), orderbookOutput.side());
    MarginBigDecimal price = orderbookOutput.price();
    MarginBigDecimal amount = rows.get(price);
    if (amount == null) {
      amount = MarginBigDecimal.ZERO;
    }
    amount = amount.add(orderbookOutput.quantity());
    if (amount.lte(MarginBigDecimal.ZERO)) {
      rows.remove(price);
    } else {
      rows.put(price, amount);
    }

    SortedMap<MarginBigDecimal, MarginBigDecimal> updatedRows =
        this.getUpdatedMap(orderbookOutput.symbol(), orderbookOutput.side());
    updatedRows.put(price, amount);
  }

  private SortedMap<MarginBigDecimal, MarginBigDecimal> getMap(String symbol, OrderSide side) {
    OrderBookData orderbookData = this.data.get(symbol);
    if (orderbookData == null) {
      orderbookData = new OrderBookData();
      this.data.put(symbol, orderbookData);
    }
    return side == OrderSide.BUY ? orderbookData.bids : orderbookData.asks;
  }

  private SortedMap<MarginBigDecimal, MarginBigDecimal> getUpdatedMap(
      String symbol, OrderSide side) {
    OrderBookData orderbookData = this.data.get(symbol);
    if (orderbookData == null) {
      orderbookData = new OrderBookData();
      this.data.put(symbol, orderbookData);
    }
    return side == OrderSide.BUY ? orderbookData.updatedBids : orderbookData.updatedAsks;
  }

  protected abstract void publish(OrderBookEvent event);

  public static class OrderBookData {

    final SortedMap<MarginBigDecimal, MarginBigDecimal> bids =
        new TreeMap<>(Comparator.reverseOrder());
    final SortedMap<MarginBigDecimal, MarginBigDecimal> asks = new TreeMap<>();

    final SortedMap<MarginBigDecimal, MarginBigDecimal> updatedBids =
        new TreeMap<>(Comparator.reverseOrder());
    final SortedMap<MarginBigDecimal, MarginBigDecimal> updatedAsks = new TreeMap<>();
  }

  private class OrderBookOutputThread extends Thread {

    @Override
    public void run() {
      while (!isClosing) {
        try {
          List<OrderBookOutput> batch = this.getBatch();
          processBatch(batch);
        } catch (Exception e) {
          //just log to keep output thread running
          log.error("OrderBookOutputStream processBatch get exception", e);
        }
      }
    }

    protected List<OrderBookOutput> getBatch() {
      long lastUpdateTime = System.currentTimeMillis();

      List<OrderBookOutput> batch = new ArrayList<>();
      do {
        OrderBookOutput data = queue.poll();
        if (data != null) {
          batch.add(data);
        } else {
          TimeUtil.sleep(20);
        }
      } while (System.currentTimeMillis() - lastUpdateTime < updateInterval);
      return batch;
    }
  }

  public record BidsAsksDataCombinedWithBinance(List<MarginBigDecimal[]> newBidsWithBinance, List<MarginBigDecimal[]> newAsksWithBinance, List<MarginBigDecimal[]> changedBidsWithBinance, List<MarginBigDecimal[]> changedAsksWithBinance) {}

  public OrderBookData getOrderBookDataBySymbol(String symbol) {
    return this.data.get(symbol);
  }
}



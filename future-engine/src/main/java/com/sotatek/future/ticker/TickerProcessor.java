package com.sotatek.future.ticker;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Ticker;
import com.sotatek.future.entity.TickerPoint;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.model.BinanceTradeDataResponse;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.util.TimeUtil;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URISyntaxException;
import java.net.URL;
import java.util.*;

import com.sotatek.future.websocket.BinanceWebSocketClientForGetTrades;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ArrayUtils;

@Slf4j
public class TickerProcessor {
  private final String symbol;
  private final Map<Long, TickerPoint> points = new LinkedHashMap<>();
  private TickerPoint lastPoint;
  private Trade lastTrade;
  private MarginBigDecimal lastPriceChange;
  private Instrument instrument;
  private Date lastUpdateAt;
  private TreeSet<Trade> trades = new TreeSet<>(Comparator.comparing(trade -> trade.getId()));
  private List<Long> lastBNBTradeIds = new ArrayList<>();
  BinanceWebSocketClientForGetTrades binanceWebSocketClientForGetTrades = null;

  public TickerProcessor(String symbol) {
    this.symbol = symbol;
    long time24hAgo = TickerProcessor.getTime24Ago();
    lastPoint =
            new TickerPoint(
                    MarginBigDecimal.ZERO, MarginBigDecimal.ZERO, MarginBigDecimal.ZERO, time24hAgo);
    points.put(time24hAgo, this.lastPoint);
    lastPriceChange = MarginBigDecimal.ZERO;
    try {
      String socketUrl = symbol.contains("USDM")
              ? "wss://dstream.binance.com/ws/" +  symbol.replace("USDM", "USD_PERP").toLowerCase() + "@aggTrade"
              : "wss://fstream.binance.com/ws/" + symbol.toLowerCase() + "@aggTrade";
      binanceWebSocketClientForGetTrades = new BinanceWebSocketClientForGetTrades(new URI( socketUrl), symbol);
    } catch (URISyntaxException e) {
      e.printStackTrace();
    }
  }

  public void setInstrument(Instrument instrument) {
    log.atDebug()
            .addKeyValue("multiplier", instrument.getMultiplier())
            .log("loaded instrument for symbol {}", instrument.getSymbol());
    this.instrument = instrument;
  }

  public void setInstrumentExtra(InstrumentExtraInformation instrumentExtraInformation) {
    log.atDebug()
            .addKeyValue("symbol", instrumentExtraInformation.getSymbol())
            .addKeyValue("indexPrice", instrumentExtraInformation.getIndexPrice())
            .log("updated instrument extra");
    if (this.lastPoint.price.eq(MarginBigDecimal.ZERO)) {
      this.lastPoint.price = instrumentExtraInformation.getIndexPrice();
    } else {
      log.atWarn()
              .addKeyValue("lastPoint.price", this.lastPoint.price)
              .log("Not update lastPrice when lastPoint.price != 0");
    }
  }

  private MarginBigDecimal getMultiplier() {
    MarginBigDecimal multiplier = null;
    if (instrument != null) {
      multiplier = instrument.getMultiplier();
      if (multiplier != null) {
        return multiplier;
      }
    }
    log.warn("instrument for symbol {} is not config. Using fallback value '1'", symbol);
    return MarginBigDecimal.ONE;
  }

  public static long getTime24Ago() {
    return TimeUtil.currentTimeSeconds() - 86400 - 1;
  }

  public Ticker getTicker() {
    TickerPoint firstPoint = points.get(TickerProcessor.getTime24Ago());
    Ticker ticker = new Ticker();
    ticker.symbol(this.symbol);
    if (firstPoint.price.eq(MarginBigDecimal.ZERO)) {
      ticker.priceChange(MarginBigDecimal.ZERO);
      ticker.priceChangePercent(MarginBigDecimal.ZERO);
    } else {
      ticker.priceChange(lastPoint.price.subtract(firstPoint.price));
      ticker.priceChangePercent(ticker.priceChange().multiply(100).divide(firstPoint.price));
    }
//    ticker.lastPrice(lastPoint.price);
    BinanceTradeDataResponse[] binanceTrades = {};
    if (binanceWebSocketClientForGetTrades.isConnected()) {
      try {
        binanceTrades = binanceWebSocketClientForGetTrades.getTrades().toArray(new BinanceTradeDataResponse[0]);
      } catch (Exception e) {
        e.printStackTrace();
      }
    } else {
      binanceTrades = this.getLastPriceFromBinance(symbol);
    }

    BinanceTradeDataResponse binanceLastTrade = ArrayUtils.isNotEmpty(binanceTrades)? binanceTrades[binanceTrades.length - 1]: null;
    ticker.lastPrice(binanceLastTrade != null ? MarginBigDecimal.valueOf(binanceLastTrade.getPrice()) : lastPoint.price);

    List<MarginBigDecimal> pointsPrice =
            points.values().stream()
                    .filter(p -> p.time > firstPoint.time)
                    .map(p -> p.allPrices)
                    .flatMap(List::stream)
                    .toList();
    ticker.highPrice(Collections.max(pointsPrice));
    ticker.lowPrice(ticker.highPrice());
    pointsPrice.forEach(
            p -> {
              if (!MarginBigDecimal.ZERO.eq(p) && p.lt(ticker.lowPrice())) {
                ticker.lowPrice(p);
              }
            });
    ticker.volume(lastPoint.volume.subtract(firstPoint.volume));
    ticker.quoteVolume(lastPoint.quoteVolume.subtract(firstPoint.quoteVolume));
    ticker.lastPriceChange(lastPriceChange);

    if (binanceLastTrade != null) {
      ticker.lastPriceChange(binanceLastTrade.isBuyerMaker()? MarginBigDecimal.valueOf(1): MarginBigDecimal.valueOf(-1));
    }

    // add last trade data from binance to list trades
    List<Trade> listTrades = new ArrayList<>(trades.stream().toList());
    if (ArrayUtils.isNotEmpty(binanceTrades)) {
      for (BinanceTradeDataResponse binanceTrade: binanceTrades) {
        if (binanceLastTrade != null && lastBNBTradeIds.stream().filter(id -> id == binanceTrade.getId()).findFirst().isEmpty()) {
          // trade from binance
          Trade bnbTrade = new Trade();
          bnbTrade.setSymbol(symbol);
          bnbTrade.setBuyAccountId(2L);
          bnbTrade.setSellAccountId(2L);
          bnbTrade.setBuyUserId(1L);
          bnbTrade.setSellUserId(1L);
          bnbTrade.setBuyOrderId(1000000712L);
          bnbTrade.setSellOrderId(1000000711L);
          bnbTrade.setBuyerIsTaker(!binanceTrade.isBuyerMaker());
          bnbTrade.setQuantity(MarginBigDecimal.valueOf(binanceTrade.getQty()));
          bnbTrade.setPrice(MarginBigDecimal.valueOf(binanceTrade.getPrice()));
          bnbTrade.setBuyFee(new MarginBigDecimal("0.0534255"));
          bnbTrade.setSellFee(new MarginBigDecimal("0.0178085"));
          bnbTrade.setBuyFeeRate(new MarginBigDecimal("0.00075"));
          bnbTrade.setSellFeeRate(new MarginBigDecimal("0.00025"));
          bnbTrade.setBuyEmail("bot1@gmail.com");
          bnbTrade.setSellEmail("bot1@gmail.com");
          bnbTrade.setId(391L);
          bnbTrade.setContractType(ContractType.USD_M);
          bnbTrade.setOperationId("13974273500000000");
          bnbTrade.setCreatedAt(new Date(binanceTrade.getTime()));
          bnbTrade.setUpdatedAt(new Date(binanceTrade.getTime()));
          listTrades.add(bnbTrade);
        }
      }

      // Sort list trades by create at
      Arrays.sort(listTrades.toArray(new Trade[0]), Comparator.comparing(trade -> trade.getCreatedAt().getTime()));

      lastBNBTradeIds = Arrays.stream(binanceTrades).filter(Objects::nonNull).map(BinanceTradeDataResponse::getId).toList();
    }
    ticker.trades(listTrades);

//    ticker.trades(trades.stream().toList());
    // clear trades for next time to ticker publish
    trades.clear();
    Date date = new Date();
    ticker.updateAt(date);
    ticker.lastUpdateAt(lastUpdateAt);
    lastUpdateAt = date;
    return ticker;
  }

  public void processTrade(Trade trade) {
    //    log.debug("Process trade {}", trade);
    // add trade to list
    trades.add(trade);
    long tradeTime = trade.getCreatedAt().getTime() / 1000;
    if (lastTrade != null) {
      lastPriceChange = trade.getPrice().subtract(lastTrade.getPrice());
    } else {
      // for initial instrument, last change is calc by lastPrice==indexPrice and firstTrade
      lastPriceChange = trade.getPrice().subtract(lastPoint.price);
    }
    if (lastPoint.time < tradeTime) {
      addMissingPoints(trade);
    }
    if (lastPoint.time == tradeTime) {
      // add other price
      TickerPoint point = points.get(tradeTime);
      point.allPrices.add(trade.getPrice());
      addTradeToLastPoint(trade);
    }
    if (lastPoint.time > tradeTime) {
      if (lastPoint.volume.eq(MarginBigDecimal.ZERO)) { // the last point is mock data
        points.remove(lastPoint.time);
        lastPoint =
                new TickerPoint(
                        trade.getPrice(),
                        trade.getQuantity(),
                        trade.getPrice().multiply(trade.getQuantity()),
                        tradeTime);
        log.debug("overwrite point old {} new {}", points.get(tradeTime), lastPoint);
        points.put(tradeTime, lastPoint);
      } else {
        log.debug("Shouldn't go here {}, {}", trade, lastPoint);
      }
    }
    lastTrade = trade;
  }

  public void clean() {
    try {
      addEmptyPointIfNeeded();
    } catch (Exception e) {
      log.error("addEmptyPointIfNeeded before publish get exception", e);
    }
    try {
      long time = TickerProcessor.getTime24Ago();
      long oldestTime = Collections.min(points.keySet());
      for (long i = oldestTime; i < time; i++) {
        points.remove(i);
      }
    } catch (Exception e) {
      log.error("clean before publish get exception", e);
    }
  }

  private void addEmptyPointIfNeeded() {
    long time = TickerProcessor.getTime24Ago() + 3600; // 23h ago
    TickerPoint point = null;
    if (lastPoint.time < time) {
      for (long i = lastPoint.time + 1; i <= time; i++) {
        point = new TickerPoint(lastPoint.price, lastPoint.volume, lastPoint.quoteVolume, i);
        points.put(i, point);
      }
      lastPoint = point;
    }
  }

  private void addMissingPoints(Trade trade) {
    long tradeTime = trade.getCreatedAt().getTime() / 1000;
    TickerPoint point = null;
    for (long i = lastPoint.time + 1; i <= tradeTime; i++) {
      point = new TickerPoint(lastPoint.price, lastPoint.volume, lastPoint.quoteVolume, i);
      points.put(i, point);
    }
    lastPoint = point;
  }

  private void addTradeToLastPoint(Trade trade) {
    this.addTradeToPoint(trade, lastPoint);
  }

  private void addTradeToPoint(Trade trade, TickerPoint point) {
    // floor to max scale to get the highest precision
    point.price = trade.getPrice();
    if (trade.isCoinM()) {
      point.volume =
              getMultiplier()
                      .multiplyThenDivide(trade.getQuantity(), trade.getPrice())
                      .add(point.volume);
      point.quoteVolume = point.quoteVolume.add(trade.getQuantity());
    } else {
      point.volume = point.volume.add(trade.getQuantity());
      point.quoteVolume = trade.getPrice().multiply(trade.getQuantity()).add(point.quoteVolume);
    }
    //    log.debug("Added trade to last point: point {}, trade {}", lastPoint, trade);
  }


  public BinanceTradeDataResponse[] getLastPriceFromBinance(String symbol) {
    try {
      URL obj = new URL("https://fapi.binance.com/fapi/v1/trades?symbol=" + symbol + "&limit=10");
      HttpURLConnection con = (HttpURLConnection) obj.openConnection();
      con.setRequestMethod("GET");
      int responseCode = con.getResponseCode();
//      System.out.println("GET Response Code :: " + responseCode);
      if (responseCode == HttpURLConnection.HTTP_OK) { // success
        BufferedReader in = new BufferedReader(new InputStreamReader(con.getInputStream()));
        String inputLine;
        StringBuffer response = new StringBuffer();
        while ((inputLine = in.readLine()) != null) {
          response.append(inputLine);
        }
        in.close();

        ObjectMapper objectMapper = new ObjectMapper();
        BinanceTradeDataResponse[] result = objectMapper.readValue(response.toString(), BinanceTradeDataResponse[].class);
        if (result == null || result.length == 0) {
//          log.error("Cannot get Binance last price of symbol " + symbol);
          return null;
        }
        Thread.sleep(500);
        return result;
      } else {
//        log.error("Cannot get Binance last price of symbol " + symbol);
        return null;
      }
    } catch (Exception e) {
      e.printStackTrace();
      return null;
    }
  }
}
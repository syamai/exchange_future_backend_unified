package com.sotatek.future.entity;

import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.List;

public class TickerPoint {

  public MarginBigDecimal price;
  public MarginBigDecimal volume;
  public MarginBigDecimal quoteVolume;
  // store price of all trade that create in the same second
  public List<MarginBigDecimal> allPrices;
  public long time;

  public TickerPoint(
      MarginBigDecimal price, MarginBigDecimal volume, MarginBigDecimal quoteVolume, long time) {
    this.volume = volume;
    this.quoteVolume = quoteVolume;
    this.price = price;
    this.time = time;
    this.allPrices = new ArrayList<>();
    this.allPrices.add(price);
  }

  @Override
  public String toString() {
    return "TickerPoint{"
        + "price="
        + price
        + ", volume="
        + volume
        + ", quoteVolume="
        + quoteVolume
        + ", time="
        + time
        + '}';
  }
}

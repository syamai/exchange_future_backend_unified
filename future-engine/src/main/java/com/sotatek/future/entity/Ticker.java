package com.sotatek.future.entity;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.sotatek.future.model.BinanceTradeDataResponse;
import com.sotatek.future.util.MarginBigDecimal;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.Date;
import java.util.List;
import lombok.Data;
import lombok.NoArgsConstructor;
import lombok.ToString;
import lombok.experimental.Accessors;
import lombok.extern.slf4j.Slf4j;

@Slf4j
@ToString
@Data
@Accessors(fluent = true)
@NoArgsConstructor
public class Ticker {
  private String symbol;
  private MarginBigDecimal priceChange;
  private MarginBigDecimal priceChangePercent;
  private MarginBigDecimal lastPrice;
  private MarginBigDecimal lastPriceChange;
  private MarginBigDecimal highPrice;
  private MarginBigDecimal lowPrice;
  private MarginBigDecimal volume;
  private MarginBigDecimal quoteVolume;
  private List<Trade> trades;
  private Date updateAt;
  private Date lastUpdateAt;
}

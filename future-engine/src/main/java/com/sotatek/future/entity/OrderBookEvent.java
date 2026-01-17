package com.sotatek.future.entity;

import lombok.AllArgsConstructor;
import lombok.Getter;

@AllArgsConstructor
@Getter
public class OrderBookEvent {
  private String symbol;
  private OrderBook orderbook;
  private OrderBook changes;
}

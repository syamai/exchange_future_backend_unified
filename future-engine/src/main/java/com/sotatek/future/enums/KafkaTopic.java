package com.sotatek.future.enums;

public enum KafkaTopic {
  MATCHING_ENGINE_INPUT("matching_engine_input"),
  MATCHING_ENGINE_PRElOAD("matching_engine_preload"),
  MATCHING_ENGINE_OUTPUT("matching_engine_output"),
  ORDERBOOK_OUTPUT("orderbook_output"),
  TICKER_ENGINE_PRELOAD("ticker_engine_preload"),
  TICKER_ENGINE_OUTPUT("ticker_engine_output");
//  MATCHING_ENGINE_INPUT("test_matching_engine_input"),
//  MATCHING_ENGINE_PRElOAD("test_matching_engine_preload"),
//  MATCHING_ENGINE_OUTPUT("test_matching_engine_output"),
//  ORDERBOOK_OUTPUT("test_orderbook_output"),
//  TICKER_ENGINE_PRELOAD("test_ticker_engine_preload"),
//  TICKER_ENGINE_OUTPUT("test_ticker_engine_output");

  private String value;

  KafkaTopic(String value) {
    this.value = value;
  }

  public String getValue() {
    return value;
  }
}

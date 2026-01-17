package com.sotatek.future.enums;

public enum KafkaGroup {
  MATCHING_ENGINE("matching_engine"),
  TICKER_ENGINE("ticker_engine");
  private String value;

  KafkaGroup(String value) {
    this.value = value;
  }

  public String getValue() {
    return value;
  }
}

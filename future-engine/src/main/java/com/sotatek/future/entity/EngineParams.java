package com.sotatek.future.entity;

import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;
import lombok.ToString;

import java.util.HashSet;
import java.util.Set;

@Getter
@Setter
@ToString
@NoArgsConstructor
public class EngineParams {
  private long lastOrderId = -1;
  private long lastPositionId = -1;
  private long lastTradeId = -1;
  private long lastMarginHistoryId = -1;
  private long lastPositionHistoryId = -1;
  private long lastFundingHistoryId = -1;
  private Set<Long> liquidationOrderIds = new HashSet<>();
}

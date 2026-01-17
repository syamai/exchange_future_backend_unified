package com.sotatek.future.util;

import org.jetbrains.annotations.VisibleForTesting;

public class OperationIdGenerator {
  private static final long START_TIME = 1577836800; // 2020-01-01 00:00:00
  private static final long MAX_OPERATION_PER_SECOND = 100000000;
  private long internalOperationId;
  private long lastSecond;

  public synchronized long generateOperationId() {
    long currentTime = getCurrentTime();
    if (currentTime != lastSecond) {
      internalOperationId = 0;
    }
    long newOperationId =
        (currentTime - START_TIME) * MAX_OPERATION_PER_SECOND + internalOperationId;
    lastSecond = currentTime;
    internalOperationId++;
    return newOperationId;
  }

  @VisibleForTesting
  long getCurrentTime() {
    return TimeUtil.currentTimeSeconds();
  }
}

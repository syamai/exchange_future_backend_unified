package com.sotatek.future.exception;

import com.sotatek.future.util.MarginBigDecimal;

public class ExceedRiskLimitException extends MarginException {

  public ExceedRiskLimitException(MarginBigDecimal riskValue) {
    super("Exceed risk limit: " + riskValue.toString());
  }
}

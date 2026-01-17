package com.sotatek.future.value;

import com.sotatek.future.util.Interval;
import com.sotatek.future.util.MarginBigDecimal;

public record LeverageMarginRule(
    MarginBigDecimal minPosition,
    MarginBigDecimal maxPosition,
    MarginBigDecimal maxLeverage,
    MarginBigDecimal maintenanceMarginRate,
    MarginBigDecimal maintenanceAmount)
    implements Interval<MarginBigDecimal> {

  @Override
  public MarginBigDecimal low() {
    return minPosition;
  }

  @Override
  public MarginBigDecimal high() {
    return maxPosition;
  }
}

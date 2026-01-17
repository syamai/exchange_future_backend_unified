package com.sotatek.future.entity;

import com.sotatek.future.util.MarginBigDecimal;
import java.util.StringJoiner;
import lombok.*;
import lombok.extern.slf4j.Slf4j;

@Getter
@Setter
@Builder
@AllArgsConstructor
@NoArgsConstructor
@Slf4j
@ToString
public class LeverageMargin extends BaseEntity {

  private long tier;
  private MarginBigDecimal min;
  private MarginBigDecimal max;
  private long maxLeverage;
  private MarginBigDecimal maintenanceMarginRate;
  private MarginBigDecimal maintenanceAmount;
  private String symbol;

  private LeverageMargin(LeverageMargin leverageMargin) {
    super(leverageMargin);
    this.tier = leverageMargin.tier;
    this.min = leverageMargin.min;
    this.max = leverageMargin.max;
    this.maxLeverage = leverageMargin.maxLeverage;
    this.maintenanceMarginRate = leverageMargin.maintenanceMarginRate;
    this.maintenanceAmount = leverageMargin.maintenanceAmount;
    this.createdAt = leverageMargin.createdAt;
    this.updatedAt = leverageMargin.updatedAt;
    this.symbol = leverageMargin.symbol;
  }

  @Override
  public Object getKey() {
    return id;
  }

  @Override
  public BaseEntity clone() {
    return new LeverageMargin(this);
  }

  @Override
  public String toString() {
    return new StringJoiner(", ", LeverageMargin.class.getSimpleName() + "[", "]")
        .add("tier=" + tier)
        .add("min=" + min)
        .add("max=" + max)
        .add("maxLeverage=" + maxLeverage)
        .add("maintenanceMarginRate=" + maintenanceMarginRate)
        .add("maintenanceAmount=" + maintenanceAmount)
        .add("symbol='" + symbol + "'")
        .add("id=" + id)
        .add("operationId=" + operationId)
        .add("createdAt=" + createdAt)
        .add("updatedAt=" + updatedAt)
        .toString();
  }
}

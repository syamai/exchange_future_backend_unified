package com.sotatek.future.entity;

import com.sotatek.future.util.MarginBigDecimal;
import java.util.Date;
import lombok.AllArgsConstructor;
import lombok.Getter;
import lombok.Setter;

@Getter
@Setter
@AllArgsConstructor
public class FundingParams {
  private String symbol;
  private MarginBigDecimal fundingRate;
  // this is mark price
  private MarginBigDecimal oraclePrice;
  private Date time;

  @Override
  public String toString() {
    return "FundingParams{"
        + "symbol='"
        + symbol
        + '\''
        + ", fundingRate="
        + fundingRate
        + ", time="
        + time
        + '}';
  }
}

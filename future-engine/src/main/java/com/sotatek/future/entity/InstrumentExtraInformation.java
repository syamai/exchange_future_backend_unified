package com.sotatek.future.entity;

import com.sotatek.future.util.MarginBigDecimal;
import java.io.Serializable;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@NoArgsConstructor
@ToString
public class InstrumentExtraInformation implements Serializable {

  private String symbol;
  private MarginBigDecimal indexPrice;
  private MarginBigDecimal lastPrice;
  // this is mark price
  private MarginBigDecimal oraclePrice;
  private MarginBigDecimal fundingRate;

  private InstrumentExtraInformation(InstrumentExtraInformation extraInformation) {
    this.symbol = extraInformation.getSymbol();
    this.indexPrice = extraInformation.getIndexPrice();
    this.fundingRate = extraInformation.getFundingRate();
    this.oraclePrice = extraInformation.getOraclePrice();
    this.lastPrice = extraInformation.getLastPrice();
  }

  public InstrumentExtraInformation deepCopy() {
    return new InstrumentExtraInformation(this);
  }

  public InstrumentExtraInformation(
      String symbol, MarginBigDecimal oraclePrice, MarginBigDecimal fundingRate) {
    this.symbol = symbol;
    this.oraclePrice = oraclePrice;
    this.fundingRate = fundingRate;
  }

  @Override
  public String toString() {
    return "InstrumentExtraInformation{"
        + "symbol='"
        + symbol
        + '\''
        + ", indexPrice="
        + indexPrice
        + ", lastPrice="
        + lastPrice
        + ", oraclePrice="
        + oraclePrice
        + ", fundingRate="
        + fundingRate
        + '}';
  }
}

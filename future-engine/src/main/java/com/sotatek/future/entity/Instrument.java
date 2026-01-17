package com.sotatek.future.entity;

import com.sotatek.future.enums.ContractType;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.util.json.Exclude;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@ToString
public class Instrument extends BaseEntity {
  private static int MAX_QUANTITY_PRECISION = 4;
  @Exclude int quantityPrecision;
  private String symbol;
  private String rootSymbol;
  private String state;
  private int type;
  private String expiry;
  private String base_underlying;
  private String quote_currency;
  private String underlying_symbol;
  private String settle_currency;
  private MarginBigDecimal initMargin;
  private MarginBigDecimal maintainMargin;
  private boolean deleverageable;
  private MarginBigDecimal makerFee;
  private MarginBigDecimal takerFee;
  private MarginBigDecimal settlementFee;
  private String has_liquidity;
  private String referenceIndex;
  private String fundingBaseIndex;
  private String fundingQuoteIndex;
  private String fundingPremiumIndex;
  private long fundingInterval;
  private MarginBigDecimal tickSize;
  private MarginBigDecimal contractSize;
  private MarginBigDecimal lotSize;
  private MarginBigDecimal maxPrice;
  private MarginBigDecimal maxOrderQty;
  private MarginBigDecimal multiplier;
  private MarginBigDecimal option_strike_price;
  private MarginBigDecimal option_ko_price;
  private MarginBigDecimal risk_step;
  private String settlement_index;
  private String timestamps;
  // to determine contract is usd-M or coin-M
  private ContractType contractType;
  @Exclude private int pricePrecision = 4;
  @Exclude private MarginBigDecimal minQuantity;

  @Override
  public Object getKey() {
    return this.symbol;
  }

  public int getPricePrecision() {
    return pricePrecision;
  }

  public MarginBigDecimal getMinQuantity() {
    return minQuantity;
  }

  public int getQuantityPrecision() {
    return quantityPrecision;
  }

  public void updatePrecisions() {
    this.pricePrecision =
        (int) Math.ceil(Math.log10(MarginBigDecimal.ONE.divide(this.tickSize).longValue()));
    this.minQuantity = this.contractSize.multiply(this.lotSize);
    this.quantityPrecision =
        (int) Math.ceil(Math.log10(MarginBigDecimal.ONE.divide(this.minQuantity).longValue()));
  }
}

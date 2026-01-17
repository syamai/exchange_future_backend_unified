package com.sotatek.future.entity;

import com.sotatek.future.util.MarginBigDecimal;
import lombok.Data;

@Data
public class TradingRule extends BaseEntity {

  // Example: "symbol": "BTCUSDT",
  private String symbol;

  // Example: "minTradeAmount": null,
  private MarginBigDecimal minTradeAmount;

  // Example: "minPrice": "11.00000000",
  private MarginBigDecimal minPrice;

  // Example: "limitOrderPrice": "1.00000000"
  private MarginBigDecimal limitOrderPrice;

  // Example: "maxMarketOrder": null,
  private MarginBigDecimal maxMarketOrder;

  // Example: "limitOrderAmount": null,
  private MarginBigDecimal limitOrderAmount;

  // Example: "numberOpenOrders": null,
  private MarginBigDecimal numberOpenOrders;

  // Example: "priceProtectionThreshold": null,
  private MarginBigDecimal priceProtectionThreshold;

  // Example: "liqClearanceFee": "5.00000000",
  private MarginBigDecimal liqClearanceFee;

  // Example: "minNotional": "23.00000000",
  private MarginBigDecimal minNotional;

  // Example: "marketOrderPrice": null,
  private MarginBigDecimal marketOrderPrice;

  // Example: "isReduceOnly": false,
  private Boolean isReduceOnly;

  // Example:  "positionsNotional": null,
  private MarginBigDecimal positionsNotional;

  // Example: "ratioOfPostion": null,
  private MarginBigDecimal ratioOfPostion;

  // Example: "liqMarkPrice": null,
  private MarginBigDecimal liqMarkPrice;

  // Example: "maxLeverage": 50,
  private MarginBigDecimal maxLeverage;

  // Example: "minOrderPrice": "20.00000000",
  private MarginBigDecimal minOrderPrice;

  // Example: "maxOrderPrice": "450000.00000000"
  private MarginBigDecimal maxOrderPrice;

  // Example: "floorRatio": "2.00000000",
  private MarginBigDecimal floorRatio;

  // Example: "maxNotinal": "23.00000000",
  private MarginBigDecimal maxNotinal;

  @Override
  public Object getKey() {
    return symbol;
  }
}

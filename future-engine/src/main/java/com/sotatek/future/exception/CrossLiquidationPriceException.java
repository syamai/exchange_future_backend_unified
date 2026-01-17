package com.sotatek.future.exception;

import com.sotatek.future.util.MarginBigDecimal;

public class CrossLiquidationPriceException extends MarginException {

  public static final String ABOVE = "above";
  public static final String BELOW = "below";

  public CrossLiquidationPriceException(
      MarginBigDecimal price, String comparation, MarginBigDecimal limitPrice) {
    super(
        String.format(
            "Your order price of %1$s is %2$s your current liquidation price of %3$s",
            price, comparation, limitPrice));
  }
}

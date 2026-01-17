package com.sotatek.future.exception;

import com.sotatek.future.util.MarginBigDecimal;

public class CrossBankruptPriceException extends MarginException {

  public static final String ABOVE = "above";
  public static final String BELOW = "below";

  public CrossBankruptPriceException(
      MarginBigDecimal price, String comparation, MarginBigDecimal limitPrice) {
    super(
        String.format(
            "Your order price of %1$s is %2$s your current bankrupt price of %3$s",
            price, comparation, limitPrice));
  }
}

package com.sotatek.future.exception;

import com.sotatek.future.entity.Order;

public class LockPriceException extends MarginException {

  public LockPriceException(Order order) {
    super("Can not calculate lock price for order " + order.getId());
  }
}

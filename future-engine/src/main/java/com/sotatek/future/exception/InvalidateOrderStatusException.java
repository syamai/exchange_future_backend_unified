package com.sotatek.future.exception;

import com.sotatek.future.enums.OrderStatus;

public class InvalidateOrderStatusException extends MarginException {

  public InvalidateOrderStatusException(OrderStatus expectStatus, OrderStatus actualStatus) {
    super("Expect " + expectStatus + ", actual " + actualStatus);
  }

  public InvalidateOrderStatusException(String message) {
    super(message);
  }
}

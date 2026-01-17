package com.sotatek.future.exception;

public class OrderStateInvalidException extends RuntimeException {
  public OrderStateInvalidException() {
    super();
  }

  public OrderStateInvalidException(String message) {
    super(message);
  }

  public OrderStateInvalidException(String message, Throwable cause) {
    super(message, cause);
  }
}

package com.sotatek.future.exception;

public class InsufficientQuantityException extends MarginException {
  public InsufficientQuantityException(long orderId) {
    super("Insufficient quantity for order " + orderId);
  }
}

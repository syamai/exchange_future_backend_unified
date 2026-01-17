package com.sotatek.future.exception;

public class PostOnlyOrderException extends MarginException {

  public PostOnlyOrderException(long orderId) {
    super("The post only order " + orderId + " can match with other order immediately");
  }
}

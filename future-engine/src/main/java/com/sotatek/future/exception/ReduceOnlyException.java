package com.sotatek.future.exception;

public class ReduceOnlyException extends MarginException {

  public ReduceOnlyException(Long orderId) {
    super("Reduce only exception " + orderId);
  }
}

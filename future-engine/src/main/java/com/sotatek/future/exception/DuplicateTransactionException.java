package com.sotatek.future.exception;

public class DuplicateTransactionException extends MarginException {

  public DuplicateTransactionException(String txHash) {
    super("Duplicate transaction with hash " + txHash);
  }
}

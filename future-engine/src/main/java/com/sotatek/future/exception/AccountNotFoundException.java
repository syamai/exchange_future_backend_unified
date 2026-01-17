package com.sotatek.future.exception;

public class AccountNotFoundException extends MarginException {

  public AccountNotFoundException(Object accountId) {
    super("Account not found: " + accountId);
  }
}

package com.sotatek.future.exception;

import com.sotatek.future.entity.Account;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.Getter;

public class InsufficientBalanceException extends MarginException {

  @Getter private final Account account;

  @Getter private final MarginBigDecimal available;

  public InsufficientBalanceException(Account account, MarginBigDecimal usdtAvailable) {
    this.account = account;
    this.available = usdtAvailable;
  }
}

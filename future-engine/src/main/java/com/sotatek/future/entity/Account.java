package com.sotatek.future.entity;

import com.sotatek.future.enums.Asset;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.StringJoiner;
import lombok.AccessLevel;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Getter;
import lombok.Setter;
import lombok.extern.slf4j.Slf4j;

@Slf4j
@Getter
@Setter
@Builder
@AllArgsConstructor(access = AccessLevel.PRIVATE)
public class Account extends BaseEntity {
  private Long userId;
  private Asset asset;
  private MarginBigDecimal balance;
  private String userEmail;

  public Account() {
    this.balance = MarginBigDecimal.ZERO;
    this.asset = Asset.USDT;
  }

  public Account(Long id, MarginBigDecimal balance) {
    this();
    this.id = id;
    this.balance = balance;
    this.asset = Asset.USDT;
  }

  public Account(Account account) {
    super(account);
    this.userId = account.getUserId();
    this.asset = account.getAsset();
    this.balance = account.getBalance();
    this.userEmail = account.getUserEmail();
  }

  public Account addAmountToBalance(MarginBigDecimal amount) {
    setBalance(this.balance.add(amount));
    return this;
  }

  public Account subAmountToBalance(MarginBigDecimal amount) {
    setBalance(this.balance.subtract(amount));
    return this;
  }

  public void setBalance(MarginBigDecimal balance) {
    log.atDebug()
        .addKeyValue("accId", id)
        .addKeyValue("userId", userId)
        .addKeyValue("asset", asset)
        .log("account balance change [old_balance={}, new_balance={}]", this.balance, balance);
    this.balance = balance;
  }

  @Override
  public Long getKey() {
    return this.id;
  }

  @Override
  public Account deepCopy() {
    return new Account(this);
  }

  @Override
  public AccountValue getValue() {
    return new AccountValue(balance);
  }

  @Override
  public String toString() {
    return new StringJoiner(", ", Account.class.getSimpleName() + "[", "]")
        .add("id=" + id)
        .add("userId=" + userId)
        .add("asset=" + asset)
        .add("balance=" + balance)
        .add("userEmail=" + userEmail)
        .add("operationId=" + operationId)
        .add("createdAt=" + createdAt)
        .add("updatedAt=" + updatedAt)
        .toString();
  }

  /** Use for value-based comparison between account */
  record AccountValue(MarginBigDecimal balance) {}
}

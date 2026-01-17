package com.sotatek.future.entity;

import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.enums.TransactionStatus;
import com.sotatek.future.enums.TransactionType;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.Data;
import lombok.NoArgsConstructor;

@Data
@NoArgsConstructor
public class Transaction extends BaseEntity {
  private Long userId;
  private Long accountId;
  private MarginBigDecimal amount;
  private TransactionStatus status;
  private TransactionType type;
  private String symbol;
  private Asset asset;
  private ContractType contractType;

  public Transaction(Transaction transaction) {
    super(transaction);
    this.userId = transaction.getUserId();
    this.accountId = transaction.getAccountId();
    this.amount = transaction.getAmount();
    this.symbol = transaction.getSymbol();
    this.asset = transaction.getAsset();
    this.status = transaction.getStatus();
    this.type = transaction.getType();
    this.contractType = transaction.getContractType();
  }

  @Override
  public Object getKey() {
    return id;
  }

  @Override
  public BaseEntity deepCopy() {
    return new Transaction(this);
  }

  @Override
  public TransactionValue getValue() {
    return new TransactionValue(
        userId, accountId, amount, status, type, symbol, asset, contractType);
  }

  @Override
  public String toString() {
    return "Transaction{"
        + "userId="
        + userId
        + "accountId="
        + accountId
        + ", amount="
        + amount
        + ", status="
        + status
        + ", type="
        + type
        + ", contractType="
        + contractType
        + ", symbol="
        + symbol
        + ", asset="
        + asset
        + ", id="
        + id
        + '}';
  }

  /** Use for value-based comparison between account */
  record TransactionValue(
      Long userId,
      Long accountId,
      MarginBigDecimal amount,
      TransactionStatus status,
      TransactionType type,
      String symbol,
      Asset asset,
      ContractType contractType) {}
}

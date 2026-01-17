package com.sotatek.future.service;

import com.sotatek.future.entity.Transaction;
import com.sotatek.future.exception.DuplicateTransactionException;

public class TransactionService extends BaseService<Transaction> {

  private static final TransactionService instance = new TransactionService();

  private TransactionService() {
    super(true);
  }

  public static TransactionService getInstance() {
    return instance;
  }

  public void initialize() {
    // Nothing to do
  }

  @Override
  public Transaction insert(Transaction transaction) {
    Transaction oldTransaction = get(transaction.getKey());
    if (oldTransaction != null) {
      throw new DuplicateTransactionException(transaction.getKey().toString());
    }
    return super.update(transaction);
  }

  public void updateOldTransaction(Transaction transaction) {
    super.update(transaction);
  }
}

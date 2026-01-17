package com.sotatek.future.usecase;

import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.Transaction;
import com.sotatek.future.enums.TransactionStatus;
import com.sotatek.future.exception.DuplicateTransactionException;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.TransactionService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;

import static com.sotatek.future.service.AccountService.*;

@RequiredArgsConstructor
@Slf4j
public class AccountUseCase {

  private final AccountService accountService;

  private final TransactionService transactionService;

  private final MatchingEngine matchingEngine;

  /**
   * Deposit balance
   *
   * @param command
   */
  public void deposit(Command command) {
    log.debug("Start deposit {}", command);
    Transaction transaction = command.getTransaction();
    try {
      if (transaction.getStatus() == TransactionStatus.PENDING) {
        accountService.deposit(transaction);
      } else {
        transactionService.updateOldTransaction(transaction);
        transactionService.commit();
      }
      matchingEngine.commit();
    } catch (DuplicateTransactionException exception) {
      log.error("Ignore duplicated transaction: " + transaction, exception);
      matchingEngine.rollback();
    }
  }

  /**
   * Withdraw balance
   *
   * @param command
   */
  public void withdraw(Command command) {
    log.debug("Start withdraw {}", command);
    Transaction transaction = command.getTransaction();
    accountService.withdraw(transaction);
    matchingEngine.commit();
  }

  /**
   * Create new account
   *
   * @param command
   */
  public void create(Command command) {
    Account data = command.getAccount();
    // if account of insurance fund then push to Map
    if (INSURANCE_USER_ID.equals(data.getUserId())) {
      INSURANCE_ACCOUNT_IDS.put(data.getAsset(), data.getId());
    }
    accountService.update(data);
    accountService.commit();
  }

  /**
   * Load bot account
   *
   * @param command
   */
  public void loadBotAccount(Command command) {
    Account data = command.getAccount();
    BOT_ACCOUNT_IDS.put(data.getId(), data.getUserId());
  }
}

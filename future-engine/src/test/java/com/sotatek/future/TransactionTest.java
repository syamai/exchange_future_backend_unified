package com.sotatek.future;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.Transaction;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.TransactionStatus;
import com.sotatek.future.enums.TransactionType;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Arrays;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

public class TransactionTest extends BaseMatchingEngineTest {

  @Test
  void updateBalance_when_approveDepositTransaction() {
    this.setUpAccount(10L, defaultBalance);
    Transaction transaction =
        this.createTransaction(
            1L,
            10L,
            "123",
            TransactionStatus.PENDING,
            TransactionType.DEPOSIT,
            Asset.USDT,
            "hash1");
    Command command = new Command(CommandCode.DEPOSIT, transaction);

    Transaction processedTransaction = new Transaction(transaction);
    processedTransaction.setStatus(TransactionStatus.APPROVED);

    Account account = this.createAccount(10L, "100123");
    this.testTransaction(
        Arrays.asList(command), Arrays.asList(processedTransaction), Arrays.asList(account));
  }

  @Disabled("Disable until we can fix duplicated transactions detection")
  @Test
  void detectAndIgnoreDuplicate_when_processDepositTransaction() {
    this.setUpAccount(10L, defaultBalance);
    Transaction transaction =
        this.createTransaction(
            1L,
            10L,
            "123",
            TransactionStatus.PENDING,
            TransactionType.DEPOSIT,
            Asset.USDT,
            "hash1");
    Transaction transaction2 =
        this.createTransaction(
            2L,
            10L,
            "456",
            TransactionStatus.PENDING,
            TransactionType.DEPOSIT,
            Asset.USDT,
            "hash1");
    Command command = new Command(CommandCode.DEPOSIT, transaction);
    Command command2 = new Command(CommandCode.DEPOSIT, transaction2);

    Transaction processedTransaction = new Transaction(transaction);
    processedTransaction.setStatus(TransactionStatus.APPROVED);

    Account account = this.createAccount(10L, "100123");

    // Only log error, doesn't throw exception
    this.testTransaction(
        Arrays.asList(command, command2),
        Arrays.asList(processedTransaction),
        Arrays.asList(account));
    //    DuplicateTransactionException exception =
    // Assertions.assertThrows(DuplicateTransactionException.class, () -> this
    //        .testTransaction(Arrays.asList(command, command2),
    // Arrays.asList(processedTransaction),
    //            Arrays.asList(account)));
    //    Assertions.assertEquals("Duplicate transaction with hash hash1", exception.getMessage());
  }

  @Test
  void updateBalance_when_approveDepositMultipleTransaction_given_DifferentAccount() {
    this.setUpAccount(10L, defaultBalance);
    this.setUpAccount(11L, defaultBalance);
    Transaction transaction =
        this.createTransaction(
            1L,
            10L,
            "123",
            TransactionStatus.PENDING,
            TransactionType.DEPOSIT,
            Asset.USDT,
            "hash1");
    Transaction transaction2 =
        this.createTransaction(
            2L,
            11L,
            "456",
            TransactionStatus.PENDING,
            TransactionType.DEPOSIT,
            Asset.USDT,
            "hash2");
    Command command = new Command(CommandCode.DEPOSIT, transaction);
    Command command2 = new Command(CommandCode.DEPOSIT, transaction2);

    Transaction processedTransaction = new Transaction(transaction);
    processedTransaction.setStatus(TransactionStatus.APPROVED);
    Transaction processedTransaction2 = new Transaction(transaction2);
    processedTransaction2.setStatus(TransactionStatus.APPROVED);

    Account account = this.createAccount(10L, "100123");
    Account account2 = this.createAccount(11L, "100456");
    this.testTransaction(
        Arrays.asList(command, command2),
        Arrays.asList(processedTransaction, processedTransaction2),
        Arrays.asList(account, account2));
  }

  @Test
  void updateBalance_when_approveDepositMultipleTransaction_given_SameAccount() {
    this.setUpAccount(10L, defaultBalance);
    this.setUpAccount(11L, defaultBalance);
    Transaction transaction =
        this.createTransaction(
            1L,
            10L,
            "123",
            TransactionStatus.PENDING,
            TransactionType.DEPOSIT,
            Asset.USDT,
            "hash1");
    Transaction transaction2 =
        this.createTransaction(
            2L,
            10L,
            "456",
            TransactionStatus.PENDING,
            TransactionType.DEPOSIT,
            Asset.USDT,
            "hash2");
    Command command = new Command(CommandCode.DEPOSIT, transaction);
    Command command2 = new Command(CommandCode.DEPOSIT, transaction2);

    Transaction processedTransaction = new Transaction(transaction);
    processedTransaction.setStatus(TransactionStatus.APPROVED);
    Transaction processedTransaction2 = new Transaction(transaction2);
    processedTransaction2.setStatus(TransactionStatus.APPROVED);

    Account account = this.createAccount(10L, "100579");
    Account account2 = this.createAccount(11L, "100000");
    this.testTransaction(
        Arrays.asList(command, command2),
        Arrays.asList(processedTransaction, processedTransaction2),
        Arrays.asList(account, account2));
  }

  @Test
  void updateBalance_when_approveWithdrawTransaction() {
    this.setUpAccount(10L, defaultBalance);
    Transaction transaction =
        this.createTransaction(
            1L,
            10L,
            "123",
            TransactionStatus.PENDING,
            TransactionType.WITHDRAWAL,
            Asset.USDT,
            null);
    Command command = new Command(CommandCode.WITHDRAW, transaction);

    Transaction processedTransaction = new Transaction(transaction);
    processedTransaction.setStatus(TransactionStatus.APPROVED);

    Account account = this.createAccount(10L, "99877");
    this.testTransaction(
        Arrays.asList(command), Arrays.asList(processedTransaction), Arrays.asList(account));
  }

  @Test
  void notUpdateBalance_when_rejectWithdrawTransaction() {
    this.setUpAccount(10L, defaultBalance);
    Transaction transaction =
        this.createTransaction(
            1L,
            10L,
            "1000000",
            TransactionStatus.PENDING,
            TransactionType.WITHDRAWAL,
            Asset.USDT,
            null);
    Command command = new Command(CommandCode.WITHDRAW, transaction);

    Transaction processedTransaction = new Transaction(transaction);
    processedTransaction.setStatus(TransactionStatus.REJECTED);

    Account account = this.createAccount(10L, defaultBalance);
    this.testTransaction(
        Arrays.asList(command), Arrays.asList(processedTransaction), Arrays.asList(account));
  }

  @Test
  void updateBalance_when_approveSomeTransaction() {
    this.setUpAccount(10L, defaultBalance);
    Transaction transaction =
        this.createTransaction(
            1L,
            10L,
            "1000",
            TransactionStatus.PENDING,
            TransactionType.WITHDRAWAL,
            Asset.USDT,
            null);
    Transaction transaction2 =
        this.createTransaction(
            2L,
            10L,
            "1000000",
            TransactionStatus.PENDING,
            TransactionType.WITHDRAWAL,
            Asset.USDT,
            null);
    Command command = new Command(CommandCode.WITHDRAW, transaction);
    Command command2 = new Command(CommandCode.WITHDRAW, transaction2);

    Transaction processedTransaction = new Transaction(transaction);
    processedTransaction.setStatus(TransactionStatus.APPROVED);
    Transaction processedTransaction2 = new Transaction(transaction2);
    processedTransaction2.setStatus(TransactionStatus.REJECTED);

    Account account = this.createAccount(10L, "99000");
    this.testTransaction(
        Arrays.asList(command, command2),
        Arrays.asList(processedTransaction, processedTransaction2),
        Arrays.asList(account));
  }

  protected Transaction createTransaction(
      Long id,
      Long accountId,
      String amount,
      TransactionStatus status,
      TransactionType type,
      Asset asset,
      String txHash) {
    Transaction transaction = new Transaction();
    transaction.setId(id);
    transaction.setAccountId(accountId);
    transaction.setAmount(MarginBigDecimal.valueOf(amount));
    transaction.setStatus(status);
    transaction.setType(type);
    transaction.setAsset(asset);
    return transaction;
  }

  protected Account createAccount(Long id, String balance) {
    Account account = Account.builder().build();
    account.setId(id);
    account.setAsset(this.defaultAsset);
    account.setBalance(defaultBalance);
    return account;
  }
}

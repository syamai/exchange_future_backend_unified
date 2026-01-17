package com.sotatek.future.service;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.Transaction;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.MarginHistoryAction;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TransactionStatus;
import com.sotatek.future.exception.AccountNotFoundException;
import com.sotatek.future.exception.InsufficientBalanceException;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.stream.Stream;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;
import org.apache.commons.lang3.tuple.Pair;
import org.jetbrains.annotations.NotNull;

@Slf4j
public class AccountService extends BaseService<Account> {
  private static final AccountService instance = new AccountService();
  private MarginHistoryService marginHistoryService;
  private TransactionService transactionService;

  private PositionService positionService;

  private OrderService orderService;

  public static final Long INSURANCE_USER_ID = 1L;
  // Map to hold those accounts of insurance user for each asset
  public static final Map<Asset, Long> INSURANCE_ACCOUNT_IDS = new HashMap<>();
  public static final Map<Long, Long> BOT_ACCOUNT_IDS = new HashMap<>(); // <accountId, userId>

  private AccountService() {
    super(true);
  }

  public static AccountService getInstance() {
    return instance;
  }

  public void initialize(
      MarginHistoryService marginHistoryService,
      TransactionService transactionService,
      PositionService positionService,
      OrderService orderService) {
    this.marginHistoryService = marginHistoryService;
    this.transactionService = transactionService;
    this.positionService = positionService;
    this.orderService = orderService;
  }

  @Override
  public Account update(Account entity) {
    Long accountId = entity.getKey();
    Account oldValue = super.get(accountId);
    Account updated = super.update(entity);
    updateAccountPositionLiquidationData(accountId, oldValue, updated);
    return updated;
  }

  public long getInsuranceAccountId(Asset asset) {
    Long id = INSURANCE_ACCOUNT_IDS.get(asset);
    if (id != null) return id;
    throw new InvalidMatchingEngineConfigException("not config insurance for asset " + asset);
  }

  public Account getInsuranceAccount(Asset asset) {
    return get(getInsuranceAccountId(asset));
  }

  /**
   * Update all cross-margin position of the account with new liquidation data if wallet balance
   * change
   *
   * @param accountId
   * @param oldValue
   * @param updated
   */
  private void updateAccountPositionLiquidationData(
      Long accountId, Account oldValue, Account updated) {
    boolean shouldUpdatePositions = (oldValue == null);
    if (oldValue != null) {
      MarginBigDecimal oldBalance = oldValue.getBalance();
      MarginBigDecimal newBalance = updated.getBalance();
      if (!oldBalance.eq(newBalance)) {
        shouldUpdatePositions = true;
      }
    }
    if (shouldUpdatePositions) {
      positionService.updateUserPosition(
          accountId,
          p -> {
            if (p.isCross()) {
              positionService.update(p);
            }
          });
    }
  }

  public Account addAmountToBalance(@NotNull Account account, MarginBigDecimal amount) {
    return account.addAmountToBalance(amount);
  }

  public Account subAmountToBalance(@NotNull Account account, MarginBigDecimal amount) {
    return account.addAmountToBalance(amount.negate());
  }

  /**
   * Validate account balance
   *
   * @param account account to validate balance
   */
  public void validateAccount(Account account) {
    boolean isBalanceNegative = account.getBalance().lt(0);
    // available balance is dynamic then we need to calculate each time
    MarginBigDecimal available = getAccountAvailableBalance(account.getId());
    boolean isAvailableBalanceNegative = available.lt(0);
    if (isBalanceNegative || isAvailableBalanceNegative) {
      throw new InsufficientBalanceException(account, available);
    }
  }

  /**
   * Calculate max amount can withdraw from future -> spot = min (Wallet balance - Position Margin -
   * Order Margin; Available balance)
   *
   * @param accountId account id to calculate
   * @return
   */
  public MarginBigDecimal getMaxWithdrawAmount(long accountId) {
    Pair<MarginBigDecimal, MarginBigDecimal> pair =
        calculateAccountAvailableBalanceAndUPnl(accountId);
    MarginBigDecimal availableBalanceWithOutUPnl = pair.getLeft();
    MarginBigDecimal uPnl = pair.getRight();
    MarginBigDecimal maxAmount =
        availableBalanceWithOutUPnl.min(availableBalanceWithOutUPnl.add(uPnl));
    // available balance maybe negative
    maxAmount = MarginBigDecimal.ZERO.max(maxAmount);
    log.info(
            "availableBalanceWithOutUPnl {} uPnl: {}",
            availableBalanceWithOutUPnl,
            uPnl);
    return maxAmount;
  }

  /**
   * Calculate available balance for each asset of account
   *
   * @param accountId
   * @return
   */
  public MarginBigDecimal getAccountAvailableBalance(@NotNull Long accountId) {
    // "Available balance = Wallet balance - Position Margin - Order Cost + Unrealized PNL of Cross
    // positions"
    Pair<MarginBigDecimal, MarginBigDecimal> pair =
        calculateAccountAvailableBalanceAndUPnl(accountId);
    MarginBigDecimal availableBalanceWithOutUPnl = pair.getLeft();
    MarginBigDecimal uPnl = pair.getRight();
      return availableBalanceWithOutUPnl.add(uPnl);
  }

  /**
   * Transfer balance from spot to future
   *
   * @param transaction
   */
  public void deposit(Transaction transaction) {
    transaction.setStatus(TransactionStatus.APPROVED);
    transactionService.insert(transaction);
    Account account = get(transaction.getAccountId());
    Account oldAccount = account.deepCopy();
    account.addAmountToBalance(transaction.getAmount());
    log.debug(
        "Deposit with transactionId {}, amount: {} to accountId {}. Old balance {}, new"
            + " balance: {}",
        transaction.getId(),
        transaction.getAmount(),
        account.getId(),
        oldAccount.getBalance(),
        account.getBalance());
    marginHistoryService.log(MarginHistoryAction.DEPOSIT, null, null, oldAccount, account);
    update(account);
  }

  /**
   * Transfer balancer from future to spot
   *
   * @param transaction withdrawn transaction
   */
  public void withdraw(Transaction transaction) {
    Account account = get(transaction.getAccountId());
    MarginBigDecimal maxWithdrawAmount = getMaxWithdrawAmount(account.getId());
    if (maxWithdrawAmount.gte(transaction.getAmount())) {
      transaction.setStatus(TransactionStatus.APPROVED);
      Account oldAccount = account.deepCopy();
      account.subAmountToBalance(transaction.getAmount());
      log.debug(
          "Withdrawal request amount: {} from accountId {} has been approved. "
              + "Old balance: {}, new balance: {}",
          transaction.getAmount(),
          account.getId(),
          oldAccount.getBalance(),
          account.getBalance());
      marginHistoryService.log(MarginHistoryAction.WITHDRAW, null, null, oldAccount, account);
      update(account);
    } else {
      transaction.setStatus(TransactionStatus.REJECTED);
      log.info(
          "Withdrawal {} request amount: {} from accountId {} has been rejected because of"
              + " maxWithdrawAmount is {}",
          transaction.getAsset(),
          transaction.getAmount(),
          account.getId(),
          maxWithdrawAmount);
    }
    transactionService.insert(transaction);
  }

  /**
   * get account by key
   *
   * @param key account key
   * @return
   */
  @Override
  public Account get(Object key) {
    Account target = super.get(key);
    if (target == null) {
      throw new AccountNotFoundException(key);
    }
    return target;
  }

  /**
   * Available balance is dynamic then we need to re-calculate each we using it
   *
   * @param accountId id of account
   * @return
   */
  private Pair<MarginBigDecimal, MarginBigDecimal> calculateAccountAvailableBalanceAndUPnl(
      long accountId) {
    Account account = get(accountId);
    MarginBigDecimal walletBalance = account.getBalance();
    Asset asset = account.getAsset();
    // position hold open order cost even when currentQty=0
    List<Position> positions =
        positionService.getUserPositions(accountId, position -> true).stream()
            .filter(p -> p.getAsset().equals(asset))
            .toList();
    MarginBigDecimal positionMargin = MarginBigDecimal.ZERO;
    MarginBigDecimal uPNLofAllCrossPosition = MarginBigDecimal.ZERO;
    MarginBigDecimal orderMargin = MarginBigDecimal.ZERO;
    for (Position position : positions) {
      String symbol = position.getSymbol();
      if (!position.getCurrentQty().eq(MarginBigDecimal.ZERO)) {
        MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(symbol);
        positionMargin = positionMargin.add(marginCalculator.calcAllocatedMargin(position));
        if (position.isCross()) {
          MarginBigDecimal uPNL = marginCalculator.calcUnrealisedPnl(position);
          uPNLofAllCrossPosition = uPNLofAllCrossPosition.add(uPNL);
        }

        // Add tmpTotalFee if position is isolated
        if (!position.isCross() && ObjectUtils.isNotEmpty(position.getTmpTotalFee())) {
          positionMargin = positionMargin.add(position.getTmpTotalFee());
        }

        // Add order margin
        orderMargin = orderMargin.add(position.getMarBuy()).add(position.getMarSel());
      }
    }

    // Get total order margin of processing market orders of this account
//    List<Order> orders = this.orderService.processingEntities.values().stream().filter(o -> o.getAccountId().equals(accountId) && o.getType().equals(OrderType.MARKET)).toList();
//    for (Order order: orders) {
//      MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(order.getSymbol());
//      MarginBigDecimal processingMarketOrderMargin = marginCalculator.calcOrderMargin(order);
//      orderMargin = orderMargin.add(processingMarketOrderMargin);
//    }

    log.debug(
        "calculateAccountAvailableBalance with accountId {} asset {} wallet balance {} "
            + "positionMargin {}  orderMargin {} uPNLofAllCrossPosition {} ",
        accountId,
        asset,
        walletBalance,
        positionMargin,
        orderMargin,
        uPNLofAllCrossPosition);

    return Pair.of(
        walletBalance.subtract(positionMargin).subtract(orderMargin), uPNLofAllCrossPosition);
  }

  public Stream<Long> getAllInsuranceAccountId() {
    List<Long> insuranceAccounts = INSURANCE_ACCOUNT_IDS.values().stream().toList();
    return insuranceAccounts.stream();
  }

  public boolean checkIsBotAccountId(Long accountId) {
      return BOT_ACCOUNT_IDS.containsKey(accountId);
  }
}

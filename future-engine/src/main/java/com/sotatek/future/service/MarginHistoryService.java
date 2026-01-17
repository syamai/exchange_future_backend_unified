package com.sotatek.future.service;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.MarginHistory;
import com.sotatek.future.entity.Order;
import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.MarginHistoryAction;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.List;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class MarginHistoryService extends BaseService<MarginHistory> {

  private static final MarginHistoryService instance = new MarginHistoryService();
  private AccountService accountService;
  private PositionService positionService;

  private MarginHistoryService() {
    super(false);
  }

  public static MarginHistoryService getInstance() {
    return instance;
  }

  public void initialize(AccountService accountService, PositionService positionService) {
    this.accountService = accountService;
    this.positionService = positionService;
  }

  @Override
  public void commit() {
    processingEntities.clear();
  }

  public MarginHistory log(
      MarginHistoryAction action,
      Position oldPosition,
      Position newPosition,
      Account oldAccount,
      Account newAccount,
      Trade trade) {
    MarginHistory history =
        MarginHistory.from(action, oldPosition, newPosition, oldAccount, newAccount, trade);
    calculateContractMargin(history);
    insert(history);
    return history;
  }

  public MarginHistory log(
          MarginHistoryAction action,
          Position oldPosition,
          Position newPosition,
          Account oldAccount,
          Account newAccount,
          Trade trade,
          MarginBigDecimal realizedPnl,
          MarginBigDecimal closeFee,
          MarginBigDecimal openFee
          ) {
    MarginHistory history =
            MarginHistory.from(action, oldPosition, newPosition, oldAccount, newAccount, trade);
    calculateContractMargin(history);
    history.setRealizedPnl(realizedPnl);
    history.setTradePrice(trade.getPrice());
    history.setFee(openFee.add(closeFee));
    history.setOpenFee(openFee);
    history.setCloseFee(closeFee);
    insert(history);
    return history;
  }

  public MarginHistory log(
      MarginHistoryAction action,
      Position oldPosition,
      Position newPosition,
      Account oldAccount,
      Account newAccount,
      Order order) {
    MarginHistory history =
        MarginHistory.from(action, oldPosition, newPosition, oldAccount, newAccount, order);
    calculateContractMargin(history);
    insert(history);
    return history;
  }

  public void log(
      MarginHistoryAction action,
      Position oldPosition,
      Position newPosition,
      Account oldAccount,
      Account newAccount) {
    MarginHistory history =
        MarginHistory.from(action, oldPosition, newPosition, oldAccount, newAccount);
    calculateContractMargin(history);
    insert(history);
  }

  private void calculateContractMargin(MarginHistory history) {
    Account account = accountService.get(history.getAccountId());
    List<Position> positions = positionService.getUserPositions(history.getAccountId());
    MarginBigDecimal contractMargin = account.getBalance();
    for (Position position : positions) {
      contractMargin = contractMargin.subtract(position.getEntryValue());
    }
    history.setContractMargin(contractMargin);
  }
}

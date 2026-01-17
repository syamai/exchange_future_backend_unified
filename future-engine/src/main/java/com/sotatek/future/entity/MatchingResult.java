package com.sotatek.future.entity;

import com.sotatek.future.util.MarginBigDecimal;
import lombok.Value;
import lombok.experimental.Accessors;

@Value
@Accessors(fluent = true)
public class MatchingResult {
  private Account account;
  private Account insuranceAccount;
  private Position position;
  private MarginBigDecimal realisedPnl;
  private MarginBigDecimal fee;
  private MarginBigDecimal openPositionFee;
  private MarginBigDecimal closePositionFee;
  private MarginBigDecimal feeRate;

  public MatchingResult(
      Account account,
      Account insuranceAccount,
      Position position,
      MarginBigDecimal realisedPnl,
      MarginBigDecimal fee,
      MarginBigDecimal openPositionFee,
      MarginBigDecimal closePositionFee,
      MarginBigDecimal feeRate) {
    this.account = account;
    this.insuranceAccount = insuranceAccount;
    this.position = position;
    this.realisedPnl = realisedPnl;
    this.fee = fee;
    this.openPositionFee = openPositionFee;
    this.closePositionFee = closePositionFee;
    this.feeRate = feeRate;
  }
}

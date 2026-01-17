package com.sotatek.future.entity;

import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Date;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;
import lombok.ToString;
import lombok.extern.slf4j.Slf4j;

@Getter
@Setter
@Slf4j
@ToString
@NoArgsConstructor
public class FundingHistory extends BaseEntity {
  // Pair name, for ex : BTCUSDT, ETHUSDT...
  private String symbol;
  // asset of this order USD/USDT
  private Asset asset;
  private Long userId;
  private Long accountId;
  private Long positionId;
  private Date time;
  private MarginBigDecimal amount;
  private MarginBigDecimal fundingRate;
  private MarginBigDecimal fundingQuantity;
  private ContractType contractType;

  private FundingHistory(FundingHistory history) {
    super(history);
    this.symbol = history.symbol;
    this.userId = history.userId;
    this.accountId = history.accountId;
    this.positionId = history.positionId;
    this.time = history.time;
    this.amount = history.amount;
    this.fundingRate = history.fundingRate;
    this.fundingQuantity = history.fundingQuantity;
    this.asset = history.asset;
    this.contractType = history.contractType;
  }

  public static String getKey(long positionId, Date time) {
    return positionId + "_" + time.getTime();
  }

  @Override
  public Object getKey() {
    return FundingHistory.getKey(positionId, time);
  }

  @Override
  public FundingHistory deepCopy() {
    return new FundingHistory(this);
  }
}

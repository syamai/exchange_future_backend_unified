package com.sotatek.future.entity;

import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.LeverageUpdateStatus;
import com.sotatek.future.util.MarginBigDecimal;
import java.io.Serializable;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@ToString
public class AdjustLeverage implements Serializable {
  private Long id;
  private Long userId;
  private Long accountId;
  private MarginBigDecimal leverage;
  private MarginBigDecimal oldLeverage;
  private String symbol;
  private String marginMode;
  private Asset asset;
  private LeverageUpdateStatus status;
}

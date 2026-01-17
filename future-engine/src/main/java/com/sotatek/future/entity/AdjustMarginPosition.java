package com.sotatek.future.entity;

import com.sotatek.future.enums.AdjustMarginPositionStatus;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.AllArgsConstructor;
import lombok.Data;
import lombok.ToString;

@Data
@AllArgsConstructor
@ToString
public class AdjustMarginPosition {
  private Long userId;
  private Long accountId;
  private String symbol;
  private MarginBigDecimal assignedMarginValue;
  private AdjustMarginPositionStatus status;
}

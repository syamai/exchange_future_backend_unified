package com.sotatek.future.entity;

import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.PositionHistoryAction;
import com.sotatek.future.util.MarginBigDecimal;
import lombok.Getter;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@ToString
public class PositionHistory extends BaseEntity {
  private Long positionId;
  // asset of this order USD/USDT
  private Asset asset;
  private PositionHistoryAction action;
  private MarginBigDecimal entryPrice;
  private MarginBigDecimal entryPriceAfter;
  private MarginBigDecimal entryValue;
  private MarginBigDecimal entryValueAfter;
  private MarginBigDecimal currentQty;
  private MarginBigDecimal currentQtyAfter;

  public static PositionHistory from(
      PositionHistoryAction action, Position oldPosition, Position newPosition) {
    PositionHistory history = new PositionHistory();
    history.setPositionId(oldPosition.getId());
    history.setAction(action);
    history.setAsset(oldPosition.getAsset());
    history.setEntryPrice(oldPosition.getEntryPrice());
    history.setEntryPriceAfter(newPosition.getEntryPrice());
    history.setEntryValue(oldPosition.getEntryValue());
    history.setEntryValueAfter(newPosition.getEntryValue());
    history.setCurrentQty(oldPosition.getCurrentQty());
    history.setCurrentQtyAfter(newPosition.getCurrentQty());
    return history;
  }

  @Override
  public Object getKey() {
    return this.id;
  }
}

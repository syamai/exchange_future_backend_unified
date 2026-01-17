package com.sotatek.future.entity;

import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.model.RetrieveData;
import lombok.Getter;
import lombok.Setter;

@Getter
@Setter
public class Command {

  private CommandCode code;
  private Object data;
  private Object extraData;

  public Command(CommandCode action, Object data) {
    this.code = action;
    this.data = data;
  }

  public Instrument getInstrument() {
    return (Instrument) this.data;
  }

  public InstrumentExtraInformation getInstrumentExtraInformation() {
    return (InstrumentExtraInformation) this.data;
  }

  public Account getAccount() {
    return (Account) this.data;
  }

  public Order getOrder() {
    return (Order) this.data;
  }

  public Position getPosition() {
    return (Position) this.data;
  }

  public LeverageMargin getLeverageMargin() {
    return (LeverageMargin) this.data;
  }

  public TradingRule getTradingRule() {
    return (TradingRule) this.data;
  }

  public AdjustLeverage getAdjustLeverage() {
    if (CommandCode.ADJUST_LEVERAGE.equals(code)) {
      return (AdjustLeverage) this.data;
    }
    return null;
  }

  public AdjustTpSlPrice getAdjustTpSlPrice() {
    return (AdjustTpSlPrice) this.data;
  }

  public AdjustTpSl getAdjustTpSl() {
    if (CommandCode.ADJUST_TP_SL.equals(code)) {
      return (AdjustTpSl) this.data;
    }
    return null;
  }

  public PositionHistory getPositionHistory() {
    return (PositionHistory) this.data;
  }

  public FundingHistory getFundingHistory() {
    return (FundingHistory) this.data;
  }

  public Transaction getTransaction() {
    return (Transaction) this.data;
  }
  public RetrieveData getRetrieveData() {
    return (RetrieveData) this.data;
  }

  public boolean isOrderCommand() {
    return this.code == CommandCode.PLACE_ORDER
        || this.code == CommandCode.CANCEL_ORDER
        || this.code == CommandCode.LOAD_ORDER
        || this.code == CommandCode.TRIGGER_ORDER;
  }

  public boolean isPlaceOrderCommand() {
    return CommandCode.PLACE_ORDER == this.code
        || CommandCode.LOAD_ORDER == this.code
        || CommandCode.TRIGGER_ORDER == this.code;
  }

  public boolean isTriggerCommand() {
    return CommandCode.TRIGGER_ORDER == this.code;
  }

  public boolean isCancelOrderCommand() {
    return CommandCode.CANCEL_ORDER.equals(this.code);
  }

  @Override
  public String toString() {
    return "Command{" + "code='" + this.code + '\'' + ", data=" + this.data + '}';
  }
}

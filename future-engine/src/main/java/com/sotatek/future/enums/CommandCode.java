package com.sotatek.future.enums;

import com.sotatek.future.entity.*;
import com.sotatek.future.model.RetrieveData;

public enum CommandCode {
  INITIALIZE_ENGINE(EngineParams.class),
  START_ENGINE(null),
  UPDATE_INSTRUMENT(Instrument.class),
  UPDATE_INSTRUMENT_EXTRA(InstrumentExtraInformation.class),
  CREATE_ACCOUNT(Account.class),
  LOAD_POSITION(Position.class),
  LOAD_POSITION_HISTORY(PositionHistory.class),
  LOAD_FUNDING_HISTORY(FundingHistory.class),
  LOAD_ORDER(Order.class),
  PLACE_ORDER(Order.class),
  CANCEL_ORDER(Order.class),
  ADJUST_LEVERAGE(AdjustLeverage.class),
  WITHDRAW(Transaction.class),
  DEPOSIT(Transaction.class),
  LIQUIDATE(InstrumentExtraInformation.class),
  PAY_FUNDING(FundingParams.class),
  TRIGGER_ORDER(Order.class),
  LOAD_LEVERAGE_MARGIN(LeverageMargin.class),
  ADJUST_MARGIN_POSITION(AdjustMarginPosition.class),
  DUMP(String.class),
  STOP_ENGINE(null),
  ADJUST_TP_SL(AdjustTpSl.class),
  LOAD_TRADING_RULE(TradingRule.class),

  ADJUST_TP_SL_PRICE(AdjustTpSlPrice.class),
  CLOSE_INSURANCE(null),

  LOAD_BOT_ACCOUNT(Account.class),

  SEED_LIQUIDATION_ORDER_ID(EngineParams.class),


  START_MEASURE_TPS(null),
  SHOW_PROCESSING_TIME(null),
  RETRIEVE_DATA(RetrieveData.class);

  private Class dataClass;

  CommandCode(Class dataClass) {
    this.dataClass = dataClass;
  }

  public Class getDataClass() {
    return dataClass;
  }
}

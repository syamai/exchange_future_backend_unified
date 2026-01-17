package com.sotatek.future.entity;

import com.google.gson.Gson;
import com.google.gson.internal.LinkedTreeMap;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.model.AccHasNoOpenOrdersAndPositions;
import com.sotatek.future.util.MarginBigDecimal;

import java.util.ArrayList;
import java.util.List;

import com.sotatek.future.util.json.JsonUtil;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;
import lombok.ToString;
import org.apache.commons.lang3.ObjectUtils;

@Getter
@Setter
@ToString
@NoArgsConstructor
public class CommandOutput {
  private static final Gson gson = JsonUtil.createGson();

  private CommandCode code;
  private Object data;
  private boolean shouldSeedLiquidationOrderId = false;
  private List<Account> accounts;
  private List<FundingHistory> fundingHistories;
  private List<MarginHistory> marginHistories;
  private List<Order> orders;
  private List<PositionHistory> positionHistories;
  private List<Position> positions;
  private List<Trade> trades;
  private List<Transaction> transactions;
  private List<CommandError> errors;
  private List<Position> liquidatedPositions;
  private AdjustLeverage adjustLeverage;
  private List<Object> retrievingData;
  private List<AccHasNoOpenOrdersAndPositions> accHasNoOpenOrdersAndPositionsList;

  public Instrument getInstrument() {
    LinkedTreeMap instrumentData = (LinkedTreeMap) this.data;
    Instrument instrument = new Instrument();
    instrument.setSymbol((String) instrumentData.get("symbol"));
    MarginBigDecimal multiplier =
        MarginBigDecimal.valueOf((String) instrumentData.get("multiplier"));
    instrument.setMultiplier(multiplier);
    return instrument;
  }

  public InstrumentExtraInformation getInstrumentExtra() {
    LinkedTreeMap instrumentExtra = (LinkedTreeMap) this.data;
    InstrumentExtraInformation instrumentExtraInformation = new InstrumentExtraInformation();
    instrumentExtraInformation.setSymbol((String) instrumentExtra.get("symbol"));
    MarginBigDecimal indexPrice =
        MarginBigDecimal.valueOf((String) instrumentExtra.get("indexPrice"));
    instrumentExtraInformation.setIndexPrice(indexPrice);
    return instrumentExtraInformation;
  }

  public void setOperationId(String operationId) {
    for (Account account : this.accounts) {
      account.setOperationId(operationId);
    }
    for (FundingHistory fundingHistory : this.fundingHistories) {
      fundingHistory.setOperationId(operationId);
    }
    for (MarginHistory history : this.marginHistories) {
      history.setOperationId(operationId);
    }
    for (Order order : this.orders) {
      order.setOperationId(operationId);
    }
    for (PositionHistory positionHistory : this.positionHistories) {
      positionHistory.setOperationId(operationId);
    }
    for (Position position : this.positions) {
      position.setOperationId(operationId);
    }
    for (Trade trade : this.trades) {
      trade.setOperationId(operationId);
    }
    for (Transaction transaction : this.transactions) {
      transaction.setOperationId(operationId);
    }
  }

  public boolean hasData() {
    if (ObjectUtils.isNotEmpty(accounts)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(fundingHistories)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(marginHistories)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(orders)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(positionHistories)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(positions)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(trades)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(transactions)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(errors)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(liquidatedPositions)) {
      return true;
    }
    if (ObjectUtils.isNotEmpty(retrievingData)) {
      return true;
    }
    return ObjectUtils.isNotEmpty(adjustLeverage);
  }

  @Override
  public String toString() {
    return String.format(
        "CommandOutput{code= %1$s, data=$2$s, orders=%3$s, trades=%4$s, accounts=%5$s,"
            + " positions=%6$s, transactions=%7$s}",
        this.code,
        this.data,
        this.orders,
        this.trades,
        this.accounts,
        this.positions,
        this.transactions);
  }

  public CommandOutput deepCopy() {
    CommandOutput copy = new CommandOutput();

    copy.setCode(this.code);
    copy.setData(gson.fromJson(gson.toJson(this.data), Object.class));
    copy.accounts = copyList(this.accounts);
    copy.fundingHistories = copyList(this.fundingHistories);
    copy.marginHistories = copyList(this.marginHistories);
    copy.orders = copyList(this.orders);
    copy.positionHistories = copyList(this.positionHistories);
    copy.positions = copyList(this.positions);
    copy.trades = copyList(this.trades);
    copy.transactions = copyList(this.transactions);
    copy.errors = copyList(this.errors);
    copy.liquidatedPositions = copyList(this.liquidatedPositions);
    copy.adjustLeverage = this.adjustLeverage;
    copy.accHasNoOpenOrdersAndPositionsList = copyList(this.accHasNoOpenOrdersAndPositionsList);
    copy.shouldSeedLiquidationOrderId = this.shouldSeedLiquidationOrderId;
    copy.retrievingData = copyList(this.retrievingData);

    return copy;
  }

  private <T> List<T> copyList(List<T> list) {
    if (list == null) return null;
    return new ArrayList<>(list); // Shallow copy of list, assuming T is immutable or won't be modified
  }
}

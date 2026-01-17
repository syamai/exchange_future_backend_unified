package com.sotatek.future.service;

import com.sotatek.future.entity.Position;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.util.MarginBigDecimal;

public class PositionCalculatorTestHelper {
  public static Position crossPositionOf(
      long id, MarginBigDecimal quantity, MarginBigDecimal entryPrice) {
    Position pos =
        positionOf(true, quantity, entryPrice, MarginBigDecimal.valueOf(3), MarginBigDecimal.ZERO, ContractType.USD_M);
    pos.setId(id);
    return pos;
  }

  public static Position isolatePositionOf(
      MarginBigDecimal quantity,
      MarginBigDecimal entryPrice,
      MarginBigDecimal positionMargin,
      MarginBigDecimal adjustMargin,
      ContractType contractType
      ) {
    return positionOf(false, quantity, entryPrice, positionMargin, adjustMargin, contractType);
  }

  public static Position positionOf(
      boolean cross,
      MarginBigDecimal quantity,
      MarginBigDecimal entryPrice,
      MarginBigDecimal positionMargin,
      MarginBigDecimal adjustMargin,
      ContractType contractType
  ) {
    Position p = new Position(cross);
    p.setCurrentQty(quantity);
    p.setEntryPrice(entryPrice);
    p.setPositionMargin(positionMargin);
    p.setAdjustMargin(adjustMargin);
    p.setSymbol("BTCUSD");
    p.setContractType(contractType);
    return p;
  }
}

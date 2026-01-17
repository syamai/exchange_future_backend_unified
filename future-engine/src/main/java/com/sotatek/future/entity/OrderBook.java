package com.sotatek.future.entity;

import com.sotatek.future.util.MarginBigDecimal;
import java.util.List;
import java.util.Objects;
import java.util.stream.Collectors;
import lombok.AllArgsConstructor;

@AllArgsConstructor
public class OrderBook {

  private final List<MarginBigDecimal[]> bids;
  private final List<MarginBigDecimal[]> asks;
  private Long updatedAt;
  private Long lastUpdatedAt;

  @Override
  public boolean equals(Object o) {
    if (this == o) {
      return true;
    }
    if (o == null || getClass() != o.getClass()) {
      return false;
    }
    OrderBook orderbook = (OrderBook) o;
    return this.isRowsEqual(bids, orderbook.bids) && this.isRowsEqual(asks, orderbook.asks);
  }

  protected boolean isRowsEqual(List<MarginBigDecimal[]> rows1, List<MarginBigDecimal[]> rows2) {
    int count = rows1.size();
    if (count != rows2.size()) {
      return false;
    }

    for (int i = 0; i < rows1.size(); i++) {
      MarginBigDecimal[] row1 = rows1.get(i);
      MarginBigDecimal[] row2 = rows2.get(i);

      if (!row1[0].equals(row2[0]) || !row1[1].equals(row2[1])) {
        return false;
      }
    }
    return true;
  }

  @Override
  public int hashCode() {
    return Objects.hash(bids, asks);
  }

  @Override
  public String toString() {
    return "OrderBook{" + "bids=" + rowsToString(bids) + ", asks=" + rowsToString(asks) + '}';
  }

  protected String rowsToString(List<MarginBigDecimal[]> rows) {
    return "["
        + rows.stream()
            .map((MarginBigDecimal[] row) -> "[" + row[0] + "," + row[1] + "]")
            .collect(Collectors.joining())
        + "]";
  }
}

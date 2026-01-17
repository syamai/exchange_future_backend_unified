package com.sotatek.future;

import com.sotatek.future.entity.OrderBook;
import com.sotatek.future.entity.OrderBookEvent;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.output.ListOrderBookStream;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.stream.Collectors;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;

class OrderBookTest {

  @Test
  void test1() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "9000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"));
    OrderBook output =
        this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList("10000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList("10000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test2() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "9000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "9000", "-1"));
    OrderBook output = this.createOrderBook(Arrays.asList(), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList("9000", "0"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test3() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "9000", "2"),
            this.createOrderBookOutput(OrderSide.BUY, "9000", "-1"));
    OrderBook output = this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test4() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "9000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "9000", "-1"),
            this.createOrderBookOutput(OrderSide.SELL, "9000", "1"));
    OrderBook output = this.createOrderBook(Arrays.asList(), Arrays.asList("9000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList("9000", "0"), Arrays.asList("9000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test5() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "9000", "3"),
            this.createOrderBookOutput(OrderSide.BUY, "9000", "-1"),
            this.createOrderBookOutput(OrderSide.BUY, "9000", "-1"));
    OrderBook output = this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test6() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "9000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "9000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "9000", "-1"),
            this.createOrderBookOutput(OrderSide.SELL, "9000", "-1"),
            this.createOrderBookOutput(OrderSide.BUY, "9000", "1"));
    OrderBook output = this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList("9000", "0")));
    this.doTest(input, output, changes);
  }

  @Test
  void test7() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "9000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "10000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "10000", "-1"));
    OrderBook output = this.createOrderBook(Arrays.asList("9000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList("10000", "0", "9000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test8() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "9000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "9000", "-1"));
    OrderBook output = this.createOrderBook(Arrays.asList(), Arrays.asList("10000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(this.createOrderBook(Arrays.asList(), Arrays.asList("9000", "0", "10000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test9() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "6000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "8000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "10000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList("10000", "1", "8000", "1", "6000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList("10000", "1", "8000", "1", "6000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test10() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "10000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "8000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "6000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList("10000", "1", "8000", "1", "6000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList("10000", "1", "8000", "1", "6000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test11() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "10000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "8000", "2"),
            this.createOrderBookOutput(OrderSide.BUY, "6000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "8000", "-1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList("10000", "1", "8000", "1", "6000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList("10000", "1", "8000", "1", "6000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test12() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "10000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "8000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "6000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "11000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList("11000", "1", "10000", "1", "8000", "1", "6000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList("11000", "1", "10000", "1", "8000", "1", "6000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test13() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.BUY, "10000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "8000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "6000", "1"),
            this.createOrderBookOutput(OrderSide.BUY, "9000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList("10000", "1", "9000", "1", "8000", "1", "6000", "1"), Arrays.asList());
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList("10000", "1", "9000", "1", "8000", "1", "6000", "1"), Arrays.asList()));
    this.doTest(input, output, changes);
  }

  @Test
  void test14() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "6000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "8000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test15() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "8000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "6000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test16() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "8000", "2"),
            this.createOrderBookOutput(OrderSide.SELL, "6000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "8000", "-1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test17() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "8000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "6000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "11000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1", "11000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "10000", "1", "11000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test18() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "8000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "6000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "9000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "9000", "1", "10000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("6000", "1", "8000", "1", "9000", "1", "10000", "1")));
    this.doTest(input, output, changes);
  }

  @Test
  void test19() {
    List<OrderBookOutput> input =
        Arrays.asList(
            this.createOrderBookOutput(OrderSide.SELL, "10000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "8000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "6000", "1"),
            this.createOrderBookOutput(OrderSide.SELL, "5000", "1"));
    OrderBook output =
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("5000", "1", "6000", "1", "8000", "1", "10000", "1"));
    List<OrderBook> changes = new ArrayList<>();
    changes.add(
        this.createOrderBook(
            Arrays.asList(), Arrays.asList("5000", "1", "6000", "1", "8000", "1", "10000", "1")));
    this.doTest(input, output, changes);
  }

  protected OrderBookOutput createOrderBookOutput(OrderSide side, String price, String quantity) {
    return new OrderBookOutput(
        side, MarginBigDecimal.valueOf(price), MarginBigDecimal.valueOf(quantity), "BTCUSD");
  }

  protected OrderBook createOrderBook(List<String> bids, List<String> asks) {
    return new OrderBook(
        this.getOrderBookRows(bids), this.getOrderBookRows(asks), System.currentTimeMillis(), null);
  }

  protected List<MarginBigDecimal[]> getOrderBookRows(List<String> data) {
    List<MarginBigDecimal[]> rows = new ArrayList<>();
    int count = data.size() / 2;
    for (int i = 0; i < count; i++) {
      rows.add(
          new MarginBigDecimal[] {
            MarginBigDecimal.valueOf(data.get(i * 2)), MarginBigDecimal.valueOf(data.get(i * 2 + 1))
          });
    }
    return rows;
  }

  protected void doTest(List<OrderBookOutput> input, OrderBook output, List<OrderBook> changes) {
    ListOrderBookStream stream = new ListOrderBookStream(10);
    stream.connect();
    stream.write(input);
    stream.flush();
    try {
      stream.close();
    } catch (Exception e) {
      e.printStackTrace();
    }
    List<OrderBookEvent> data = stream.getData();
    Assertions.assertEquals(output, data.get(data.size() - 1).getOrderbook());
    Assertions.assertEquals(
        changes, data.stream().map(event -> event.getChanges()).collect(Collectors.toList()));
  }

  protected void doTest2(
      List<List<OrderBookOutput>> inputs, OrderBook output, List<OrderBook> changes) {
    ListOrderBookStream stream = new ListOrderBookStream(10);
    stream.connect();
    for (List<OrderBookOutput> input : inputs) {
      stream.write(input);
    }
    stream.flush();
    try {
      stream.close();
    } catch (Exception e) {
      e.printStackTrace();
    }
    List<OrderBookEvent> data = stream.getData();
    Assertions.assertEquals(output, data.get(data.size() - 1).getOrderbook());
    Assertions.assertEquals(
        changes, data.stream().map(event -> event.getChanges()).collect(Collectors.toList()));
  }
}

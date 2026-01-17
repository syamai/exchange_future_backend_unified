package com.sotatek.future.output;

import com.sotatek.future.entity.OrderBookEvent;
import com.sotatek.future.util.TimeUtil;
import java.util.ArrayList;
import java.util.List;

public class ListOrderBookStream extends OrderBookOutputStream {

  private final List<OrderBookEvent> data = new ArrayList<>();

  public ListOrderBookStream(int interval) {
    this.updateInterval = interval;
  }

  public List<OrderBookEvent> getData() {
    return this.data;
  }

  @Override
  protected void publish(OrderBookEvent event) {
    this.data.add(event);
  }

  @Override
  public void flush() {
    TimeUtil.sleep(Math.max(this.updateInterval, 100));
  }
}

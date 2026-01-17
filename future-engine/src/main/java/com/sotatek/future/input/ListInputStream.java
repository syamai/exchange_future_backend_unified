package com.sotatek.future.input;

import java.util.List;

public class ListInputStream<T> extends BaseInputStream<T> {

  private final List<T> data;

  public ListInputStream(List<T> data) {
    this.data = data;
  }

  @Override
  public boolean connect() {
    if (this.callback == null) {
      return true;
    }
    for (T action : this.data) {
      this.callback.onNewData(action);
    }
    this.isClosed = true;
    return true;
  }

  @Override
  public void close() {}
}

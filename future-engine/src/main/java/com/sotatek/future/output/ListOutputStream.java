package com.sotatek.future.output;

import java.util.ArrayList;
import java.util.List;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class ListOutputStream<T> extends BaseOutputStream<T> {

  private final List<T> data = new ArrayList<>();

  public List<T> getData() {
    return this.data;
  }

  @Override
  public boolean connect() {
    return true;
  }

  @Override
  public void write(T t) {
    this.data.add(t);
    if (this.data.size() % 50000 == 0) {
      //           log.info("Output length: " + this.data.size() + t);
    }
  }

  @Override
  public void write(List<T> ts) {
    this.data.addAll(ts);
  }

  @Override
  public void flush() {
    // nothing to do
  }

  @Override
  public void close() {
    // nothing to do
  }
}

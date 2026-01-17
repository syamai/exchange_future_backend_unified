package com.sotatek.future.output;

import java.io.IOException;
import java.util.List;
import java.util.concurrent.TimeoutException;

public interface OutputStream<T> {

  boolean connect() throws IOException, TimeoutException;

  void write(T t);

  void write(List<T> ts);

  void flush();

  void close();
}

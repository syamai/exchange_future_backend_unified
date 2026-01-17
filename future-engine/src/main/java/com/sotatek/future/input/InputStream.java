package com.sotatek.future.input;

import java.io.IOException;
import java.util.concurrent.TimeoutException;

public interface InputStream<T> {

  boolean connect() throws IOException, TimeoutException;

  void setOnNewDataListener(OnNewDataListener<T> callback);

  void close();

  boolean isClosed();
}

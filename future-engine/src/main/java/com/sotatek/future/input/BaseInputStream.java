package com.sotatek.future.input;

public abstract class BaseInputStream<T> implements InputStream<T> {

  protected OnNewDataListener<T> callback;
  protected boolean isClosed = false;

  @Override
  public void setOnNewDataListener(OnNewDataListener<T> callback) {
    this.callback = callback;
  }

  @Override
  public boolean isClosed() {
    return isClosed;
  }
}

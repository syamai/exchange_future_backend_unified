package com.sotatek.future.input;

public interface OnNewDataListener<T> {

  long onNewData(T data);
}

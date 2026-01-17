package com.sotatek.future.exception;

public class PositionNotFoundException extends MarginException {

  public PositionNotFoundException(long positionId) {
    super("Position not found: " + positionId);
  }

  public PositionNotFoundException(String message) {
    super(message);
  }
}

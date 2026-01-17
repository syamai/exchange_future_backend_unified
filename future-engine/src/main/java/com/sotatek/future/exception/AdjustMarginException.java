package com.sotatek.future.exception;

public class AdjustMarginException extends MarginException {

  public AdjustMarginException(Object positionId) {
    super("Position is isolated: " + positionId);
  }
}

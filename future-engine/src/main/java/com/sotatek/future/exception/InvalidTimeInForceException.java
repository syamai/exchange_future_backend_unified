package com.sotatek.future.exception;

import com.sotatek.future.enums.TimeInForce;

public class InvalidTimeInForceException extends RuntimeException {

  public InvalidTimeInForceException(TimeInForce timeInForce) {
    super("Unknown time in force " + timeInForce);
  }
}

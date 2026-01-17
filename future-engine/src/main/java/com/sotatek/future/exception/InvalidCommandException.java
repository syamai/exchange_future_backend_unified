package com.sotatek.future.exception;

public class InvalidCommandException extends RuntimeException {
  public InvalidCommandException(String message) {
    super(message);
  }
}

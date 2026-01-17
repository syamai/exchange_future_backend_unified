package com.sotatek.future.enums;

public enum TimeInForce {
  /** Good-Till-Cancelled order that stay alive until explicitly cancelled by external command */
  GTC,
  /**
   * Immediate-Or-Cancel order that needs to be filled immediately (entirely or partially) All
   * remaining amount will be cancelled
   */
  IOC,
  /** Fill-Or-Kill order that needs to be filled immediately entirely or cancelled all amount */
  FOK
}

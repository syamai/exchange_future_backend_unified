package com.sotatek.future.util;

public class TimeUtil {

  public static long currentTimeMilliSeconds() {
    return System.currentTimeMillis();
  }

  public static long currentTimeSeconds() {
    return System.currentTimeMillis() / 1000;
  }

  public static void sleep(long time) {
    try {
      Thread.sleep(time);
    } catch (InterruptedException e) {
      e.printStackTrace();
    }
  }
}

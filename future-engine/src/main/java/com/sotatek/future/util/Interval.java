package com.sotatek.future.util;

/**
 * Interval with high/low value
 *
 * @param <T>
 */
public interface Interval<T extends Comparable> {
  T low();

  T high();
}

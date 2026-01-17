package com.sotatek.future.util;

import java.io.Serializable;
import java.math.BigInteger;
import java.util.Objects;
import lombok.extern.slf4j.Slf4j;
import org.jetbrains.annotations.VisibleForTesting;

@Slf4j
public class MarginBigDecimal implements Comparable<MarginBigDecimal>, Serializable {
  public static final int MAX_SCALE = 18;
  public static final int DEFAULT_SCALE = 11;
  private static final BigInteger[] SCALES =
      new BigInteger[] {
        BigInteger.valueOf(1),
        BigInteger.valueOf(10),
        BigInteger.valueOf(100),
        BigInteger.valueOf(1000),
        BigInteger.valueOf(10000),
        BigInteger.valueOf(100000),
        BigInteger.valueOf(1000000),
        BigInteger.valueOf(10000000),
        BigInteger.valueOf(100000000),
        BigInteger.valueOf(1000000000),
        BigInteger.valueOf(10000000000L),
        BigInteger.valueOf(100000000000L),
        BigInteger.valueOf(1000000000000L),
        BigInteger.valueOf(10000000000000L),
        BigInteger.valueOf(100000000000000L),
        BigInteger.valueOf(1000000000000000L),
        BigInteger.valueOf(10000000000000000L),
        BigInteger.valueOf(100000000000000000L),
        BigInteger.valueOf(1000000000000000000L)
      };
  public static final MarginBigDecimal ZERO = MarginBigDecimal.valueOf(0);
  public static final MarginBigDecimal ONE = MarginBigDecimal.valueOf(1);
  public static final MarginBigDecimal NEGATIVE_ONE = MarginBigDecimal.valueOf(-1);
  private static final String[] ZERO_PADDINGS =
      new String[] {"", "0", "00", "000", "0000", "00000", "000000", "0000000", "00000000", "000000000", "0000000000", "00000000000", "000000000000", "0000000000000", "00000000000000", "000000000000000", "0000000000000000", "00000000000000000", "000000000000000000"};
  private BigInteger value;
  private int scale;

  @VisibleForTesting
  private MarginBigDecimal(BigInteger value) {
    this.value = value;
    this.scale = DEFAULT_SCALE;
  }

  /**
   * Method to convert a string of decimal number to MarginBigDecimal with scale = DEFAULT_SCALE = 8
   *
   * @param string
   */
  public MarginBigDecimal(String string) {
    int dotIndex = string.indexOf('.');
    if (dotIndex < 0) {
      BigInteger longValue = new BigInteger(string);
      scale = DEFAULT_SCALE;
      value = longValue.multiply(SCALES[scale]);
    } else {
      String formattedString = string.replaceAll("0*$", "");
      scale = formattedString.length() - dotIndex - 1;
      if (scale > MAX_SCALE) {
        log.debug("The value has scale {} is over the maximum scale is {}", scale, MAX_SCALE);
        int reduceScale = scale - MAX_SCALE;
        scale = MAX_SCALE;
        // cutting over scale of that value
        log.debug(
            "The value before cutting is {} with reduce scale is {}", formattedString, reduceScale);
        formattedString = formattedString.substring(0, dotIndex + MAX_SCALE + 1);
        log.debug("The value after cutting is {}", formattedString);
      }
      formattedString = formattedString.replace(".", "");
      value = new BigInteger(formattedString);
      if (scale < DEFAULT_SCALE) {
        value = value.multiply(SCALES[DEFAULT_SCALE - scale]);
        scale = DEFAULT_SCALE;
      }
    }
  }

  /**
   * Convert a long number to MarginBigDecimal
   *
   * @param n
   * @return
   */
  public static MarginBigDecimal valueOf(long n) {
    return new MarginBigDecimal(BigInteger.valueOf(n).multiply(SCALES[DEFAULT_SCALE]));
  }

  /**
   * Convert a string to MarginBigDecimal
   *
   * @param n
   * @return
   */
  public static MarginBigDecimal valueOf(String n) {
    return new MarginBigDecimal(n);
  }

  public long longValue() {
    return value.divide(SCALES[this.scale]).longValue();
  }

  public static MarginBigDecimal getSign(MarginBigDecimal num) {
    if (num.gt(0)) return MarginBigDecimal.ONE;
    if (num.lt(0)) return MarginBigDecimal.NEGATIVE_ONE;
    return MarginBigDecimal.ZERO;
  }

  public boolean eq(long n) {
    return value.equals(BigInteger.valueOf(n).multiply(SCALES[scale]));
  }

  public boolean eq(MarginBigDecimal n) {
    return compare(this, n) == 0;
  }

  public boolean gt(MarginBigDecimal n) {
    return compare(this, n) > 0;
  }

  public boolean gt(long n) {
    return gt(MarginBigDecimal.valueOf(n));
  }

  public boolean gte(long n) {
    return gte(MarginBigDecimal.valueOf(n));
  }

  public boolean gte(MarginBigDecimal n) {
    return compare(this, n) >= 0;
  }

  public boolean lt(long n) {
    return lt(MarginBigDecimal.valueOf(n));
  }

  public boolean lt(MarginBigDecimal n) {
    return compare(this, n) < 0;
  }

  public boolean lte(MarginBigDecimal n) {
    return compareTo(n) <= 0;
  }

  public MarginBigDecimal add(MarginBigDecimal augend) {
    normalize();
    augend.normalize();
    return new MarginBigDecimal(value.add(augend.value));
  }

  public MarginBigDecimal subtract(MarginBigDecimal augend) {
    normalize();
    augend.normalize();
    return new MarginBigDecimal(value.subtract(augend.value));
  }

  public MarginBigDecimal multiply(MarginBigDecimal multiplicand) {
    normalize();
    multiplicand.normalize();
    BigInteger result = value.multiply(multiplicand.value).divide(SCALES[DEFAULT_SCALE]);
    return new MarginBigDecimal(result);
  }

  public MarginBigDecimal multiply(long multiplicand) {
    return multiply(MarginBigDecimal.valueOf(multiplicand));
  }

  public MarginBigDecimal divide(MarginBigDecimal divisor) {
    normalize();
    divisor.normalize();
    BigInteger result = value.multiply(SCALES[DEFAULT_SCALE]).divide(divisor.value);
    return new MarginBigDecimal(result);
  }

  public MarginBigDecimal multiplyThenDivide(
      MarginBigDecimal multiplicand, MarginBigDecimal divisor) {
    normalize();
    multiplicand.normalize();
    divisor.normalize();
    return this.multiply(multiplicand).divide(divisor);
  }

  public int multiplySign(MarginBigDecimal multiplicand) {
    int sign = compare(this, ZERO);
    int multiplicandSign = compare(multiplicand, ZERO);
    return sign * multiplicandSign;
  }

  public MarginBigDecimal abs() {
    return new MarginBigDecimal(value.abs());
  }

  public MarginBigDecimal min(MarginBigDecimal val) {
    return this.lte(val) ? this : val;
  }

  public MarginBigDecimal max(MarginBigDecimal val) {
    return this.gte(val) ? this : val;
  }

  public MarginBigDecimal negate() {
    return new MarginBigDecimal(value.multiply(new BigInteger("-1")));
  }

  private int compare(MarginBigDecimal value1, MarginBigDecimal value2) {
    value1.normalize();
    value2.normalize();
    return value1.value.compareTo(value2.value);
  }

  /**
   * normalized to scale = DEFAULT_SCALE = 8 because MAX_SCALE = 8 so scale always <= 8
   *
   * @return
   */
  public MarginBigDecimal normalize() {
    if (this.scale != DEFAULT_SCALE && this.scale < DEFAULT_SCALE) {
      value = value.multiply(SCALES[DEFAULT_SCALE - scale]);
      scale = DEFAULT_SCALE;
    }
    return this;
  }

  @Override
  public int compareTo(MarginBigDecimal o) {
    return this.compare(this, o);
  }

  @Override
  public boolean equals(Object o) {
    if (this == o) {
      return true;
    }
    if (o == null || this.getClass() != o.getClass()) {
      return false;
    }
    MarginBigDecimal that = (MarginBigDecimal) o;
    return that.eq(this);
  }

  @Override
  public int hashCode() {
    return Objects.hash(value, scale);
  }

  @Override
  public String toString() {
    BigInteger quotient = value.divide(SCALES[scale]);
    BigInteger remainder = value.remainder(SCALES[scale]);
    if (remainder.equals(BigInteger.ZERO)) {
      return String.valueOf(quotient);
    } else {
      String remainderString = String.valueOf(remainder.abs());
      String padding = ZERO_PADDINGS[scale - remainderString.length()];
      int index = remainderString.length() - 1;
      while (remainderString.charAt(index) == '0') {
        index--;
      }
      String string = String.valueOf(quotient);
      if (quotient.equals(BigInteger.ZERO) & value.compareTo(BigInteger.ZERO) < 0) {
        string = "-0";
      }
      return string + "." + padding + remainderString.substring(0, index + 1);
    }
  }

  @VisibleForTesting
  BigInteger getUnscaledValue() {
    return value;
  }

  @VisibleForTesting
  int getScale() {
    return scale;
  }
}

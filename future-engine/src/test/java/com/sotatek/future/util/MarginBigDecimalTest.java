package com.sotatek.future.util;

import static org.assertj.core.api.Assertions.assertThat;
import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import org.junit.jupiter.api.Test;

public class MarginBigDecimalTest {

  @Test
  void beValidDecimal_when_convertFromString() {
    MarginBigDecimal number = new MarginBigDecimal("1.2300");
    assertEquals(12300, number.getUnscaledValue());
    assertEquals(4, number.getScale());
    assertEquals("1.23", number.toString());

    number = new MarginBigDecimal("1568");
    assertEquals(15680000, number.getUnscaledValue());
    assertEquals(4, number.getScale());
    assertEquals("1568", number.toString());

    number = new MarginBigDecimal("123000");
    assertEquals(1230000000, number.getUnscaledValue());
    assertEquals(4, number.getScale());
    assertEquals("123000", number.toString());

    number = new MarginBigDecimal("1.23");
    assertEquals(12300, number.getUnscaledValue());
    assertEquals(4, number.getScale());
    assertEquals("1.23", number.toString());
  }

  @Test
  void notLosePrecision_when_convertToString() {
    MarginBigDecimal number = new MarginBigDecimal("1.2345");
    MarginBigDecimal result =
        number.multiplyThenDivide(MarginBigDecimal.valueOf("2"), MarginBigDecimal.valueOf("7"));
    // MarginBigDecimal can keep at most 8 digits after decimal points
    assertEquals(35271428, result.getUnscaledValue());
    assertEquals(8, result.getScale());
    assertEquals("0.35271428", result.toString());
  }

  @Test
  void notOverflow_when_calculate() {
    MarginBigDecimal entryPrice = new MarginBigDecimal("6500000000000");

    MarginBigDecimal leverage = MarginBigDecimal.valueOf(100);

    MarginBigDecimal size = MarginBigDecimal.valueOf(5);

    MarginBigDecimal adjustMargin = MarginBigDecimal.ZERO;

    MarginBigDecimal numerator =
        entryPrice
            .multiply(leverage.add(MarginBigDecimal.ONE))
            .multiply(size)
            .add(adjustMargin.multiply(leverage));

    assertThat(numerator).isGreaterThan(MarginBigDecimal.ZERO);
  }

  @Test
  public void preciseErrorNotExceed10PowerMinus8_when_division() {
    MarginBigDecimal price = MarginBigDecimal.valueOf("8000");
    MarginBigDecimal size = MarginBigDecimal.valueOf("1000");
    MarginBigDecimal leverage = MarginBigDecimal.valueOf("13");

    MarginBigDecimal result = price.multiply(size).divide(leverage);
    System.out.println("test result " + result);
    MarginBigDecimal expectResult = MarginBigDecimal.valueOf("615384.61538461");
    assertTrue((expectResult.subtract(result)).lte(MarginBigDecimal.valueOf("0.00000001")));
  }

  @Test
  public void preciseErrorNotExceed10PowerMinus8_when_multiplyThenDivide() {
    MarginBigDecimal price = MarginBigDecimal.valueOf("800");
    MarginBigDecimal size = MarginBigDecimal.valueOf("1");
    MarginBigDecimal leverage = MarginBigDecimal.valueOf("13");

    MarginBigDecimal result = price.multiplyThenDivide(size, leverage);
    System.out.println("test result " + result);
    MarginBigDecimal expectResult = MarginBigDecimal.valueOf("61.53846154");
    assertTrue((expectResult.subtract(result)).lte(MarginBigDecimal.valueOf("0.00000001")));
  }

  @Test
  public void multiplyThenDivideReturnCorrectValue() {
    MarginBigDecimal num = MarginBigDecimal.valueOf("1");
    MarginBigDecimal multiplier = MarginBigDecimal.valueOf("1838.24");
    MarginBigDecimal numerator = MarginBigDecimal.valueOf("100");

    MarginBigDecimal expect = MarginBigDecimal.valueOf("18.3824");

    assertEquals(expect, num.multiplyThenDivide(multiplier, numerator));
  }
}

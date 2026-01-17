package com.sotatek.future.util;

import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.mockito.Mockito.spy;
import static org.mockito.Mockito.when;

import org.junit.jupiter.api.Test;

class OperationIdGeneratorTest {

  @Test
  public void testGenerateOperationIdGenDifferentIdWhenReturnSameTime() {
    OperationIdGenerator generator = spy(new OperationIdGenerator());
    when(generator.getCurrentTime()).thenReturn(1688702367L);
    System.out.println("time return: " + generator.getCurrentTime());

    long previousId = generator.generateOperationId();

    long id = generator.generateOperationId();
    assertTrue(id != previousId, "checking first duplication");
    previousId = id;

    id = generator.generateOperationId();
    assertTrue(id != previousId, "checking second duplication");
    previousId = id;

    id = generator.generateOperationId();
    assertTrue(id != previousId, "checking third duplication");
  }
}
package com.sotatek.future.util;

import static org.assertj.core.api.Assertions.assertThat;

import java.util.Collections;
import java.util.List;
import java.util.stream.Stream;
import lombok.Value;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.Arguments;
import org.junit.jupiter.params.provider.MethodSource;

public class IntervalTreeTest {

  @ParameterizedTest
  @MethodSource("provider_returnSingleInterval_when_notOverlap")
  void returnSingleInterval_when_notOverlap(
      List<TestInterval> intervals, Long point, TestInterval expected) {
    IntervalTree<Long, TestInterval> tree = new IntervalTree<>();
    intervals.forEach(tree::insert);

    List<TestInterval> results = tree.lookup(point);

    assertThat(results).containsExactly(expected);
  }

  private static Stream<Arguments> provider_returnSingleInterval_when_notOverlap() {
    return Stream.of(
        Arguments.of(
            List.of(TestInterval.of(0, 5), TestInterval.of(5, 10), TestInterval.of(10, 15)),
            0L,
            TestInterval.of(0, 5)),
        Arguments.of(
            List.of(TestInterval.of(10, 15), TestInterval.of(0, 5), TestInterval.of(5, 10)),
            6L,
            TestInterval.of(5, 10)),
        Arguments.of(
            List.of(TestInterval.of(10, 15), TestInterval.of(5, 10), TestInterval.of(0, 5)),
            14L,
            TestInterval.of(10, 15)));
  }

  @ParameterizedTest
  @MethodSource("provider_returnMultipleInterval_when_overlap")
  void returnSingleInterval_when_notOverlap(
      List<TestInterval> intervals, Long point, List<TestInterval> expecteds) {
    IntervalTree<Long, TestInterval> tree = new IntervalTree<>();
    intervals.forEach(tree::insert);

    List<TestInterval> results = tree.lookup(point);

    assertThat(results).containsExactlyElementsOf(expecteds);
  }

  private static Stream<Arguments> provider_returnMultipleInterval_when_overlap() {
    return Stream.of(
        Arguments.of(
            List.of(TestInterval.of(0, 5), TestInterval.of(3, 8), TestInterval.of(4, 10)),
            4L,
            List.of(TestInterval.of(0, 5), TestInterval.of(3, 8), TestInterval.of(4, 10))),
        Arguments.of(
            List.of(TestInterval.of(0, 5), TestInterval.of(3, 8), TestInterval.of(4, 10)),
            7L,
            List.of(TestInterval.of(3, 8), TestInterval.of(4, 10))),
        Arguments.of(
            List.of(TestInterval.of(0, 5), TestInterval.of(5, 10), TestInterval.of(10, 15)),
            14L,
            List.of(TestInterval.of(10, 15))));
  }

  @ParameterizedTest
  @MethodSource("provider_returnNothing_when_outOfRange")
  void returnSingleInterval_when_notOverlap(List<TestInterval> intervals, Long point) {
    IntervalTree<Long, TestInterval> tree = new IntervalTree<>();
    intervals.forEach(tree::insert);

    List<TestInterval> results = tree.lookup(point);

    assertThat(results).isEmpty();
  }

  private static Stream<Arguments> provider_returnNothing_when_outOfRange() {
    return Stream.of(
        Arguments.of(
            List.of(TestInterval.of(0, 5), TestInterval.of(3, 8), TestInterval.of(4, 10)),
            -1L,
            Collections.EMPTY_LIST),
        Arguments.of(
            List.of(TestInterval.of(0, 5), TestInterval.of(3, 8), TestInterval.of(4, 10)),
            11L,
            Collections.EMPTY_LIST));
  }

  @Test
  void shouldBalance_when_ascendingInsert() {
    IntervalTree<Long, TestInterval> tree = new IntervalTree<>();
    for (int i = 0; i < 100; i += 5) {
      tree.insert(TestInterval.of(i, i + 5));
    }

    assertThat(tree.isBalance()).isTrue();
  }

  @Test
  void shouldOverwrite_when_insertingSameRange() {
    IntervalTree<Long, TestInterval> tree = new IntervalTree<>();
    for (int i = 0; i < 100; i += 5) {
      tree.insert(TestInterval.of(i, i + 5));
    }

    assertThat(tree.isBalance()).isTrue();
  }

  @Test
  void shouldBalance_when_descendingInsert() {
    IntervalTree<Long, TestInterval> tree = new IntervalTree<>();
    for (int i = 100; i > 0; i -= 5) {
      tree.insert(TestInterval.of(i - 5, i));
      tree.insert(TestInterval.of("new_data_" + i, i - 5, i));
    }

    assertThat(tree.lookup(9L)).containsExactly(TestInterval.of("new_data_10", 5, 10));
  }

  @Value
  static class TestInterval implements Interval<Long> {

    private final String data;
    private final long low;
    private final long high;

    static TestInterval of(int low, int high) {
      return new TestInterval("default", low, high);
    }

    static TestInterval of(String data, int low, int high) {
      return new TestInterval(data, low, high);
    }

    @Override
    public Long low() {
      return low;
    }

    @Override
    public Long high() {
      return high;
    }
  }
}

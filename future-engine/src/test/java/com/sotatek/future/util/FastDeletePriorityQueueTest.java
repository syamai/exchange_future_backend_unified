package com.sotatek.future.util;

import static org.assertj.core.api.Assertions.assertThat;
import static org.junit.jupiter.api.Assertions.assertThrows;

import java.util.Comparator;
import java.util.List;
import java.util.NoSuchElementException;
import java.util.stream.StreamSupport;
import org.junit.jupiter.api.Test;

public class FastDeletePriorityQueueTest {

  @Test
  public void returnCorrectValue_when_addRemove() {
    FastDeletePriorityQueue<Integer> pq =
        new FastDeletePriorityQueue<>(Comparator.comparingInt(Integer::intValue));
    pq.add(123);
    pq.add(3);
    pq.add(5);
    assertThat(pq.size()).isEqualTo(3);
    assertThat(pq.peek()).isEqualTo(3);
    assertThat(pq.poll()).isEqualTo(3);
    assertThat(pq.size()).isEqualTo(2);

    pq.add(7);
    assertThat(pq.size()).isEqualTo(3);

    assertThat(pq.peek()).isEqualTo(5);
    assertThat(pq.poll()).isEqualTo(5);
    assertThat(pq.size()).isEqualTo(2);

    assertThat(pq.peek()).isEqualTo(7);
    assertThat(pq.poll()).isEqualTo(7);
    assertThat(pq.size()).isEqualTo(1);

    assertThat(pq.peek()).isEqualTo(123);
    assertThat(pq.poll()).isEqualTo(123);
    assertThat(pq.size()).isEqualTo(0);

    assertThrows(NoSuchElementException.class, pq::peek);
    assertThat(pq.poll()).isNull();
  }

  @Test
  public void returnCorrectItemFromQueue_when_iterate() {
    FastDeletePriorityQueue<Integer> pq =
        new FastDeletePriorityQueue<>(Comparator.comparingInt(Integer::intValue));
    pq.add(123);
    pq.add(3);
    pq.add(5);

    List<Integer> queueAsIterable = StreamSupport.stream(pq.spliterator(), false).toList();
    assertThat(queueAsIterable).containsExactlyInAnyOrder(3, 5, 123);

    pq.remove(5);
    List<Integer> queueAsStream2 = StreamSupport.stream(pq.spliterator(), false).toList();
    assertThat(queueAsStream2).containsExactlyInAnyOrder(3, 123);

    pq.add(1);
    pq.add(100);
    List<Integer> queueAsStream3 = StreamSupport.stream(pq.spliterator(), false).toList();
    assertThat(queueAsStream3).containsExactlyInAnyOrder(1, 3, 100, 123);

    for (Integer a : queueAsStream3) {
      if (a % 2 == 1) {
        pq.remove(a);
      }
    }
    assertThat(pq.peek()).isEqualTo(100);
    assertThat(pq.size()).isEqualTo(1);
    assertThat(pq.poll()).isEqualTo(100);
    assertThat(pq.size()).isEqualTo(0);
    assertThat(pq.isEmpty()).isTrue();
  }
}

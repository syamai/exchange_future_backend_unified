package com.sotatek.future.service;

import static org.assertj.core.api.Assertions.assertThat;

import com.sotatek.future.entity.Position;
import com.sotatek.future.service.PositionPnlRankingIndexer.PositionPnlIndexValue;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Optional;
import java.util.stream.Stream;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.Arguments;
import org.junit.jupiter.params.provider.MethodSource;

public class PositionPnlRankingIndexerTest {
  private static final String testSymbol = "BTCUSD";

  @ParameterizedTest
  @MethodSource("provider_returnCorrectPositionOrder_when_notUpdating")
  void returnCorrectPositionOrder_when_lookUp(
      List<Position> positions,
      String symbol,
      List<Long> expectedShorts,
      List<Long> expectedLongs) {
    PositionPnlRankingIndexer indexer = new PositionPnlRankingIndexer();
    for (Position p : positions) {
      indexer.updatePnlRankingIndex(p);
    }

    validatePositions(indexer, symbol, expectedShorts, false);
    validatePositions(indexer, symbol, expectedLongs, true);
  }

  static Stream<Arguments> provider_returnCorrectPositionOrder_when_notUpdating() {
    return Stream.of(
        Arguments.of(
            List.of(
                newPosition(1L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("1")),
                newPosition(2L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("2")),
                newPosition(3L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("3"))),
            testSymbol,
            Collections.emptyList(),
            List.of(3L, 2L, 1L)),
        Arguments.of(
            List.of(
                newPosition(
                    4L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("3")),
                newPosition(
                    5L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("1")),
                newPosition(
                    6L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("2"))),
            testSymbol,
            List.of(4L, 6L, 5L),
            Collections.emptyList()),
        Arguments.of(
            List.of(
                newPosition(1L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("1")),
                newPosition(2L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("2")),
                newPosition(3L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("3")),
                newPosition(
                    4L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("3")),
                newPosition(
                    5L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("2")),
                newPosition(
                    6L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("1"))),
            testSymbol,
            List.of(4L, 5L, 6L),
            List.of(3L, 2L, 1L)),
        Arguments.of(
            List.of(
                newPosition(1L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("1")),
                newPosition(2L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("2")),
                newPosition(1L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("3")),
                newPosition(3L, testSymbol, MarginBigDecimal.ONE, MarginBigDecimal.valueOf("3")),
                newPosition(
                    4L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("3")),
                newPosition(
                    5L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("2")),
                newPosition(
                    4L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("1")),
                newPosition(
                    6L, testSymbol, MarginBigDecimal.NEGATIVE_ONE, MarginBigDecimal.valueOf("3"))),
            testSymbol,
            List.of(6L, 5L, 4L),
            List.of(1L, 3L, 2L)));
  }

  private static Position newPosition(
      Long accountId, String symbol, MarginBigDecimal quantity, MarginBigDecimal pnlRanking) {
    Position p = new Position();
    p.setAccountId(accountId);
    p.setSymbol(symbol);
    p.setPnlRanking(pnlRanking);
    p.setCurrentQty(quantity);
    return p;
  }

  private void validatePositions(
      PositionPnlRankingIndexer indexer, String symbol, List<Long> expected, boolean longPosition) {
    List<Long> actual = new ArrayList<>();
    while (true) {
      Optional<PositionPnlIndexValue> iter = indexer.poll(symbol, longPosition);
      if (iter.isPresent()) {
        actual.add(iter.get().getAccountId());
      } else {
        break;
      }
    }
    assertThat(actual).containsExactlyElementsOf(expected);
  }
}

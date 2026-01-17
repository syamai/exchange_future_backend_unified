package com.sotatek.future.service;

import com.sotatek.future.entity.Position;
import com.sotatek.future.util.FastDeletePriorityQueue;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Comparator;
import java.util.HashMap;
import java.util.Map;
import java.util.Optional;
import lombok.EqualsAndHashCode.Exclude;
import lombok.Value;
import lombok.extern.slf4j.Slf4j;

/** Indexer for maintaining PnlRanking index for each symbol */
@Slf4j
public class PositionPnlRankingIndexer {

  // Maintain indices to quickly get the highest pnlRanking position of each symbol
  // We have 1 index for long position and 1 index for short position
  // We cannot use TreeSet here, because TreeSet removal operation uses Comparator to compare items
  // instead of equals and hashCode, leading to wrong removal of position with the same PnlRanking
  private final Map<String, FastDeletePriorityQueue<PositionPnlIndexValue>> shortPnlRankingIndex =
      new HashMap<>();
  private final Map<String, FastDeletePriorityQueue<PositionPnlIndexValue>> longPnlRankingIndex =
      new HashMap<>();

  public void updatePnlRankingIndex(Position p) {
    String symbol = p.getSymbol();
    Long accountId = p.getAccountId();
    FastDeletePriorityQueue<PositionPnlIndexValue> symbolShortPnlRankingIndex =
        getSymbolPnlRankingIndex(shortPnlRankingIndex, symbol);
    FastDeletePriorityQueue<PositionPnlIndexValue> symbolLongPnlRankingIndex =
        getSymbolPnlRankingIndex(longPnlRankingIndex, symbol);

    MarginBigDecimal pnlRanking = p.getPnlRanking();
    PositionPnlIndexValue newValue = new PositionPnlIndexValue(accountId, symbol, pnlRanking);
    // Remove old value from both index
    symbolShortPnlRankingIndex.remove(newValue);
    symbolLongPnlRankingIndex.remove(newValue);
    // Update index value by reinserting items
    if (p.getCurrentQty().lt(MarginBigDecimal.ZERO)) {
      // Update short index
      symbolShortPnlRankingIndex.add(newValue);
    } else if (p.getCurrentQty().gt(MarginBigDecimal.ZERO)) {
      // Update long index
      symbolLongPnlRankingIndex.add(newValue);
    }
  }

  /**
   * Remove and return the first position from the index for a symbol
   *
   * @param longPosition when True return long position, when False return short position
   * @return
   */
  public Optional<PositionPnlIndexValue> poll(String symbol, boolean longPosition) {
    Optional<FastDeletePriorityQueue<PositionPnlIndexValue>> symbolPnlRankingIndexOpt;
    if (longPosition) {
      symbolPnlRankingIndexOpt = Optional.ofNullable(longPnlRankingIndex.get(symbol));
    } else {
      symbolPnlRankingIndexOpt = Optional.ofNullable(shortPnlRankingIndex.get(symbol));
    }
    return symbolPnlRankingIndexOpt.map(FastDeletePriorityQueue::poll);
  }

  private FastDeletePriorityQueue<PositionPnlIndexValue> getSymbolPnlRankingIndex(
      Map<String, FastDeletePriorityQueue<PositionPnlIndexValue>> pnlRankingIndex, String symbol) {
    FastDeletePriorityQueue<PositionPnlIndexValue> symbolPnlRankingIndex;
    if (!pnlRankingIndex.containsKey(symbol)) {
      symbolPnlRankingIndex = new FastDeletePriorityQueue<>(PnlRankingComparator.COMPARATOR);
      pnlRankingIndex.put(symbol, symbolPnlRankingIndex);
    } else {
      symbolPnlRankingIndex = pnlRankingIndex.get(symbol);
    }
    return symbolPnlRankingIndex;
  }

  @Value
  public class PositionPnlIndexValue {

    private final Long accountId;
    private final String symbol;

    @Exclude private final MarginBigDecimal pnlRanking;
  }

  static class PnlRankingComparator implements Comparator<PositionPnlIndexValue> {

    static Comparator<PositionPnlIndexValue> COMPARATOR = new PnlRankingComparator();

    private PnlRankingComparator() {}

    @Override
    public int compare(PositionPnlIndexValue o1, PositionPnlIndexValue o2) {
      // Make o1 appears before o2 if it has a higher ranking
      return 0 - o1.getPnlRanking().compareTo(o2.getPnlRanking());
    }
  }
}

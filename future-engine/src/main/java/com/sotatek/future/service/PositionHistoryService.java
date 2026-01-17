package com.sotatek.future.service;

import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.PositionHistory;
import com.sotatek.future.enums.PositionHistoryAction;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.Date;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;
import org.apache.commons.lang3.tuple.Pair;

@Slf4j
public class PositionHistoryService extends BaseService<PositionHistory> {

  private static final PositionHistoryService instance = new PositionHistoryService();

  protected Map<Long, List<PositionHistory>> positionHistoryMap = new HashMap<>();
  private static int fundingPeriodSecond = 60 * 1000;
  private static int fundingInterval8Hour = 60 * 60 * 8 * 1000;
  private static long expireEntityPeriodMili = 86400 * 1000; // 1 day

  private PositionHistoryService() {
    super(false);
  }

  public static PositionHistoryService getInstance() {
    return instance;
  }

  public void initialize() {
    // nothing to do
  }

  @Override
  public PositionHistory get(Object key) {
    throw new RuntimeException("Get method is not supported");
  }

  @Override
  public void commit() {
    for (var entry : processingEntities.entrySet()) {
      PositionHistory entity = entry.getValue();
      List<PositionHistory> histories = positionHistoryMap.get(entity.getPositionId());
      if (histories == null) {
        histories = new ArrayList<>();
        positionHistoryMap.put(entity.getPositionId(), histories);
      }
      histories.add(entity);
    }
    processingEntities.clear();
  }

  @Override
  public List<PositionHistory> getEntities() {
    throw new RuntimeException("This method is not supported");
  }

  @Override
  public List<PositionHistory> getCurrentEntities() {
    throw new RuntimeException("This method is not supported");
  }

  @Override
  public void clear() {
    positionHistoryMap.clear();
  }

  public void log(PositionHistoryAction action, Position oldPosition, Position newPosition) {
    PositionHistory history = PositionHistory.from(action, oldPosition, newPosition);
    if (oldPosition.getCurrentQty().eq(newPosition.getCurrentQty())) {
      log.atDebug()
          .addKeyValue("history", history)
          .log("ignore save position history when position quantity not change ");
    } else {
      insert(history);
      this.removeOldEntity(history);
    }
  }

  /**
   * Lookup the nearest point in history for a position id. It can be either:
   *
   * <ul>
   *   <li>The first point if date is before the first recorded history
   *   <li>The last point if date is after the last recorded history
   *   <li>The nearest point where creation_time <= date
   * </ul>
   *
   * @param position
   * @param date
   * @return
   */
  public MarginBigDecimal getPositionHistoryQuantity(Position position, Date date) {
    List<PositionHistory> histories = positionHistoryMap.get(position.getId());
    if (ObjectUtils.isEmpty(histories)) {
      return position.getCurrentQty();
    }
    // get the nearest position history on before or after a minute at time which charge funding fee
    long startFundingInterval = date.getTime() - fundingInterval8Hour;
    long start = date.getTime() - fundingPeriodSecond;
    long end = date.getTime() + fundingPeriodSecond;

    List<PositionHistory> history =
        histories.stream()
            .filter(
                p ->
                    p.getCreatedAt().getTime() >= startFundingInterval
                        && p.getCreatedAt().getTime() <= end)
            .sorted(Comparator.comparing(PositionHistory::getCreatedAt).reversed())
            .collect(Collectors.toList());

    log.atDebug()
        .addKeyValue("startFundingInterval", startFundingInterval)
        .addKeyValue("end", end)
        .addKeyValue("history", history)
        .addKeyValue("positionId", position.getId())
        .log("found history for position");

    if (history != null && history.size() != 0) {
      PositionHistory nearestHistory = history.get(0);
      if (nearestHistory.getCurrentQtyAfter().eq(0)) {
        if (nearestHistory.getCreatedAt().getTime() >= start
            && nearestHistory.getCreatedAt().getTime() <= end) {
          // lookup first history
          PositionHistory firstHistoryInRange =
              history.stream()
                  .filter(
                      p -> p.getCreatedAt().getTime() >= start && p.getCreatedAt().getTime() <= end)
                  .min(Comparator.comparing(PositionHistory::getCreatedAt))
                  .orElse(nearestHistory);
          // return current before
          return firstHistoryInRange.getCurrentQty();
        } else {
          return MarginBigDecimal.ZERO;
        }
      } else {
        // return current after
        return nearestHistory.getCurrentQtyAfter();
      }
    } else {
      PositionHistory futureHistory =
          histories.stream()
              .filter(p -> p.getCreatedAt().getTime() > end)
              .min(Comparator.comparing(PositionHistory::getCreatedAt))
              .orElse(null);

      log.atDebug()
          .addKeyValue("futureHistory", futureHistory)
          .log("getting nearest future history");
      if (futureHistory != null) {
        // return current before
        return futureHistory.getCurrentQty();
      } else {
        // return current
        return position.getCurrentQty();
      }
    }
  }

  @Override
  public void removeOldEntity(PositionHistory positionHistory) {
    removingEntities.add(
        Pair.of(
            positionHistory, positionHistory.getCreatedAt().getTime() + expireEntityPeriodMili));
  }

  @Override
  public void cleanOldEntities() {
    if (removingEntities.size() == 0) {
      return;
    }
    Pair<PositionHistory, Long> oldEntity = removingEntities.peek();
    while (oldEntity != null && oldEntity.getRight() < System.currentTimeMillis()) {
      PositionHistory entity = oldEntity.getLeft();
      List<PositionHistory> histories = positionHistoryMap.get(entity.getPositionId());
      if (histories != null) {
        histories.remove(entity);
      }
      removingEntities.remove();
      oldEntity = removingEntities.peek();
    }
  }
}

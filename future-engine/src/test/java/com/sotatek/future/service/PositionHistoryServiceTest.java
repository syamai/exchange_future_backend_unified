//package com.sotatek.future.service;
//
//import static org.junit.jupiter.api.Assertions.assertEquals;
//import static org.junit.jupiter.api.Assertions.assertNull;
//
//import com.sotatek.future.BaseTest;
//import com.sotatek.future.entity.PositionHistory;
//import com.sotatek.future.util.MarginBigDecimal;
//import java.util.Date;
//import org.junit.jupiter.api.AfterEach;
//import org.junit.jupiter.api.BeforeEach;
//import org.junit.jupiter.api.Test;
//
//public class PositionHistoryServiceTest extends BaseTest {
//
//  private long positionHistoryId;
//
//  @Override
//  @BeforeEach
//  public void setUp() throws Exception {
//    super.setUp();
//    ServiceFactory.initialize();
//    this.positionHistoryId = 1;
//  }
//
//  @Override
//  @AfterEach
//  public void tearDown() throws Exception {
//    super.tearDown();
//  }
//
//  @Test
//  void returnLastPosition_when_dateAfterHistory() {
//    this.positionHistoryService.update(this.createPositionHistory(1, null, "1", 1));
//    this.positionHistoryService.update(this.createPositionHistory(1, "1", "2", 2));
//    this.positionHistoryService.update(this.createPositionHistory(1, "2", "3", 3));
//    this.positionHistoryService.commit();
//
//    PositionHistory history = this.positionHistoryService.getNearestHistory(1L, new Date(4));
//    assertEquals(MarginBigDecimal.valueOf("3"), history.getCurrentQtyAfter());
//  }
//
//  @Test
//  void returnFirstPosition_when_dateBeforeHistory() {
//    this.positionHistoryService.update(this.createPositionHistory(2, "3", "4", 4));
//    this.positionHistoryService.update(this.createPositionHistory(2, "4", "5", 5));
//    this.positionHistoryService.update(this.createPositionHistory(2, "5", "6", 6));
//    this.positionHistoryService.commit();
//
//    PositionHistory history = this.positionHistoryService.getNearestHistory(2L, new Date(3));
//    assertEquals(MarginBigDecimal.valueOf("4"), history.getCurrentQtyAfter());
//  }
//
//  @Test
//  void returnNull_when_noHistory() {
//    PositionHistory history = this.positionHistoryService.getNearestHistory(4L, new Date(1));
//    assertNull(history);
//  }
//
//  @Test
//  void returnNearestPositionBeforePointInTime_when_queryHistory() {
//    this.positionHistoryService.update(this.createPositionHistory(3, "6", "7", 7));
//    this.positionHistoryService.update(this.createPositionHistory(3, "7", "8", 8));
//    this.positionHistoryService.update(this.createPositionHistory(3, "8", "9", 9));
//    this.positionHistoryService.update(this.createPositionHistory(3, "8", "12", 12));
//    this.positionHistoryService.commit();
//
//    PositionHistory history = this.positionHistoryService.getNearestHistory(3L, new Date(10));
//    assertEquals(MarginBigDecimal.valueOf("9"), history.getCurrentQtyAfter());
//
//    history = this.positionHistoryService.getNearestHistory(3L, new Date(9));
//    assertEquals(MarginBigDecimal.valueOf("9"), history.getCurrentQtyAfter());
//
//    history = this.positionHistoryService.getNearestHistory(3L, new Date(8));
//    assertEquals(MarginBigDecimal.valueOf("8"), history.getCurrentQtyAfter());
//  }
//
//  private PositionHistory createPositionHistory(
//      long positionId, String quantity, String quantityAfter, long time) {
//    PositionHistory history = new PositionHistory();
//    history.setId(this.positionHistoryId++);
//    history.setPositionId(positionId);
//    if (quantity != null) {
//      history.setCurrentQty(MarginBigDecimal.valueOf(quantity));
//    }
//    if (quantityAfter != null) {
//      history.setCurrentQtyAfter(MarginBigDecimal.valueOf(quantityAfter));
//    }
//    history.setCreatedAt(new Date(time));
//    return history;
//  }
//}

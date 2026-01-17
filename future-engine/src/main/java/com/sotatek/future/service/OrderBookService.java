package com.sotatek.future.service;

import com.sotatek.future.entity.OrderBookOutput;
import java.util.ArrayList;
import java.util.List;
import lombok.AccessLevel;
import lombok.NoArgsConstructor;
import lombok.extern.slf4j.Slf4j;

@Slf4j
@NoArgsConstructor(access = AccessLevel.PRIVATE)
public class OrderBookService {
  private static final OrderBookService instance = new OrderBookService();
  protected List<OrderBookOutput> processingEntities = new ArrayList<>();

  public static OrderBookService getInstance() {
    return instance;
  }

  public void update(OrderBookOutput output) {
    processingEntities.add(output);
  }

  public void rollback() {
    processingEntities.clear();
  }

  public void commit() {
    processingEntities.clear();
  }

  public List<OrderBookOutput> getProcessingEntities() {
    return new ArrayList<>(processingEntities);
  }
}

package com.sotatek.future.service;

import com.sotatek.future.entity.Trade;
import java.util.ArrayList;
import java.util.List;

public class TradeService extends BaseService<Trade> {

  private static final TradeService instance = new TradeService();

  protected final List<Trade> processingEntities = new ArrayList();

  private TradeService() {
    super(true);
  }

  public static TradeService getInstance() {
    return instance;
  }

  public void initialize() {
    // Nothing to do
  }

  @Override
  public Trade get(Object key) {
    throw new RuntimeException("This method is not supported");
  }

  @Override
  public Trade update(Trade entity) {
    throw new RuntimeException("This method is not supported");
  }

  @Override
  public Trade insert(Trade entity) {
    assignNewId(entity);
    processingEntities.add(entity);
    return entity;
  }

  @Override
  public void rollback() {
    processingEntities.clear();
  }

  @Override
  public void commit() {
    processingEntities.clear();
    temporaryEntities.clear();
  }

  @Override
  public List<Trade> getProcessingEntities() {
    return new ArrayList<>(processingEntities);
  }

  @Override
  public List<Trade> getEntities() {
    throw new RuntimeException("This method is not supported");
  }

  @Override
  public void clear() {
    processingEntities.clear();
  }
}

package com.sotatek.future.service;

import com.sotatek.future.entity.LeverageMargin;
import java.util.List;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class LeverageMarginService extends BaseService<LeverageMargin> {

  private static final LeverageMarginService instance = new LeverageMarginService();

  public static LeverageMarginService getInstance() {
    return instance;
  }

  public void initialize() {
    // do nothing
  }

  public LeverageMarginService() {
    super(false);
  }

  public void upsertEntitys(List<LeverageMargin> leverageMargins) {
    for (LeverageMargin leverageMargin : leverageMargins) {
      entities.put(leverageMargin.getId(), leverageMargin);
    }
  }

  public List<LeverageMargin> getLeverageMargins(String symbol) {
    return entities.values().stream().filter(lm -> lm.getSymbol().equals(symbol)).toList();
  }
}

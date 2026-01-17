package com.sotatek.future.service;

import static com.sotatek.future.engine.MatchingEngine.triggers;

import com.sotatek.future.engine.Trigger;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.exception.InstrumentNotFoundException;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;

@Slf4j
public class InstrumentService extends BaseService<Instrument> {

  private static final InstrumentService instance = new InstrumentService();
  protected Map<Object, InstrumentExtraInformation> extraInfoEntities = new HashMap<>();

  private InstrumentService() {
    super(false);
  }

  public static InstrumentService getInstance() {
    return instance;
  }

  @Override
  public Instrument get(Object key) {
    Instrument target = super.get(key);
    if (ObjectUtils.isEmpty(target)) {
      throw new InstrumentNotFoundException(target.getSymbol());
    }
    return target;
  }

  @Override
  public Instrument update(Instrument entity) {
    entity.updatePrecisions();
    return super.update(entity);
  }

  public InstrumentExtraInformation getExtraInfo(String symbol) {
    InstrumentExtraInformation extraInformation = extraInfoEntities.get(symbol);
    if (extraInformation != null) {
      // Don't clone extra information, we need to get the latest market price
      return extraInformation;
    }
    return null;
  }

  public void updateExtraInfo(InstrumentExtraInformation entity) {
    extraInfoEntities.put(entity.getSymbol(), entity);
  }

  public void clearExtraInfo() {
    extraInfoEntities.clear();
  }

  public void updateLastPrice(List<Trade> trades) {
    for (Trade trade : trades) {
      InstrumentExtraInformation extraInformation = getExtraInfo(trade.getSymbol());
      InstrumentExtraInformation oldInstrumentExtra = extraInformation.deepCopy();
      extraInformation.setLastPrice(trade.getPrice());
      updateExtraInfo(extraInformation);
      commit();
      log.debug(
          "updateLastPrice for symbol {} and oldPrice {} newPrice {}",
          trade.getSymbol(),
          oldInstrumentExtra.getLastPrice(),
          extraInformation.getLastPrice());
      // update trailing price for trailing stop order
      // get trigger of this instrument
      Trigger trigger = triggers.get(trade.getSymbol());
      // update trailing price for all trailing stop order of that instrument
      if (ObjectUtils.isNotEmpty(trigger)) {
        trigger.updateTrailingPrice(oldInstrumentExtra, extraInformation);
      }
    }
  }
}

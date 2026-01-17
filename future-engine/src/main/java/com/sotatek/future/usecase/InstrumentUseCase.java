package com.sotatek.future.usecase;

import static com.sotatek.future.engine.MatchingEngine.matchers;
import static com.sotatek.future.engine.MatchingEngine.triggers;

import com.sotatek.future.engine.Matcher;
import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.engine.Trigger;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.OrderBookOutput;
import com.sotatek.future.output.OrderBookOutputStream;
import com.sotatek.future.output.OutputStream;
import com.sotatek.future.service.InstrumentService;
import lombok.RequiredArgsConstructor;
import org.apache.commons.lang3.ObjectUtils;

@RequiredArgsConstructor
public class InstrumentUseCase {

  private final InstrumentService instrumentService;

  private final MatchingEngine matchingEngine;

  public void updateInstrument(
      Command command, OutputStream<OrderBookOutput> orderBookOutputStream) {
    Instrument instrument = command.getInstrument();
    instrumentService.update(instrument);
    instrumentService.commit();

    String symbol = instrument.getSymbol();
    matchers.putIfAbsent(symbol, new Matcher(instrument.getSymbol()));
    if (matchers.get(symbol) == null) {
      if (orderBookOutputStream instanceof OrderBookOutputStream) {
        ((OrderBookOutputStream) orderBookOutputStream).refreshOrderBook(symbol);
      }
    }
    triggers.putIfAbsent(symbol, new Trigger(symbol, matchingEngine));
    matchingEngine.commit();
  }

  public void updateInstrumentExtra(Command command) {
    InstrumentExtraInformation instrumentExtra = command.getInstrumentExtraInformation();
    InstrumentExtraInformation oldInstrumentExtra =
        instrumentService.getExtraInfo(instrumentExtra.getSymbol());
    // get trigger of this instrument
    Trigger trigger = triggers.get(instrumentExtra.getSymbol());
    // update trailing price for all trailing stop order of that instrument
    if (ObjectUtils.isNotEmpty(trigger)) {
      trigger.updateTrailingPrice(oldInstrumentExtra, instrumentExtra);
    }
    // handle update instrument extra info
    instrumentService.updateExtraInfo(instrumentExtra);
    instrumentService.commit();
    matchingEngine.commit();
  }
}

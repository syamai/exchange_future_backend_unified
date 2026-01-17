package com.sotatek.future.ticker;

import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Ticker;
import com.sotatek.future.entity.Trade;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;
import com.sotatek.future.input.InputStream;
import com.sotatek.future.input.OnNewDataListener;
import com.sotatek.future.output.OutputStream;
import com.sotatek.future.util.TimeUtil;
import java.io.IOException;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.BlockingQueue;
import java.util.concurrent.LinkedBlockingQueue;
import java.util.concurrent.TimeoutException;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class TickerEngine implements OnNewDataListener<List<CommandOutput>> {
  private final long publishInterval = 100; // in millis second
  private final Map<String, TickerProcessor> processors = new HashMap<>();
  private InputStream<List<CommandOutput>> inputStream;
  private InputStream<List<CommandOutput>> preloadStream;
  private OutputStream<List<Ticker>> outputStream;
  private final BlockingQueue<CommandOutput> commands = new LinkedBlockingQueue<>();
  private long lastProcessedTradeId = -1;
  private long lastPublishTime = 0;
  protected boolean isPreloadCompleted = false;

  public TickerEngine(
      InputStream<List<CommandOutput>> preloadStream,
      InputStream<List<CommandOutput>> inputStream,
      OutputStream<List<Ticker>> outputStream) {
    this.inputStream = inputStream;
    this.preloadStream = preloadStream;
    this.outputStream = outputStream;
  }

  public void initialize()
      throws InvalidMatchingEngineConfigException, IOException, TimeoutException {
    startPreloadStream();
    outputStream.connect();
  }

  private void startPreloadStream() throws InvalidMatchingEngineConfigException {
    preloadStream.setOnNewDataListener(this);
    try {
      preloadStream.connect();
    } catch (IOException | TimeoutException e) {
      throw new RuntimeException(e);
    }
  }

  private void startInputStream() throws InvalidMatchingEngineConfigException {
    this.inputStream.setOnNewDataListener(this);
    try {
      inputStream.connect();
    } catch (IOException | TimeoutException e) {
      throw new RuntimeException(e);
    }
  }

  @Override
  public long onNewData(List<CommandOutput> commandOutputs) {
    commands.addAll(commandOutputs);
    return commands.size();
  }

  public void start() {
    while (true) {
      try {
        CommandOutput command = commands.poll();
        if (command != null) {
          if (command.getCode() == CommandCode.START_ENGINE) {
            processStartCommand();
            continue;
          }
          if (command.getCode() == CommandCode.UPDATE_INSTRUMENT) {
            // command.getInstrument() just mapping symbol and multiplier from a map
            setupTickerProcessor(command.getInstrument());
            continue;
          }
          if (command.getCode() == CommandCode.UPDATE_INSTRUMENT_EXTRA) {
            // command.getInstrumentExtra() just mapping symbol and lastPrice from a map
            updateInstrumentExtra(command.getInstrumentExtra());
            continue;
          }
          if (command.getTrades() != null) {
            command.getTrades().forEach(this::processTrade);
          }
        } else {
          if (inputStream != null && inputStream.isClosed()) {
            break;
          } else {
            sleep(20);
          }
        }
        if (shouldPublishTicker()) {
          publish();
        }
      } catch (Exception e) {
        // just log to keep thread running
        log.error("get exception when process", e);
      }
    }
  }

  private void setupTickerProcessor(Instrument instrument) {
    TickerProcessor processor = processors.get(instrument.getSymbol());
    if (processor == null) {
      processor = new TickerProcessor(instrument.getSymbol());
      processors.put(instrument.getSymbol(), processor);
    }
    processor.setInstrument(instrument);
  }

  private void updateInstrumentExtra(InstrumentExtraInformation instrumentExtraInformation) {
    TickerProcessor processor = processors.get(instrumentExtraInformation.getSymbol());
    if (processor != null) {
      processor.setInstrumentExtra(instrumentExtraInformation);
    } else {
      log.atWarn()
          .addKeyValue("symbol", instrumentExtraInformation.getSymbol())
          .log("Ticker processor not found to update InstrumentExtra");
    }
  }

  private void processStartCommand() {
    preloadStream.close();
    isPreloadCompleted = true;
    log.info("Start ticker engine");
    startInputStream();
  }

  private void processTrade(Trade trade) {
    if (trade.getId() <= lastProcessedTradeId) {
      log.debug("Ignore old trade {} Current id {}", trade, lastProcessedTradeId);
    }
    String symbol = trade.getSymbol();
    TickerProcessor processor = processors.get(symbol);
    if (processor == null) {
      processor = new TickerProcessor(symbol);
      processors.put(symbol, processor);
    }
    processor.processTrade(trade);
    lastProcessedTradeId = trade.getId();
  }

  private boolean shouldPublishTicker() {
    if (!isPreloadCompleted) {
      return false;
    }
    return lastPublishTime + publishInterval < TimeUtil.currentTimeMilliSeconds();
  }

  public void publish() {
    List<Ticker> tickers = new ArrayList<>();
    for (TickerProcessor processor : processors.values()) {
      processor.clean();
      tickers.add(processor.getTicker());
    }
    try {
      //      log.info("Ticker publish info {}", tickers);
      outputStream.write(tickers);
    } catch (Exception e) {
      log.error("Ticker output stream has an error", e);
    }
    lastPublishTime = TimeUtil.currentTimeMilliSeconds();
  }

  private void sleep(long time) {
    try {
      Thread.sleep(time);
    } catch (InterruptedException e) {
      log.error(e.getMessage(), e);
    }
  }
}

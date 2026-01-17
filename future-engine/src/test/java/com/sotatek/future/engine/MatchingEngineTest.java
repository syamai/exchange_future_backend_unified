package com.sotatek.future.engine;

import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.CommandOutput;
import com.sotatek.future.entity.EngineParams;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.InputDriver;
import com.sotatek.future.enums.OutputDriver;
import com.sotatek.future.exception.InvalidMatchingEngineConfigException;
import com.sotatek.future.input.InputStream;
import com.sotatek.future.input.ListInputStream;
import com.sotatek.future.input.TestInputStream;
import com.sotatek.future.output.ListOrderBookStream;
import com.sotatek.future.output.ListOutputStream;
import com.sotatek.future.service.InstrumentService;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.common.serialization.StringSerializer;

public class MatchingEngineTest {

  public static void main(String[] args) throws Exception {
    new MatchingEngineTest().testKafka();
  }

  private void testKafka() throws Exception {
    this.createInstrument();
    long start;

    MatchingEngineConfig config = new MatchingEngineConfig();
    config.setTesting(true);

    config.setCommandInputDriver(InputDriver.AUTO_GENERATE);
    config.setCommandInputStream(new TestInputStream(10000, 10001, TestInputStream.MATCHING));

    config.setCommandPreloadDriver(InputDriver.JAVA_LIST);
    config.setCommandPreloadStream(this.createPreloadStream());

    config.setCommandOutputDriver(OutputDriver.JAVA_LIST);
    config.setOrderBookOutputDriver(OutputDriver.JAVA_LIST);
    ListOutputStream<CommandOutput> listOutputStream = new ListOutputStream<>();
    config.setCommandOutputStream(listOutputStream);
    ListOrderBookStream listOrderBookStream = new ListOrderBookStream(100);
    config.setOrderBookOutputStream(listOrderBookStream);

    Map<String, Object> outputParams = new HashMap<>();
    outputParams.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
    outputParams.put(
        ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, StringSerializer.class.getName());
    config.setOutputParameters(outputParams);

    MatchingEngine matchingEngine = MatchingEngine.getInstance();
    try {
      matchingEngine.initialize(config);
      //            Thread.sleep(5000);
    } catch (InvalidMatchingEngineConfigException e) {
      e.printStackTrace();
    }

    start = System.currentTimeMillis();
    matchingEngine.start();
    System.out.println("Finish " + (System.currentTimeMillis() - start) + "ms");
  }

  protected InputStream<Command> createPreloadStream() {
    List<Command> commands = new ArrayList<>();
    EngineParams engineParams = new EngineParams();
    engineParams.setLastOrderId(0);
    engineParams.setLastPositionId(0);
    engineParams.setLastTradeId(0);
    engineParams.setLastMarginHistoryId(0);
    engineParams.setLastPositionHistoryId(0);
    engineParams.setLastFundingHistoryId(0);
    commands.add(new Command(CommandCode.INITIALIZE_ENGINE, engineParams));
    for (Instrument instrument : InstrumentService.getInstance().getEntities()) {
      commands.add(new Command(CommandCode.UPDATE_INSTRUMENT, instrument));
      commands.add(
          new Command(
              CommandCode.UPDATE_INSTRUMENT_EXTRA,
              InstrumentService.getInstance().getExtraInfo(instrument.getSymbol())));
    }
    commands.add(new Command(CommandCode.START_ENGINE, null));
    return new ListInputStream<>(commands);
  }

  private void test0b() throws Exception {
    long time = System.nanoTime();

    this.createInstrument();

    MatchingEngineConfig config = new MatchingEngineConfig();
    config.setTesting(true);
    config.setCommandInputDriver(InputDriver.AUTO_GENERATE);
    config.setCommandInputStream(new TestInputStream(100000, 1, TestInputStream.MATCHING));
    config.setCommandOutputDriver(OutputDriver.JAVA_LIST);
    ListOutputStream orderOutputStream = new ListOutputStream();
    config.setCommandOutputStream(orderOutputStream);
    config.setOrderBookOutputDriver(OutputDriver.JAVA_LIST);
    ListOutputStream orderbookOutputStream = new ListOutputStream();
    config.setOrderBookOutputStream(orderbookOutputStream);

    MatchingEngine matchingEngine = MatchingEngine.getInstance();
    try {
      matchingEngine.initialize(config);
      //            Thread.sleep(2000);
    } catch (InvalidMatchingEngineConfigException e) {
      e.printStackTrace();
    }

    time = System.currentTimeMillis();
    matchingEngine.start();
    System.out.println("Finish" + (System.currentTimeMillis() - time));
  }

  protected Instrument createInstrument() {
    Instrument instrument = new Instrument();
    instrument.setSymbol("BTCUSDT");
    instrument.setRootSymbol("BTC");
    instrument.setState("Open");
    instrument.setType(0);
    instrument.setInitMargin(MarginBigDecimal.valueOf("0.01"));
    instrument.setMaintainMargin(MarginBigDecimal.valueOf("0.005"));
    instrument.setMultiplier(MarginBigDecimal.ONE);
    instrument.setTickSize(MarginBigDecimal.valueOf("0.01"));
    instrument.setContractSize(MarginBigDecimal.valueOf("0.000001"));
    instrument.setLotSize(MarginBigDecimal.valueOf("100"));
    instrument.setReferenceIndex("BTC");
    instrument.setFundingBaseIndex("BTCBON8H");
    instrument.setFundingQuoteIndex("USDBON8H");
    instrument.setFundingPremiumIndex("BTCUSDPI8H");
    instrument.setFundingInterval(8);
    //        instrument.setRiskLimit(MarginBigDecimal.valueOf(10000000));
    instrument.setMaxPrice(MarginBigDecimal.valueOf(1000000));
    instrument.setMaxOrderQty(MarginBigDecimal.valueOf(1000000));
    instrument.setTakerFee(MarginBigDecimal.valueOf("0.00075"));
    instrument.setMakerFee(MarginBigDecimal.valueOf("0.00025"));

    InstrumentService.getInstance().update(instrument);
    InstrumentService.getInstance().commit();
    InstrumentService.getInstance().updateExtraInfo(this.createInstrumentExtraInformation());

    return instrument;
  }

  protected InstrumentExtraInformation createInstrumentExtraInformation() {
    InstrumentExtraInformation instrumentExtraInformation = new InstrumentExtraInformation();
    instrumentExtraInformation.setSymbol("BTCUSDT");
    instrumentExtraInformation.setOraclePrice(MarginBigDecimal.valueOf(28000));
    return instrumentExtraInformation;
  }
}

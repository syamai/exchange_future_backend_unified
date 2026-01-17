package com.sotatek.future;

import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.service.ServiceFactory;
import com.sotatek.future.util.MarginBigDecimal;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;

public class BaseMatchingEngineTest extends BaseTest {

  @Override
  @BeforeEach
  public void setUp() throws Exception {
    super.setUp();
    ServiceFactory.initialize();
    this.setUpAccount(1, this.defaultBalance);
    this.setUpAccount(2, this.defaultBalance);
  }

  @Override
  @AfterEach
  public void tearDown() throws Exception {
    super.tearDown();
  }

  protected void setLastPrice(String price) {
    InstrumentExtraInformation instrumentExtra =
        this.instrumentService.getExtraInfo(this.defaultSymbol);
    instrumentExtra.setLastPrice(MarginBigDecimal.valueOf(price));
    this.instrumentService.updateExtraInfo(instrumentExtra);
  }

  protected void setIndexPrice(String price) {
    InstrumentExtraInformation instrumentExtra =
        this.instrumentService.getExtraInfo(this.defaultSymbol);
    instrumentExtra.setIndexPrice(MarginBigDecimal.valueOf(price));
    this.instrumentService.updateExtraInfo(instrumentExtra);
  }

  protected void setOraclePrice(String price) {
    InstrumentExtraInformation instrumentExtra =
        this.instrumentService.getExtraInfo(this.defaultSymbol);
    instrumentExtra.setOraclePrice(MarginBigDecimal.valueOf(price));
    this.instrumentService.updateExtraInfo(instrumentExtra);
  }
}

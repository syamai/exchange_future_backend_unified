package com.sotatek.future.service;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.when;

import com.sotatek.future.BaseTest;
import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Position;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.util.MarginBigDecimal;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

class MarginCalculatorTest extends BaseTest {

  @BeforeEach
  public void setUp() throws Exception {
    super.setUp();
    ServiceFactory.initialize();
  }

  @Override
  @AfterEach
  public void tearDown() throws Exception {
    super.tearDown();
  }

  //  @Test
  //  public void testCalculateOrderCostWithPosition() {
  //    MarginCalculator calculator = new MarginCalculator(this.defaultSymbol);
  //    boolean costWithPosition = true;
  //    boolean isLongPosition = true;
  //    MarginBigDecimal positionMargin = MarginBigDecimal.valueOf("0.2");
  //    MarginBigDecimal positionCurrentQty = MarginBigDecimal.valueOf("2");
  //    MarginBigDecimal inputPrice = MarginBigDecimal.valueOf("100");
  //    MarginBigDecimal oraclePrice = MarginBigDecimal.valueOf("95.18");
  //    MarginBigDecimal marBuy = MarginBigDecimal.valueOf("0");
  //    MarginBigDecimal marSel = MarginBigDecimal.valueOf("0.15");
  //    MarginBigDecimal mulBuy = MarginBigDecimal.valueOf("9.82");
  //    MarginBigDecimal mulSell = MarginBigDecimal.valueOf("-0.061");
  //    Order order = new Order();
  //    order.setQuantity(MarginBigDecimal.ONE);
  //    order.setLeverage(MarginBigDecimal.valueOf("20"));
  //    order.setSide(OrderSide.SELL);
  //
  //    MarginBigDecimal res =
  //        calculator.calculateOrderCost(
  //            costWithPosition,
  //            isLongPosition,
  //            positionMargin,
  //            positionCurrentQty,
  //            inputPrice,
  //            oraclePrice,
  //            marBuy,
  //            marSel,
  //            mulBuy,
  //            mulSell,
  //            order);
  //
  //    assertEquals(MarginBigDecimal.valueOf("4.75"), res);
  //  }
  //
  //  @Test
  //  public void testCalculateOrderCostWithoutPosition() {
  //    MarginCalculator calculator = new MarginCalculator(this.defaultSymbol);
  //    boolean costWithPosition = false;
  //
  //    // redundant because order don't have position >>>>>>>>>>>>>
  //    boolean isLongPosition = true;
  //    MarginBigDecimal positionMargin = MarginBigDecimal.valueOf("0.2");
  //    MarginBigDecimal positionCurrentQty = MarginBigDecimal.valueOf("2");
  //    // redundant because order don't have position ^^^^^^^^^^^^^
  //
  //    MarginBigDecimal inputPrice = MarginBigDecimal.valueOf("15");
  //    MarginBigDecimal oraclePrice = MarginBigDecimal.valueOf("6.54");
  //    MarginBigDecimal marBuy = MarginBigDecimal.valueOf("160");
  //    MarginBigDecimal marSel = MarginBigDecimal.valueOf("0");
  //    MarginBigDecimal mulBuy = MarginBigDecimal.valueOf("23.46");
  //    MarginBigDecimal mulSell = MarginBigDecimal.valueOf("-1.92");
  //    Order order = new Order();
  //    order.setQuantity(MarginBigDecimal.valueOf("2"));
  //    order.setLeverage(MarginBigDecimal.valueOf("1"));
  //    order.setSide(OrderSide.SELL);
  //
  //    MarginBigDecimal resForSellOrder =
  //        calculator.calculateOrderCost(
  //            costWithPosition,
  //            isLongPosition,
  //            positionMargin,
  //            positionCurrentQty,
  //            inputPrice,
  //            oraclePrice,
  //            marBuy,
  //            marSel,
  //            mulBuy,
  //            mulSell,
  //            order);
  //
  //    assertEquals(MarginBigDecimal.valueOf("0"), resForSellOrder);
  //    order.setSide(OrderSide.BUY);
  //    MarginBigDecimal resForBuyOrder =
  //        calculator.calculateOrderCost(
  //            costWithPosition,
  //            isLongPosition,
  //            positionMargin,
  //            positionCurrentQty,
  //            inputPrice,
  //            oraclePrice,
  //            marBuy,
  //            marSel,
  //            mulBuy,
  //            mulSell,
  //            order);
  //
  //    assertEquals(MarginBigDecimal.valueOf("46.92"), resForBuyOrder);
  //  }

  @Test
  public void testMaxRemovableAdjustMarginFormula() {
    MarginCalculator calculator = MarginCalculator.getCalculatorFor(defaultSymbol);
    MarginBigDecimal allocatedMargin = MarginBigDecimal.valueOf("1.979047619");
    MarginBigDecimal mark = MarginBigDecimal.valueOf("0.067");
    Position position = new Position();
    position.setCurrentQty(MarginBigDecimal.valueOf("-10"));
    position.setEntryPrice(MarginBigDecimal.valueOf("10.456"));
    position.setLeverage(MarginBigDecimal.valueOf("21"));
    position.setAdjustMargin(MarginBigDecimal.valueOf("-3"));
    MarginBigDecimal res = calculator.getMaxRemovableAdjustMargin(allocatedMargin, mark, position);
    System.out.println(res);
    assertEquals(MarginBigDecimal.valueOf("1.97904761"), res);
  }

  @Test
  public void positionMarginCrossUsdMCorrectFormula() {
    Instrument instrument = InstrumentService.getInstance().get(defaultSymbol);
    assertEquals(ContractType.USD_M, instrument.getContractType());
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(defaultSymbol);

    MarginBigDecimal margin = marginCalculator.positionMarginCrossUsdM(
        MarginBigDecimal.valueOf("1.5"),
        MarginBigDecimal.valueOf("2314"),
        MarginBigDecimal.valueOf("20")
    );
    assertEquals(MarginBigDecimal.valueOf("173.55"), margin, "margin long position");
    margin = marginCalculator.positionMarginCrossUsdM(
        MarginBigDecimal.valueOf("-1.5"),
        MarginBigDecimal.valueOf("2314"),
        MarginBigDecimal.valueOf("20")
    );
    assertEquals(MarginBigDecimal.valueOf("173.55"), margin, "margin short position");
  }

  @Test
  public void positionMarginCrossCoinMCorrectFormula() {
    Instrument instrument = InstrumentService.getInstance().get(defaultSymbolCoinM);
    assertEquals(ContractType.COIN_M, instrument.getContractType());
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(defaultSymbolCoinM);

    MarginBigDecimal margin = marginCalculator.positionMarginCrossCoinM(
        MarginBigDecimal.valueOf("2"),
        MarginBigDecimal.valueOf("100"),
        MarginBigDecimal.valueOf("20"),
        MarginBigDecimal.valueOf("1342")
    );
    assertEquals(MarginBigDecimal.valueOf("0.00745156"), margin, "margin long position");
    margin = marginCalculator.positionMarginCrossCoinM(
        MarginBigDecimal.valueOf("-2"),
        MarginBigDecimal.valueOf("100"),
        MarginBigDecimal.valueOf("20"),
        MarginBigDecimal.valueOf("1342")
    );
    assertEquals(MarginBigDecimal.valueOf("0.00745156"), margin, "margin short position");
  }

  @Test
  public void positionMarginIsolateUsdMCorrectFormula() {
    Instrument instrument = InstrumentService.getInstance().get(defaultSymbol);
    assertEquals(ContractType.USD_M, instrument.getContractType());
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(defaultSymbol);

    MarginBigDecimal margin = marginCalculator.positionMarginIsolateUsdM(
        MarginBigDecimal.valueOf("1.5"),
        MarginBigDecimal.valueOf("2314"),
        MarginBigDecimal.valueOf("20")
    );
    assertEquals(MarginBigDecimal.valueOf("173.55"), margin, "margin long position");

    margin = marginCalculator.positionMarginIsolateUsdM(
        MarginBigDecimal.valueOf("-1.5"),
        MarginBigDecimal.valueOf("2314"),
        MarginBigDecimal.valueOf("20")
    );
    assertEquals(MarginBigDecimal.valueOf("173.55"), margin, "margin short position");
  }

  @Test
  public void positionMarginIsolateCoinMCorrectFormula() {
    Instrument instrument = InstrumentService.getInstance().get(defaultSymbolCoinM);
    assertEquals(ContractType.COIN_M, instrument.getContractType());
    MarginCalculator marginCalculator = MarginCalculator.getCalculatorFor(defaultSymbolCoinM);

    MarginBigDecimal margin = marginCalculator.positionMarginIsolateCoinM(
        MarginBigDecimal.valueOf("2"),
        MarginBigDecimal.valueOf("100"),
        MarginBigDecimal.valueOf("20"),
        MarginBigDecimal.valueOf("1342")
    );
    assertEquals(MarginBigDecimal.valueOf("0.00745156"), margin, "margin long position");
    margin = marginCalculator.positionMarginIsolateCoinM(
        MarginBigDecimal.valueOf("-2"),
        MarginBigDecimal.valueOf("100"),
        MarginBigDecimal.valueOf("20"),
        MarginBigDecimal.valueOf("1342")
    );
    assertEquals(MarginBigDecimal.valueOf("0.00745156"), margin, "margin short position");
  }
}

package com.sotatek.future.service;

import static com.sotatek.future.service.PositionCalculatorTestHelper.crossPositionOf;
import static com.sotatek.future.service.PositionCalculatorTestHelper.isolatePositionOf;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.ArgumentMatchers.eq;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.when;

import com.sotatek.future.entity.Instrument;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Position;
import com.sotatek.future.enums.ContractType;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.value.LeverageMarginRule;
import java.util.Optional;
import java.util.stream.Stream;
import lombok.extern.slf4j.Slf4j;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.Arguments;
import org.junit.jupiter.params.provider.MethodSource;

@Slf4j
public class PositionCalculatorIsolatePositionTest {

  // For mocking only, value doesn't matter since we'll mock
  private MarginBigDecimal mockMarkPrice = MarginBigDecimal.valueOf("1337");

  @Test
  void throwError_when_getIsolateLiquidationPrice_given_crossPosition() {
    Position p = crossPositionOf(1, MarginBigDecimal.ZERO, MarginBigDecimal.ZERO);

    PositionCalculator calculator = getCalculator(MarginBigDecimal.ZERO, p);

    assertThatThrownBy(() -> calculator.getIsolatedLiquidationPrice(p, mockMarkPrice))
        .isInstanceOf(IllegalArgumentException.class)
        .hasMessageContaining("Position must be isolated");
  }

  @ParameterizedTest
  @MethodSource("provider_returnLiquidationPrice_when_isolatePosition")
  void returnLiquidationPrice_when_isolatePositionUsdM(
      Position position,
      MarginBigDecimal mmr,
      MarginBigDecimal cumB,
      MarginBigDecimal multiplier,
      MarginBigDecimal expected) {
    PositionCalculator positionCalculator = getCalculator(mmr, cumB, multiplier, position);
    MarginBigDecimal liquidationPrice =
        positionCalculator.getIsolatedLiquidationPrice(position, mockMarkPrice);
    assertThat(liquidationPrice).isEqualTo(expected);
  }

  static Stream<Arguments> provider_returnLiquidationPrice_when_isolatePosition() {
    return Stream.of(
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(0),
                MarginBigDecimal.valueOf(10_000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("0.005"),
            MarginBigDecimal.valueOf("0"),
            MarginBigDecimal.ONE,
            MarginBigDecimal.valueOf("105573.51758793")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(11),
                MarginBigDecimal.valueOf(980),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("0.004"),
            MarginBigDecimal.valueOf("7"),
            MarginBigDecimal.ONE,
            MarginBigDecimal.valueOf("63302.86144578")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(125),
                MarginBigDecimal.valueOf(1_000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("0.004"),
            MarginBigDecimal.valueOf("0"),
            MarginBigDecimal.ONE,
            MarginBigDecimal.valueOf("67027.53984063")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(10_000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("0.005"),
            MarginBigDecimal.valueOf("4"),
            MarginBigDecimal.ONE,
            MarginBigDecimal.valueOf("144530.99502487")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("1"),
                MarginBigDecimal.valueOf("1345"),
                MarginBigDecimal.valueOf(0),
                MarginBigDecimal.valueOf(13),
                ContractType.COIN_M),
            MarginBigDecimal.valueOf("0.005"),
            MarginBigDecimal.valueOf("30"),
            MarginBigDecimal.valueOf("10"),
            MarginBigDecimal.valueOf("0.23368052")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-1"),
                MarginBigDecimal.valueOf("1256.65"),
                MarginBigDecimal.valueOf(11),
                MarginBigDecimal.valueOf(17),
                ContractType.COIN_M),
            MarginBigDecimal.valueOf("0.004"),
            MarginBigDecimal.valueOf("10"),
            MarginBigDecimal.valueOf("100"),
            MarginBigDecimal.valueOf("-2.6265529")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("2"),
                MarginBigDecimal.valueOf("1356.56"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(100),
                ContractType.COIN_M),
            MarginBigDecimal.valueOf("0.004"),
            MarginBigDecimal.valueOf("20"),
            MarginBigDecimal.valueOf("10"),
            MarginBigDecimal.valueOf("0.09126661")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-2"),
                MarginBigDecimal.valueOf("2123.453"),
                MarginBigDecimal.valueOf(0),
                MarginBigDecimal.valueOf(0),
                ContractType.COIN_M),
            MarginBigDecimal.valueOf("0.005"),
            MarginBigDecimal.valueOf("40"),
            MarginBigDecimal.valueOf("100"),
            MarginBigDecimal.valueOf("-4.98674203")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-3.25"),
                MarginBigDecimal.valueOf("4.31615384"),
                MarginBigDecimal.valueOf("0.58248333"),
                MarginBigDecimal.valueOf(0),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("0.005"),
            MarginBigDecimal.valueOf("0"),
            MarginBigDecimal.ONE,
            MarginBigDecimal.valueOf("4.47301440")));
  }

  @ParameterizedTest
  @MethodSource("provider_returnZeroLiquidationPrice_when_isolatePositionLargeMargin")
  void returnNegativeLiquidationPrice_when_isolatePositionLargeMargin(Position position) {
    PositionCalculator calculator = getCalculator(MarginBigDecimal.valueOf("0.004"), position);

    MarginBigDecimal liquidationPrice =
        calculator.getIsolatedLiquidationPrice(position, mockMarkPrice);

    assertThat(liquidationPrice).isLessThan(MarginBigDecimal.ZERO);
  }

  static Stream<Arguments> provider_returnZeroLiquidationPrice_when_isolatePositionLargeMargin() {
    return Stream.of(
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(125),
                MarginBigDecimal.valueOf(1_000_000),
                ContractType.USD_M)),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(10_000_000),
                ContractType.USD_M)));
  }

  @ParameterizedTest
  @MethodSource("provider_returnBankruptPrice_when_isolatePosition")
  void returnBankruptPrice_when_isolatePosition(
      Position position,
      MarginBigDecimal cumB,
      MarginBigDecimal multiplier,
      MarginBigDecimal expected) {
    // Don't need trading rule lookup to calculate bankrupt price
    PositionCalculator positionCalculator =
        getCalculator(MarginBigDecimal.ZERO, cumB, multiplier, position);

    MarginBigDecimal bankruptPrice =
        positionCalculator.getIsolatedBankruptPrice(position, mockMarkPrice);

    assertThat(bankruptPrice).isEqualTo(expected);
  }

  static Stream<Arguments> provider_returnBankruptPrice_when_isolatePosition() {
    return Stream.of(
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(10000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("10"),
            MarginBigDecimal.valueOf("1"),
            MarginBigDecimal.valueOf("104825.65")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(125),
                MarginBigDecimal.valueOf(1000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("10"),
            MarginBigDecimal.valueOf("1"),
            MarginBigDecimal.valueOf("62775.65")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(10000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("10"),
            MarginBigDecimal.valueOf("1"),
            MarginBigDecimal.valueOf("145265.65")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(125),
                MarginBigDecimal.valueOf(1000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("15"),
            MarginBigDecimal.valueOf("1"),
            MarginBigDecimal.valueOf("67325.65")));
  }

  @ParameterizedTest
  @MethodSource("provider_returnNegativeBankruptPrice_when_isolateLongPositionLargeMargin")
  void returnNegativeBankruptPrice_when_isolateLongPositionLargeMargin(Position position) {
    // Don't need trading rule lookup to calculate bankrupt price
    PositionCalculator positionCalculator =
        getCalculator(MarginBigDecimal.valueOf("0.004"), position);

    MarginBigDecimal bankruptPrice =
        positionCalculator.getIsolatedBankruptPrice(position, mockMarkPrice);

    assertThat(bankruptPrice).isLessThan(MarginBigDecimal.ZERO);
  }

  static Stream<Arguments>
      provider_returnNegativeBankruptPrice_when_isolateLongPositionLargeMargin() {
    return Stream.of(
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(125),
                MarginBigDecimal.valueOf(1_000_000),
                ContractType.USD_M)),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(10_000_000),
                ContractType.USD_M)));
  }

  @ParameterizedTest
  @MethodSource("provider_returnPnlRanking_when_isolatePosition")
  void returnPnlRanking_when_isolatePosition(Position position, MarginBigDecimal expected) {
    // Don't need trading rule lookup to calculate bankrupt price
    PositionCalculator positionCalculator = getCalculator(MarginBigDecimal.ZERO, position);

    MarginBigDecimal bankruptPrice =
        positionCalculator.getIsolatedBankruptPrice(position, mockMarkPrice);
    position.setBankruptPrice(bankruptPrice);
    MarginBigDecimal pnlRanking = positionCalculator.getPnlRanking(position);
    assertThat(pnlRanking).isEqualTo(expected);
  }

  static Stream<Arguments> provider_returnPnlRanking_when_isolatePosition() {
    return Stream.of(
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(10000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("75.8135")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(125),
                MarginBigDecimal.valueOf(1000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("44.8246")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-0.5"),
                MarginBigDecimal.valueOf("125045.65"),
                MarginBigDecimal.valueOf(100),
                MarginBigDecimal.valueOf(10000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("0.0091")),
        Arguments.of(
            isolatePositionOf(
                MarginBigDecimal.valueOf("-0.5"),
                MarginBigDecimal.valueOf("65045.65"),
                MarginBigDecimal.valueOf(125),
                MarginBigDecimal.valueOf(1000),
                ContractType.USD_M),
            MarginBigDecimal.valueOf("0.0197")));
  }

  private PositionCalculator getCalculator(MarginBigDecimal mmr, Position position) {
    return this.getCalculator(mmr, MarginBigDecimal.ZERO, MarginBigDecimal.ONE, position);
  }

  private PositionCalculator getCalculator(
      MarginBigDecimal mmr, MarginBigDecimal cumB, MarginBigDecimal multiplier, Position position) {
    LeverageMarginRule testRule =
        new LeverageMarginRule(
            MarginBigDecimal.ZERO, MarginBigDecimal.ZERO, MarginBigDecimal.ZERO, mmr, cumB);
    TradingRuleService tradingRuleService = mock(TradingRuleService.class);
    InstrumentService instrumentService = mock(InstrumentService.class);
    PositionService positionService = mock(PositionService.class);
    when(tradingRuleService.getLeverageMarginRule(
            anyString(), eq(position), eq(mockMarkPrice), any(MarginBigDecimal.class)))
        .thenReturn(Optional.of(testRule));
    InstrumentExtraInformation instrumentExtraInformation = new InstrumentExtraInformation();
    instrumentExtraInformation.setOraclePrice(mockMarkPrice);
    when(instrumentService.getExtraInfo(anyString())).thenReturn(instrumentExtraInformation);

    Instrument instrument = new Instrument();
    instrument.setMultiplier(multiplier);
    when(instrumentService.get(anyString())).thenReturn(instrument);

    return new PositionCalculator(tradingRuleService, instrumentService, positionService);
  }
}

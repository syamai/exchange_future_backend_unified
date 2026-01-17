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
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Optional;
import java.util.stream.Stream;
import lombok.extern.slf4j.Slf4j;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.Arguments;
import org.junit.jupiter.params.provider.MethodSource;

@Slf4j
public class PositionCalculatorCrossPositionTest {

  @Test
  void throwError_when_getCrossLiquidationPrice_given_isolatePosition() {
    Position p =
        isolatePositionOf(
            MarginBigDecimal.ZERO,
            MarginBigDecimal.ZERO,
            MarginBigDecimal.ZERO,
            MarginBigDecimal.ZERO,
            ContractType.USD_M);

    PositionCalculator calculator = new PositionCalculator(null, null, null);

    assertThatThrownBy(
            () ->
                calculator.getCrossLiquidationPrice(
                    p, Collections.emptyList(), MarginBigDecimal.ZERO, MarginBigDecimal.ZERO))
        .isInstanceOf(IllegalArgumentException.class)
        .hasMessageContaining("Position must be cross");
  }

  @ParameterizedTest
  @MethodSource("provider_returnLiquidationPrice_when_crossPosition")
  void returnLiquidationPrice_when_crossPosition(
      Position position,
      List<PositionWithRule> allPositionWithRules,
      MarginBigDecimal walletBalance,
      MarginBigDecimal markPrice,
      MarginBigDecimal expected) {
    List<Position> allPositions = new ArrayList<>();
    PositionPriceCalculatorMocker mocker = new PositionPriceCalculatorMocker();
    for (PositionWithRule pwr : allPositionWithRules) {
      allPositions.add(pwr.position());
      mocker.addRule(markPrice, pwr.position(), pwr.leverageMarginRule());
    }
    PositionCalculator positionCalculator = mocker.build();

    MarginBigDecimal liquidationPrice =
        positionCalculator.getCrossLiquidationPrice(
            position, allPositions, walletBalance, markPrice);

    assertThat(liquidationPrice).isEqualTo(expected);
  }

  static Stream<Arguments> provider_returnLiquidationPrice_when_crossPosition() {
    List<LeverageMarginRule> mockRules =
        List.of(
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.004"),
                MarginBigDecimal.ZERO),
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.005"),
                MarginBigDecimal.valueOf(50)),
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.01"),
                MarginBigDecimal.valueOf(1300)));
    List<PositionWithRule> allLongPositions =
        List.of(
            new PositionWithRule(
                crossPositionOf(
                    1, MarginBigDecimal.valueOf("0.5"), MarginBigDecimal.valueOf("65000.25")),
                mockRules.get(0)),
            new PositionWithRule(
                crossPositionOf(
                    2, MarginBigDecimal.valueOf("2.5"), MarginBigDecimal.valueOf("62000.625")),
                mockRules.get(1)),
            new PositionWithRule(
                crossPositionOf(
                    3, MarginBigDecimal.valueOf("6.5"), MarginBigDecimal.valueOf("69000.125")),
                mockRules.get(2)));
    List<PositionWithRule> allShortPositions =
        List.of(
            new PositionWithRule(
                crossPositionOf(
                    4, MarginBigDecimal.valueOf("-0.5"), MarginBigDecimal.valueOf("65000.25")),
                mockRules.get(0)),
            new PositionWithRule(
                crossPositionOf(
                    5, MarginBigDecimal.valueOf("-2.5"), MarginBigDecimal.valueOf("62000.625")),
                mockRules.get(1)),
            new PositionWithRule(
                crossPositionOf(
                    6, MarginBigDecimal.valueOf("-6.5"), MarginBigDecimal.valueOf("69000.125")),
                mockRules.get(2)));
    return Stream.of(
        Arguments.of(
            allLongPositions.get(0).position(), // id = 1
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("58536.6465")),
        Arguments.of(
            allLongPositions.get(1).position(), // id = 2
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("63306.1306")),
        Arguments.of(
            allLongPositions.get(2).position(), // id = 3
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("64038.5003")),
        Arguments.of(
            allShortPositions.get(0).position(), // id = 4
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("163055.2788")),
        Arguments.of(
            allShortPositions.get(1).position(), // id = 5
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("84191.4427")),
        Arguments.of(
            allShortPositions.get(2).position(), // id = 6
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("72036.1386")));
  }

  @ParameterizedTest
  @MethodSource("provider_returnBankruptPrice_when_crossPosition")
  void returnBankruptPrice_when_crossPosition(
      Position position,
      List<PositionWithRule> allPositionWithRules,
      MarginBigDecimal walletBalance,
      MarginBigDecimal markPrice,
      MarginBigDecimal expected) {
    PositionPriceCalculatorMocker mocker = new PositionPriceCalculatorMocker();
    List<Position> allPositions = new ArrayList<>();
    for (PositionWithRule pwr : allPositionWithRules) {
      allPositions.add(pwr.position());
      mocker.addRule(markPrice, pwr.position(), pwr.leverageMarginRule());
    }
    PositionCalculator positionCalculator = mocker.build();

    MarginBigDecimal bankruptPrice =
        positionCalculator.getCrossBankruptPrice(position, allPositions, walletBalance, markPrice);

    assertThat(bankruptPrice).isEqualTo(expected);
  }

  static Stream<Arguments> provider_returnBankruptPrice_when_crossPosition() {
    List<LeverageMarginRule> mockRules =
        List.of(
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.004"),
                MarginBigDecimal.ZERO),
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.005"),
                MarginBigDecimal.valueOf(50)),
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.01"),
                MarginBigDecimal.valueOf(1300)));
    List<PositionWithRule> allLongPositions =
        List.of(
            new PositionWithRule(
                crossPositionOf(
                    1, MarginBigDecimal.valueOf("0.5"), MarginBigDecimal.valueOf("65000.25")),
                mockRules.get(0)),
            new PositionWithRule(
                crossPositionOf(
                    2, MarginBigDecimal.valueOf("2.5"), MarginBigDecimal.valueOf("62000.625")),
                mockRules.get(1)),
            new PositionWithRule(
                crossPositionOf(
                    3, MarginBigDecimal.valueOf("6.5"), MarginBigDecimal.valueOf("69000.125")),
                mockRules.get(2)));
    List<PositionWithRule> allShortPositions =
        List.of(
            new PositionWithRule(
                crossPositionOf(
                    4, MarginBigDecimal.valueOf("-0.5"), MarginBigDecimal.valueOf("65000.25")),
                mockRules.get(0)),
            new PositionWithRule(
                crossPositionOf(
                    5, MarginBigDecimal.valueOf("-2.5"), MarginBigDecimal.valueOf("62000.625")),
                mockRules.get(1)),
            new PositionWithRule(
                crossPositionOf(
                    6, MarginBigDecimal.valueOf("-6.5"), MarginBigDecimal.valueOf("69000.125")),
                mockRules.get(2)));
    return Stream.of(
        Arguments.of(
            allLongPositions.get(0).position(), // id = 1
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("51005")),
        Arguments.of(
            allLongPositions.get(1).position(), // id = 2
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("61801")),
        Arguments.of(
            allLongPositions.get(2).position(), // id = 3
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("63461.923")),
        Arguments.of(
            allShortPositions.get(0).position(), // id = 4
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("171005")),
        Arguments.of(
            allShortPositions.get(1).position(), // id = 5
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("85801")),
        Arguments.of(
            allShortPositions.get(2).position(), // id = 6
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("72692.6923")));
  }

  @ParameterizedTest
  @MethodSource("provider_returnPnlRanking_when_crossPosition")
  void returnPnlRanking_when_crossPosition(
      Position position,
      List<PositionWithRule> allPositionWithRules,
      MarginBigDecimal walletBalance,
      MarginBigDecimal markPrice,
      MarginBigDecimal expected) {
    PositionPriceCalculatorMocker mocker = new PositionPriceCalculatorMocker();
    List<Position> allPositions = new ArrayList<>();
    for (PositionWithRule pwr : allPositionWithRules) {
      allPositions.add(pwr.position());
      mocker.addRule(markPrice, pwr.position(), pwr.leverageMarginRule());
    }
    PositionCalculator positionCalculator = mocker.build();

    MarginBigDecimal bankruptPrice =
        positionCalculator.getCrossBankruptPrice(position, allPositions, walletBalance, markPrice);
    position.setBankruptPrice(bankruptPrice);
    MarginBigDecimal pnlRanking = positionCalculator.getPnlRanking(position);
    assertThat(pnlRanking).isEqualTo(expected);
  }

  static Stream<Arguments> provider_returnPnlRanking_when_crossPosition() {
    List<LeverageMarginRule> mockRules =
        List.of(
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.004"),
                MarginBigDecimal.ZERO),
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.005"),
                MarginBigDecimal.valueOf(50)),
            new LeverageMarginRule(
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.ZERO,
                MarginBigDecimal.valueOf("0.01"),
                MarginBigDecimal.valueOf(1300)));
    List<PositionWithRule> allLongPositions =
        List.of(
            new PositionWithRule(
                crossPositionOf(
                    1, MarginBigDecimal.valueOf("0.5"), MarginBigDecimal.valueOf("65000.25")),
                mockRules.get(0)),
            new PositionWithRule(
                crossPositionOf(
                    2, MarginBigDecimal.valueOf("2.5"), MarginBigDecimal.valueOf("62000.625")),
                mockRules.get(1)),
            new PositionWithRule(
                crossPositionOf(
                    3, MarginBigDecimal.valueOf("6.5"), MarginBigDecimal.valueOf("69000.125")),
                mockRules.get(2)));
    List<PositionWithRule> allShortPositions =
        List.of(
            new PositionWithRule(
                crossPositionOf(
                    4, MarginBigDecimal.valueOf("-0.5"), MarginBigDecimal.valueOf("65000.25")),
                mockRules.get(0)),
            new PositionWithRule(
                crossPositionOf(
                    5, MarginBigDecimal.valueOf("-2.5"), MarginBigDecimal.valueOf("62000.625")),
                mockRules.get(1)),
            new PositionWithRule(
                crossPositionOf(
                    6, MarginBigDecimal.valueOf("-6.5"), MarginBigDecimal.valueOf("69000.125")),
                mockRules.get(2)));
    return Stream.of(
        Arguments.of(
            allLongPositions.get(0).position(), // id = 1
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("-0.0016")),
        Arguments.of(
            allLongPositions.get(1).position(), // id = 2
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("0.9633")),
        Arguments.of(
            allLongPositions.get(2).position(), // id = 3
            allLongPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("-0.001")),
        Arguments.of(
            allShortPositions.get(0).position(), // id = 4
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("0.0046")),
        Arguments.of(
            allShortPositions.get(1).position(), // id = 5
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("-0.0133")),
        Arguments.of(
            allShortPositions.get(2).position(), // id = 6
            allShortPositions,
            MarginBigDecimal.valueOf(30_000),
            MarginBigDecimal.valueOf("64500"),
            MarginBigDecimal.valueOf("0.5134")));
  }

  /** Helper class to hold a position with corresponding LeverageMargin rule for testing */
  private record PositionWithRule(Position position, LeverageMarginRule leverageMarginRule) {}

  private static class PositionPriceCalculatorMocker {
    private TradingRuleService tradingRuleService = mock(TradingRuleService.class);
    private InstrumentService instrumentService = mock(InstrumentService.class);

    private PositionService positionService = mock(PositionService.class);

    void addRule(MarginBigDecimal markPrice, Position position, LeverageMarginRule rule) {
      when(tradingRuleService.getLeverageMarginRule(
              anyString(), eq(position), eq(markPrice), any(MarginBigDecimal.class)))
          .thenReturn(Optional.of(rule));
      Instrument instrument = new Instrument();
      instrument.setSymbol(position.getSymbol());
      instrument.setMultiplier(MarginBigDecimal.ONE);
      InstrumentExtraInformation instrumentExtraInformation = new InstrumentExtraInformation();
      instrumentExtraInformation.setOraclePrice(markPrice);
      instrumentExtraInformation.setSymbol(position.getSymbol());
      when(instrumentService.getExtraInfo(anyString())).thenReturn(instrumentExtraInformation);
      when(instrumentService.get(any())).thenReturn(instrument);
    }

    PositionCalculator build() {
      return new PositionCalculator(tradingRuleService, instrumentService, positionService);
    }
  }
}

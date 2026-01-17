package com.sotatek.future.service;

import com.sotatek.future.entity.LeverageMargin;
import com.sotatek.future.entity.Position;
import com.sotatek.future.entity.TradingRule;
import com.sotatek.future.util.IntervalTree;
import com.sotatek.future.util.MarginBigDecimal;
import com.sotatek.future.value.LeverageMarginRule;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import lombok.extern.slf4j.Slf4j;

@Slf4j
public class TradingRuleService {

  public static final TradingRuleService INSTANCE = new TradingRuleService();
  private final Map<String, IntervalTree<MarginBigDecimal, LeverageMarginRule>> lmSymbolIndex;
  // default leverage margin rule is the highest tier notional bracket(or LM have the highest Max
  // notional value)
  public final Map<String, LeverageMarginRule> lmSymbolIndexDefault;

  private final Map<String, MarginBigDecimal> liquidationClearanceRateIndex;

  public TradingRuleService() {
    this.lmSymbolIndex = new HashMap<>();
    this.liquidationClearanceRateIndex = new HashMap<>();
    this.lmSymbolIndexDefault = new HashMap<>();
  }

  public Optional<LeverageMarginRule> getLeverageMarginRule(
      String symbol, Position position, MarginBigDecimal markPrice, MarginBigDecimal multiplier) {
    MarginBigDecimal notionalValue;
    if (position.isCoinM()) {
      notionalValue = position.getCurrentQty().abs().multiply(multiplier).divide(markPrice);
    } else {
      notionalValue = position.getCurrentQty().abs().multiply(markPrice);
    }
    log.atDebug()
        .addKeyValue("symbol", symbol)
        .log(
            "Looking up trading rule for position. [isCoinM={}, size={}, markPrice={},"
                + " multiplier={}, value={}]",
            position.isCoinM(),
            position.getCurrentQty(),
            markPrice,
            multiplier,
            notionalValue);
    Optional<IntervalTree<MarginBigDecimal, LeverageMarginRule>> rulesIndexOpt =
        Optional.ofNullable(lmSymbolIndex).map(l -> l.get(symbol));
    return rulesIndexOpt.flatMap(
        rulesIndex -> {
          List<LeverageMarginRule> matched = rulesIndex.lookup(notionalValue);
          if (matched.isEmpty()) {
            return Optional.empty();
          }
          if (matched.size() > 1) {
            log.atDebug()
                .log(
                    "More than 1 trading rule matched, returning the first one. [size={} using={}]",
                    matched.size(),
                    matched.get(0));
          }
          return Optional.of(matched.get(0));
        });
  }

  public void loadLeverageMarginRule(LeverageMargin leverageMargin) {
    String symbol = leverageMargin.getSymbol();
    LeverageMarginRule rule =
        new LeverageMarginRule(
            leverageMargin.getMin(),
            leverageMargin.getMax(),
            MarginBigDecimal.valueOf(leverageMargin.getMaxLeverage()),
            leverageMargin.getMaintenanceMarginRate().divide(MarginBigDecimal.valueOf(100)),
            leverageMargin.getMaintenanceAmount());
    IntervalTree<MarginBigDecimal, LeverageMarginRule> symbolIndex;
    if (!lmSymbolIndex.containsKey(symbol)) {
      symbolIndex = new IntervalTree<>();
      lmSymbolIndex.put(symbol, symbolIndex);
    } else {
      symbolIndex = lmSymbolIndex.get(symbol);
    }
    symbolIndex.insert(rule);
    // initial default leverage margin rule for each symbol
    if (lmSymbolIndexDefault.containsKey(symbol)) {
      LeverageMarginRule defaultLM = lmSymbolIndexDefault.get(symbol);
      if (rule.maxPosition().gt(defaultLM.maxPosition())) {
        log.atInfo()
            .addKeyValue("symbol", symbol)
            .addKeyValue("lmRule", rule)
            .log("replace default lm for symbol");
        lmSymbolIndexDefault.put(symbol, rule);
      }
    } else {
      lmSymbolIndexDefault.put(symbol, rule);
      log.atInfo()
          .addKeyValue("symbol", symbol)
          .addKeyValue("lmRule", rule)
          .log("initial default lm for symbol");
    }
  }

  public Optional<MarginBigDecimal> getLiquidationClearanceRate(String symbol) {
    return Optional.ofNullable(liquidationClearanceRateIndex.get(symbol));
  }

  public void loadTradingRule(TradingRule tradingRule) {
    String symbol = tradingRule.getSymbol();
    MarginBigDecimal liqClearanceRate = tradingRule.getLiqClearanceFee();
    if (liqClearanceRate != null) {
      MarginBigDecimal realClearanceRate = liqClearanceRate.divide(MarginBigDecimal.valueOf(100));
      log.atDebug()
          .addKeyValue("symbol", tradingRule.getSymbol())
          .log("Indexing liquidation clearance rate. [rate={}]", realClearanceRate);
      liquidationClearanceRateIndex.put(symbol, realClearanceRate);
    }
  }

  public IntervalTree<MarginBigDecimal, LeverageMarginRule> getLmSymbolIndexBySymbol(String symbol) {
    return this.lmSymbolIndex.get(symbol);
  }

  public LeverageMarginRule getLmSymbolIndexDefaultBySymbol(String symbol) {
    return this.lmSymbolIndexDefault.get(symbol);
  }

  public MarginBigDecimal getLiquidationClearanceRateIndexBySymbol(String symbol) {
    return this.liquidationClearanceRateIndex.get(symbol);
  }
}

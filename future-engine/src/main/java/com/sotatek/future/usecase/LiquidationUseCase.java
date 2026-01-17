package com.sotatek.future.usecase;

import static com.sotatek.future.engine.MatchingEngine.matchers;

import com.sotatek.future.engine.Matcher;
import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.engine.Trigger;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Position;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.exception.InsufficientBalanceException;
import com.sotatek.future.service.AccountService;
import com.sotatek.future.service.InstrumentService;
import com.sotatek.future.service.LiquidationService;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayDeque;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.Queue;
import java.util.Set;
import java.util.stream.Collectors;
import java.util.stream.Stream;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.ObjectUtils;

@Slf4j
@RequiredArgsConstructor
public class LiquidationUseCase {

  private final InstrumentService instrumentService;

  private final LiquidationService liquidationService;

  private final PositionService positionService;

  private final AccountService accountService;

  private final MatchingEngine matchingEngine;

  public void liquidate(Command command, Map<String, Trigger> triggers) {
    InstrumentExtraInformation instrumentExtra = command.getInstrumentExtraInformation();
    InstrumentExtraInformation oldInstrumentExtra =
        instrumentService.getExtraInfo(instrumentExtra.getSymbol());
    // update trailing price for trailing stop order
    // get trigger of this instrument
    Trigger trigger = triggers.get(instrumentExtra.getSymbol());
    // update trailing price for all trailing stop order of that instrument
    if (ObjectUtils.isNotEmpty(trigger)) {
      trigger.updateTrailingPrice(oldInstrumentExtra, instrumentExtra);
    }

    boolean markPriceChange = false;
    if (ObjectUtils.isEmpty(oldInstrumentExtra)) {
      log.atDebug()
          .addKeyValue("symbol", instrumentExtra.getSymbol())
          .log("Can not load old instrument extra for symbol.");
      //      updateInstrumentPrices(command);
    } else {
      MarginBigDecimal oldOraclePrice = oldInstrumentExtra.getOraclePrice();
      InstrumentExtraInformation updatedInstrumentExtra = updateInstrumentPrices(command);
      if (oldOraclePrice.eq(updatedInstrumentExtra.getOraclePrice())) {
        log.atDebug()
            .addKeyValue("symbol", instrumentExtra.getSymbol())
            .log("Oracle price not changed. Skip liquidation check");
      } else {
        markPriceChange = true;
      }
    }
    if (markPriceChange) {
      log.atDebug()
          .addKeyValue("symbol", instrumentExtra.getSymbol())
          .log(
              "Liquidation check due to mark price change. [new_price={}]",
              instrumentExtra.getOraclePrice());
      performLiquidation(matchingEngine, instrumentExtra);
      matchingEngine.commit();
    }
  }

  public void closeInsurancePosition() {
    Stream<Long> allInsuranceAccount = accountService.getAllInsuranceAccountId();
    allInsuranceAccount.forEach(
        accId -> {
          List<Position> liquidationPosition = positionService.getUserPositions(accId);
          log.atDebug()
              .addKeyValue("accId", accId)
              .log("Closing insurance position. [count={}]", liquidationPosition.size());
          for (Position p : liquidationPosition) {
            Matcher matcher = matchers.get(p.getSymbol());
            if (matcher == null) {
              log.atError()
                  .addKeyValue("posId", p.getId())
                  .addKeyValue("accId", p.getAccountId())
                  .addKeyValue("symbol", p.getSymbol())
                  .log("Unable to get matcher for symbol");
              continue;
            }
            try {
              liquidationService.closeInsurancePosition(p, matcher, matchingEngine);
              matcher.commit();
              matchingEngine.commit();
            } catch (Exception e) {
              log.atError()
                  .setCause(e)
                  .addKeyValue("posId", p.getId())
                  .addKeyValue("accId", p.getAccountId())
                  .addKeyValue("symbol", p.getSymbol())
                  .log("Exception when trying to close insurance position");
              matcher.rollback();
              matchingEngine.rollback();
            }
          }
          matchingEngine.commit();
        });
  }

  private InstrumentExtraInformation updateInstrumentPrices(Command command) {
    InstrumentExtraInformation instrumentExtra = (InstrumentExtraInformation) command.getData();
    InstrumentExtraInformation oldInstrumentExtra =
        instrumentService.getExtraInfo(instrumentExtra.getSymbol());
    if (instrumentExtra.getOraclePrice() != null) {
      oldInstrumentExtra.setOraclePrice(instrumentExtra.getOraclePrice());
    }
    if (instrumentExtra.getIndexPrice() != null) {
      oldInstrumentExtra.setIndexPrice(instrumentExtra.getIndexPrice());
    }
    instrumentService.updateExtraInfo(oldInstrumentExtra);
    instrumentService.commit();
    return oldInstrumentExtra;
  }

  private Set<LiquidatedAccount> performLiquidation(
      MatchingEngine matchingEngine, InstrumentExtraInformation instrumentExtra) {
    Set<LiquidatedAccount> liquidatedAccounts = new HashSet<>();
    // Update liquidation data
    positionService.updateLiquidationData(instrumentExtra.getSymbol());
    // Update positions
    Stream<Position> positions =
        positionService.getLiquidablePositionsForSymbol(instrumentExtra.getSymbol());
    Queue<Position> pendingLiquidatedPositions =
        positions.collect(Collectors.toCollection(ArrayDeque::new));

    Set<String> alreadyLiquidated = new HashSet<>();
    while (!pendingLiquidatedPositions.isEmpty()) {
      Position cursor = pendingLiquidatedPositions.poll();
      log.atDebug()
          .addKeyValue("symbol", cursor.getSymbol())
          .addKeyValue("accId", cursor.getAccountId())
          .log("Start liquidating position");
      Matcher matcher = matchers.get(cursor.getSymbol());
      if (matcher == null) {
        log.atError()
            .addKeyValue("posId", cursor.getId())
            .addKeyValue("accId", cursor.getAccountId())
            .addKeyValue("symbol", cursor.getSymbol())
            .log("Unable to get matcher for symbol");
        continue;
      }
      try {
        Position afterLiquidate = liquidationService.liquidate(cursor);
        alreadyLiquidated.add(afterLiquidate.getKey());
        matcher.commit();
        matchingEngine.commit();
        liquidatedAccounts.add(new LiquidatedAccount(cursor.getAccountId(), cursor.getAsset()));
        // Also check other positions of the same account, for possible cascading liquidation
        Optional<Position> cascadingPosition =
            positionService.getNextLiquidableCrossPositionForAccount(
                cursor.getAccountId(), alreadyLiquidated);
        cascadingPosition.ifPresent(
            p -> {
              log.atDebug()
                  .addKeyValue("symbol", p.getSymbol())
                  .addKeyValue("accId", p.getAccountId())
                  .log("Queuing position for liquidation due to cascading");
              pendingLiquidatedPositions.add(p);
            });
      } catch (InsufficientBalanceException e) {
        if (AccountService.INSURANCE_USER_ID.equals(e.getAccount().getUserId())) {
          log.warn("Insurance account has insufficient balance");
        } else {
          log.atWarn()
              .addKeyValue("userId", e.getAccount().getUserId())
              .addKeyValue("accountId", e.getAccount().getId())
              .addKeyValue("balance", e.getAccount().getBalance())
              .log("User account get insufficient balance");
        }
        // commit all position that has been processed
        matchingEngine.commit();
      } catch (Exception e) {
        log.error(
            "Exception {} when trying to liquidate position [posId={}, accId={}, symbol={}] {}",
            e,
            cursor.getId(),
            cursor.getAccountId(),
            cursor.getSymbol(),
            e.getStackTrace());
        matcher.rollback();
        matchingEngine.rollback();
      }
    }
    return liquidatedAccounts;
  }

  record LiquidatedAccount(Long accId, Asset asset) {}
}

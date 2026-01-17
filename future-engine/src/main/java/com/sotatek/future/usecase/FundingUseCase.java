package com.sotatek.future.usecase;

import com.sotatek.future.engine.MatchingEngine;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.FundingParams;
import com.sotatek.future.entity.Position;
import com.sotatek.future.service.FundingService;
import com.sotatek.future.service.PositionService;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.Date;
import java.util.List;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;

@RequiredArgsConstructor
@Slf4j
public class FundingUseCase {

  private final FundingService fundingService;

  private final PositionService positionService;

  private final MatchingEngine matchingEngine;

  public void payFunding(Command command) {
    FundingParams params = (FundingParams) command.getData();
    try {
      String symbol = params.getSymbol();
      MarginBigDecimal fundingRate = params.getFundingRate();
      MarginBigDecimal oraclePrice = params.getOraclePrice();
      Date time = params.getTime();

      if (fundingService.isFundingPaid(symbol, time)) {
        log.error("Funding for symbol {} at {} is already paid", symbol, time);
        return;
      }
      List<Position> positions = positionService.getPositions(symbol).toList();
      if (!positions.isEmpty()) {
        log.info("Pay funding for {} positions.", positions.size());
      }
      for (Position position : positions) {
        if (!fundingService.isPositionFundingPaid(position, time)) {
          fundingService.payFunding(position, fundingRate, oraclePrice, time);
          matchingEngine.commit();
        } else {
          log.info("Already paid funding for position {} at {}", position, time);
        }
      }
      fundingService.setFundingPaid(symbol, time);
      // commit with no data to set funding paid
      matchingEngine.commit();
    } catch (Exception e) {
      log.error("Exception when pay funding fee for {}", params, e);
    }
  }
}

package com.sotatek.future.usecase;

import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.FundingHistory;
import com.sotatek.future.service.FundingService;
import lombok.RequiredArgsConstructor;

@RequiredArgsConstructor
public class FundingHistoryUseCase {

  private final FundingService fundingService;

  public void execute(Command command) {
    FundingHistory data = command.getFundingHistory();
    fundingService.update(data);
    fundingService.commit();
  }
}

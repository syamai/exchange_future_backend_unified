package com.sotatek.future.usecase;

import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.PositionHistory;
import com.sotatek.future.service.PositionHistoryService;
import lombok.RequiredArgsConstructor;

@RequiredArgsConstructor
public class PositionHistoryUseCase {

  private final PositionHistoryService positionHistoryService;

  public void execute(Command command) {
    PositionHistory data = command.getPositionHistory();
    positionHistoryService.update(data);
    positionHistoryService.commit();
  }
}

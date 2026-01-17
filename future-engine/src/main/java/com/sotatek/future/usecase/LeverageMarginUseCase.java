package com.sotatek.future.usecase;

import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.LeverageMargin;
import com.sotatek.future.service.LeverageMarginService;
import java.util.Collections;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;

@RequiredArgsConstructor
@Slf4j
public class LeverageMarginUseCase {

  private final LeverageMarginService leverageMarginService;

  public void loadLeverageMargin(Command command) {
    log.info("load leverage margin {}", command.getData());
    LeverageMargin leverageMargin = (LeverageMargin) command.getData();

    leverageMarginService.upsertEntitys(Collections.singletonList(leverageMargin));
  }
}

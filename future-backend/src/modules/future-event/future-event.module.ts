import { forwardRef, Module } from "@nestjs/common";
import { AccountsModule } from "../account/account.module";
import { FutureEventConsole } from "./future-event.console";
import { FutureEventService } from "./future-event.service";
import { FutureEventController } from "./future-event.controller";
import { FutureEventRevokeRewardService } from "./future-event-revoke-reward.service";
import { UpdateTradingVolumeCronUseCase } from "./use-case/update-trading-volume-cron-use-case";
import { UpsertTradingVolumeSessionUseCase } from "./use-case/upsert-trading-volume-use-case";
import { TradingVolumeService } from "./trading-volume.service";
import { UpdateTradingVolumeSessionUseCase } from "./use-case/update-trading-volume-session-use-case";
// import { UpdateTradingVolumeUseCase } from "./use-case/update-trading-volume-use-case";

@Module({
  imports: [forwardRef(() => AccountsModule)],
  providers: [
    FutureEventService,
    FutureEventConsole,
    FutureEventRevokeRewardService,
    UpdateTradingVolumeCronUseCase,
    UpsertTradingVolumeSessionUseCase,
    TradingVolumeService,
    UpdateTradingVolumeSessionUseCase
  ],
  controllers: [FutureEventController],
  exports: [FutureEventService, FutureEventRevokeRewardService],
})
export class FutureEventModule {}

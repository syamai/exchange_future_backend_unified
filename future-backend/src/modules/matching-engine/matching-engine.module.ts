import {
  CacheModule,
  HttpModule,
  Logger,
  Module,
  forwardRef,
} from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";
import { AccountsModule } from "src/modules/account/account.module";
import { FundingModule } from "src/modules/funding/funding.module";
import { IndexModule } from "src/modules/index/index.module";
import { InstrumentModule } from "src/modules/instrument/instrument.module";
import { MailModule } from "src/modules/mail/mail.module";
import { MatchingEngineTestConsole } from "src/modules/matching-engine/matching-engine-test.console";
import { MatchingEngineConsole } from "src/modules/matching-engine/matching-engine.console";
import { MatchingEngineService } from "src/modules/matching-engine/matching-engine.service";
import { NotificationService } from "src/modules/matching-engine/notifications.service";
import { OrderController } from "src/modules/order/order.controller";
import { OrderModule } from "src/modules/order/order.module";
import { PositionModule } from "src/modules/position/position.module";
import { TradeModule } from "src/modules/trade/trade.module";
import { TransactionModule } from "src/modules/transaction/transaction.module";
import { LeverageModule } from "../leverage-margin/leverage-margin.module";
import { BalanceModule } from "../balance/balance.module";
import { UserSettingModule } from "../user-setting/user-setting.module";
import { UserSettingeService } from "../user-setting/user-setting.service";
import { RedisModule } from "nestjs-redis";
import { UsersModule } from "../user/users.module";
import { OrderAverageByTradeModule } from "../order-average-by-trade/order-average-by-trade.module";
import { FirebaseNotiModule } from "../firebase-noti-module/firebase-noti.module";
import { BotModule } from "../bot/bot.module";
import { SaveOrderToCacheUseCase } from "./usecase/save-order-to-cache.usecase";
import { SaveOrderToDbUseCase } from "./usecase/save-order-to-db.usecase";
import { SavePositionUseCase } from "./usecase/save-position.usecase";
import { SaveAccountToCacheUseCase } from "./usecase/save-account-to-cache.usecase";
import { SaveAccountToDbUseCase } from "./usecase/save-account-to-db.usecase";
import { SaveMarginHistoriesUseCase } from "./usecase/save-margin-histories.usecase";
import { SavePositionHistoriesUseCase } from "./usecase/save-position-histories.usecase";
import { SavePositionHistoryBySessionFromMarginHistoryUseCase } from "./usecase/save-position-history-by-session-from-margin-history.usecase";
import { UserMarginModeModule } from "../user-margin-mode/user-margin-mode.module";
import { SaveUserPositionToCacheUseCase } from "./usecase/save-user-position-to-cache.usecase";
import { CheckToSeedLiquidationOrderIdsUseCase } from "./usecase/check-to-seed-liq-order-ids.usecase";

@Module({
  providers: [
    Logger,
    MatchingEngineService,
    MatchingEngineConsole,
    MatchingEngineTestConsole,
    NotificationService,
    UserSettingeService,
    SaveOrderToCacheUseCase,
    SaveOrderToDbUseCase,
    SavePositionUseCase,
    SaveAccountToCacheUseCase,
    SaveAccountToDbUseCase,
    SaveMarginHistoriesUseCase,
    SavePositionHistoriesUseCase,
    SavePositionHistoryBySessionFromMarginHistoryUseCase,
    SaveUserPositionToCacheUseCase,
    CheckToSeedLiquidationOrderIdsUseCase
  ],
  // controllers: [OrderController],
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    RedisModule.register({ ...redisConfig }),
    forwardRef(() => AccountsModule),
    forwardRef(() => FundingModule),
    forwardRef(() => IndexModule),
    forwardRef(() => InstrumentModule),
    forwardRef(() => OrderModule),
    forwardRef(() => PositionModule),
    forwardRef(() => TradeModule),
    forwardRef(() => TransactionModule),
    forwardRef(() => MailModule),
    forwardRef(() => LeverageModule),
    forwardRef(() => BalanceModule),
    forwardRef(() => UserSettingModule),
    forwardRef(() => HttpModule),
    forwardRef(() => UsersModule),
    forwardRef(() => OrderAverageByTradeModule),
    forwardRef(() => FirebaseNotiModule),
    forwardRef(() => BotModule),
    forwardRef(() => UserMarginModeModule),
  ],
  exports: [NotificationService],
})
export class MatchingEngineModule {}

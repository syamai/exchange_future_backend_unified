import { BullModule } from "@nestjs/bull";
import { CacheModule, forwardRef, Logger } from "@nestjs/common";
import { ScheduleModule } from "@nestjs/schedule";
import { TypeOrmModule, TypeOrmModuleOptions } from "@nestjs/typeorm";
import * as redisStore from "cache-manager-redis-store";
import { ConsoleModule } from "nestjs-console";
import { masterConfig, reportConfig } from "src/configs/database.config";
import { redisConfig } from "src/configs/redis.config";
import { DatabaseCommonModule } from "src/models/database-common";
import { AccountsModule } from "src/modules/account/account.module";
import { AuthModule } from "src/modules/auth/auth.module";
import { CandleModule } from "src/modules/candle/candle.module";
import { DexModule } from "src/modules/dex/dex.module";
import { EventModule } from "src/modules/events/event.module";
import { FundingModule } from "src/modules/funding/funding.module";
import { HealthModule } from "src/modules/health/health.module";
import { IndexModule } from "src/modules/index/index.module";
import { InstrumentModule } from "src/modules/instrument/instrument.module";
import { LatestBlockModule } from "src/modules/latest-block/latest-block.module";
import { MailModule } from "src/modules/mail/mail.module";
import { MatchingEngineModule } from "src/modules/matching-engine/matching-engine.module";
import { OrderModule } from "src/modules/order/order.module";
import { OrderbookModule } from "src/modules/orderbook/orderbook.module";
import { PositionModule } from "src/modules/position/position.module";
import { SettingModule } from "src/modules/setting/setting.module";
import { TickerModule } from "src/modules/ticker/ticker.module";
import { TradeModule } from "src/modules/trade/trade.module";
import { TransactionModule } from "src/modules/transaction/transaction.module";
import { UsersModule } from "src/modules/user/users.module";
import { HttpClientModule } from "src/shares/http-clients/http.module";
import { KafkaModule } from "src/shares/kafka-client/kafka-module";
import { CoinInfoModule } from "./modules/coin-info/coin-info.module";
import { AccessTokenModule } from "./modules/access-token/access-token.module";
import { TradingRulesModule } from "./modules/trading-rules/trading-rules.module";
import { LeverageModule } from "./modules/leverage-margin/leverage-margin.module";
import { UserMarginModeModule } from "./modules/user-margin-mode/user-margin-mode.module";
import { UserSettingModule } from "./modules/user-setting/user-setting.module";
import { MasterDataModule } from "./modules/master-data/master-data.module";
import { MetricsModule } from "./modules/metrics/metrics.module";
import { AssetsModule } from "./modules/assets/assets.module";
import { SpotModule } from "./modules/spot/spot.modules";
import { TranslateModule } from "./modules/translate/translate.module";
import { BotModule } from "./modules/bot/bot.module";
import { FirebaseNotiModule } from "./modules/firebase-noti-module/firebase-noti.module";
import { UserStatisticModule } from "./modules/statistic/user-statistic.module";
import { FutureEventModule } from "./modules/future-event/future-event.module";
import { RedisModule } from "./shares/redis-client/redis-module";
import { BullMqModule } from "./modules/bull-mq/bull-mq.module";
import { ToolModule } from "./shares/tools/tool.module";
import { PositionHistoryBySessionModule } from "./modules/position-history-by-session/position-history-by-session.module";
import { OrderRouterModule } from "./shares/order-router/order-router.module";

const Modules = [
  Logger,
  TypeOrmModule.forRoot(masterConfig as TypeOrmModuleOptions),
  TypeOrmModule.forRoot(reportConfig as TypeOrmModuleOptions),
  ScheduleModule.forRoot(),
  DatabaseCommonModule,
  KafkaModule,
  OrderRouterModule,
  ConsoleModule,
  HttpClientModule,
  BullModule.forRoot({
    redis: redisConfig,
  }),
  CacheModule.register({
    store: redisStore,
    ...redisConfig,
    isGlobal: true,
  }),
  RedisModule,
  EventModule,
  AuthModule,
  forwardRef(() => HealthModule),
  UsersModule,
  AccountsModule,
  OrderModule,
  InstrumentModule,
  PositionModule,
  IndexModule,
  FundingModule,
  DexModule,
  MatchingEngineModule,
  OrderbookModule,
  TickerModule,
  TradeModule,
  LatestBlockModule,
  CandleModule,
  TransactionModule,
  MailModule,
  SettingModule,
  CoinInfoModule,
  AccessTokenModule,
  UserMarginModeModule,
  UserSettingModule,
  TradingRulesModule,
  LeverageModule,
  MasterDataModule,
  MetricsModule,
  AssetsModule,
  SpotModule,
  TranslateModule,
  BotModule,
  FirebaseNotiModule,
  UserStatisticModule,
  FutureEventModule,
  BullMqModule,
  ToolModule,
  PositionHistoryBySessionModule
];
export default Modules;

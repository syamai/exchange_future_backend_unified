import { CacheModule, Module, forwardRef } from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";
import { AccountsModule } from "src/modules/account/account.module";
import { PositionController } from "src/modules/position/position.controller";
import { PositionService } from "src/modules/position/position.service";
import { IndexModule } from "../index/index.module";
import { InstrumentModule } from "../instrument/instrument.module";
import { TradingRulesModule } from "../trading-rules/trading-rules.module";
import { PositionConsole } from "./position.console";
import { OrderbookModule } from "../orderbook/orderbook.module";
import { OrderModule } from "../order/order.module";
import { LeverageModule } from "../leverage-margin/leverage-margin.module";
import { LeverageMarginService } from "../leverage-margin/leverage-margin.service";
import { TickerModule } from "../ticker/ticker.module";
import { ExcelService } from "../export-excel/services/excel.service";
import { SaveUserMarketOrderUseCase } from "../order/usecase/save-user-market-order.usecase";
import { OrderService } from "../order/order.service";
import { UserMarginModeModule } from "../user-margin-mode/user-margin-mode.module";
import { BalanceModule } from "../balance/balance.module";
import { BotModule } from "../bot/bot.module";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    forwardRef(() => AccountsModule),
    forwardRef(() => InstrumentModule),
    forwardRef(() => TradingRulesModule),
    forwardRef(() => IndexModule),
    forwardRef(() => OrderbookModule),
    forwardRef(() => OrderModule),
    forwardRef(() => TickerModule),

    forwardRef(() => UserMarginModeModule),
    forwardRef(() => BalanceModule),
    forwardRef(() => BotModule),
  ],
  providers: [PositionService, PositionConsole, LeverageMarginService, ExcelService, SaveUserMarketOrderUseCase],
  controllers: [PositionController],
  exports: [PositionService],
})
export class PositionModule {}

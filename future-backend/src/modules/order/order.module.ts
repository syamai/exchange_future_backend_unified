import { CacheModule, Logger, Module, forwardRef } from "@nestjs/common";
import { redisConfig } from "src/configs/redis.config";
import { AccountsModule } from "src/modules/account/account.module";
import { InstrumentModule } from "src/modules/instrument/instrument.module";
import { OrderController } from "src/modules/order/order.controller";
import { OrderService } from "src/modules/order/order.service";
import { PositionModule } from "src/modules/position/position.module";
import { UsersModule } from "src/modules/user/users.module";
import { TradeModule } from "../trade/trade.module";
import * as redisStore from "cache-manager-redis-store";
import { OrderConsole } from "./order.console";
import { TradingRulesModule } from "../trading-rules/trading-rules.module";
import { OrderbookModule } from "../orderbook/orderbook.module";
import { ExcelService } from "../export-excel/services/excel.service";
import { UserMarginModeModule } from "../user-margin-mode/user-margin-mode.module";
import { BalanceModule } from "../balance/balance.module";
import { TickerModule } from "../ticker/ticker.module";
import { BotModule } from "../bot/bot.module";
import { MatchingEngineModule } from "../matching-engine/matching-engine.module";
import { SaveOrderFromClientV2UseCase } from "./usecase/save-order-from-client-v2.usecase";
import { CancelOrderFromClientUseCase } from "./usecase/cancel-order-from-client.usecase";
import { GetOpenOrdersByAccountFromRedisUseCase } from "./usecase/get-open-orders-by-account-from-redis.usecase";
import { SaveUserMarketOrderUseCase } from "./usecase/save-user-market-order.usecase";

@Module({
  imports: [
    Logger,
    forwardRef(() => AccountsModule),
    forwardRef(() => InstrumentModule),
    forwardRef(() => PositionModule),
    forwardRef(() => UsersModule),
    forwardRef(() => TradeModule),
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    forwardRef(() => TradingRulesModule),
    forwardRef(() => OrderbookModule),
    forwardRef(() => UserMarginModeModule),
    forwardRef(() => BalanceModule),
    forwardRef(() => TickerModule),
    forwardRef(() => BotModule),
    forwardRef(() => MatchingEngineModule),
  ],
  providers: [OrderService, Logger, OrderConsole, ExcelService, SaveOrderFromClientV2UseCase, CancelOrderFromClientUseCase, GetOpenOrdersByAccountFromRedisUseCase, SaveUserMarketOrderUseCase],
  controllers: [OrderController],
  exports: [OrderService],
})
export class OrderModule {}

import { CacheModule, Module } from "@nestjs/common";
import { redisConfig } from "src/configs/redis.config";
import { DatabaseCommonModule } from "src/models/database-common";
import { MasterDataController } from "./master-data.controller";
import { MasterDataService } from "./master-data.service";
import * as redisStore from "cache-manager-redis-store";
import { TradingRulesModule } from "../trading-rules/trading-rules.module";
import { CoinInfoModule } from "../coin-info/coin-info.module";
import { LeverageModule } from "../leverage-margin/leverage-margin.module";
import { InstrumentModule } from "../instrument/instrument.module";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      host: redisConfig.host,
      port: redisConfig.port,
    }),
    DatabaseCommonModule,
    TradingRulesModule,
    CoinInfoModule,
    LeverageModule,
    InstrumentModule,
  ],
  controllers: [MasterDataController],
  providers: [MasterDataService],
  exports: [MasterDataService],
})
export class MasterDataModule {}

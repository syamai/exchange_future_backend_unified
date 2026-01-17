import { CacheModule, Module, forwardRef } from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { RedisModule } from "nestjs-redis";
import { redisConfig } from "src/configs/redis.config";
import { FundingModule } from "src/modules/funding/funding.module";
import { IndexService } from "src/modules/index/index.service";
import { InstrumentModule } from "src/modules/instrument/instrument.module";
import { OrderbookModule } from "src/modules/orderbook/orderbook.module";
import { IndexController } from "./index.controller";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    RedisModule.register({ ...redisConfig }),
    forwardRef(() => InstrumentModule),
    forwardRef(() => FundingModule),
    forwardRef(() => OrderbookModule),
  ],
  providers: [IndexService],
  exports: [IndexService],
  controllers: [IndexController],
})
export class IndexModule {}

import { CacheModule, Module } from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";
import { CandleConsole } from "src/modules/candle/candle.console";
import { CandlesController } from "src/modules/candle/candle.controller";
import { CandleService } from "src/modules/candle/candle.service";
import { InstrumentModule } from "src/modules/instrument/instrument.module";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    InstrumentModule,
  ],
  providers: [CandleService, CandleConsole],
  exports: [CandleService],
  controllers: [CandlesController],
})
export class CandleModule {}

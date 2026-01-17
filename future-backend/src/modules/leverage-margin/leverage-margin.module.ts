import { CacheModule, Logger, Module } from "@nestjs/common";
import { redisConfig } from "src/configs/redis.config";
import { LeverageMarginController } from "./leverage-margin.controller";
import { LeverageMarginService } from "./leverage-margin.service";
import * as redisStore from "cache-manager-redis-store";

@Module({
  imports: [
    Logger,
    CacheModule.register({
      store: redisStore,
      host: redisConfig.host,
      port: redisConfig.port,
    }),
  ],
  providers: [LeverageMarginService, Logger],
  controllers: [LeverageMarginController],
  exports: [LeverageMarginService],
})
export class LeverageModule {}

import { CacheModule, Logger, Module } from "@nestjs/common";
import { CoinInfoConsole } from "./coin-info.console";
import { CoinInfoService } from "./coin-info.service";
import { CoinInfoController } from "./coin-info.controller";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      host: redisConfig.host,
      port: redisConfig.port,
    }),
  ],
  providers: [Logger, CoinInfoService, CoinInfoConsole],
  exports: [CoinInfoService],
  controllers: [CoinInfoController],
})
export class CoinInfoModule {}

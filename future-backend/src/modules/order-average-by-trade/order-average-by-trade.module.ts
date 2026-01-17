import { CacheModule, Logger, Module } from "@nestjs/common";
import { redisConfig } from "src/configs/redis.config";
import * as redisStore from "cache-manager-redis-store";
import { DatabaseCommonModule } from "src/models/database-common";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    DatabaseCommonModule,
  ],
  providers: [Logger],
  controllers: [],
  exports: [],
})
export class OrderAverageByTradeModule {} 
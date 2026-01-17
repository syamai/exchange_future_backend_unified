import { CacheModule, Module } from "@nestjs/common";
import { DatabaseCommonModule } from "src/models/database-common";
import { TradingRulesService } from "./trading-rule.service";
import { TradingRulesController } from "./trading-rules.controller";
import { redisConfig } from "src/configs/redis.config";
import * as redisStore from "cache-manager-redis-store";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      host: redisConfig.host,
      port: redisConfig.port,
    }),
    DatabaseCommonModule,
  ],
  controllers: [TradingRulesController],
  providers: [TradingRulesService],
  exports: [TradingRulesService],
})
export class TradingRulesModule {}

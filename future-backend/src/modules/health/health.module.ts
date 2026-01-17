import { CacheModule, forwardRef, Logger, Module } from "@nestjs/common";
import { RedisModule } from "nestjs-redis";
import { CandleModule } from "src/modules/candle/candle.module";
import { FundingModule } from "src/modules/funding/funding.module";
import { HealthConsole } from "src/modules/health/health.console";
import { HealthController } from "src/modules/health/health.controller";
import { HealthService } from "src/modules/health/health.service";
import { IndexModule } from "src/modules/index/index.module";
import { LatestBlockModule } from "src/modules/latest-block/latest-block.module";
import { MailModule } from "src/modules/mail/mail.module";
import { redisConfig } from "src/configs/redis.config";
import * as redisStore from "cache-manager-redis-store";


@Module({
  controllers: [HealthController],
  providers: [HealthService, HealthConsole, Logger],
  imports: [
    forwardRef(() => FundingModule),
    IndexModule,
    LatestBlockModule,
    MailModule,
    CandleModule,
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    RedisModule.register({ ...redisConfig }),
  ],
})
export class HealthModule {}

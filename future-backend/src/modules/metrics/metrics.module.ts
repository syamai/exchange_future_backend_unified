import { CacheModule, Module } from "@nestjs/common";
import { Registry } from "prom-client";
import { MetricsController } from "./metrics.controller";
import { MetricsService } from "./metrics.service";
import { redisConfig } from "../../configs/redis.config";
import * as redisStore from "cache-manager-redis-store";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
  ],
  controllers: [MetricsController],
  providers: [
    {
      provide: "PROM_REGISTRY",
      useValue: new Registry(),
    },
    MetricsService,
  ],
})
export class MetricsModule {}

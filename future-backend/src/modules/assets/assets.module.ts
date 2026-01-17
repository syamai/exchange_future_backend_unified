import { CacheModule, Module } from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";
import AssetsSeedCommand from "./assets.console";
import { AssetsController } from "./assets.controller";
import { AssetsService } from "./assets.service";

@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
  ],
  providers: [AssetsService, AssetsSeedCommand],
  controllers: [AssetsController],
  exports: [AssetsService],
})
export class AssetsModule {}

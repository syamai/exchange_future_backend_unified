import { CacheModule, forwardRef, Logger, Module } from "@nestjs/common";
import { DatabaseCommonModule } from "src/models/database-common";
import { AccountsModule } from "../account/account.module";
import { UserMarginModeController } from "./user-margin-mode.controller";
import { UserMarginModeService } from "./user-margin-mode.service";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";


@Module({
  imports: [
    DatabaseCommonModule,
    forwardRef(() => AccountsModule),
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
  ],
  controllers: [UserMarginModeController],
  providers: [UserMarginModeService],
  exports: [UserMarginModeService],
})
export class UserMarginModeModule {}

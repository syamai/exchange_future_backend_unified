import { CacheModule, Global, Module, forwardRef } from "@nestjs/common";
import { UsersModule } from "src/modules/user/users.module";
import { BotService } from "./bot.service";
import { redisConfig } from "src/configs/redis.config";
import * as redisStore from "cache-manager-redis-store";
import { BotConsole } from "./bot.console";
import { BotInMemoryService } from "./bot.in-memory.service";

@Global()
@Module({
  imports: [
    forwardRef(() => UsersModule),
    CacheModule.register({
      store: redisStore,
      host: redisConfig.host,
      port: redisConfig.port,
    }),
  ],
  providers: [BotService, BotInMemoryService, BotConsole],
  controllers: [],
  exports: [BotService, BotInMemoryService],
})
export class BotModule {}

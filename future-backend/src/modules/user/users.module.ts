import { CacheModule, Logger, Module } from "@nestjs/common";
import { UserController } from "src/modules/user/users.controller";
import { UserService } from "src/modules/user/users.service";
import { JwtModule } from "@nestjs/jwt";
import { jwtConstants } from "src/modules/auth/auth.constants";
import { MailModule } from "src/modules/mail/mail.module";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";

@Module({
  imports: [
    Logger,
    JwtModule.register({
      secret: jwtConstants.accessTokenSecret,
      signOptions: { expiresIn: jwtConstants.accessTokenExpiry },
    }),
    MailModule,
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
    }),
  ],
  providers: [UserService, Logger],
  exports: [UserService],
  controllers: [UserController],
})
export class UsersModule {}

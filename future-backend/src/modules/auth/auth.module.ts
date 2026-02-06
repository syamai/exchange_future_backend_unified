import { CacheModule, Module } from "@nestjs/common";
import { PassportModule } from "@nestjs/passport";
import { JwtModule } from "@nestjs/jwt";
import { UsersModule } from "src/modules/user/users.module";
import { AuthService } from "src/modules/auth/auth.service";
import { JwtStrategy } from "src/modules/auth/strategies/jwt.strategy";
import { AuthController } from "src/modules/auth/auth.controller";
import { AuthConsole } from "src/modules/auth/auth.console";
import * as redisStore from "cache-manager-redis-store";
import { redisConfig } from "src/configs/redis.config";
import { MailModule } from "src/modules/mail/mail.module";
import * as config from "config";
@Module({
  imports: [
    UsersModule,
    PassportModule.register({ defaultStrategy: "jwt" }),
    JwtModule.register({
      secretOrPrivateKey: config.get("jwt_key.private").toString(),
      signOptions: {
        expiresIn: 3600,
        algorithm: "RS256",
      },
    }),
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    MailModule,
  ],
  providers: [AuthService, JwtStrategy, AuthConsole],
  exports: [AuthService],
  controllers: [AuthController],
})
export class AuthModule {}

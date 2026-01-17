import { MailerModule } from "@nestjs-modules/mailer";
import { Logger, MiddlewareConsumer, Module, forwardRef } from "@nestjs/common";

import Modules from "src/modules";

import { LoggerMiddleware } from "src/shares/middlewares/logger.middleware";
import { BalanceModule } from "./modules/balance/balance.module";
import { MailModule } from "./modules/mail/mail.module";

@Module({
  imports: [
    ...Modules,
    forwardRef(() => BalanceModule),
    MailerModule.forRoot({
      transport: {
        host: process.env.MAIL_HOST,
        port: Number(process.env.MAIL_PORT),
        secure: true,
        auth: {
          user: process.env.MAIL_ACCOUNT,
          pass: process.env.MAIL_PASSWORD,
        },
        debug: true,
      },
    }),
    forwardRef(() => MailModule),
  ],
  controllers: [],
  providers: [Logger],
})
export class AppModules {
  configure(consumer: MiddlewareConsumer): void {
    consumer.apply(LoggerMiddleware).exclude("/api/v1/ping").forRoutes("/");
  }
}

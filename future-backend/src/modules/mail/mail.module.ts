import { MailerModule } from "@nestjs-modules/mailer";
import { HandlebarsAdapter } from "@nestjs-modules/mailer/dist/adapters/handlebars.adapter";
import { BullModule } from "@nestjs/bull";
import { BullModuleOptions } from "@nestjs/bull/dist/interfaces/bull-module-options.interface";
import {
  CacheModule,
  HttpModule,
  Logger,
  Module,
  Provider,
} from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { join } from "path";
import { mailConfig } from "src/configs/mail.config";
import { redisConfig } from "src/configs/redis.config";
import { MailConsole } from "src/modules/mail/mail.console";
import { MailProcessor } from "src/modules/mail/mail.processor";
import { MailService } from "src/modules/mail/mail.service";
import { UserService } from "src/modules/user/users.service";
import { UserSettingeService } from "../user-setting/user-setting.service";
import { TransportType } from "@nestjs-modules/mailer/dist/interfaces/mailer-options.interface";
import { TranslateModule } from "../translate/translate.module";
import { FundingService } from "../funding/funding.service";
import { LeverageMarginService } from "../leverage-margin/leverage-margin.service";
import { NotificationService } from "../matching-engine/notifications.service";
import { FirebaseNotiModule } from "../firebase-noti-module/firebase-noti.module";

const bullOptions: BullModuleOptions = { name: "mail" };
const providers: Provider[] = [
  MailService,
  MailConsole,
  Logger,
  UserService,
  UserSettingeService,
  FundingService,
  LeverageMarginService,
  NotificationService,
];
if (mailConfig.enable) {
  providers.push(MailProcessor);
} else {
  bullOptions["processors"] = [];
}

@Module({
  imports: [
    HttpModule,
    MailerModule.forRoot({
      transport: mailConfig as TransportType,
      defaults: {
        from: mailConfig.from,
      },
      template: {
        dir: join(__dirname, "templates"),
        adapter: new HandlebarsAdapter(),
        options: {
          strict: true,
        },
      },
    }),
    BullModule.registerQueue(bullOptions),
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    TranslateModule,
    FirebaseNotiModule
  ],
  providers: providers,
  exports: [MailService],
})
export class MailModule {}

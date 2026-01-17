import { BullModule, BullModuleOptions } from "@nestjs/bull";
import { CacheModule, forwardRef, Module, Provider } from "@nestjs/common";
import * as redisStore from "cache-manager-redis-store";
import { Logger } from "ethers/lib/utils";
import { RedisModule } from "nestjs-redis";
import { mailConfig } from "src/configs/mail.config";
import { redisConfig } from "src/configs/redis.config";
import { AccountsModule } from "src/modules/account/account.module";
import { FundingConsole } from "src/modules/funding/funding.console";
import { FundingController } from "src/modules/funding/funding.controller";
import { FundingService } from "src/modules/funding/funding.service";
import { OrderbookModule } from "src/modules/orderbook/orderbook.module";
import { InstrumentModule } from "../instrument/instrument.module";
import { LeverageModule } from "../leverage-margin/leverage-margin.module";
import { MailConsole } from "../mail/mail.console";
import { MailModule } from "../mail/mail.module";
import { MailProcessor } from "../mail/mail.processor";
import { MailService } from "../mail/mail.service";
import { NotificationService } from "../matching-engine/notifications.service";
import { UserSettingeService } from "../user-setting/user-setting.service";
import { UserService } from "../user/users.service";
import { FirebaseNotiModule } from "../firebase-noti-module/firebase-noti.module";

const bullOptions: BullModuleOptions = { name: "mail" };
const providers: Provider[] = [
  MailService,
  MailConsole,
  Logger,
  UserService,
  UserSettingeService,
];
if (mailConfig.enable) {
  providers.push(MailProcessor);
} else {
  bullOptions["processors"] = [];
}
@Module({
  imports: [
    CacheModule.register({
      store: redisStore,
      ...redisConfig,
      isGlobal: true,
    }),
    RedisModule.register({ ...redisConfig }),
    BullModule.registerQueue(bullOptions),

    forwardRef(() => OrderbookModule),
    forwardRef(() => AccountsModule),
    LeverageModule,
    MailModule,
    forwardRef(() => InstrumentModule),
    FirebaseNotiModule
  ],
  providers: [FundingService, FundingConsole, NotificationService, UserService],
  controllers: [FundingController],
  exports: [FundingService],
})
export class FundingModule {}

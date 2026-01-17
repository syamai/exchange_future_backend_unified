import { forwardRef, Logger, Module } from "@nestjs/common";
import { FirebaseAdminService } from "./firebase-admin.service";
import { FirebaseConsole } from "./firebase.console";
import { FirebaseSendNotiUseCase } from "./use-case/firebase-send-noti-use-case";
import { UsersModule } from "../user/users.module";
import { PairPriceChangeNotiUseCase } from "./pair-price-change-noti/pair-price-change-noti-use-case";

@Module({
  imports: [forwardRef(() => UsersModule)],
  providers: [FirebaseAdminService, FirebaseConsole, Logger, FirebaseSendNotiUseCase, PairPriceChangeNotiUseCase],
  exports: [FirebaseAdminService],
  controllers: [],
})
export class FirebaseNotiModule {}

import { Logger, Module } from "@nestjs/common";
import { DatabaseCommonModule } from "src/models/database-common";
import { UserSettingController } from "./user-setting.controller";
import { UserSettingeService } from "./user-setting.service";

@Module({
  imports: [DatabaseCommonModule],
  controllers: [UserSettingController],
  providers: [UserSettingeService, Logger],
  exports: [],
})
export class UserSettingModule {}

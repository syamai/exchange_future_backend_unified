import { Module } from "@nestjs/common";
import { SettingController } from "src/modules/setting/setting.controller";
import { SettingService } from "src/modules/setting/setting.service";

@Module({
  imports: [],
  controllers: [SettingController],
  providers: [SettingService],
  exports: [SettingService],
})
export class SettingModule {}

import { Body, Controller, Get, Post, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { UserSettingEntity } from "src/models/entities/user-setting.entity";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { JwtAuthGuard } from "../auth/guards/jwt-auth.guard";
import { UpdateNotificationSettingDto } from "./dto/user-setting-dto";
import { UserSettingeService } from "./user-setting.service";

@Controller("user-setting")
@ApiTags("User Setting")
@ApiBearerAuth()
export class UserSettingController {
  constructor(private readonly userSettingService: UserSettingeService) {}

  @Post("preference")
  @UseGuards(JwtAuthGuard)
  async updateUserPreferenceSetting(
    @Body() body: UpdateNotificationSettingDto,
    @UserID() userId: number
  ): Promise<ResponseDto<UserSettingEntity>> {
    return {
      data: await this.userSettingService.updateUserSettingByKey(
        UserSettingEntity.NOTIFICATION,
        body,
        userId
      ),
    };
  }

  @Get("preference")
  @UseGuards(JwtAuthGuard)
  async getUserPreferenceSetting(
    @UserID() userId: number
  ): Promise<ResponseDto<UserSettingEntity>> {
    const userSetting = await this.userSettingService.getUserSettingByKey(
      UserSettingEntity.NOTIFICATION,
      userId
    );
    return {
      data: userSetting,
    };
  }
}

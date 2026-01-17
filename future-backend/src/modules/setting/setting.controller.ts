import {
  Body,
  Controller,
  Get,
  HttpException,
  HttpStatus,
  Post,
  UseGuards,
} from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import BigNumber from "bignumber.js";
import { SettingEntity } from "src/models/entities/setting.entity";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { UpdateSettingDto } from "src/modules/setting/dto/update-setting.dto";
import { SettingService } from "src/modules/setting/setting.service";
import { AdminAndSuperAdmin } from "src/shares/decorators/roles.decorator";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { httpErrors } from "src/shares/exceptions";

@Controller("setting")
@ApiTags("Setting")
@ApiBearerAuth()
export class SettingController {
  constructor(private readonly settingService: SettingService) {}

  @Get()
  async getAll(): Promise<ResponseDto<SettingEntity[]>> {
    return {
      data: await this.settingService.findAll(),
    };
  }

  @Get("minimum-withdrawal")
  async getMinimumWithdrawal(): Promise<ResponseDto<SettingEntity>> {
    return {
      data: await this.settingService.findBySettingKey(
        SettingEntity.MINIMUM_WITHDRAWAL
      ),
    };
  }

  @Post("minimum-withdrawal")
  @UseGuards(JwtAuthGuard, AdminAndSuperAdmin)
  async updateMinimumWithdrawal(
    @Body() dto: UpdateSettingDto
  ): Promise<ResponseDto<SettingEntity>> {
    if (new BigNumber(dto.value).lt(0))
      throw new HttpException(
        httpErrors.SETTING_NOT_VALID,
        HttpStatus.BAD_REQUEST
      );
    return {
      data: await this.settingService.updateSettingByKey(
        SettingEntity.MINIMUM_WITHDRAWAL,
        dto.value
      ),
    };
  }

  @Get("withdrawal-fee")
  async getWithdrawalFee(): Promise<ResponseDto<SettingEntity>> {
    return {
      data: await this.settingService.findBySettingKey(
        SettingEntity.WITHDRAW_FEE
      ),
    };
  }

  @Post("withdrawal-fee")
  @UseGuards(JwtAuthGuard, AdminAndSuperAdmin)
  async updateWithdrawalFee(
    @Body() dto: UpdateSettingDto
  ): Promise<ResponseDto<SettingEntity>> {
    if (new BigNumber(dto.value).lt(0))
      throw new HttpException(
        httpErrors.SETTING_NOT_VALID,
        HttpStatus.BAD_REQUEST
      );
    return {
      data: await this.settingService.updateSettingByKey(
        SettingEntity.WITHDRAW_FEE,
        dto.value
      ),
    };
  }
}

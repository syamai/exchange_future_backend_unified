import {
  Body,
  Controller,
  Get,
  Param,
  Patch,
  Post,
  Put,
  Query,
  UseGuards,
} from "@nestjs/common";
import { ApiBearerAuth, ApiOperation, ApiResponse, ApiTags } from "@nestjs/swagger";
import { UserBonusV2Entity } from "src/models/entities/user-bonus-v2.entity";
import { EventSettingV2Entity } from "src/models/entities/event-setting-v2.entity";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { JwtAuthGuard } from "../auth/guards/jwt-auth.guard";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { FutureEventV2Service } from "./future-event-v2.service";
import { CreateEventSettingV2Dto } from "./dto/create-event-setting-v2.dto";
import { UpdateEventSettingV2Dto } from "./dto/update-event-setting-v2.dto";
import { GrantBonusV2Dto } from "./dto/grant-bonus-v2.dto";
import { AdminBonusV2QueryDto } from "./dto/admin-bonus-v2-query.dto";

@ApiTags("Future Event V2")
@Controller("future-event-v2")
@ApiBearerAuth()
export class FutureEventV2Controller {
  constructor(private readonly futureEventV2Service: FutureEventV2Service) {}

  // ===== Admin: Event Settings =====

  @Post("admin/settings")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Create event setting" })
  @ApiResponse({ status: 201, description: "Event setting created" })
  async createEventSetting(
    @Body() dto: CreateEventSettingV2Dto
  ): Promise<ResponseDto<EventSettingV2Entity>> {
    const data = await this.futureEventV2Service.createEventSetting(dto);
    return { data };
  }

  @Put("admin/settings/:id")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Update event setting" })
  @ApiResponse({ status: 200, description: "Event setting updated" })
  async updateEventSetting(
    @Param("id") id: number,
    @Body() dto: UpdateEventSettingV2Dto
  ): Promise<ResponseDto<EventSettingV2Entity>> {
    const data = await this.futureEventV2Service.updateEventSetting(id, dto);
    return { data };
  }

  @Patch("admin/settings/:id/status")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Toggle event status (ACTIVE/INACTIVE)" })
  @ApiResponse({ status: 200, description: "Event status toggled" })
  async toggleEventStatus(
    @Param("id") id: number
  ): Promise<ResponseDto<EventSettingV2Entity>> {
    const data = await this.futureEventV2Service.toggleEventStatus(id);
    return { data };
  }

  @Get("admin/settings")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Get all event settings" })
  @ApiResponse({ status: 200, description: "Event settings list" })
  async getEventSettings(): Promise<ResponseDto<EventSettingV2Entity[]>> {
    const data = await this.futureEventV2Service.getEventSettings();
    return { data };
  }

  @Get("admin/settings/:id")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Get event setting by ID" })
  @ApiResponse({ status: 200, description: "Event setting details" })
  async getEventSettingById(
    @Param("id") id: number
  ): Promise<ResponseDto<EventSettingV2Entity | null>> {
    const data = await this.futureEventV2Service.getEventSettingById(id);
    return { data };
  }

  // ===== Admin: Bonus Management =====

  @Post("admin/grant-bonus")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Grant bonus manually" })
  @ApiResponse({ status: 201, description: "Bonus granted" })
  async grantBonus(@Body() dto: GrantBonusV2Dto): Promise<ResponseDto<UserBonusV2Entity>> {
    const data = await this.futureEventV2Service.grantBonus(dto);
    return { data };
  }

  @Get("admin/bonuses")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Get bonuses with filters and pagination" })
  @ApiResponse({ status: 200, description: "Bonuses list" })
  async getBonuses(
    @Query() pagination: PaginationDto,
    @Query() filters: AdminBonusV2QueryDto
  ): Promise<ResponseDto<UserBonusV2Entity[]>> {
    const [bonuses, total] = await this.futureEventV2Service.getBonusesWithPagination(
      filters,
      pagination
    );
    return {
      data: bonuses,
      metadata: {
        totalPage: Math.ceil(total / pagination.size),
        total,
      },
    };
  }

  @Post("admin/revoke-bonus/:id")
  @UseGuards(JwtAdminGuard)
  @ApiOperation({ summary: "Revoke bonus" })
  @ApiResponse({ status: 200, description: "Bonus revoked" })
  async revokeBonus(
    @Param("id") id: number,
    @Body("reason") reason: string
  ): Promise<ResponseDto<{ success: boolean }>> {
    await this.futureEventV2Service.revokeBonus(id, reason || "Admin revoked");
    return { data: { success: true } };
  }

  // ===== User: My Bonuses =====

  @Get("my-bonuses")
  @UseGuards(JwtAuthGuard)
  @ApiOperation({ summary: "Get my bonuses" })
  @ApiResponse({ status: 200, description: "User bonuses list" })
  async getMyBonuses(@UserID() userId: number): Promise<ResponseDto<UserBonusV2Entity[]>> {
    const data = await this.futureEventV2Service.getUserBonuses(userId);
    return { data };
  }

  @Get("my-bonuses/:id/history")
  @UseGuards(JwtAuthGuard)
  @ApiOperation({ summary: "Get bonus history" })
  @ApiResponse({ status: 200, description: "Bonus history" })
  async getBonusHistory(@Param("id") id: number): Promise<ResponseDto<any[]>> {
    const data = await this.futureEventV2Service.getBonusHistory(id);
    return { data };
  }

  // ===== Public: Active Events =====

  @Get("active-events")
  @ApiOperation({ summary: "Get active events (public)" })
  @ApiResponse({ status: 200, description: "Active events list" })
  async getActiveEvents(): Promise<ResponseDto<EventSettingV2Entity[]>> {
    const data = await this.futureEventV2Service.getActiveEventSettings();
    return { data };
  }
}

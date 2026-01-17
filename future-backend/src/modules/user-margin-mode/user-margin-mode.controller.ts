import { Body, Controller, Get, Post, Query, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { JwtAuthGuard } from "../auth/guards/jwt-auth.guard";
import { UpdateMarginModeDto } from "./dto/update-user-margin-mode.dto";
import { UserMarginModeService } from "./user-margin-mode.service";

@Controller("marginMode")
@ApiTags("marginMode")
@ApiBearerAuth()
export class UserMarginModeController {
  constructor(private readonly userMarginModeService: UserMarginModeService) {}

  @Post()
  @UseGuards(JwtAuthGuard)
  async updateMarginMode(
    @Body() input: UpdateMarginModeDto,
    @UserID() userId: number
  ) {
    return {
      data: await this.userMarginModeService.updateMarginMode(userId, input),
    };
  }

  @Get()
  @UseGuards(JwtAuthGuard)
  async getMarginMode(
    @UserID() userId: number,
    @Query("instrumentId") instrumentId: number
  ) {
    return {
      data: await this.userMarginModeService.getMarginMode(
        userId,
        instrumentId
      ),
    };
  }
}

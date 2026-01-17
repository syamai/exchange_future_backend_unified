import { Body, Controller, Get, Post, Query, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { LeverageMarginEntity } from "src/models/entities/leverage-margin.entity";
import { LeverageMarginService } from "./leverage-margin.service";
import { AdminAndSuperAdmin } from "src/shares/decorators/roles.decorator";
import { LeverageMarginDto } from "./dto/leverage-margin.dto";

@Controller("leverage-margin")
@ApiTags("Leverage-margin")
@ApiBearerAuth()
export class LeverageMarginController {
  constructor(private readonly leverageMarginService: LeverageMarginService) {}

  @Get()
  async getAllLeverageMargin(
    @Query("symbol") symbol: string
  ): Promise<LeverageMarginEntity[]> {
    const response = await this.leverageMarginService.findBy({
      symbol: symbol,
    });
    return response;
  }

  @Post()
  @UseGuards(AdminAndSuperAdmin)
  async upsertLeverageMargin(
    @Body() leverageMarginDto: LeverageMarginDto
  ): Promise<LeverageMarginEntity> {
    const response = await this.leverageMarginService.upsertLeverageMargin(
      leverageMarginDto
    );
    return response;
  }
}

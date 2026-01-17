import { Body, Controller, Delete, Get, Param, Post, Put, Query, UseGuards } from "@nestjs/common";
import { AdminPositionDto } from "./dto/admin-position.dto";
import { ApiBearerAuth, ApiQuery, ApiTags } from "@nestjs/swagger";
import { OrderEntity } from "src/models/entities/order.entity";
import { PositionEntity } from "src/models/entities/position.entity";
import { AccountService } from "src/modules/account/account.service";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { PositionService } from "src/modules/position/position.service";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { ClosePositionDto } from "./dto/close-position.dto";
import { RemoveTpSlDto } from "./dto/RemoveTpSlDto";
import { UpdateMarginDto } from "./dto/update-margin.dto";
import { UpdatePositionDto } from "./dto/update-position.dto";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { ContractType } from "src/shares/enums/order.enum";
import { CloseAllPositionDto } from "./dto/close-all-position.dto";
import { GetInforPositionDto } from "./dto/get-info-position.dto";
import { IsTestingRequest } from "src/shares/decorators/is-testing-request.decorator";

@Controller("positions")
@ApiTags("Position")
@ApiBearerAuth()
export class PositionController {
  constructor(private readonly positionService: PositionService, private readonly accountService: AccountService) {}

  @Get("/")
  @UseGuards(JwtAuthGuard)
  @ApiQuery({
    name: "symbol",
    type: String,
    required: false,
  })
  @ApiQuery({
    name: "contractType",
    type: String,
    required: true,
  })
  async getAllPosition(
    @UserID() userId: number,
    @Query() paging: PaginationDto,
    @Query("contractType") contractType: ContractType,
    @Query("symbol") symbol?: string
  ): Promise<ResponseDto<PositionEntity[]>> {
    const positions = await this.positionService.getAllPositionByUserIdV2(userId, paging, contractType, symbol);
    return positions;
  }

  @Get("/all")
  @UseGuards(JwtAuthGuard)
  @ApiQuery({
    name: "symbol",
    type: String,
    required: false,
  })
  @ApiQuery({
    name: "contractType",
    type: String,
    required: true,
  })
  async getAllPositionWithQty(
    @UserID() userId: number,
    // @Query() paging: PaginationDto,
    @Query("contractType") contractType: ContractType,
    @Query("symbol") symbol?: string
  ): Promise<ResponseDto<PositionEntity[]>> {
    const positions = await this.positionService.getAllPositionWithQuantity(userId, contractType, symbol);
    return positions;
  }

  @Get("/admin")
  @UseGuards(JwtAdminGuard)
  async getAllPositionAdmin(@Query() paging: PaginationDto, @Query() queries: AdminPositionDto): Promise<ResponseDto<PositionEntity[]>> {
    const positions = await this.positionService.getAllPositionByAdmin(paging, queries);
    return positions;
  }

  @Get("/admin/excel")
  @UseGuards(JwtAdminGuard)
  async exportPositionAdminExcel(@Query() paging: PaginationDto, @Query() queries: AdminPositionDto) {
    const positions = await this.positionService.exportPositionAdminExcel(paging, queries);
    return positions;
  }

  @Get("/position-history")
  @UseGuards(JwtAdminGuard)
  async getAllPositionHistoryAdmin(
    @Query() paging: PaginationDto,
    @Query() queries: AdminPositionDto
  ): Promise<ResponseDto<PositionEntity[]>> {
    const positions = await this.positionService.getAllPositionHistoryByAdmin(paging, queries);
    return positions;
  }

  @Get("/position-history/excel")
  @UseGuards(JwtAdminGuard)
  async exportExcelPositionHistoryAdmin(@Query() paging: PaginationDto, @Query() queries: AdminPositionDto) {
    return await this.positionService.exportPositionHistoryAdminExcelFile(paging, queries);
  }

  @Get("/get-average-index-price")
  async getAverageIndexPrice(@Query("symbol") symbol: string) {
    const data = await this.positionService.calculateIndexPriceAverage(symbol);
    return {
      data: data,
    };
  }

  @Get("/infor/value")
  @UseGuards(JwtAuthGuard)
  async getInforPosition(@UserID() userId: number, @Query() query: GetInforPositionDto) {
    const data = await this.positionService.getInforPositions(userId, query.symbol);
    return {
      data: data,
    };
  }

  @Get("/:symbol")
  @UseGuards(JwtAuthGuard)
  async getPositionByAccountIdBySymbol(@Param("symbol") symbol: string, @UserID() userId: number): Promise<ResponseDto<PositionEntity>> {
    const position = await this.positionService.getPositionByUserIdBySymbol(userId, symbol);
    return {
      data: position,
    };
  }

  @Put("/adjust-margin")
  @UseGuards(JwtAuthGuard)
  async updateMargin(@UserID() userId: number, @Body() updateMarginDto: UpdateMarginDto) {
    const data = await this.positionService.updateMargin(userId, updateMarginDto);
    return {
      data: data,
    };
  }

  @Post("/close")
  @UseGuards(JwtAuthGuard)
  async closePosition(@UserID() userId: number, @Body() body: ClosePositionDto, @IsTestingRequest() isTesting?: boolean): Promise<ResponseDto<OrderEntity>> {
    return {
      data: await this.positionService.closePosition(userId, body, isTesting),
    };
  }

  @Post("/close-all-positions")
  @UseGuards(JwtAuthGuard)
  async closeAllPosition(@UserID() userId: number, @Body() body: CloseAllPositionDto): Promise<ResponseDto<boolean>> {
    return {
      data: await this.positionService.closeAllPosition(userId, body.contractType),
    };
  }

  @Put("/update-position")
  @UseGuards(JwtAuthGuard)
  async updatePosition(@UserID() userId: number, @Body() updatePositionDto: UpdatePositionDto) {
    return {
      data: await this.positionService.updatePosition(userId, updatePositionDto),
    };
  }

  @Delete("/update-position")
  @UseGuards(JwtAuthGuard)
  async removeTpSlPosition(@UserID() userId: number, @Query() removeTpSlDto: RemoveTpSlDto) {
    return {
      data: await this.positionService.removeTpSlPosition(userId, removeTpSlDto),
    };
  }

  @Get("/update-position/:positionId")
  @UseGuards(JwtAuthGuard)
  async getTpSlOrderPosition(@UserID() userId: number, @Param("positionId") positionId: number) {
    return {
      data: await this.positionService.getTpSlOrderPosition(userId, positionId),
    };
  }
}

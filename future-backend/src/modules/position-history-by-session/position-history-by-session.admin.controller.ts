import {
  Body,
  Controller,
  Get,
  Param,
  Put,
  Query,
  Res,
  UseGuards,
} from "@nestjs/common";
import { GetPagListPositionHistoryBySessionAdminDto } from "./dto/admin/get-pag-list.admin.dto";
import { GetPagListPositionHistoryBySessionAdminUseCase } from "./usecase/admin/get-pag-list-position-history-by-session.admin.usecase";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { GetPagListPositionHistoryBySessionAdminResponse } from "./response/admin/get-pag-list.admin.response";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { GetPagListOrdersPositionHistoryBySessionIdAdminResponse } from "./response/admin/get-pag-list-orders-by-position-history-by-session-id.admin.response";
import { GetPagListOrdersPositionHistoryBySessionIdAdminUseCase } from "./usecase/admin/get-pag-list-orders-by-position-history-by-session-id.admin.usecase";
import { MAX_RESULT_COUNT } from "../trade/trade.const";
import { GetPagListOrdersByPositionHistoryBySessionIdDto } from "./dto/admin/get-pag-list-orders-by-phbs-id.admin.dto";
import { UpdateCheckingStatusPhbsDto } from "./dto/admin/update-checking-status-phbs.admin.dto";
import { UpdateCheckingStatusPhbsUseCase } from "./usecase/admin/update-checking-status-phbs.admin.usecase";

@Controller("/position-history-by-session/admin")
@ApiTags("Admin - Position History By Session")
@ApiBearerAuth()
export class PositionHistoryBySessionController {
  constructor(
    private readonly getPagListAdminUseCase: GetPagListPositionHistoryBySessionAdminUseCase,
    private readonly getPagListOrdersPositionHistoryBySessionIdAdminUseCase: GetPagListOrdersPositionHistoryBySessionIdAdminUseCase,
    private readonly updateCheckingStatusPhbsUseCase: UpdateCheckingStatusPhbsUseCase
  ) {}

  @Get("/")
  @UseGuards(JwtAdminGuard)
  async getPagListAdmin(
    @Query() query: GetPagListPositionHistoryBySessionAdminDto,
    @Query() paging: PaginationDto
  ): Promise<ResponseDto<GetPagListPositionHistoryBySessionAdminResponse[]>> {
    return this.getPagListAdminUseCase.execute(query, paging);
  }

  @Get("/export")
  @UseGuards(JwtAdminGuard)
  async exportListAdmin(
    @Query() query: GetPagListPositionHistoryBySessionAdminDto
  ) {
    return await this.getPagListAdminUseCase.exportExcel(query, {
      page: 1,
      size: MAX_RESULT_COUNT,
    });
  }

  @Get("/:positionHistoryBySessionId/orders")
  @UseGuards(JwtAdminGuard)
  async getPagListOrdersByPositionHistoryBySessionId(
    @Query() paging: PaginationDto,
    @Param("positionHistoryBySessionId") positionHistoryBySessionId: number,
    @Query() query: GetPagListOrdersByPositionHistoryBySessionIdDto
  ): Promise<
    ResponseDto<GetPagListOrdersPositionHistoryBySessionIdAdminResponse[]>
  > {
    return this.getPagListOrdersPositionHistoryBySessionIdAdminUseCase.execute(
      paging,
      +positionHistoryBySessionId,
      query
    );
  }

  @Put("/:positionHistoryBySessionId/checking-status")
  @UseGuards(JwtAdminGuard)
  async updateCheckingStatusPhbs(
    @Param("positionHistoryBySessionId") positionHistoryBySessionId: number,
    @Body() body: UpdateCheckingStatusPhbsDto
  ): Promise<ResponseDto<boolean>> {
    return {
      data: await this.updateCheckingStatusPhbsUseCase.execute(
        +positionHistoryBySessionId,
        body
      ),
    };
  }
}

import { Body, Controller, Get, Headers, Post, Query, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { GetTradingMetricsAdminDto } from "./dto/get-trading-metrics.admin.dto";
import { GetTradingMetricsAdminResponse } from "./repsonse/get-trading-metrics.admin.response";
import { GetTradingMetricsAdminUseCase } from "./usecase/get-trading-metrics.admin.usecase";
import { GetListTradingMetricsByPairAdminDto } from "./dto/get-list-trading-metrics-by-pair.admin.dto";
import { GetListTradingMetricsByPairAdminResponse } from "./repsonse/get-list-trading-metrics-by-pair.admin.response";
import { GetListTradingMetricsByPairAdminUseCase } from "./usecase/get-list-trading-metrics-by-pair.admin.usecase";
import { ExportService, ExportDefs } from "@felix042024/nestjs-export";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { GetPagListTradingDataByUserAdminDto } from "./dto/get-pag-list-trading-data-by-user.admin.dto";
import { GetPagListTradingDataByUserAdminResponse } from "./repsonse/get-pag-list-trading-data-by-user.admin.response";
import { GetRevenueMetricsAdminDto } from "./dto/get-revenue-metrics.admin.dto";
import { GetRevenueMetricsAdminResponse } from "./repsonse/get-revenue-metrics.admin.response";
import { GetRevenueMetricsAdminUseCase } from "./usecase/get-revenue-metrics.admin.usecase";
import { GetRevenueMetricsByUserForAdminDto } from "./dto/get-revenue-metrics-by-user.admin.dto";
import { GetRevenueMetricsByUserForAdminResponse } from "./repsonse/get-revenue-metrics-by-user.admin.response";
import { GetRevenueMetricsByUserForAdminUseCase } from "./usecase/get-revenue-metrics-by-user.admin.usecase";
import { JwtBasicTokenGuard } from "../auth/guards/jwt-basic-token.guard";

@Controller("trading-statistics")
@ApiTags("Trading Statistics")
@ApiBearerAuth()
export class TradingStatisticsController {
  constructor(
    private readonly getTradingMetricsAdminUseCase: GetTradingMetricsAdminUseCase,
    private readonly getListTradingMetricsByPairAdminUseCase: GetListTradingMetricsByPairAdminUseCase,
    private readonly getRevenueMetricsAdminUseCase: GetRevenueMetricsAdminUseCase,
    private readonly getRevenueMetricsByUserForAdminUseCase: GetRevenueMetricsByUserForAdminUseCase
  ) {}

  @Get("/trading-metrics")
  @UseGuards(JwtAdminGuard)
  async getTradingMetricsOnDashboard(
    @Query() query: GetTradingMetricsAdminDto,
    @Headers("authorization") authHeader: string
  ): Promise<ResponseDto<GetTradingMetricsAdminResponse>> {
    return {
      data: await this.getTradingMetricsAdminUseCase.execute(query, authHeader),
    };
  }

  @Get("/trading-metrics/by-pair")
  @UseGuards(JwtAdminGuard)
  async getTradingMetricsByPair(
    @Query() query: GetListTradingMetricsByPairAdminDto
  ): Promise<ResponseDto<GetListTradingMetricsByPairAdminResponse[]>> {
    return {
      data: await this.getListTradingMetricsByPairAdminUseCase.execute(query),
    };
  }

  @Get("/trading-metrics/export")
  @UseGuards(JwtAdminGuard)
  async exportTradingMetricsOnDashboard(
    @Query() query: GetTradingMetricsAdminDto,
    @Headers("authorization") authHeader: string
  ) {
    return await this.getTradingMetricsAdminUseCase.exportExcel(
      query,
      authHeader
    );
  }

  @Get("/trading-metrics/by-pair/export")
  @UseGuards(JwtAdminGuard)
  async exportTradingMetricsByPair(
    @Query() query: GetListTradingMetricsByPairAdminDto
  ) {
    return await this.getListTradingMetricsByPairAdminUseCase.exportExcel(
      query
    );
  }

  // @Get("/trading-data/by-user")
  // async getPagListTradingDataByUser(
  //   @Query() query: GetPagListTradingDataByUserAdminDto,
  //   @Query() paging: PaginationDto
  // ): Promise<ResponseDto<GetPagListTradingDataByUserAdminResponse[]>> {
  //   return {
  //     data: await this.getPagListTradingDataByUserAdminUseCase.execute(query, paging),
  //   };
  // }

  @Post("/revenue-metrics")
  @UseGuards(JwtBasicTokenGuard)
  async getRevenueMetrics(
    @Body() query: GetRevenueMetricsAdminDto,
  ): Promise<ResponseDto<GetRevenueMetricsAdminResponse>> {
    return {
      data: await this.getRevenueMetricsAdminUseCase.execute(query),
    };
  }

  @Post("/revenue-metrics/by-user")
  @UseGuards(JwtBasicTokenGuard)
  async getRevenueMetricsByUser(
    @Body() query: GetRevenueMetricsByUserForAdminDto,
  ): Promise<ResponseDto<GetRevenueMetricsByUserForAdminResponse[]>> {
    return {
      data: await this.getRevenueMetricsByUserForAdminUseCase.execute(query),
    };
  }
}

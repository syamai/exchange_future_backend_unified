import { Controller, Get, Query, Res, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { UserStatisticService } from "./user-statistic.service";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ExportService, ExportDefs } from "@felix042024/nestjs-export";
import { Response } from "express";
import * as moment from "moment";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";

@Controller("user-statistics")
@ApiTags("userStatistics")
@ApiBearerAuth()
@UseGuards(JwtAdminGuard)
export class UserStatisticsController {
  constructor(
    private readonly userStatisticsService: UserStatisticService,
    private readonly exportDefs: ExportDefs,
    private readonly exportService: ExportService
  ) {}

  @Get("/top-gain-list")
  async getTopGainList() {
    return {
      data: await this.userStatisticsService.getTopGainList(),
    };
  }

  @Get("/top-loser-list")
  async getTopLoserList() {
    return {
      data: await this.userStatisticsService.getTopLoserList(),
    };
  }

  @Get("/top-deposit-list")
  async getTopDepositList(@Query() coin: string) {
    return {
      data: await this.userStatisticsService.getTopDepositList(coin),
    };
  }

  @Get("/top-withdraw-list")
  async getTopWithdrawList(@Query() coin: string) {
    return {
      data: await this.userStatisticsService.getTopWithdrawList(coin),
    };
  }

  @Get("/no-deposit-user")
  async getNoDepositUser(@Query() paging: PaginationDto) {
    const response = await this.userStatisticsService.getNoDepositUsers(paging);
    return response;
  }

  @Get("/player-real-balance-report")
  async getPlayerRealBalanceReport(
    @Query() paging: PaginationDto,
    @Query("orderBy") orderBy?: string,
    @Query("direction") direction?: "ASC" | "DESC",
    @Query("q") q?: string
  ) {
    const response = await this.userStatisticsService.getPlayerRealBalanceReport(paging, orderBy, direction, q);
    return response;
  }

  @Get("/export-player-real-balance-report")
  async exportPlayerRealBalanceReport(
    @Res() res: Response,
    @Query("type") type: "csv" | "excel",
    @Query("orderBy") orderBy?: string,
    @Query("direction") direction?: "ASC" | "DESC",
    @Query("q") q?: string
  ) {
    const { data } = await this.userStatisticsService.getPlayerRealBalanceReport({ page: 1, size: 1000000 }, orderBy, direction, q);
    const exportData = {
      name: `player_real_balance_report_${moment().format("YYYY-MM-DD_HH_mm_ss")}`,
      data,
    };
    const sheet = this.exportDefs.createSheet(exportData);
    return type === 'csv' ? this.exportService.generateCSV(sheet, res) : this.exportService.generateExcel(sheet, res);
  }
}

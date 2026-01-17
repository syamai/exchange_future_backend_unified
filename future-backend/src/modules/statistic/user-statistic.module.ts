import { forwardRef, Module } from "@nestjs/common";
import { UserStatisticConsole } from "./user-statistic.console";
import { UserStatisticService } from "./user-statistic.service";
import { BotModule } from "../bot/bot.module";
import { UserStatisticsController } from "./user-statistics.controller";
import { ExportModule } from "@felix042024/nestjs-export";
import { UpdateUserPeakAssetUsecase } from "./usecase/update-user-peak-asset.usecase";
import { GetTradingMetricsAdminUseCase } from "./usecase/get-trading-metrics.admin.usecase";
import { PositionHistoryBySessionModule } from "../position-history-by-session/position-history-by-session.module";
import { TransactionModule } from "../transaction/transaction.module";
import { TradingStatisticsController } from "./trading-statistic.controller";
import { GetListTradingMetricsByPairAdminUseCase } from "./usecase/get-list-trading-metrics-by-pair.admin.usecase";
import { FutureEventModule } from "../future-event/future-event.module";
import { ExcelService } from "../export-excel/services/excel.service";
import { GetRevenueMetricsAdminUseCase } from "./usecase/get-revenue-metrics.admin.usecase";
import { GetRevenueMetricsByUserForAdminUseCase } from "./usecase/get-revenue-metrics-by-user.admin.usecase";

@Module({
  imports: [
    forwardRef(() => BotModule),
    forwardRef(() => ExportModule),
    forwardRef(() => PositionHistoryBySessionModule),
    forwardRef(() => TransactionModule),
    forwardRef(() => FutureEventModule),
    forwardRef(() => ExportModule),
  ],
  providers: [
    UserStatisticConsole,
    UserStatisticService,
    UpdateUserPeakAssetUsecase,
    GetTradingMetricsAdminUseCase,
    GetListTradingMetricsByPairAdminUseCase,
    ExcelService,
    GetRevenueMetricsAdminUseCase,
    GetRevenueMetricsByUserForAdminUseCase
  ],
  controllers: [UserStatisticsController, TradingStatisticsController],
  exports: [UserStatisticService],
})
export class UserStatisticModule {}

import { forwardRef, Module } from "@nestjs/common";
import { PositionHistoryBySessionController } from "./position-history-by-session.admin.controller";
import { GetPagListPositionHistoryBySessionAdminUseCase } from "./usecase/admin/get-pag-list-position-history-by-session.admin.usecase";
import { GetPagListOrdersPositionHistoryBySessionIdAdminUseCase } from "./usecase/admin/get-pag-list-orders-by-position-history-by-session-id.admin.usecase";
import { UpdateCheckingStatusPhbsUseCase } from "./usecase/admin/update-checking-status-phbs.admin.usecase";
import { ExcelService } from "../export-excel/services/excel.service";

@Module({
  imports: [],
  controllers: [PositionHistoryBySessionController],
  providers: [
    GetPagListPositionHistoryBySessionAdminUseCase,
    GetPagListOrdersPositionHistoryBySessionIdAdminUseCase,
    UpdateCheckingStatusPhbsUseCase,
    ExcelService,
  ],
})
export class PositionHistoryBySessionModule {}

import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { PositionHistoryBySessionRepository } from "src/models/repositories/position-history-by-session.repository";
import { GetPagListPositionHistoryBySessionAdminDto } from "../../dto/admin/get-pag-list.admin.dto";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { getQueryLimit } from "src/shares/pagination-util";
import { MAX_RESULT_COUNT } from "src/modules/trade/trade.const";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { GetPagListPositionHistoryBySessionAdminResponse } from "../../response/admin/get-pag-list.admin.response";
import { ExcelService } from "src/modules/export-excel/services/excel.service";
import * as moment from "moment";
import BigNumber from "bignumber.js";
import { PositionHistoryBySessionEntity } from "src/models/entities/position_history_by_session.entity";
import { OrderWithPositionHistoryBySessionRepository } from "src/models/repositories/order-with-position-history-by-session.repository";
import { FundingHistoryRepository } from "src/models/repositories/funding-history.repository";

@Injectable()
export class GetPagListPositionHistoryBySessionAdminUseCase {
  constructor(
    @InjectRepository(PositionHistoryBySessionRepository, "report")
    private readonly positionHistoryRepoReport: PositionHistoryBySessionRepository,
    @InjectRepository(PositionHistoryBySessionRepository, "master")
    private readonly positionHistoryRepoMaster: PositionHistoryBySessionRepository,
    @InjectRepository(OrderWithPositionHistoryBySessionRepository, "report")
    private readonly orderWithPositionHistoryRepoReport: OrderWithPositionHistoryBySessionRepository,
    @InjectRepository(FundingHistoryRepository, "report")
    private readonly fundingHistoryRepoReport: FundingHistoryRepository,
    private readonly excelService: ExcelService
  ) {}

  public async execute(
    query: GetPagListPositionHistoryBySessionAdminDto,
    paging: PaginationDto
  ): Promise<ResponseDto<GetPagListPositionHistoryBySessionAdminResponse[]>> {
    const qb = this.positionHistoryRepoReport.createQueryBuilder("ph");

    if (query.keyword) {
      qb.andWhere("(ph.userEmail LIKE :keyword OR ph.userId = :userId)", {
        keyword: `%${query.keyword}%`,
        userId: Number.isNaN(Number(query.keyword))
          ? -1
          : Number(query.keyword),
      });
    }
    if (query.symbol) {
      qb.andWhere("ph.symbol = :symbol", { symbol: query.symbol });
    }
    if (query.side) {
      qb.andWhere("ph.side = :side", { side: query.side });
    }
    if (query.status) {
      qb.andWhere("ph.status = :status", { status: query.status });
    }
    if (query.openTimeFrom) {
      qb.andWhere("ph.openTime >= :openTimeFrom", {
        openTimeFrom: query.openTimeFrom,
      });
    }
    if (query.openTimeTo) {
      qb.andWhere("ph.openTime <= :openTimeTo", {
        openTimeTo: query.openTimeTo,
      });
    }
    if (query.closeTimeFrom) {
      qb.andWhere("ph.closeTime >= :closeTimeFrom", {
        closeTimeFrom: query.closeTimeFrom,
      });
    }
    if (query.closeTimeTo) {
      qb.andWhere("ph.closeTime <= :closeTimeTo", {
        closeTimeTo: query.closeTimeTo,
      });
    }

    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    if (!query.sortBy) {
      qb.orderBy("ph.openTime", "DESC");
    } else {
      switch (query.sortBy) {
        case "realizedPnl":
          qb.orderBy("ph.pnl", query.sortDirection || "DESC");
          break;
        case "fee":
          qb.orderBy("ph.fee", query.sortDirection || "DESC");
          break;
        case "realizedPnlRate":
          qb.orderBy("ph.pnlRate", query.sortDirection || "DESC");
          break;
        default:
          qb.orderBy("ph.openTime", query.sortDirection || "DESC");
          break;
      }
    }
    qb.skip(offset).take(limit);
    const [positionHistoryBySessions, totalItems] = await qb.getManyAndCount();

    if (totalItems === 0) {
      return {
        data: [],
        metadata: {
          totalPage: 0,
          total: 0,
        },
      };
    }

    const fundingFeeByPhbsId = await this.getFundingFeeOfListPhbses(
      positionHistoryBySessions
    );

    const responses: GetPagListPositionHistoryBySessionAdminResponse[] = [];
    for (const positionHistoryBySession of positionHistoryBySessions) {
      const response = new GetPagListPositionHistoryBySessionAdminResponse();
      response.id = positionHistoryBySession.id;
      response.traderEmail = positionHistoryBySession.userEmail;
      response.traderId = positionHistoryBySession.userId;
      response.positionId = positionHistoryBySession.positionId;
      response.openTime = positionHistoryBySession.openTime?.toISOString();
      response.closeTime = positionHistoryBySession.closeTime?.toISOString();
      response.symbol = positionHistoryBySession.symbol;
      response.marginMode = positionHistoryBySession.marginMode;

      response.leverage = positionHistoryBySession.leverages;

      response.side = positionHistoryBySession.side;
      response.avgEntryPrice = (
        +positionHistoryBySession.sumEntryPrice /
        positionHistoryBySession.numOfOpenOrders
      ).toString();
      response.avgClosePrice = (
        +positionHistoryBySession.sumClosePrice /
        positionHistoryBySession.numOfCloseOrders
      ).toString();
      response.margin = positionHistoryBySession.maxMargin;
      response.size = positionHistoryBySession.maxSize;
      response.value = positionHistoryBySession.maxValue;
      response.realizedPnl = positionHistoryBySession.hasUpdatedFundingFee
        ? positionHistoryBySession.pnlAfterFundingFee
        : positionHistoryBySession.pnl;
      response.fee = (-positionHistoryBySession.fee).toString();
      response.realizedPnlPercent = positionHistoryBySession.pnlRate;
      response.status = positionHistoryBySession.status;
      response.checkingStatus = positionHistoryBySession.checkingStatus;

      response.realizedPnlDetail = {
        closingProfits: positionHistoryBySession.profit,
        fundingFee: fundingFeeByPhbsId
          ? fundingFeeByPhbsId[positionHistoryBySession.id]?.toFixed() ?? "0"
          : "0",
        openingFee: positionHistoryBySession.openingFee,
        closingFee: positionHistoryBySession.closingFee,
      };

      response.feeDetail = {
        openingFee: positionHistoryBySession.openingFee,
        closingFee: positionHistoryBySession.closingFee,
      };

      responses.push(response);
    }

    return {
      data: responses,
      metadata: {
        totalPage: Math.ceil(totalItems / paging.size),
        total: totalItems,
      },
    };
  }

  private async getFundingFeeOfListPhbses(
    phbses: PositionHistoryBySessionEntity[]
  ): Promise<{ [phbsId: number]: BigNumber }> {
    if (!phbses || !phbses.length) return null;
    const phbsIds = phbses.map((p) => p.id);
    const ophbsesRawResult = await this.orderWithPositionHistoryRepoReport
      .createQueryBuilder("ophbs")
      .leftJoin("orders", "order", "order.id = ophbs.orderId")
      .leftJoin(
        "position_history_by_session",
        "phbs",
        "phbs.id = ophbs.positionHistoryBySessionId"
      )
      .where(`ophbs.positionHistoryBySessionId IN (:...phbsIds)`, { phbsIds })
      .select([
        `ophbs.positionHistoryBySessionId as phbsId`,
        `order.accountId as accountId`,
        `phbs.positionId as positionId`,
        `phbs.status as phbsStatus`,
        `phbs.pnl as phbsPnl`,
        `phbs.fundingFee as phbsFundingFee`,
        `phbs.hasUpdatedFundingFee as phbsHasUpdatedFundingFee`,
        `MAX(order.operationId) as maxOpId`,
        `MIN(order.operationId) as minOpId`,
      ])
      .groupBy(`ophbs.positionHistoryBySessionId`)
      .getRawMany();
    if (!ophbsesRawResult || !ophbsesRawResult.length) return null;

    // Sum fundingHistories
    const fundingFeeByPhbsId: { [phbsId: number]: BigNumber } = {};
    await Promise.all(
      ophbsesRawResult.map(async (ophbs) => {
        const fundingFee = new BigNumber(ophbs.phbsFundingFee ?? 0);
        if (
          !fundingFee.isEqualTo(0) ||
          ophbs.phbsHasUpdatedFundingFee === 1 // true
        ) {
          fundingFeeByPhbsId[Number(ophbs.phbsId)] = new BigNumber(fundingFee);
          return;
        }

        const fundingHistorySumRawResult = await this.fundingHistoryRepoReport
          .createQueryBuilder("fh")
          .where(`fh.accountId = :accountId`, { accountId: ophbs.accountId })
          .andWhere(`fh.positionId = :positionId`, {
            positionId: ophbs.positionId,
          })
          .andWhere(
            `fh.operationId >= :startOpId and fh.operationId <= :endOpId`,
            {
              startOpId: ophbs.minOpId,
              endOpId:
                ophbs.phbsStatus === "CLOSED"
                  ? ophbs.maxOpId
                  : "90000000000000000",
            }
          )
          .select([`SUM(fh.amount) as fundingFee`])
          .groupBy(`fh.accountId`)
          .getRawOne();

        fundingFeeByPhbsId[Number(ophbs.phbsId)] = new BigNumber(
          fundingHistorySumRawResult?.fundingFee ?? 0
        );

        // Udpate fundingFee of positionHistoryBySession
        if (
          String(ophbs.phbsStatus) === "CLOSED" &&
          ophbs.phbsHasUpdatedFundingFee === 0 // false
        ) {
          const pnlAfterFundingFee = new BigNumber(ophbs.phbsPnl ?? 0).plus(
            fundingFeeByPhbsId[Number(ophbs.phbsId)]
          );
          const updateValues = {
            fundingFee: fundingFeeByPhbsId[Number(ophbs.phbsId)].toFixed(),
            hasUpdatedFundingFee: true,
            pnlAfterFundingFee: pnlAfterFundingFee.toFixed(),
          };
          await this.positionHistoryRepoMaster.update(
            Number(ophbs.phbsId),
            updateValues
          );

          const phbseIdx = phbses.findIndex(
            (p) => Number(p.id) === Number(ophbs.phbsId)
          );
          if (phbseIdx >= 0) {
            phbses[phbseIdx] = {
              ...phbses[phbseIdx],
              ...updateValues,
            };
          }
        }
      })
    );

    return fundingFeeByPhbsId;
  }

  public async exportExcel(
    query: GetPagListPositionHistoryBySessionAdminDto,
    paging: PaginationDto
  ) {
    const { data } = await this.execute(query, paging);
    const COLUMN_NAMES = [
      "Trader",
      "Position ID",
      "Open Time",
      "Close Time",
      "Symbol/Leverage",
      "Side",
      "Avg. Entry Price",
      "Avg. Close Price",
      "Margin",
      "Size/Value",
      "Realized PNL",
      "Fee",
      "Realized PNL%",
      "Checking status",
      "Status",
    ];
    const columnDataKeys = [
      "trader",
      "positionId",
      "openTime",
      "closeTime",
      "symbolLeverage",
      "side",
      "avgEntryPrice",
      "avgClosePrice",
      "margin",
      "sizeValue",
      "realizedPnl",
      "fee",
      "realizedPnlPercent",
      "checkingStatus",
      "status",
    ];

    const responses = data.map((r) => {
      return {
        trader: r.traderEmail,
        positionId: r.positionId,
        openTime: moment
          .utc(r.openTime)
          .utcOffset(7)
          .format("DD/MM/YYYY HH:mm:ss"),
        closeTime: moment
          .utc(r.closeTime)
          .utcOffset(7)
          .format("DD/MM/YYYY HH:mm:ss"),
        symbolLeverage: `${r.symbol} - ${r.marginMode} ${r.leverage}x`,
        side: r.side,
        avgEntryPrice: new BigNumber(r.avgEntryPrice).toFixed(2),
        avgClosePrice: new BigNumber(r.avgClosePrice).toFixed(2),
        margin: new BigNumber(r.margin).toFixed(2),
        sizeValue: `${new BigNumber(r.size).toFixed(2)}/${new BigNumber(
          r.value
        ).toFixed(2)}`,
        realizedPnl: new BigNumber(r.realizedPnl).toFixed(2),
        fee: new BigNumber(r.fee).toFixed(2),
        realizedPnlPercent: new BigNumber(r.realizedPnlPercent).toFixed(2),
        checkingStatus: r.checkingStatus,
        status: r.status,
      };
    });

    // Generate the Excel buffer
    const buffer = await this.excelService.generateExcelBuffer(
      COLUMN_NAMES,
      columnDataKeys,
      responses
    );

    const fileName = "position-history";
    // Set response headers for file download
    const exportTime = moment().format("YYYY-MM-DD_HH-mm-ss");
    return {
      fileName: `${fileName}-${exportTime}.xlsx`,
      base64Data: Buffer.from(buffer).toString("base64"),
    };
  }
}

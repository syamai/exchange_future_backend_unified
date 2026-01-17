import { Injectable } from "@nestjs/common";
import { GetListTradingMetricsByPairAdminDto } from "../dto/get-list-trading-metrics-by-pair.admin.dto";
import { GetListTradingMetricsByPairAdminResponse } from "../repsonse/get-list-trading-metrics-by-pair.admin.response";
import { InjectRepository } from "@nestjs/typeorm";
import { PositionHistoryBySessionRepository } from "src/models/repositories/position-history-by-session.repository";
import BigNumber from "bignumber.js";
import { OrderWithPositionHistoryBySessionRepository } from "src/models/repositories/order-with-position-history-by-session.repository";
import { ExcelService } from "src/modules/export-excel/services/excel.service";
import * as moment from "moment";

@Injectable()
export class GetListTradingMetricsByPairAdminUseCase {
  constructor(
    @InjectRepository(PositionHistoryBySessionRepository, "report")
    private readonly positionHistoryBySessionRepoReport: PositionHistoryBySessionRepository,
    @InjectRepository(OrderWithPositionHistoryBySessionRepository, "report")
    private readonly orderWithPositionHistoryBySessionRepoReport: OrderWithPositionHistoryBySessionRepository,
    private readonly excelService: ExcelService
  ) {}

  public async execute(
    query: GetListTradingMetricsByPairAdminDto
  ): Promise<GetListTradingMetricsByPairAdminResponse[]> {
    const phbsQueryBuilder = this.positionHistoryBySessionRepoReport
      .createQueryBuilder("ph")
      .where("ph.status = :status", { status: "CLOSED" })
      .andWhere("ph.pnl != 0")
      .groupBy("ph.symbol");

    if (query.startDate) {
      phbsQueryBuilder.andWhere("ph.openTime >= :startDate", {
        startDate: query.startDate,
      });
    }
    if (query.endDate) {
      phbsQueryBuilder.andWhere("ph.closeTime <= :endDate", {
        endDate: query.endDate,
      });
    }
    if (query.pair) {
      phbsQueryBuilder.andWhere("ph.symbol = :symbol", {
        symbol: query.pair,
      });
    }

    // Get phbs ids
    const phbsIds = (await phbsQueryBuilder.select(["ph.id"]).getMany()).map(
      (ph) => ph.id
    );

    // Sum metrics
    phbsQueryBuilder.select([
      "ph.symbol as symbol",
      "COUNT(ph.id) as totalPositions",
      "SUM(CASE WHEN ph.pnl > 0 THEN 1 ELSE 0 END) as totalWins",
      "SUM(CASE WHEN ph.pnl < 0 THEN 1 ELSE 0 END) as totalLosses",

      "SUM(CASE WHEN ph.profit > 0 THEN ph.profit ELSE 0 END) as totalProfit",
      "SUM(CASE WHEN ph.profit < 0 THEN ph.profit ELSE 0 END) as totalLoss",

      "SUM(CASE WHEN ph.pnl > 0 THEN ph.pnl ELSE 0 END) as totalPnlWin",
      "SUM(CASE WHEN ph.pnl < 0 THEN ph.pnl ELSE 0 END) as totalPnlLoss",

      // "SUM(ABS(ph.maxValue)) as totalSize",

      "SUM(ph.fee) as totalTradingFee",
      // "SUM(ph.fundingFee) as totalFundingFee",
    ]);

    const phbsRaws = await phbsQueryBuilder.getRawMany();

    // Get Trading Volume
    const tradingVolumesBySymbol =
      phbsIds && phbsIds.length > 0
        ? await this.orderWithPositionHistoryBySessionRepoReport
            .createQueryBuilder("ophbs")
            .where(`ophbs.positionHistoryBySessionId IN (:...phbsIds)`, {
              phbsIds,
            })
            .innerJoin("orders", "order", "ophbs.orderId = order.id")
            .select([
              "order.symbol as symbol",
              "SUM(ophbs.tradePriceAfter*(order.quantity - order.remaining)) as tradingVolume",
            ])
            .groupBy("order.symbol")
            .getRawMany()
        : [];

    let responses: GetListTradingMetricsByPairAdminResponse[] = [];
    for (const phbsRaw of phbsRaws) {
      const response = new GetListTradingMetricsByPairAdminResponse();
      response.pair = phbsRaw.symbol;

      // Position
      const totalPositions = new BigNumber(phbsRaw.totalPositions);
      response.position = {
        total: totalPositions.toNumber(),
        win: new BigNumber(phbsRaw.totalWins).toNumber(),
        loss: new BigNumber(phbsRaw.totalLosses).toNumber(),
      };

      // Trading volume
      const tradingVolumeBySymbol = tradingVolumesBySymbol.find(
        (t) => String(t.symbol) === String(phbsRaw.symbol)
      );
      response.tradingVolume = {
        total: `${Number(
          new BigNumber(tradingVolumeBySymbol?.tradingVolume ?? 0).toFixed(0)
        ).toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}`,
      };

      // Fee
      const totalTradingFee = new BigNumber(phbsRaw.totalTradingFee);
      const avgFeeByPosition = totalTradingFee.dividedBy(totalPositions);
      response.fee = {
        total: `$${Number(totalTradingFee.toFixed(2)).toLocaleString(
          undefined,
          { minimumFractionDigits: 2, maximumFractionDigits: 2 }
        )}`,
        avg: `$${Number(avgFeeByPosition.toFixed(2)).toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}`,
      };

      // PNL Win
      const totalPnlWin = new BigNumber(phbsRaw.totalPnlWin);
      const avgPnlWinByPosition = totalPnlWin.dividedBy(totalPositions);
      response.profit = {
        total: `$${Number(totalPnlWin.toFixed(2)).toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}`,
        avg: `$${Number(avgPnlWinByPosition.toFixed(2)).toLocaleString(
          undefined,
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`,
      };

      // PNL Loss
      const totalPnlLoss = new BigNumber(phbsRaw.totalPnlLoss);
      const avgPnlLossByPosition = totalPnlLoss.dividedBy(totalPositions);
      response.loss = {
        total: `$${Number(totalPnlLoss.abs().toFixed(2)).toLocaleString(
          undefined,
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`,
        avg: `$${Number(avgPnlLossByPosition.abs().toFixed(2)).toLocaleString(
          undefined,
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`,
      };

      // Total PNL
      const totalProfit = new BigNumber(phbsRaw.totalProfit);
      const totalLoss = new BigNumber(phbsRaw.totalLoss);
      const totalCommission = new BigNumber(0); // TODO
      const totalPnl = totalProfit
        .plus(totalLoss)
        .plus(totalTradingFee)
        .minus(totalCommission);
      response.totalPnl = `$${Number(totalPnl.toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`;

      responses.push(response);
    }

    // Sort the result
    if (query.sort) {
      responses = this.sortResponses(responses, query);
    }

    return responses;
  }

  private sortResponses(
    responses: GetListTradingMetricsByPairAdminResponse[],
    query: GetListTradingMetricsByPairAdminDto
  ): GetListTradingMetricsByPairAdminResponse[] {
    if (!query.sort_type) query.sort_type = "asc";

    switch (query.sort) {
      case "position":
        responses.sort((a, b) => {
          return query.sort_type === "desc"
            ? b.position.total - a.position.total
            : a.position.total - b.position.total;
        });
        break;
      case "trading_volume":
        responses.sort((a, b) => {
          // Remove $ and commas, then parse as float
          const parseValue = (val: string) =>
            parseFloat(val.replace(/[$,]/g, ""));
          const aVal = parseValue(a.tradingVolume.total);
          const bVal = parseValue(b.tradingVolume.total);
          return query.sort_type === "desc" ? bVal - aVal : aVal - bVal;
        });
        break;
      case "fee":
        responses.sort((a, b) => {
          // Remove $ and commas, then parse as float
          const parseValue = (val: string) =>
            parseFloat(val.replace(/[$,]/g, ""));
          const aVal = parseValue(a.fee.total);
          const bVal = parseValue(b.fee.total);
          return query.sort_type === "desc" ? bVal - aVal : aVal - bVal;
        });
        break;
      case "profit":
        responses.sort((a, b) => {
          // Remove $ and commas, then parse as float
          const parseValue = (val: string) =>
            parseFloat(val.replace(/[$,]/g, ""));
          const aVal = parseValue(a.profit.total);
          const bVal = parseValue(b.profit.total);
          return query.sort_type === "desc" ? bVal - aVal : aVal - bVal;
        });
        break;
      case "loss":
        responses.sort((a, b) => {
          // Remove $ and commas, then parse as float
          const parseValue = (val: string) =>
            parseFloat(val.replace(/[$,]/g, ""));
          const aVal = parseValue(a.loss.total);
          const bVal = parseValue(b.loss.total);
          return query.sort_type === "desc" ? bVal - aVal : aVal - bVal;
        });
        break;
      case "totalPnl":
        responses.sort((a, b) => {
          // Remove $ and commas, then parse as float
          const parseValue = (val: string) =>
            parseFloat(val.replace(/[$,]/g, ""));
          const aVal = parseValue(a.totalPnl);
          const bVal = parseValue(b.totalPnl);
          return query.sort_type === "desc" ? bVal - aVal : aVal - bVal;
        });
        break;
      default:
        break;
    }

    return responses;
  }

  public async exportExcel(query: GetListTradingMetricsByPairAdminDto) {
    const responses = await this.execute(query);
    const COLUMN_NAMES = [
      "Pair",
      "Position",
      "Win Position",
      "Loss Position",
      "Trading Volume",
      "Fee",
      "Avg. Fee",
      "PNL Win",
      "Avg. PNL Win",
      "PNL Loss",
      "Avg. PNL Loss",
      "Total PNL",
    ];
    const columnDataKeys = [
      "pair",
      "position",
      "winPosition",
      "lossPosition",
      "tradindVolume",
      "fee",
      "avgFee",
      "pnlWin",
      "avgPnlWin",
      "pnlLoss",
      "avgPnlLoss",
      "totalPnl",
    ];

    const data = responses.map((r) => {
      return {
        pair: r.pair,
        position: r.position.total,
        winPosition: r.position.win,
        lossPosition: r.position.loss,
        tradindVolume: r.tradingVolume.total.replace("$", "").replace(/,/g, ""),
        fee: r.fee.total.replace("$", "").replace(/,/g, ""),
        avgFee: r.fee.avg.replace("$", "").replace(/,/g, ""),
        pnlWin: r.profit.total.replace("$", "").replace(/,/g, ""),
        avgPnlWin: r.profit.avg.replace("$", "").replace(/,/g, ""),
        pnlLoss: r.loss.total.replace("$", "").replace(/,/g, ""),
        avgPnlLoss: r.loss.avg.replace("$", "").replace(/,/g, ""),
        totalPnl: r.totalPnl.replace("$", "").replace(/,/g, ""),
      };
    });

    // Generate the Excel buffer
    const buffer = await this.excelService.generateExcelBuffer(
      COLUMN_NAMES,
      columnDataKeys,
      data
    );

    const fileName = "trading-metrics-by-pair";
    // Set response headers for file download
    const exportTime = moment().format("YYYY-MM-DD_HH-mm-ss");
    return {
      fileName: `${fileName}-${exportTime}.xlsx`,
      base64Data: Buffer.from(buffer).toString("base64"),
    };
  }
}

import { Injectable, Logger } from "@nestjs/common";
import { GetTradingMetricsAdminDto } from "../dto/get-trading-metrics.admin.dto";
import { GetTradingMetricsAdminResponse } from "../repsonse/get-trading-metrics.admin.response";
import { InjectRepository } from "@nestjs/typeorm";
import { PositionHistoryBySessionRepository } from "src/models/repositories/position-history-by-session.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import BigNumber from "bignumber.js";
import { OrderWithPositionHistoryBySessionRepository } from "src/models/repositories/order-with-position-history-by-session.repository";
import { TransactionType } from "src/shares/enums/transaction.enum";
import { AccountRepository } from "src/models/repositories/account.repository";
import { USDT } from "src/modules/balance/balance.const";
import { FutureEventService } from "src/modules/future-event/future-event.service";
import fetch from "node-fetch";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { ExcelService } from "src/modules/export-excel/services/excel.service";
import * as moment from "moment";

@Injectable()
export class GetTradingMetricsAdminUseCase {
  constructor(
    @InjectRepository(PositionHistoryBySessionRepository, "report")
    private readonly positionHistoryBySessionRepoReport: PositionHistoryBySessionRepository,
    @InjectRepository(OrderWithPositionHistoryBySessionRepository, "report")
    private readonly orderWithPositionHistoryBySessionRepoReport: OrderWithPositionHistoryBySessionRepository,
    @InjectRepository(TransactionRepository, "report")
    private readonly transactionRepoReport: TransactionRepository,
    @InjectRepository(AccountRepository, "report")
    private readonly accountRepoReport: AccountRepository,
    private readonly futureEventService: FutureEventService,
    private readonly redisClient: RedisClient,
    private readonly excelService: ExcelService
  ) {}

  public async execute(
    query: GetTradingMetricsAdminDto,
    authHeader: string
  ): Promise<GetTradingMetricsAdminResponse> {
    const phbsQueryBuilder = this.positionHistoryBySessionRepoReport
      .createQueryBuilder("ph")
      .where("ph.status = :status", { status: "CLOSED" })
      .andWhere("ph.pnl != 0");
    const txQueryBuilder = this.transactionRepoReport.createQueryBuilder("tx");

    if (query.startDate) {
      phbsQueryBuilder.andWhere("ph.openTime >= :startDate", {
        startDate: query.startDate,
      });
      txQueryBuilder.andWhere("tx.createdAt >= :startDate", {
        startDate: query.startDate,
      });
    }
    if (query.endDate) {
      phbsQueryBuilder.andWhere("ph.closeTime <= :endDate", {
        endDate: query.endDate,
      });
      txQueryBuilder.andWhere("tx.createdAt <= :endDate", {
        endDate: query.endDate,
      });
    }

    // Get phbs ids
    const phbsIds = (await phbsQueryBuilder.select(["ph.id"]).getMany()).map(
      (ph) => ph.id
    );

    // Sum metrics
    phbsQueryBuilder.select([
      "COUNT(ph.id) as totalPositions",
      "SUM(CASE WHEN ph.pnl > 0 THEN 1 ELSE 0 END) as totalWins",
      "SUM(CASE WHEN ph.pnl < 0 THEN 1 ELSE 0 END) as totalLosses",

      "SUM(CASE WHEN ph.profit > 0 THEN ph.profit ELSE 0 END) as totalProfit",
      "SUM(CASE WHEN ph.profit < 0 THEN ph.profit ELSE 0 END) as totalLoss",
      "SUM(CASE WHEN ph.pnl > 0 THEN ph.pnl ELSE 0 END) as totalPnlWin",
      "SUM(CASE WHEN ph.pnl < 0 THEN ph.pnl ELSE 0 END) as totalPnlLoss",

      "SUM(ABS(ph.maxValue)) as totalSize",

      "SUM(ph.fee) as totalTradingFee",
      "SUM(ph.fundingFee) as totalFundingFee",
    ]);

    const phbsRaw = await phbsQueryBuilder.getRawOne();

    // Total Positions
    const totalPositions = new BigNumber(phbsRaw.totalPositions ?? 0);

    // Total Wins - Avg Win Rate
    const totalWins = new BigNumber(phbsRaw.totalWins ?? 0);
    const avgWinRate = totalPositions.isEqualTo(0)? new BigNumber(0): totalWins.dividedBy(totalPositions).multipliedBy(100);

    // Total Losses - Avg Loss Rate
    const totalLosses = new BigNumber(phbsRaw.totalLosses ?? 0);
    const avgLossRate = totalPositions.isEqualTo(0)? new BigNumber(0): totalLosses.dividedBy(totalPositions).multipliedBy(100);

    // Current position
    const currentPositions = new BigNumber(
      await this.positionHistoryBySessionRepoReport
        .createQueryBuilder("phbs")
        .where(`phbs.status IN (:...status)`, {
          status: ["OPEN", "PARTIAL_CLOSED"],
        })
        .getCount()
    );

    // Total profit
    const totalProfit = new BigNumber(phbsRaw.totalProfit ?? 0);
    // Total PNL win
    const totalPnlWin = new BigNumber(phbsRaw.totalPnlWin ?? 0);
    // Avg Pnl Win
    const avgPnlWin = totalWins.isEqualTo(0)? new BigNumber(0): totalPnlWin.dividedBy(totalWins);
    // Total loss
    const totalLoss = new BigNumber(phbsRaw.totalLoss ?? 0);
    // Total PNL loss
    const totalPnlLoss = new BigNumber(phbsRaw.totalPnlLoss ?? 0);
    // Avg Pnl Loss
    const avgPnlLoss = totalLosses.isEqualTo(0)? new BigNumber(0): totalPnlLoss.dividedBy(totalLosses);

    // Get total volume
    // tìm toàn bộ ophbs join với order
    // tính tổng order.executedPrice * (order.quantity - order.remaining)
    let totalVolume = new BigNumber(0);
    if (phbsIds && phbsIds.length !== 0) {
      totalVolume = new BigNumber(
        (
          await this.orderWithPositionHistoryBySessionRepoReport
            .createQueryBuilder("ophbs")
            .where(`ophbs.positionHistoryBySessionId IN (:...phbsIds)`, {
              phbsIds,
            })
            .innerJoin("orders", "order", "ophbs.orderId = order.id")
            .select([
              "SUM(ophbs.tradePriceAfter*(order.quantity - order.remaining)) as totalVolume",
            ])
            .getRawOne()
        ).totalVolume ?? 0
      );
    }

    // Total Size
    const totalSize = new BigNumber(phbsRaw.totalSize ?? 0);
    // Average Size / Position
    const avgSizeByPosition = totalPositions.isEqualTo(0)? new BigNumber(0): totalSize.dividedBy(totalPositions);

    // Total Trading Fee
    const totalTradingFee = new BigNumber(phbsRaw.totalTradingFee ?? 0);
    // Total Funding Fee
    const totalFundingFee = new BigNumber(phbsRaw.totalFundingFee ?? 0);
    // Average Fee / Position
    const avgFeePerPosition = totalPositions.isEqualTo(0)? new BigNumber(0): totalTradingFee.dividedBy(totalPositions);
    // Total commission
    const totalCommission = await this.getTotalCommission(authHeader);

    // Total MM profit
    const totalMmProfit = totalProfit
      .plus(totalLoss)
      .plus(totalTradingFee)
      .minus(totalCommission);

    // Total MM Pnl
    const totalMmPnl = totalProfit.plus(totalLoss);

    // Total MM Fee
    const totalMmFee = totalTradingFee.minus(totalCommission);

    // Total reward voucher
    const totalRewardVoucher = new BigNumber(
      (
        await txQueryBuilder
          .andWhere(`tx.type = :txType`, { txType: TransactionType.EVENT_REWARD })
          .select(["SUM(tx.amount) as totalRewardVoucher"])
          .getRawOne()
      ).totalRewardVoucher ?? 0
    );

    // Available for Withdrawal
    const availToWithdrawal = await this.getAvailableToWithdrawal();

    const response = {
      totalPosition: totalPositions.toNumber(),
      totalWins: `${totalWins.toFixed()} (${avgWinRate.toFixed(1)}%)`,
      totalLosses: `${totalLosses.toFixed()} (${avgLossRate.toFixed(1)}%)`,
      currentPosition: currentPositions.toNumber(),

      totalProfit: `$${Number(totalProfit.toFixed(2)).toLocaleString(
        undefined,
        { minimumFractionDigits: 2, maximumFractionDigits: 2 }
      )}`,
      totalPnlWin: `$${Number(totalPnlWin.toFixed(2)).toLocaleString(
        undefined,
        { minimumFractionDigits: 2, maximumFractionDigits: 2 }
      )} - $${Number(avgPnlWin.toFixed(2)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,
      // avgPnlWin: `$${Number(avgPnlWin.toFixed(2)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
      totalLoss: `$${Number(totalLoss.abs().toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`,
      totalPnlLoss: `$${Number(totalPnlLoss.abs().toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )} - $${Number(avgPnlLoss.abs().toFixed(2)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,
      // avgPnlLoss: `$${Number(avgPnlLoss.abs().toFixed(2)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,

      totalVolume: `${Number(totalVolume.toFixed(0)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,
      totalSize: `${Number(totalSize.toFixed(0)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,
      averageSize: `${Number(avgSizeByPosition.toFixed(1)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`,

      totalTradingFee: `$${Number(totalTradingFee.toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`,
      totalFundingFee: `$${Number(totalFundingFee.toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`,
      averageFee: `$${Number(avgFeePerPosition.toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`,
      totalCommission: `$${Number(totalCommission.toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`,

      totalMmProfit: `$${Number(totalMmProfit.toFixed(2)).toLocaleString(
        undefined,
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`,
      mmPnl: `$${Number(totalMmPnl.toFixed(2)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,
      totalMmFee: `$${Number(totalMmFee.toFixed(2)).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,
      totalRewardVoucher: `$${Number(
        totalRewardVoucher.toFixed(2)
      ).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,

      availableForWithdrawal: `$${Number(
        availToWithdrawal.toFixed(2)
      ).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`,
    };

    return response;
  }

  private async getAvailableToWithdrawal(): Promise<BigNumber> {
    // Cache the result in Redis for 5 minutes
    const redisKey = "trading-metrics:availableToWithdrawal";
    const cachedValue = await this.redisClient.getInstance().get(redisKey);
    if (cachedValue !== null && cachedValue !== undefined) {
      return new BigNumber(cachedValue);
    }

    // Get all accounts with asset = 'USDT' and balance > 0
    const accounts = await this.accountRepoReport
      .createQueryBuilder("account")
      .where("account.asset = :asset", { asset: USDT })
      .andWhere("account.balance > 0")
      .andWhere("account.id > 10000") // not get bot account
      .select([
        "account.id",
        "account.userId",
        "account.balance",
        "account.rewardBalance",
      ])
      .getMany();

    const lockedProfits = await Promise.all(
      accounts.map((a) => this.futureEventService.getLockedProfit(a.userId))
    );

    let totalBalanceCanWithdrawal = new BigNumber(0);
    for (let i = 0; i < lockedProfits.length; i++) {
      const account = accounts[i];
      const lockedProfit = lockedProfits[i];
      const balanceCanWithdrawal = new BigNumber(account.balance)
        .minus(account.rewardBalance)
        .minus(lockedProfit);
      totalBalanceCanWithdrawal = totalBalanceCanWithdrawal.plus(
        balanceCanWithdrawal
      );
    }

    // Save to Redis with 5 minutes expiration (300 seconds)
    await this.redisClient
      .getInstance()
      .set(redisKey, totalBalanceCanWithdrawal.toFixed(), "EX", 300);
    return totalBalanceCanWithdrawal;
  }

  private async getTotalCommission(authHeader: string) {
    const response = await fetch(
      `${process.env.SPOT_URL_API}/admin/api/referrer/commission/referrals/total`,
      {
        method: "GET",
        headers: {
          Authorization: authHeader,
          "Content-Type": "application/json",
        },
      }
    );

    if (!response.ok) {
      Logger.error(`Failed to fetch total commission: ${response.statusText}`);
      return new BigNumber(0);
    }

    const result = await response.json();
    if (
      !result ||
      !result.success ||
      !result.data ||
      typeof result.data.total !== "string"
    ) {
      Logger.error("Invalid response structure when fetching total commission");
      return new BigNumber(0);
    }

    return new BigNumber(result.data.total);
  }

  public async exportExcel(
    query: GetTradingMetricsAdminDto,
    authHeader: string
  ) {
    const response = await this.execute(query, authHeader);
    const COLUMN_NAMES = [
      "Position Summary",
      "Total Positions",
      `${response.totalPosition}`,
    ];
    const columnDataKeys = ["column1", "column2", "column3"];

    const responses = [
      {
        column1: "",
        column2: "Total Wins",
        column3: `${response.totalWins
          .split(" ")[0]
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Avg Win Rate",
        column3: `${response.totalWins
          .split(" ")[1]
          .replace("$", "")
          .replace(/,/g, "")
          .replace("(", "")
          .replace(")", "")}`,
      },
      {
        column1: "",
        column2: "Total Losses",
        column3: `${response.totalLosses
          .split(" ")[0]
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Avg Lose Rate",
        column3: `${response.totalLosses
          .split(" ")[1]
          .replace("$", "")
          .replace(/,/g, "")
          .replace("(", "")
          .replace(")", "")}`,
      },
      {
        column1: "",
        column2: "Current Position",
        column3: response.currentPosition,
      },
      {
        column1: "",
        column2: "",
        column3: "",
      },
      {
        column1: "Profit & Loss",
        column2: "Total Profit",
        column3: `${response.totalProfit.replace("$", "").replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Total PNL Win",
        column3: `${response.totalPnlWin
          .split(" - ")[0]
          .replace(/,/g, "")
          .replace("$", "")}`,
      },
      {
        column1: "",
        column2: "Average PNL Win",
        column3: `${response.totalPnlWin
          .split(" - ")[1]
          .replace(/,/g, "")
          .replace("$", "")}`,
      },
      {
        column1: "",
        column2: "Total Loss",
        column3: `${response.totalLoss.replace("$", "").replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Total PNL Loss",
        column3: `${response.totalPnlLoss
          .split(" - ")[0]
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Average PNL Loss",
        column3: `${response.totalPnlLoss
          .split(" - ")[1]
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "",
        column3: "",
      },
      {
        column1: "Trading Volume",
        column2: "Total Volume",
        column3: `${response.totalVolume.replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Total Size",
        column3: `${response.totalSize.replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Average Size/Position",
        column3: `${response.averageSize.replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "",
        column3: "",
      },
      {
        column1: "Fee Structure",
        column2: "Total Trading Fee",
        column3: `${response.totalTradingFee
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Total Funding Fee",
        column3: `${response.totalFundingFee
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Average Fee per Position",
        column3: `${response.averageFee.replace("$", "").replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Total Commission",
        column3: `${response.totalCommission
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "",
        column3: "",
      },
      {
        column1: "Additional Metrics",
        column2: "Total MM Profit",
        column3: `${response.totalMmProfit.replace("$", "").replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "MM PNL",
        column3: `${response.mmPnl.replace("$", "").replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "Total Reward Voucher",
        column3: `${response.totalRewardVoucher
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
      {
        column1: "",
        column2: "",
        column3: "",
      },
      {
        column1: "Future Balance",
        column2: "Available for Withdrawal",
        column3: `${response.availableForWithdrawal
          .replace("$", "")
          .replace(/,/g, "")}`,
      },
    ];

    // Generate the Excel buffer
    const buffer = await this.excelService.generateExcelBuffer(
      COLUMN_NAMES,
      columnDataKeys,
      responses
    );

    const fileName = "trading-metrics-data";
    // Set response headers for file download
    const exportTime = moment().format("YYYY-MM-DD_HH-mm-ss");
    return {
      fileName: `${fileName}-${exportTime}.xlsx`,
      base64Data: Buffer.from(buffer).toString("base64"),
    };
  }
}

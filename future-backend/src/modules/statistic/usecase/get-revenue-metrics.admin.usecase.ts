import { Injectable, Logger } from "@nestjs/common";
import { GetRevenueMetricsAdminDto } from "../dto/get-revenue-metrics.admin.dto";
import { GetRevenueMetricsAdminResponse } from "../repsonse/get-revenue-metrics.admin.response";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { InjectRepository } from "@nestjs/typeorm";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { TransactionType } from "src/shares/enums/transaction.enum";
import BigNumber from "bignumber.js";

@Injectable()
export class GetRevenueMetricsAdminUseCase {
  constructor(
    @InjectRepository(TradeRepository, "report")
    private readonly tradeRepoReport: TradeRepository,
    @InjectRepository(TransactionRepository, "report")
    private readonly transactionRepoReport: TransactionRepository
  ) {}

  public async execute(
    query: GetRevenueMetricsAdminDto
  ): Promise<GetRevenueMetricsAdminResponse> {
    const tradingVolumeQb = this.tradeRepoReport
      .createQueryBuilder(`trade`)
      .where(`(trade.sellUserId > 500 OR trade.buyUserId > 500)`)
      .select([`sum(trade.quantity * trade.price) as totalTradingVolume`]);

    const totalFeesQb = this.transactionRepoReport
      .createQueryBuilder(`tx`)
      .where(`(tx.type = :txType AND tx.userId > 500)`, {
        txType: TransactionType.TRADING_FEE,
      })
      .select([`sum(tx.amount) totalFees`]);

    if (query.fromDate) {
      tradingVolumeQb.andWhere("trade.createdAt >= :fromDate", {
        fromDate: query.fromDate,
      });
      totalFeesQb.andWhere("tx.createdAt >= :fromDate", {
        fromDate: query.fromDate,
      });
    }
    if (query.toDate) {
      tradingVolumeQb.andWhere("trade.createdAt <= :toDate", {
        toDate: query.toDate,
      });
      totalFeesQb.andWhere("tx.createdAt <= :toDate", {
        toDate: query.toDate,
      });
    }

    const tradingVolume = new BigNumber((await tradingVolumeQb.getRawOne())?.totalTradingVolume ?? 0).abs();;
    const totalFees = new BigNumber((await totalFeesQb.getRawOne())?.totalFees ?? 0).abs();;

    return {
      tradingVolume: tradingVolume.toFixed(2),
      tradingFee: totalFees.toFixed(2),
    }
  }

  private async getReferralCommission(
    query: GetRevenueMetricsAdminDto,
    authHeader: string
  ) {
    let url = `${process.env.SPOT_URL_API}/admin/api/referrer/commission/referrals/total?`;
    if (query.fromDate) {
      url = `${url}sdate=${query.fromDate}&`;
    }
    if (query.toDate) {
      url = `${url}edate=${query.toDate}&`;
    }

    const response = await fetch(url, {
      method: "GET",
      headers: {
        Authorization: authHeader,
        "Content-Type": "application/json",
      },
    });

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

    return new BigNumber(result?.data?.total ?? 0);
  }
}

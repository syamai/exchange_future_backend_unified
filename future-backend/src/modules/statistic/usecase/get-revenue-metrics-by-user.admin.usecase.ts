import { Injectable, Logger } from "@nestjs/common";
import { GetRevenueMetricsAdminDto } from "../dto/get-revenue-metrics.admin.dto";
import { GetRevenueMetricsAdminResponse } from "../repsonse/get-revenue-metrics.admin.response";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { InjectRepository } from "@nestjs/typeorm";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { TransactionType } from "src/shares/enums/transaction.enum";
import BigNumber from "bignumber.js";
import { GetRevenueMetricsByUserForAdminDto } from "../dto/get-revenue-metrics-by-user.admin.dto";
import { GetRevenueMetricsByUserForAdminResponse } from "../repsonse/get-revenue-metrics-by-user.admin.response";
import { AccountRepository } from "src/models/repositories/account.repository";
import { USDT } from "src/modules/balance/balance.const";

@Injectable()
export class GetRevenueMetricsByUserForAdminUseCase {
  constructor(
    @InjectRepository(AccountRepository, "report")
    private readonly accountRepoReport: AccountRepository,
    @InjectRepository(TransactionRepository, "report")
    private readonly transactionRepoReport: TransactionRepository
  ) {}

  public async execute(
    query: GetRevenueMetricsByUserForAdminDto
  ): Promise<GetRevenueMetricsByUserForAdminResponse[]> {
    if (!query.userIds || query.userIds.length === 0) return [];

    // Get balance
    const accountsWithBalance = await this.accountRepoReport
      .createQueryBuilder("account")
      .where(`account.userId > 500`) // not bot
      .andWhere(`account.userId IN (:...userIds)`, { userIds: query.userIds })
      .andWhere(`account.asset = :asset`, { asset: USDT })
      .select([`account.id`, `account.userId`, `account.balance`])
      .getMany();

    const balanceByUserId = new Map<number, BigNumber>();
    for (const account of accountsWithBalance) {
      balanceByUserId.set(
        Number(account.userId),
        new BigNumber(account.balance)
      );
    }

    // Get fee
    const feeRaws = await this.transactionRepoReport
      .createQueryBuilder("tx")
      .where(`tx.userId > 500`) // not bot
      .andWhere(`tx.userId IN (:...userIds)`, { userIds: query.userIds })
      .andWhere(`tx.type IN (:...txTypes)`, {
        txTypes: [
          TransactionType.TRADING_FEE,
          TransactionType.LIQUIDATION_CLEARANCE,
          TransactionType.MARGIN_INSURANCE_FEE,
        ],
      })
      .groupBy("tx.userId")
      .select([`tx.userId AS userId`, `SUM(ABS(tx.amount)) AS fee`])
      .getRawMany();

    const feeByUserId = new Map<number, BigNumber>();
    if (feeRaws && feeRaws.length > 0) {
      for (const feeRaw of feeRaws) {
        feeByUserId.set(Number(feeRaw.userId), new BigNumber(feeRaw.fee));
      }
    }

    // return responses
    const responses: GetRevenueMetricsByUserForAdminResponse[] = [];
    for (const userId of query.userIds) {
      responses.push({
        userId,
        totalValueVoucher: null,
        futureBalance: balanceByUserId.get(userId)?.toFixed(2) ?? "0",
        futureFee: feeByUserId.get(userId)?.toFixed(2) ?? "0",
      });
    }
    return responses;
  }
}

import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { TradingVolumeSessionEntity } from "src/models/entities/trading-volume-session.entity";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { TradingVolumeSessionLogRepository } from "src/models/repositories/trading-volume-session-log.repository";
import { TradingVolumeSessionRepository } from "src/models/repositories/trading-volume-session.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { UserRewardFutureEventUsedRepository } from "src/models/repositories/user-reward-future-event-used.repository";
import { TradingVolumeStatus } from "./constants/trading-volume-status.enum";
import BigNumber from "bignumber.js";
import { v4 as uuidv4 } from "uuid";

@Injectable()
export class TradingVolumeService {
  constructor(
    @InjectRepository(TradingVolumeSessionRepository, "master")
    private readonly tradingVolumeRepoMaster: TradingVolumeSessionRepository,

    @InjectRepository(TradingVolumeSessionLogRepository, "master")
    private readonly tradingVolumeLogRepoMaster: TradingVolumeSessionLogRepository,

    @InjectRepository(TradeRepository, "report")
    private readonly tradeRepoReport: TradeRepository,

    @InjectRepository(TransactionRepository, "report")
    private readonly transactionRepoReport: TransactionRepository,

    @InjectRepository(UserRewardFutureEventUsedRepository, "report")
    private readonly userRewardFutureEventUsedRepoReport: UserRewardFutureEventUsedRepository
  ) {}

  public async createTrdVlSessionIfNotExist(userId: number) {
    await this.tradingVolumeRepoMaster
      .createQueryBuilder()
      .insert()
      .into(TradingVolumeSessionEntity)
      .values({
        userId,
        totalReward: "0",
        currentTradingVolume: "0",
        totalProfit: "0",
        totalLoss: "0",
        totalUsedReward: "0",
        targetTradingVolume: "0",
        status: TradingVolumeStatus.INACTIVE,
      })
      .orIgnore() // Generates INSERT IGNORE in MariaDB/MySQL
      .updateEntity(false)
      .execute();
  }

  public async getTradingVolumeData(userId: number, fromDate: string | Date, toDate?: string | Date) {
    // Get trading volume data using the 3 queries beneath
    const [currentTradingVolume, transactionData, usedRwbl] = await Promise.all([
      this.getUserTradingVolume(userId, fromDate, toDate),
      this.getTransactionData(userId, fromDate, toDate),
      this.getTotalUsedRwbl(userId, fromDate, toDate),
    ]);

    return {
      currentTradingVolume,
      totalLoss: transactionData.totalLoss,
      totalProfit: transactionData.totalProfit,
      totalUsedReward: usedRwbl,
    };
  }

  public async processResetTrdVl(trdVl: TradingVolumeSessionEntity, toDate?: string | Date) {
    const metRqmt = await this.checkResetTrdVl(trdVl, toDate);
    if (metRqmt.status === true) {
      // log an end session to trading volume session log
      await this.createEndSessionLog(trdVl, metRqmt.reason, toDate);
      // reset trading volume to default
      this.resetTrdVlToDefault(trdVl);
    }
  }

  private resetTrdVlToDefault(trdVl: TradingVolumeSessionEntity) {
    // reset trading volume to default values
    trdVl.currentTradingVolume = "0";
    trdVl.totalProfit = "0";
    trdVl.totalLoss = "0";
    trdVl.totalUsedReward = "0";
    trdVl.totalReward = "0";
    trdVl.targetTradingVolume = "0";
    trdVl.status = TradingVolumeStatus.INACTIVE;
    trdVl.startDate = null;
    trdVl.sessionUUID = null;
  }

  private async createEndSessionLog(trdVl: TradingVolumeSessionEntity, logDetail: string, endSessionDate: Date | string) {
    await this.tradingVolumeLogRepoMaster.save({
      startDate: trdVl.startDate,
      endDate: endSessionDate,
      totalReward: trdVl.totalReward,
      currentTradingVolume: trdVl.currentTradingVolume,
      totalProfit: trdVl.totalProfit,
      totalLoss: trdVl.totalLoss,
      totalUsedReward: trdVl.totalUsedReward,
      targetTradingVolume: trdVl.targetTradingVolume,
      sessionUUID: trdVl.sessionUUID,
      userId: trdVl.userId,
      logDetail
    });
  }

  private async checkResetTrdVl(trdVl: TradingVolumeSessionEntity, toDate?: string | Date) {
    // current trading volume >= target trading volume
    const tradVlRqmt = new BigNumber(trdVl.currentTradingVolume).gte(trdVl.targetTradingVolume);
    if (tradVlRqmt) {
      return {
        status: true,
        reason: "Met trading volume requirement",
      };
    }

    // current reward balance = 0 and lockedProfit <= 0
    const usedRwblQb = this.userRewardFutureEventUsedRepoReport
      .createQueryBuilder("u")
      .where("userId = :userId", { userId: trdVl.userId })
      .andWhere(`dateUsed >= :startDate`, { startDate: trdVl.startDate })
      .orderBy("id", "DESC");

    if (toDate) {
      usedRwblQb.andWhere("dateUsed <= :toDate", { toDate });
    }

    const usedRwbl = await usedRwblQb.getOne();

    if (!usedRwbl) {
      return {
        status: false,
        reason: "No satisfied any requirement",
      };
    }

    if (new BigNumber(usedRwbl.remainingRewardBalance).eq('0') && new BigNumber(this.calcLockedProfit(trdVl)).isLessThanOrEqualTo("0")) {
      return {
        status: true,
        reason: "Locked profit <= 0 and no remainingRewardBalance",
      };
    }

    return {
      status: false,
      reason: "No satisfied any requirement",
    };
  }

  private async getUserTradingVolume(userId: number, fromDate: string | Date, toDate?: string | Date) {
    // get user trading volume from date rewardBalance
    const qb = this.tradeRepoReport
      .createQueryBuilder("t")
      .select([`coalesce(sum(price * quantity), 0) currentTradingVolume`])
      .where("createdAt >= :fromDate", { fromDate })
      .andWhere(`(sellUserId = :userId or buyUserId = :userId)`, { userId });

    if (toDate) {
      qb.andWhere("createdAt <= :toDate", { toDate });
    }

    const { currentTradingVolume } = await qb.getRawOne();

    return currentTradingVolume;
  }

  private async getTransactionData(userId: number, fromDate: string | Date, toDate?: string | Date) {
    const qb = this.transactionRepoReport
      .createQueryBuilder("t")
      .select([
        `
        abs(COALESCE(sum(CASE when type = 'FUNDING_FEE' and amount < 0 and status ='approved' then amount end), 0)) +
        abs(COALESCE(sum(CASE when type = 'TRADING_FEE' and status ='approved' then amount end), 0)) + 
        abs(COALESCE(sum(CASE when type = 'REALIZED_PNL' and amount < 0 and status = 'approved' then amount end), 0)) +
        abs(COALESCE(sum(CASE when type = 'LIQUIDATION_CLEARANCE' and status = 'confirmed' then amount end), 0)) +
        abs(COALESCE((CASE when type = 'MARGIN_INSURANCE_FEE' and status = 'confirmed' then amount end), 0)) totalLoss,
        COALESCE(sum(CASE when type = 'REALIZED_PNL' and amount > 0 and status = 'approved' then amount end), 0) totalProfit
        `,
      ])
      .andWhere(`userId = :userId`, { userId })
      .andWhere("createdAt >= :fromDate", { fromDate });

    if (toDate) {
      qb.andWhere("createdAt <= :toDate", { toDate });
    }

    const { totalLoss, totalProfit } = await qb.getRawOne();

    return { totalLoss, totalProfit };
  }

  private async getTotalUsedRwbl(userId: number, fromDate: string | Date, toDate?: string | Date) {
    const qb = this.userRewardFutureEventUsedRepoReport
      .createQueryBuilder("usedRw")
      .select([`COALESCE (sum(amount), 0) usedRwbl`])
      .andWhere(`userId = :userId`, { userId })
      .andWhere("dateUsed >= :fromDate", { fromDate });

    if (toDate) {
      qb.andWhere("createdAt <= :toDate", { toDate });
    }

    const { usedRwbl } = await qb.getRawOne();

    return usedRwbl;
  }

  // profit locked = profit (pnl > 0) - (totalLoss (all using used rwbl) - used_rwbl)
  private calcLockedProfit(trdVl: TradingVolumeSessionEntity) {
    return new BigNumber(trdVl.totalProfit).minus(new BigNumber(trdVl.totalLoss).minus(trdVl.totalUsedReward)).toString();
  }

  public generateSessionUUID(): string {
    return `session_${Date.now()}_${uuidv4()}`;
  }
}

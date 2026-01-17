import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { UserRewardFutureEventUsedEntity } from "src/models/entities/user-reward-future-event-used.entity";
import { UserRewardFutureEventEntity } from "src/models/entities/user-reward-future-event.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { UserRewardFutureEventUsedRepository } from "src/models/repositories/user-reward-future-event-used.repository";
import { UserRewardFutureEventRepository } from "src/models/repositories/user-reward-future-event.repository";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { FutureEventKafkaTopic, KafkaTopics } from "src/shares/enums/kafka.enum";
import { TransactionStatus, TransactionType } from "src/shares/enums/transaction.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { getQueryLimit } from "src/shares/pagination-util";
import { MoreThan } from "typeorm";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { RewardStatus } from "./constants/reward-status.enum";
import { UserRewardFutureEventUsedDetailEntity } from "src/models/entities/user-reward-future-event-used-detail.entity";
import { UserRewardFutureEventUsedDetailRepository } from "src/models/repositories/user-reward-future-event-used-detail.repository";
import { TradingVolumeSessionRepository } from "src/models/repositories/trading-volume-session.repository";
import { TradingVolumeStatus } from "./constants/trading-volume-status.enum";

@Injectable()
export class FutureEventService {
  private readonly logger = new Logger(FutureEventService.name);

  constructor(
    @InjectRepository(UserRewardFutureEventRepository, "master")
    private readonly userRewardFutureEventRepoMaster: UserRewardFutureEventRepository,
    @InjectRepository(UserRewardFutureEventUsedRepository, "master")
    private readonly userRewardFutureEventUsedRepoMaster: UserRewardFutureEventUsedRepository,
    @InjectRepository(UserRewardFutureEventUsedRepository, "report")
    private readonly userRewardFutureEventUsedRepoReport: UserRewardFutureEventUsedRepository,
    @InjectRepository(AccountRepository, "master")
    public readonly accountRepoMaster: AccountRepository,
    private readonly kafkaClient: KafkaClient,
    @InjectRepository(TransactionRepository, "report")
    private readonly transactionRepoReport: TransactionRepository,

    @InjectRepository(TradeRepository, "report")
    private readonly tradeRepoReport: TradeRepository,

    @InjectRepository(UserRewardFutureEventRepository, "report")
    private readonly userRewardFutureEventRepoReport: UserRewardFutureEventRepository,

    @InjectRepository(UserRewardFutureEventUsedDetailRepository, "master")
    private readonly userRewardFutureEventUsedDetailRepoMaster: UserRewardFutureEventUsedDetailRepository,

    @InjectRepository(TradingVolumeSessionRepository, "report")
    private readonly tradingVolumeSessionRepoReport: TradingVolumeSessionRepository,

  ) {}

  async getEventRewards(userId: number): Promise<UserRewardFutureEventEntity[]> {
    return await this.userRewardFutureEventRepoMaster.find({
      where: { userId },
      order: { id: "DESC" },
    });
  }

  async getActiveEventRewards(userId: number): Promise<UserRewardFutureEventEntity[]> {
    const now = new Date().toISOString();
    return await this.userRewardFutureEventRepoMaster.find({
      where: {
        userId,
        expiredDate: MoreThan(now),
        isRevoke: false,
      },
      order: { id: "DESC" },
    });
  }

  async revokeEventReward(rewardId: number): Promise<void> {
    await this.userRewardFutureEventRepoMaster.update({ id: rewardId }, { isRevoke: true });
  }

  public async updateUserUsedRewardBalance(transaction: TransactionEntity) {
    const usedRewardTransactionTypes = ["FUNDING_FEE", "TRADING_FEE", "REALIZED_PNL", "LIQUIDATION_CLEARANCE", "MARGIN_INSURANCE_FEE"];
    if (!usedRewardTransactionTypes.includes(transaction.type)) return;

    if (transaction.status !== TransactionStatus.APPROVED && transaction.status !== TransactionStatus.CONFIRMED) {
      return;
    }

    if (transaction.type === TransactionType.REALIZED_PNL || transaction.type === TransactionType.FUNDING_FEE) {
      if (new BigNumber(transaction.amount).gte(0)) return;
    }

    const amount = new BigNumber(transaction.amount).abs().toString();

    // get reward balance account
    const currentRewardBalance = (await this.accountRepoMaster.findOne({ where: { id: transaction.accountId } })).rewardBalance;

    // Use direct SQL UPDATE with calculation to avoid race conditions
    const result = await this.accountRepoMaster
      .createQueryBuilder()
      .update()
      .set({
        rewardBalance: () => `GREATEST(rewardBalance - ${amount}, 0)`,
      })
      .where("id = :accountId", { accountId: transaction.accountId })
      .andWhere("rewardBalance > 0")
      .execute();

    if (result.affected > 0) {
      // Insert record into user_reward_future_event_used
      const remainingRewardBalance = new BigNumber(currentRewardBalance).minus(amount);
      const usedRewardEntity = new UserRewardFutureEventUsedEntity();
      usedRewardEntity.userId = transaction.userId;
      usedRewardEntity.transactionUuid = transaction.uuid;
      usedRewardEntity.amount = remainingRewardBalance.lt(0) ? currentRewardBalance : amount;
      usedRewardEntity.dateUsed = new Date();
      usedRewardEntity.remainingRewardBalance = remainingRewardBalance.lt(0) ? "0" : remainingRewardBalance.toString();
      usedRewardEntity.symbol = transaction.symbol;
      usedRewardEntity.transactionType = transaction.type;

      const usedReward = await this.userRewardFutureEventUsedRepoMaster.save(usedRewardEntity);
      
      await this.kafkaClient.send(FutureEventKafkaTopic.reward_balance_used_to_process_used_detail, usedReward);

      this.logger.log(`Updated reward balance for account ${transaction.accountId} by subtracting ${amount}`);
    }
  }

  async getFutureEventRewardsWithPagination(
    paginationDto: PaginationDto,
    userId: number
  ): Promise<[UserRewardFutureEventEntity[], number]> {
    const { offset, limit } = getQueryLimit(paginationDto);
    const [rewards, total] = await this.userRewardFutureEventRepoMaster.findAndCount({
      skip: offset,
      take: limit,
      order: {
        id: "DESC",
      },
      where: {
        userId,
      },
    });

    return [rewards, total];
  }

  async getRewardUsageHistoryWithPagination(
    paginationDto: PaginationDto,
    userId: number
  ): Promise<[UserRewardFutureEventUsedEntity[], number]> {
    const { offset, limit } = getQueryLimit(paginationDto);
    const [usageHistory, total] = await this.userRewardFutureEventUsedRepoReport.findAndCount({
      skip: offset,
      take: limit,
      order: {
        id: "DESC",
      },
      where: {
        userId,
      },
    });

    return [usageHistory, total];
  }

  private getUsageStats = async () => {
    // Get usage statistics from all records (no filters)
    const usageStats = await this.userRewardFutureEventUsedRepoReport
      .createQueryBuilder("usage")
      .select(["COUNT(usage.id) as totalUsageCount", "COALESCE(SUM(usage.amount), 0) as totalUsedAmount"])
      .getRawOne();

    return {
      totalUsageCount: parseInt(usageStats.totalUsageCount) || 0,
      totalUsedAmount: usageStats.totalUsedAmount || "0",
    };
  };

  private getTotalReward = async () => {
    // Get total reward amount from all records (no filters)
    const totalReward = await this.userRewardFutureEventRepoMaster
      .createQueryBuilder("reward")
      .select("COALESCE(SUM(reward.amount), 0) as totalRewardAmount")
      .getRawOne();
    return totalReward.totalRewardAmount || "0";
  };

  async getRewardUsageHistoryAdminWithPagination(
    paginationDto: PaginationDto,
    filters: {
      userId?: number;
      startDate?: string;
      endDate?: string;
      symbol?: string;
      search?: string;
      transactionType?: string;
    }
  ): Promise<[any[], number, any]> {
    const { offset, limit } = getQueryLimit(paginationDto);
    const queryBuilder = this.userRewardFutureEventUsedRepoReport
      .createQueryBuilder("usage")
      .leftJoin("users", "user", "user.id = usage.userId")
      .select([
        "usage.id id",
        "usage.userId userId",
        "usage.transactionUuid transactionUuid",
        "usage.amount amount",
        "usage.dateUsed dateUsed",
        "usage.remainingRewardBalance remainingRewardBalance",
        "usage.symbol symbol",
        "usage.transactionType transactionType",
        "user.email as userEmail",
        "user.uid as userUid",
      ]);

    // Add filters
    if (filters.userId) {
      queryBuilder.andWhere("usage.userId = :userId", { userId: filters.userId });
    }

    if (filters.startDate) {
      queryBuilder.andWhere("usage.dateUsed >= :startDate", { startDate: filters.startDate });
    }

    if (filters.endDate) {
      queryBuilder.andWhere("usage.dateUsed <= :endDate", { endDate: filters.endDate });
    }

    if (filters.symbol) {
      queryBuilder.andWhere("usage.symbol = :symbol", { symbol: filters.symbol });
    }

    if (filters.search) {
      queryBuilder.andWhere("(usage.transactionUuid LIKE :search OR user.uid LIKE :search OR user.email LIKE :search)", {
        search: `%${filters.search}%`,
      });
    }

    if (filters.transactionType) {
      queryBuilder.andWhere("usage.transactionType = :transactionType", {
        transactionType: filters.transactionType,
      });
    }

    const [usageHistory, total, usageStats, totalReward] = await Promise.all([
      queryBuilder.offset(offset).limit(limit).orderBy("usage.id", "DESC").getRawMany(),
      queryBuilder.getCount(),
      this.getUsageStats(),
      this.getTotalReward(),
    ]);

    const additionalInfo = {
      totalUsageCount: usageStats.totalUsageCount,
      totalUsedAmount: usageStats.totalUsedAmount,
      remainingRewardAmount: new BigNumber(totalReward).minus(usageStats.totalUsedAmount).toString(),
      totalRewardAmount: totalReward,
    };

    return [usageHistory, total, additionalInfo];
  }

  async getLast7DaysStatistics() {
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
    sevenDaysAgo.setHours(0, 0, 0, 0);

    // Get issued rewards for last 7 days
    const issuedRewards = await this.userRewardFutureEventRepoMaster
      .createQueryBuilder("reward")
      .select(["DATE(reward.createdAt) as date", "COALESCE(SUM(reward.amount), 0) as issuedAmount"])
      .where("reward.createdAt >= :sevenDaysAgo", { sevenDaysAgo })
      .groupBy("DATE(reward.createdAt)")
      .getRawMany();

    // Get used rewards for last 7 days
    const usedRewards = await this.userRewardFutureEventUsedRepoReport
      .createQueryBuilder("usage")
      .select(["DATE(usage.dateUsed) as date", "COALESCE(SUM(usage.amount), 0) as usedAmount"])
      .where("usage.dateUsed >= :sevenDaysAgo", { sevenDaysAgo })
      .groupBy("DATE(usage.dateUsed)")
      .getRawMany();

    // Get count of rewards by event name for last 7 days
    const rewardTypeDistribution = await this.userRewardFutureEventRepoMaster
      .createQueryBuilder("reward")
      .select(["reward.eventName eventName", "COUNT(reward.id) as count"])
      .where("reward.createdAt >= :sevenDaysAgo", { sevenDaysAgo })
      .groupBy("reward.eventName")
      .getRawMany();

    // Create a map of all dates in the last 7 days
    const dateMap = new Map();
    for (let i = 0; i < 7; i++) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      date.setHours(0, 0, 0, 0);
      const dateStr = date.toISOString().split("T")[0];
      dateMap.set(dateStr, {
        date: dateStr,
        issuedAmount: "0",
        usedAmount: "0",
      });
    }

    // Fill in issued amounts
    issuedRewards.forEach((record) => {
      const issueDate = record.date.toISOString().split("T")[0];
      if (dateMap.has(issueDate)) {
        dateMap.get(issueDate).issuedAmount = record.issuedAmount;
      }
    });

    // Fill in used amounts
    usedRewards.forEach((record) => {
      const usedDate = record.date.toISOString().split("T")[0];
      if (dateMap.has(usedDate)) {
        dateMap.get(usedDate).usedAmount = record.usedAmount;
      }
    });

    // Convert map to array and sort by date descending
    const last7DaysStatistic = Array.from(dateMap.values()).sort((a, b) => b.date.localeCompare(a.date));

    const [usageStats, totalReward, totalRevokeAmount] = await Promise.all([
      this.getUsageStats(),
      this.getTotalReward(),
      this.getTotalRevokeReward(),
    ]);

    const statistics = {
      totalRewardAmount: totalReward,
      totalUsedAmount: usageStats.totalUsedAmount,
      remainingRewardAmount: new BigNumber(totalReward).minus(usageStats.totalUsedAmount).toString(),
      revokeAmount: totalRevokeAmount,
    };

    return {
      last7DaysStatistic,
      rewardTypeDistribution,
      statistics,
    };
  }

  private async getTotalRevokeReward() {
    const usageStats = await this.transactionRepoReport
      .createQueryBuilder("t")
      .select(["COALESCE(SUM(t.amount), 0) as totalRevokeAmount"])
      .where(`t.type = 'REVOKE_EVENT_REWARD' and t.status = 'APPROVED'`)
      .getRawOne();

    return usageStats.totalRevokeAmount;
  }

  public async getLockedProfit(userId: number) {
    const firstDateInUseRw = await this.getFirstDateInUseReward(userId);

    // console.log(`firstDateInUseRw: ${firstDateInUseRw}`);

    // user has no reward balance in use
    if (!firstDateInUseRw) {
      return '0';
    }

    const hasMetTrdgVlRqmt = await this.hasMetTradingVolumeRequirement(userId, firstDateInUseRw);
    if (!hasMetTrdgVlRqmt) {
      // profit locked = profit (pnl > 0) - (totalLoss (all using used rwbl) - used_rwbl) 
      const [totalPositivePnl, totalLoss, totalUsedRwbl] = await Promise.all([
        this.getTotalPositivePnlFromDate(userId, firstDateInUseRw), 
        this.getTotalLossFromDate(userId, firstDateInUseRw),
        this.getTotalUsedRwblFromDate(userId, firstDateInUseRw)
      ])
      
      const lockedProfit = new BigNumber(totalPositivePnl).minus(new BigNumber(totalLoss).minus(totalUsedRwbl));
    
      if (lockedProfit.lte('0')) return '0';
      // console.log(`lockedProfit: ${lockedProfit.toString()}`);
      
      return lockedProfit.toString();
    }

    return '0';
  }

  public async hasMetTradingVolumeRequirement(userId: number, firstDateInUseRw: Date): Promise<boolean> {
    // get user's current trading volume
    const [userCurrentTradingVolume, targetTradingVolume] = await Promise.all([
      this.getUserTradingVolumeFromDate(userId, firstDateInUseRw),
      this.getTargetTradingVolume(userId),
    ]);
    // console.log(`userCurrentTradingVolume: ${(userCurrentTradingVolume)}, targetTradingVolume: ${targetTradingVolume}`);

    if (new BigNumber(userCurrentTradingVolume).lt(targetTradingVolume)) {
      return false;
    }

    return true;
  }

  public async getFirstDateInUseReward(userId: number) {
    // get first IN_USE reward balance
    // const firstRwQb = this.userRewardFutureEventRepoReport
    //   .createQueryBuilder("rw")
    //   .select(["createdAt firstDateInUseRw"])
    //   .where("userId = :userId", { userId })
    //   .andWhere(`status = '${RewardStatus.IN_USE}'`)
    //   .andWhere(`createdAt > '2025-07-23 04:30:00'`)
    //   .orderBy("id", "ASC");

    // const firstInUseRw = await firstRwQb.getRawOne();

    // return firstInUseRw?.firstDateInUseRw;

    const trdVl = await this.tradingVolumeSessionRepoReport
    .createQueryBuilder("session")
    .where("session.userId = :userId", { userId })
    .getOne();

    if (!trdVl || trdVl.status === TradingVolumeStatus.INACTIVE) {      
      return null;
    }

    return trdVl?.startDate;
  }

  public async getTotalInUseReward(userId: number) {
    // get total IN_USE rewardBalance
    const totalRwQb = this.userRewardFutureEventRepoReport
      .createQueryBuilder("rw")
      .select([`coalesce(sum(amount), 0) totalRw`])
      .where("userId = :userId", { userId })
      .andWhere(`status = '${RewardStatus.IN_USE}'`)
      .andWhere(`createdAt > '2025-07-23 04:30:00'`)

    const { totalRw } = await totalRwQb.getRawOne();

    return totalRw;
  }

  public async getTargetTradingVolume(userId: number) {
    const trdVl = await this.tradingVolumeSessionRepoReport
    .createQueryBuilder("session")
    .where("session.userId = :userId", { userId })
    .getOne();

    return trdVl.targetTradingVolume || '0';
  }

  public async getUserTradingVolumeFromDate(userId: number, fromDate: Date) {
    // get user trading volume from date rewardBalance
    const currentTradingVolumeQb = this.tradeRepoReport
      .createQueryBuilder("t")
      .select([`coalesce(sum(price * quantity), 0) currentTradingVolume`])
      .where("createdAt >= :fromDate", { fromDate: fromDate.toISOString() })
      .andWhere(`(sellUserId = :userId or buyUserId = :userId)`, { userId });

    const { currentTradingVolume } = await currentTradingVolumeQb.getRawOne(); 

    return currentTradingVolume;
  }

  public async getTotalPositivePnlFromDate(userId: number, fromDate: Date) {
    const positivePnlQb = this.transactionRepoReport
      .createQueryBuilder("t")
      .select([`coalesce(sum(amount), 0) positivePnl`])
      .andWhere(`userId = :userId`, { userId })
      .andWhere("type = :type", { type: TransactionType.REALIZED_PNL })
      .andWhere("amount > 0")
      .andWhere("createdAt >= :fromDate", { fromDate: fromDate.toISOString() });

    const { positivePnl } = await positivePnlQb.getRawOne(); 

    return positivePnl;
  }

  public async getTotalLossFromDate(userId: number, fromDate: Date) {
    const positivePnlQb = this.transactionRepoReport
      .createQueryBuilder("t")
      .select([`
        COALESCE(abs(sum(CASE when type = 'FUNDING_FEE' and amount < 0 and status ='approved' then amount end)), 0) +
        COALESCE(sum(CASE when type = 'TRADING_FEE' and status ='approved' then amount end), 0) + 
        COALESCE(abs(sum(CASE when type = 'REALIZED_PNL' and amount < 0 and status = 'approved' then amount end)), 0) +
        COALESCE(abs(sum(CASE when type = 'LIQUIDATION_CLEARANCE' and status = 'confirmed' then amount end)), 0) +
        COALESCE(abs(sum(CASE when type = 'MARGIN_INSURANCE_FEE' and status = 'confirmed' then amount end)), 0) totalLoss
      `])
      .andWhere(`userId = :userId`, { userId })
      .andWhere("createdAt >= :fromDate", { fromDate: fromDate.toISOString() });

    const { totalLoss } = await positivePnlQb.getRawOne(); 

    return totalLoss;
  }

  public async getTotalUsedRwblFromDate(userId: number, fromDate: Date) {
    const positivePnlQb = this.userRewardFutureEventUsedRepoReport
      .createQueryBuilder("usedRw")
      .select([`COALESCE (sum(amount), 0) usedRwbl`])
      .andWhere(`userId = :userId`, { userId })
      .andWhere("dateUsed >= :fromDate", { fromDate: fromDate.toISOString() });

    const { usedRwbl } = await positivePnlQb.getRawOne(); 

    return usedRwbl;
  }

  async getUserCurrentAndTargetTradingVolume(userId: number) {
    const firstDateInUseRw = await this.getFirstDateInUseReward(userId);
    if (!firstDateInUseRw) {
      return {
        curentTradingVolume: '0',
        targetTradingVoume: '0'
      }
    }

    const [curentTradingVolume, targetTradingVolume] = await Promise.all([
      this.getUserTradingVolumeFromDate(userId, firstDateInUseRw),
      this.getTargetTradingVolume(userId),
    ]);

    return {
      curentTradingVolume,
      targetTradingVolume
    }
  }

  public async updateUserUsedRewardBalanceDetail(userRewardUsed: UserRewardFutureEventUsedEntity) {
    const { amount, id, userId, transactionUuid, symbol, transactionType, dateUsed } = userRewardUsed
    const rewards = await this.userRewardFutureEventRepoMaster.createQueryBuilder('ur')
    .andWhere(`userId = :userId`, { userId })
    // .andWhere(`status = :status`, { status: RewardStatus.IN_USE })
    .andWhere(`remaining > 0`)
    .orderBy('ur.expiredDate', 'ASC')
    .addOrderBy('ur.id', 'ASC')
    .getMany();

    console.log(`rewards: ${JSON.stringify(rewards)}`);
    if(!rewards.length) return;

    const updatedRewards = [];
    const usedDetailRewards = [];
    let remainingAmount = amount;
    for (const reward of rewards) {
      // if remainingAmount = 0 => no used amount
      if (new BigNumber(remainingAmount).eq("0")) {
        break;
      }
      
      const rewardAmountBefore = reward.remaining;
      const rewardAmountAfter = this.bigNumberGreater(new BigNumber(rewardAmountBefore).minus(remainingAmount).toString(), "0");
      reward.remaining = rewardAmountAfter;
      reward.updatedAt = new Date();
      if (new BigNumber(reward.remaining).eq('0')) {
        reward.status = RewardStatus.FULLY_USED;
      }
      updatedRewards.push(reward);
      
      const userRewardUsedDetail = new UserRewardFutureEventUsedDetailEntity();
      userRewardUsedDetail.userId = userId;
      userRewardUsedDetail.transactionUuid = transactionUuid;
      userRewardUsedDetail.amount = new BigNumber(rewardAmountAfter).eq(0) ? rewardAmountBefore : remainingAmount;
      userRewardUsedDetail.dateUsed = dateUsed;
      userRewardUsedDetail.symbol = symbol;
      userRewardUsedDetail.transactionType = transactionType;
      userRewardUsedDetail.rewardId = reward.id;
      userRewardUsedDetail.rewardUsedId = id;
      userRewardUsedDetail.rewardAmountBefore = rewardAmountBefore;
      userRewardUsedDetail.rewardAmountAfter = reward.remaining;
      remainingAmount = new BigNumber(rewardAmountAfter).eq("0") ? new BigNumber(remainingAmount).minus(rewardAmountBefore).toString() : "0";

      usedDetailRewards.push(userRewardUsedDetail);
    }
    
    // update list changed voucher
    if (updatedRewards.length) {
      console.log(`updatedRewards: ${JSON.stringify(updatedRewards)}`);
      await this.userRewardFutureEventRepoMaster.save(updatedRewards);
    }
    if (usedDetailRewards.length) {
      console.log(`usedDetailRewards: ${JSON.stringify(usedDetailRewards)}`);
      
      this.userRewardFutureEventUsedDetailRepoMaster.save(usedDetailRewards);
    }
  }

  private bigNumberGreater(a: string, b: string) {
    const numA = new BigNumber(a);
    const numB = new BigNumber(b);
    return numA.isGreaterThan(numB) ? a : b;
  }

}

// import { Injectable } from "@nestjs/common";
// import { InjectRepository } from "@nestjs/typeorm";
// import BigNumber from "bignumber.js";
// import * as moment from "moment";
// import { TradeEntity } from "src/models/entities/trade.entity";
// import { TradingVolumeSessionLogEntity } from "src/models/entities/trading-volume-session-log.entity";
// import { TradingVolumeSessionEntity } from "src/models/entities/trading-volume-session.entity";
// import { TransactionEntity } from "src/models/entities/transaction.entity";
// import { UserRewardFutureEventUsedEntity } from "src/models/entities/user-reward-future-event-used.entity";
// import { UserRewardFutureEventEntity } from "src/models/entities/user-reward-future-event.entity";
// import { TradingVolumeSessionLogRepository } from "src/models/repositories/trading-volume-session-log.repository";
// import { TradingVolumeSessionRepository } from "src/models/repositories/trading-volume-session.repository";
// import { convertDateFields } from "src/modules/matching-engine/helper";
// import { TransactionType } from "src/shares/enums/transaction.enum";
// import { v4 as uuidv4 } from "uuid";
// import { TradingVolumeMsgType } from "../constants/trading-volume-msg-type.enum";
// import { TradingVolumeStatus } from "../constants/trading-volume-status.enum";
// import { TradingVolumeLogType } from "../constants/trading-volume-log-type.enum";

// export interface UpdateTrdVolumeMsgInterface {
//   type: TradingVolumeMsgType;
//   userId: number;
//   data: any;
//   createdAt: string;
// }

// @Injectable()
// export class UpdateTradingVolumeUseCase {
//   constructor(
//     @InjectRepository(TradingVolumeSessionRepository, "master")
//     private readonly tradingVolumeRepoMaster: TradingVolumeSessionRepository,

//     @InjectRepository(TradingVolumeSessionLogRepository, "master")
//     private readonly tradingVolumeLogRepoMaster: TradingVolumeSessionLogRepository
//   ) {}

//   public async execute(msg: UpdateTrdVolumeMsgInterface) {
//     const trdVl = await this.getOrCreateTrdVlSession(msg.userId);
//     if (msg.type !== TradingVolumeMsgType.NEW_REWARD && trdVl.status === TradingVolumeStatus.INACTIVE) {
//       return;
//     }

//     const trVlBefore = this.cloneObj(trdVl);

//     let metResetTradingVolumeRequirement: boolean;
//     let refId: string;
//     switch (msg.type) {
//       case TradingVolumeMsgType.NEW_REWARD:
//         const newReward = convertDateFields(new UserRewardFutureEventEntity(), msg.data);
//         this.processNewReward(trdVl, newReward);
//         refId = newReward.id.toString();
//         break;
//       case TradingVolumeMsgType.TRADE:
//         const newTrade = convertDateFields(new TradeEntity(), msg.data);
//         metResetTradingVolumeRequirement = this.processNewTrade(trdVl, newTrade);
//         refId = newTrade.id.toString();
//         break;
//       case TradingVolumeMsgType.TRANSACTION:
//         const newTransaction = convertDateFields(new TransactionEntity(), msg.data);
//         this.processNewTransaction(trdVl, newTransaction);
//         refId = newTransaction.uuid;
//         break;
//       case TradingVolumeMsgType.USED_REWARD:
//         const usedReward = convertDateFields(new UserRewardFutureEventUsedEntity(), msg.data);
//         metResetTradingVolumeRequirement = this.processNewUsedReward(trdVl, usedReward);
//         refId = usedReward.id.toString();
//         break;
//     }

//     const log = this.createSessionLog(trVlBefore, trdVl, msg.type, refId);

//     // return null if nothing change
//     if (!log) return;

//     // Use transaction to ensure data consistency
//     const queryRunner = this.tradingVolumeRepoMaster.manager.connection.createQueryRunner();
//     await queryRunner.connect();
//     await queryRunner.startTransaction();

//     try {
//       // check met requirement to reset trading volume
//       if (metResetTradingVolumeRequirement) {
//         const trVlBeforeReset = this.cloneObj(trdVl);
//         this.resetTrdVlToDefault(trdVl);
//         const resetLog = this.createSessionLog(trVlBeforeReset, trdVl, TradingVolumeLogType.END_SESSION);
        
//         // Save reset log within transaction
//         await queryRunner.manager.save(TradingVolumeSessionLogEntity, resetLog);
//       }

//       // Save main log and trading volume session within transaction
//       await queryRunner.manager.save(TradingVolumeSessionLogEntity, log);
//       await queryRunner.manager.save(TradingVolumeSessionEntity, trdVl);

//       // Commit transaction
//       await queryRunner.commitTransaction();
//     } catch (error) {
//       // Rollback transaction on error
//       await queryRunner.rollbackTransaction();
//       throw error;
//     } finally {
//       // Release query runner
//       await queryRunner.release();
//     }
//   }

//   /**
//    * Get or create a trading volume record for a user
//    * Each user will have only one trading volume record
//    */
//   public async getOrCreateTrdVlSession(userId: number): Promise<TradingVolumeSessionEntity> {
//     // Try to find existing trading volume record for the user
//     let tradingVolume = await this.tradingVolumeRepoMaster.findOne({
//       where: { userId },
//     });

//     // If no record exists, create a new one
//     if (!tradingVolume) {
//       tradingVolume = new TradingVolumeSessionEntity();
//       tradingVolume.userId = userId;
//       tradingVolume.totalReward = "0";
//       tradingVolume.currentTradingVolume = "0";
//       tradingVolume.profit = "0";
//       tradingVolume.totalLoss = "0";
//       tradingVolume.totalUsedReward = "0";
//       tradingVolume.targetTradingVolume = "0";
//       tradingVolume.status = TradingVolumeStatus.INACTIVE;

//       // Save the new record
//       tradingVolume = await this.tradingVolumeRepoMaster.save(tradingVolume);
//     }

//     return tradingVolume;
//   }

//   /**
//    * Generate a unique session UUID
//    */
//   private generateSessionUUID(): string {
//     return `session_${Date.now()}_${uuidv4()}`;
//   }

//   private processNewReward(trdVl: TradingVolumeSessionEntity, newReward: UserRewardFutureEventEntity) {
//     if (trdVl.status === TradingVolumeStatus.INACTIVE) {
//       trdVl.startDate = newReward.createdAt;
//       trdVl.status = TradingVolumeStatus.ACTIVE;
//       trdVl.sessionUUID = this.generateSessionUUID();
//     }

//     trdVl.totalReward = new BigNumber(newReward.amount).plus(trdVl.totalReward).toString();
//     trdVl.targetTradingVolume = new BigNumber(newReward.amount).multipliedBy(7000).plus(trdVl.targetTradingVolume).toString();
//   }

//   private processNewTrade(trdVl: TradingVolumeSessionEntity, newTrade: TradeEntity) {
//     if (moment(newTrade.createdAt).isBefore(trdVl.startDate)) {
//       return;
//     }
//     const { price, quantity } = newTrade;
//     if (trdVl.userId === newTrade.buyUserId) {
//       trdVl.currentTradingVolume = new BigNumber(trdVl.currentTradingVolume).plus(new BigNumber(quantity).multipliedBy(price)).toString();
//     }

//     if (trdVl.userId === newTrade.sellUserId) {
//       trdVl.currentTradingVolume = new BigNumber(trdVl.currentTradingVolume).plus(new BigNumber(quantity).multipliedBy(price)).toString();
//     }

//     // current >= target trdvl
//     if (new BigNumber(trdVl.currentTradingVolume).gte(trdVl.targetTradingVolume)) {
//       return true;
//     }

//     return false;
//   }

//   private processNewTransaction(trdVl: TradingVolumeSessionEntity, newTransaction: TransactionEntity) {
//     const { type, amount } = newTransaction;

//     const usedRewardTransactionTypes = ["FUNDING_FEE", "TRADING_FEE", "REALIZED_PNL", "LIQUIDATION_CLEARANCE", "MARGIN_INSURANCE_FEE"];
//     if (!usedRewardTransactionTypes.includes(type)) return;

//     if (type === TransactionType.REALIZED_PNL && new BigNumber(amount).gt("0")) {
//       trdVl.profit = new BigNumber(trdVl.profit).plus(amount).toString();
//       return;
//     }

//     if (type === TransactionType.FUNDING_FEE && new BigNumber(amount).gt("0")) {
//       return;
//     }

//     trdVl.totalLoss = new BigNumber(amount).abs().plus(trdVl.totalLoss).toString();
//   }

//   private processNewUsedReward(trdVl: TradingVolumeSessionEntity, usedReward: UserRewardFutureEventUsedEntity) {
//     trdVl.totalUsedReward += usedReward.amount;

//     // profitLocked <= 0 && rewardBalance = 0
//     const noProfitLockedAndNoRwbl =
//       new BigNumber(this.calcLockedProfit(trdVl)).lte("0") && new BigNumber(usedReward.remainingRewardBalance).eq("0");

//     if (noProfitLockedAndNoRwbl) {
//       return true;
//     }

//     return false;
//   }

//   private resetTrdVlToDefault(trdVl: TradingVolumeSessionEntity) {
//     // reset trading volume to default values
//     trdVl.currentTradingVolume = "0";
//     trdVl.profit = "0";
//     trdVl.totalLoss = "0";
//     trdVl.totalUsedReward = "0";
//     trdVl.totalReward = "0";
//     trdVl.targetTradingVolume = "0";
//     trdVl.status = TradingVolumeStatus.INACTIVE;
//     trdVl.startDate = null;
//     trdVl.sessionUUID = null;
//   }

//   // profit locked = profit (pnl > 0) - (totalLoss (all using used rwbl) - used_rwbl)
//   private calcLockedProfit(trdVl: TradingVolumeSessionEntity) {
//     return new BigNumber(trdVl.profit).minus(new BigNumber(trdVl.totalLoss).minus(trdVl.totalUsedReward)).toString();
//   }

//   private createSessionLog(
//     trdVlBefore: TradingVolumeSessionEntity,
//     trdVlAfter: TradingVolumeSessionEntity,
//     typeChange: TradingVolumeMsgType | TradingVolumeLogType,
//     refId?: string
//   ) {
//     const log = new TradingVolumeSessionLogEntity();

//     log.refId = refId;
//     log.sessionUUID = trdVlBefore.sessionUUID || trdVlAfter.sessionUUID;
//     log.userId = trdVlBefore.userId || trdVlAfter.userId;
//     log.startDate = trdVlBefore.startDate || trdVlAfter.startDate;

//     // totalRewardAmount before/after
//     log.totalRewardAmountBefore = trdVlBefore.totalReward;
//     log.totalRewardAmountAfter = trdVlAfter.totalReward;

//     // currentTradingVolume before/after
//     log.currentTradingVolumeBefore = trdVlBefore.currentTradingVolume;
//     log.currentTradingVolumeAfter = trdVlAfter.currentTradingVolume;

//     // profit before/after
//     log.profitBefore = trdVlBefore.profit;
//     log.profitAfter = trdVlAfter.profit;

//     // totalLoss before/after
//     log.totalLossBefore = trdVlBefore.totalLoss;
//     log.totalLossAfter = trdVlAfter.totalLoss;

//     // usedRewardAmount before/after
//     log.usedRewardAmountBefore = trdVlBefore.totalUsedReward;
//     log.usedRewardAmountAfter = trdVlAfter.totalUsedReward;

//     // targetTradingVolume before/after
//     log.targetTradingVolumeBefore = trdVlBefore.targetTradingVolume;
//     log.targetTradingVolumeAfter = trdVlAfter.targetTradingVolume;
//     // Generate change description
//     log.changeDescription = this.generateChangeDescription(trdVlBefore, trdVlAfter);

//     log.typeChange = typeChange;

//     if (!log.changeDescription) {
//       return null;
//     }

//     return log;
//   }

//   /**
//    * Generate a human-readable description of the changes
//    */
//   private generateChangeDescription(trdVlBefore: TradingVolumeSessionEntity, trdVlAfter: TradingVolumeSessionEntity): string {
//     const changes: string[] = [];

//     // Check for changes in each field
//     if (trdVlBefore.totalReward !== trdVlAfter.totalReward) {
//       const change = new BigNumber(trdVlAfter.totalReward).minus(trdVlBefore.totalReward);
//       const direction = change.gte(0) ? "increased" : "decreased";
//       changes.push(`Total reward amount ${direction} from ${trdVlBefore.totalReward} to ${trdVlAfter.totalReward}`);
//     }

//     if (trdVlBefore.currentTradingVolume !== trdVlAfter.currentTradingVolume) {
//       const change = new BigNumber(trdVlAfter.currentTradingVolume).minus(trdVlBefore.currentTradingVolume);
//       const direction = change.gte(0) ? "increased" : "decreased";
//       changes.push(`Trading volume ${direction} from ${trdVlBefore.currentTradingVolume} to ${trdVlAfter.currentTradingVolume}`);
//     }

//     if (trdVlBefore.profit !== trdVlAfter.profit) {
//       const change = new BigNumber(trdVlAfter.profit).minus(trdVlBefore.profit);
//       const direction = change.gte(0) ? "increased" : "decreased";
//       changes.push(`Profit ${direction} from ${trdVlBefore.profit} to ${trdVlAfter.profit}`);
//     }

//     if (trdVlBefore.totalLoss !== trdVlAfter.totalLoss) {
//       const change = new BigNumber(trdVlAfter.totalLoss).minus(trdVlBefore.totalLoss);
//       const direction = change.gte(0) ? "increased" : "decreased";
//       changes.push(`Total loss ${direction} from ${trdVlBefore.totalLoss} to ${trdVlAfter.totalLoss}`);
//     }

//     if (trdVlBefore.totalUsedReward !== trdVlAfter.totalUsedReward) {
//       const change = new BigNumber(trdVlAfter.totalUsedReward).minus(trdVlBefore.totalUsedReward);
//       const direction = change.gte(0) ? "increased" : "decreased";
//       changes.push(`Used reward amount ${direction} from ${trdVlBefore.totalUsedReward} to ${trdVlAfter.totalUsedReward}`);
//     }

//     if (trdVlBefore.targetTradingVolume !== trdVlAfter.targetTradingVolume) {
//       const change = new BigNumber(trdVlAfter.targetTradingVolume).minus(trdVlBefore.targetTradingVolume);
//       const direction = change.gte(0) ? "increased" : "decreased";
//       changes.push(`Target trading volume ${direction} from ${trdVlBefore.targetTradingVolume} to ${trdVlAfter.targetTradingVolume}`);
//     }

//     if (trdVlBefore.status !== trdVlAfter.status) {
//       changes.push(`Status changed from ${trdVlBefore.status} to ${trdVlAfter.status}`);
//     }

//     return changes.join("; ");
//   }

//   private cloneObj(obj: any) {
//     return JSON.parse(JSON.stringify(obj));
//   }
// }

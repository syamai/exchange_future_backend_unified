import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { EventSettingV2Entity, EventStatusV2 } from "src/models/entities/event-setting-v2.entity";
import { UserBonusV2Entity, BonusStatusV2 } from "src/models/entities/user-bonus-v2.entity";
import { UserBonusV2HistoryEntity, BonusChangeType } from "src/models/entities/user-bonus-v2-history.entity";
import { EventSettingV2Repository } from "src/models/repositories/event-setting-v2.repository";
import { UserBonusV2Repository } from "src/models/repositories/user-bonus-v2.repository";
import { UserBonusV2HistoryRepository } from "src/models/repositories/user-bonus-v2-history.repository";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { getQueryLimit } from "src/shares/pagination-util";
import { LessThanOrEqual, MoreThanOrEqual } from "typeorm";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { FutureEventV2KafkaTopic } from "src/shares/enums/kafka.enum";
import { CreateEventSettingV2Dto } from "./dto/create-event-setting-v2.dto";
import { UpdateEventSettingV2Dto } from "./dto/update-event-setting-v2.dto";
import { GrantBonusV2Dto } from "./dto/grant-bonus-v2.dto";
import { AdminBonusV2QueryDto } from "./dto/admin-bonus-v2-query.dto";

@Injectable()
export class FutureEventV2Service {
  private readonly logger = new Logger(FutureEventV2Service.name);

  constructor(
    @InjectRepository(EventSettingV2Repository, "master")
    private readonly eventSettingRepoMaster: EventSettingV2Repository,

    @InjectRepository(EventSettingV2Repository, "report")
    private readonly eventSettingRepoReport: EventSettingV2Repository,

    @InjectRepository(UserBonusV2Repository, "master")
    private readonly userBonusRepoMaster: UserBonusV2Repository,

    @InjectRepository(UserBonusV2Repository, "report")
    private readonly userBonusRepoReport: UserBonusV2Repository,

    @InjectRepository(UserBonusV2HistoryRepository, "master")
    private readonly userBonusHistoryRepoMaster: UserBonusV2HistoryRepository,

    @InjectRepository(UserBonusV2HistoryRepository, "report")
    private readonly userBonusHistoryRepoReport: UserBonusV2HistoryRepository,

    private readonly kafkaClient: KafkaClient
  ) {}

  // ===== Event Setting Management =====

  async createEventSetting(dto: CreateEventSettingV2Dto): Promise<EventSettingV2Entity> {
    const entity = new EventSettingV2Entity();
    entity.eventName = dto.eventName;
    entity.eventCode = dto.eventCode;
    entity.bonusRatePercent = dto.bonusRatePercent;
    entity.minDepositAmount = dto.minDepositAmount || "0";
    entity.maxBonusAmount = dto.maxBonusAmount || "0";
    entity.startDate = new Date(dto.startDate);
    entity.endDate = new Date(dto.endDate);
    entity.status = EventStatusV2.INACTIVE;

    return await this.eventSettingRepoMaster.save(entity);
  }

  async updateEventSetting(id: number, dto: UpdateEventSettingV2Dto): Promise<EventSettingV2Entity> {
    const entity = await this.eventSettingRepoMaster.findOne({ where: { id } });
    if (!entity) {
      throw new Error(`Event setting with id ${id} not found`);
    }

    if (dto.eventName) entity.eventName = dto.eventName;
    if (dto.status) entity.status = dto.status;
    if (dto.bonusRatePercent) entity.bonusRatePercent = dto.bonusRatePercent;
    if (dto.minDepositAmount) entity.minDepositAmount = dto.minDepositAmount;
    if (dto.maxBonusAmount) entity.maxBonusAmount = dto.maxBonusAmount;
    if (dto.startDate) entity.startDate = new Date(dto.startDate);
    if (dto.endDate) entity.endDate = new Date(dto.endDate);

    return await this.eventSettingRepoMaster.save(entity);
  }

  async toggleEventStatus(id: number): Promise<EventSettingV2Entity> {
    const entity = await this.eventSettingRepoMaster.findOne({ where: { id } });
    if (!entity) {
      throw new Error(`Event setting with id ${id} not found`);
    }

    entity.status = entity.status === EventStatusV2.ACTIVE ? EventStatusV2.INACTIVE : EventStatusV2.ACTIVE;
    return await this.eventSettingRepoMaster.save(entity);
  }

  async getEventSettings(): Promise<EventSettingV2Entity[]> {
    return await this.eventSettingRepoReport.find({ order: { id: "DESC" } });
  }

  async getEventSettingById(id: number): Promise<EventSettingV2Entity | null> {
    return await this.eventSettingRepoReport.findOne({ where: { id } });
  }

  async getActiveEventSettings(): Promise<EventSettingV2Entity[]> {
    const now = new Date();
    return await this.eventSettingRepoReport.find({
      where: {
        status: EventStatusV2.ACTIVE,
        startDate: LessThanOrEqual(now),
        endDate: MoreThanOrEqual(now),
      },
    });
  }

  async isEventActive(eventCode: string): Promise<boolean> {
    const now = new Date();
    const event = await this.eventSettingRepoReport.findOne({
      where: {
        eventCode,
        status: EventStatusV2.ACTIVE,
        startDate: LessThanOrEqual(now),
        endDate: MoreThanOrEqual(now),
      },
    });
    return !!event;
  }

  // ===== Deposit Processing (Auto Grant) =====

  async processDeposit(transaction: TransactionEntity): Promise<UserBonusV2Entity | null> {
    const activeEvents = await this.getActiveEventSettings();
    if (activeEvents.length === 0) {
      this.logger.log("No active events found for deposit processing");
      return null;
    }

    // Use the first active event (can be extended for multiple event support)
    const eventSetting = activeEvents[0];

    const isEligible = await this.checkDepositEligibility(
      transaction.userId,
      transaction.amount,
      eventSetting
    );

    if (!isEligible) {
      this.logger.log(`User ${transaction.userId} is not eligible for bonus`);
      return null;
    }

    const bonusAmount = this.calculateBonusAmount(transaction.amount, eventSetting);

    const dto: GrantBonusV2Dto = {
      userId: transaction.userId,
      accountId: transaction.accountId,
      eventSettingId: eventSetting.id,
      depositAmount: transaction.amount,
      bonusAmount,
      transactionId: transaction.id,
    };

    return await this.grantBonus(dto);
  }

  async checkDepositEligibility(
    userId: number,
    depositAmount: string,
    eventSetting: EventSettingV2Entity
  ): Promise<boolean> {
    // Check minimum deposit amount
    if (new BigNumber(depositAmount).lt(eventSetting.minDepositAmount)) {
      return false;
    }

    // Additional eligibility checks can be added here
    return true;
  }

  calculateBonusAmount(depositAmount: string, eventSetting: EventSettingV2Entity): string {
    const bonus = new BigNumber(depositAmount)
      .multipliedBy(eventSetting.bonusRatePercent)
      .dividedBy(100);

    // Apply max bonus limit if set
    if (
      eventSetting.maxBonusAmount &&
      new BigNumber(eventSetting.maxBonusAmount).gt(0) &&
      bonus.gt(eventSetting.maxBonusAmount)
    ) {
      return eventSetting.maxBonusAmount;
    }

    return bonus.toString();
  }

  // ===== Manual Bonus Grant (Admin) =====

  async grantBonus(dto: GrantBonusV2Dto): Promise<UserBonusV2Entity> {
    const eventSetting = await this.eventSettingRepoMaster.findOne({
      where: { id: dto.eventSettingId },
    });

    if (!eventSetting) {
      throw new Error(`Event setting with id ${dto.eventSettingId} not found`);
    }

    const bonusAmount = dto.bonusAmount || this.calculateBonusAmount(dto.depositAmount, eventSetting);

    const bonus = new UserBonusV2Entity();
    bonus.userId = dto.userId;
    bonus.accountId = dto.accountId;
    bonus.eventSettingId = dto.eventSettingId;
    bonus.transactionId = dto.transactionId || 0;
    bonus.bonusAmount = bonusAmount;
    bonus.originalDeposit = dto.depositAmount;
    bonus.currentPrincipal = dto.depositAmount;
    bonus.status = BonusStatusV2.ACTIVE;
    bonus.grantedAt = new Date();

    const savedBonus = await this.userBonusRepoMaster.save(bonus);

    // Record history
    await this.recordHistory(
      savedBonus.id,
      dto.userId,
      BonusChangeType.GRANT,
      dto.depositAmount,
      "0",
      dto.depositAmount,
      null,
      `Bonus granted: ${bonusAmount} for deposit: ${dto.depositAmount}`
    );

    this.logger.log(`Bonus granted to user ${dto.userId}: ${bonusAmount}`);
    return savedBonus;
  }

  // ===== Bonus Query =====

  async getUserBonuses(userId: number): Promise<UserBonusV2Entity[]> {
    return await this.userBonusRepoReport.find({
      where: { userId },
      order: { id: "DESC" },
    });
  }

  async getActiveBonusByAccountId(accountId: number): Promise<UserBonusV2Entity | null> {
    return await this.userBonusRepoReport.findOne({
      where: { accountId, status: BonusStatusV2.ACTIVE },
    });
  }

  async getBonusesWithPagination(
    filters: AdminBonusV2QueryDto,
    pagination: PaginationDto
  ): Promise<[UserBonusV2Entity[], number]> {
    const { offset, limit } = getQueryLimit(pagination);

    const queryBuilder = this.userBonusRepoReport.createQueryBuilder("bonus");

    if (filters.userId) {
      queryBuilder.andWhere("bonus.userId = :userId", { userId: filters.userId });
    }
    if (filters.accountId) {
      queryBuilder.andWhere("bonus.accountId = :accountId", { accountId: filters.accountId });
    }
    if (filters.eventSettingId) {
      queryBuilder.andWhere("bonus.eventSettingId = :eventSettingId", {
        eventSettingId: filters.eventSettingId,
      });
    }
    if (filters.status) {
      queryBuilder.andWhere("bonus.status = :status", { status: filters.status });
    }
    if (filters.startDate) {
      queryBuilder.andWhere("bonus.grantedAt >= :startDate", { startDate: filters.startDate });
    }
    if (filters.endDate) {
      queryBuilder.andWhere("bonus.grantedAt <= :endDate", { endDate: filters.endDate });
    }

    const [bonuses, total] = await queryBuilder
      .orderBy("bonus.id", "DESC")
      .skip(offset)
      .take(limit)
      .getManyAndCount();

    return [bonuses, total];
  }

  async getBonusHistory(bonusId: number): Promise<UserBonusV2HistoryEntity[]> {
    return await this.userBonusHistoryRepoReport.find({
      where: { userBonusId: bonusId },
      order: { id: "DESC" },
    });
  }

  // ===== Principal Deduction (Core Logic - TODO: Full Implementation) =====

  async deductFromPrincipal(
    accountId: number,
    amount: string,
    changeType: string,
    transactionUuid?: string
  ): Promise<void> {
    const bonus = await this.getActiveBonusByAccountId(accountId);
    if (!bonus) {
      return; // No active bonus for this account
    }

    const principalBefore = bonus.currentPrincipal;
    const newPrincipal = new BigNumber(principalBefore).minus(amount);

    bonus.currentPrincipal = newPrincipal.toString();

    // Check if liquidation should be triggered
    if (newPrincipal.lte(0)) {
      await this.handleLiquidation(accountId);
      return;
    }

    await this.userBonusRepoMaster.save(bonus);

    await this.recordHistory(
      bonus.id,
      bonus.userId,
      changeType,
      `-${amount}`,
      principalBefore,
      bonus.currentPrincipal,
      transactionUuid,
      `Principal deducted: ${amount}`
    );

    this.logger.log(`Principal deducted for account ${accountId}: ${amount}`);
  }

  // ===== Liquidation Processing =====

  async handleLiquidation(accountId: number): Promise<void> {
    const bonus = await this.userBonusRepoMaster.findOne({
      where: { accountId, status: BonusStatusV2.ACTIVE },
    });

    if (!bonus) {
      return;
    }

    const principalBefore = bonus.currentPrincipal;

    bonus.status = BonusStatusV2.LIQUIDATED;
    bonus.currentPrincipal = "0";
    bonus.liquidatedAt = new Date();

    await this.userBonusRepoMaster.save(bonus);

    await this.recordHistory(
      bonus.id,
      bonus.userId,
      BonusChangeType.LIQUIDATION,
      `-${principalBefore}`,
      principalBefore,
      "0",
      null,
      "Liquidated: principal depleted"
    );

    this.logger.log(`Bonus liquidated for account ${accountId}`);

    // Send liquidation trigger to Kafka for matching engine to process
    await this.kafkaClient.send(FutureEventV2KafkaTopic.future_event_v2_liquidation_trigger, {
      accountId,
      userId: bonus.userId,
      bonusId: bonus.id,
      reason: "PRINCIPAL_DEPLETED",
      liquidatedAt: bonus.liquidatedAt,
    });

    this.logger.log(`Liquidation trigger sent for account ${accountId}`);
  }

  async checkAndTriggerLiquidation(accountId: number): Promise<boolean> {
    const bonus = await this.getActiveBonusByAccountId(accountId);
    if (!bonus) {
      return false;
    }

    if (new BigNumber(bonus.currentPrincipal).lte(0)) {
      await this.handleLiquidation(accountId);
      return true;
    }

    return false;
  }

  // ===== Bonus Revocation =====

  async revokeBonus(bonusId: number, reason: string): Promise<void> {
    const bonus = await this.userBonusRepoMaster.findOne({ where: { id: bonusId } });
    if (!bonus) {
      throw new Error(`Bonus with id ${bonusId} not found`);
    }

    const principalBefore = bonus.currentPrincipal;

    bonus.status = BonusStatusV2.REVOKED;
    bonus.currentPrincipal = "0";

    await this.userBonusRepoMaster.save(bonus);

    await this.recordHistory(
      bonus.id,
      bonus.userId,
      BonusChangeType.REVOKE,
      `-${principalBefore}`,
      principalBefore,
      "0",
      null,
      `Revoked: ${reason}`
    );

    this.logger.log(`Bonus ${bonusId} revoked: ${reason}`);
  }

  // ===== Helper Methods =====

  private async recordHistory(
    userBonusId: number,
    userId: number,
    changeType: string,
    changeAmount: string,
    principalBefore: string,
    principalAfter: string,
    transactionUuid: string | null,
    description: string
  ): Promise<void> {
    const history = new UserBonusV2HistoryEntity();
    history.userBonusId = userBonusId;
    history.userId = userId;
    history.changeType = changeType;
    history.changeAmount = changeAmount;
    history.principalBefore = principalBefore;
    history.principalAfter = principalAfter;
    history.transactionUuid = transactionUuid;
    history.description = description;

    await this.userBonusHistoryRepoMaster.save(history);
  }
}

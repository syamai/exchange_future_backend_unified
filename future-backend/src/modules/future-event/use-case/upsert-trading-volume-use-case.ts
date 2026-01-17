import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { TradingVolumeSessionEntity } from "src/models/entities/trading-volume-session.entity";
import { UserRewardFutureEventEntity } from "src/models/entities/user-reward-future-event.entity";
import { TradingVolumeSessionRepository } from "src/models/repositories/trading-volume-session.repository";
import { TradingVolumeStatus } from "../constants/trading-volume-status.enum";
import { TradingVolumeService } from "../trading-volume.service";
import { UpdateTradingVolumeSessionUseCase } from "./update-trading-volume-session-use-case";

@Injectable()
export class UpsertTradingVolumeSessionUseCase {
  constructor(
    @InjectRepository(TradingVolumeSessionRepository, "master")
    private readonly tradingVolumeRepoMaster: TradingVolumeSessionRepository,

    private readonly tradingVolumeService: TradingVolumeService,

    private readonly updateTradingVolumeSessionUseCase: UpdateTradingVolumeSessionUseCase
  ) {}

  public async execute(reward: any) {
    // create session if not exist
    await this.tradingVolumeService.createTrdVlSessionIfNotExist(reward.userId);

    console.log(`Done create session if not exist`);
    
    // update trading volume session
    if (typeof reward.createdAt === 'number') {
      reward.createdAt = new Date(reward.createdAt).toISOString();
    }
    
    await this.updateTradingVolumeSessionUseCase.execute(reward.userId, reward.createdAt);

    console.log(`Done updateTradingVolumeSessionUseCase`);

    // get current trading volume session
    return await this.tradingVolumeRepoMaster.manager.transaction(async (manager) => {
      // Lock the row for update
      const trdVl = await manager
        .getRepository(TradingVolumeSessionEntity)
        .createQueryBuilder("session")
        .setLock("pessimistic_write")
        .where("session.userId = :userId", { userId: reward.userId })
        .getOne();

      console.log(`[UpsertTradingVolumeSessionUseCase][execute] - trdVl: ${JSON.stringify(trdVl)}`);
      

      // if status = inactive, create new session; status = active, update trading data to check met reset requirement
      switch (trdVl.status) {
        case TradingVolumeStatus.INACTIVE:
          await this.processNewTrdVlSession(trdVl, reward);
          break;
        case TradingVolumeStatus.ACTIVE:
          await this.updateTrdVlSession(trdVl, reward);
          break;
      }

      console.log(`[UpsertTradingVolumeSessionUseCase] trvl after: ${JSON.stringify(trdVl)}`);
      

      await manager.getRepository(TradingVolumeSessionEntity).save(trdVl);
      return trdVl;
    });
  }

  private async processNewTrdVlSession(trdVl: TradingVolumeSessionEntity, reward: UserRewardFutureEventEntity) {
    console.log(`[UpsertTradingVolumeSessionUseCase][processNewTrdVlSession]: processNewTrdVlSession - reward: ${JSON.stringify({id: reward.id, createdAt: reward.createdAt, userId: reward.userId})}`);
    
    trdVl.startDate = reward.createdAt;
    trdVl.sessionUUID = this.tradingVolumeService.generateSessionUUID();
    trdVl.userId = reward.userId;
    trdVl.status = TradingVolumeStatus.ACTIVE;
    trdVl.targetTradingVolume = this.calcTrdVlPerUsdtReward(reward.amount);
    trdVl.totalReward = new BigNumber(reward.amount).toString();
  }

  private async updateTrdVlSession(trdVl: TradingVolumeSessionEntity, reward: UserRewardFutureEventEntity) {
    console.log(`[UpsertTradingVolumeSessionUseCase][updateTrdVlSession]: processNewTrdVlSession - reward: ${JSON.stringify({id: reward.id, createdAt: reward.createdAt, userId: reward.userId})}`);

    // if status active: plus trading volume to amount trading volume
    trdVl.totalReward = new BigNumber(trdVl.totalReward).plus(reward.amount).toString();
    const trdVlAddOn = this.calcTrdVlPerUsdtReward(reward.amount);
    trdVl.targetTradingVolume = new BigNumber(trdVl.targetTradingVolume)
      .plus(trdVlAddOn)
      .toString();
  }

  private calcTrdVlPerUsdtReward(amount: string) {
    return new BigNumber(amount).multipliedBy(7500).toString()
  }
}

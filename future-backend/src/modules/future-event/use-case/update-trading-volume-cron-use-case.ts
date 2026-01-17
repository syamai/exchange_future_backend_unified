import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { UserRewardFutureEventUsedRepository } from "src/models/repositories/user-reward-future-event-used.repository";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { UpdateTradingVolumeSessionUseCase } from "./update-trading-volume-session-use-case";
import { TradingVolumeSessionRepository } from "src/models/repositories/trading-volume-session.repository";

@Injectable()
export class UpdateTradingVolumeCronUseCase {
  constructor(
    private readonly updateTradingVolumeSessionUseCase: UpdateTradingVolumeSessionUseCase,

    @InjectRepository(UserRewardFutureEventUsedRepository, "report")
    private readonly userRewardFutureEventUsedRepoReport: UserRewardFutureEventUsedRepository,

    @InjectRepository(TradingVolumeSessionRepository, "report")
    private readonly tradingVolumeSessionRepoReport: TradingVolumeSessionRepository,

    private readonly redisClient: RedisClient,
  ) {}

  public async execute() {
    while (true) {
      const redisClient = this.redisClient.getInstance();
      const lastSyncTimeKey = "last_sync_trading_volume_session";
      const lastSyncTime = new Date().getTime();
  
      try {
        const lastSyncTimeCache = await redisClient.get(lastSyncTimeKey);
  
        const userIds = await this.getUsersToUpdate(lastSyncTimeCache);

        for (const userId of userIds) {
          await this.updateTradingVolumeSessionUseCase.execute(userId, new Date().toISOString())
        }
  
        // set last sync time if success
        await redisClient.set(lastSyncTimeKey, lastSyncTime);
  
        console.log(`Update trading volume cron success, userIds: ${JSON.stringify(userIds)}, lastUpdatedTime: ${new Date(lastSyncTime).toISOString()}`);
        
      } catch (error) {
        console.error('Error while UpdateTradingVolumeCronUseCase: ', error);
      }
      // Sleep for 5 minutes
      await new Promise(resolve => setTimeout(resolve, 5 * 60 * 1000));
    }
  }

  private async getUsersToUpdate(lastSyncTime: string) {
    const usedRewards = await this.tradingVolumeSessionRepoReport
    .createQueryBuilder("ts")
    .select("userId", "userId")
    .getRawMany();

    return usedRewards.map(ur => ur.userId)
  }
}

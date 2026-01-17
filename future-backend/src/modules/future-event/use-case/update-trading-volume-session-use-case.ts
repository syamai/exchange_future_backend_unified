import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { TradingVolumeSessionEntity } from "src/models/entities/trading-volume-session.entity";
import { TradingVolumeSessionRepository } from "src/models/repositories/trading-volume-session.repository";
import { TradingVolumeStatus } from "../constants/trading-volume-status.enum";
import { TradingVolumeService } from "../trading-volume.service";

@Injectable()
export class UpdateTradingVolumeSessionUseCase {
  constructor(
    @InjectRepository(TradingVolumeSessionRepository, "master")
    private readonly tradingVolumeRepoMaster: TradingVolumeSessionRepository,

    private readonly tradingVolumeService: TradingVolumeService
  ) {}

  public async execute(userId: number, toDate: string | Date) {
    console.log(`toDate: ${JSON.stringify(toDate)}`);
    
    // get current trading volume session
    return await this.tradingVolumeRepoMaster.manager.transaction(async (manager) => {
      // Lock the row for update
      const trdVl = await manager
        .getRepository(TradingVolumeSessionEntity)
        .createQueryBuilder("session")
        .setLock("pessimistic_write")
        .where("session.userId = :userId", { userId })
        // .andWhere("updatedAt <= :toDate", { toDate })
        .getOne();

      if (!trdVl || trdVl.status === TradingVolumeStatus.INACTIVE) {
        console.log(`[UpdateTradingVolumeSessionUseCase] - execute: status is inactive, return`);
        
        return;
      }

      const updatedTrdVlData = await this.tradingVolumeService.getTradingVolumeData(userId, trdVl.startDate, toDate);

      console.log(`[UpdateTradingVolumeSessionUseCase] - updatedTrdVlData: ${JSON.stringify(updatedTrdVlData)}`);
      

      Object.assign(trdVl, updatedTrdVlData);

      await this.tradingVolumeService.processResetTrdVl(trdVl, toDate);

      await manager.getRepository(TradingVolumeSessionEntity).save(trdVl);
      return trdVl;
    });
  }
}

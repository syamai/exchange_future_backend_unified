import { EntityRepository, Repository } from "typeorm";
import { TradingVolumeSessionLogEntity } from "../entities/trading-volume-session-log.entity";

@EntityRepository(TradingVolumeSessionLogEntity)
export class TradingVolumeSessionLogRepository extends Repository<TradingVolumeSessionLogEntity> {}

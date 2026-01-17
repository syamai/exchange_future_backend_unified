import { EntityRepository, Repository } from "typeorm";
import { TradingVolumeSessionEntity } from "../entities/trading-volume-session.entity";

@EntityRepository(TradingVolumeSessionEntity)
export class TradingVolumeSessionRepository extends Repository<TradingVolumeSessionEntity> {}

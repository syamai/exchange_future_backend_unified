import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { MarketFeeEntity } from "../entities/market_fee.entity";

@EntityRepository(MarketFeeEntity)
export class MarketFeeRepository extends BaseRepository<MarketFeeEntity> {}

import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { TradingRulesEntity } from "../entities/trading_rules.entity";

@EntityRepository(TradingRulesEntity)
export class TradingRulesRepository extends BaseRepository<TradingRulesEntity> {}

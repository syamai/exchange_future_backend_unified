import { EntityRepository, Repository } from "typeorm";
import { MarketDataEntity } from "src/models/entities/market-data.entity";

@EntityRepository(MarketDataEntity)
export class MarketDataRepository extends Repository<MarketDataEntity> {}

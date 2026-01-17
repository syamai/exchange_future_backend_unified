import { EntityRepository, Repository } from "typeorm";
import { MarketIndexEntity } from "src/models/entities/market-index.entity";

@EntityRepository(MarketIndexEntity)
export class MarketIndexRepository extends Repository<MarketIndexEntity> {}

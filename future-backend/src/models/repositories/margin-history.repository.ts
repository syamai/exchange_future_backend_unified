import { MarginHistoryEntity } from "src/models/entities/margin-history";
import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";

@EntityRepository(MarginHistoryEntity)
export class MarginHistoryRepository extends BaseRepository<MarginHistoryEntity> {}

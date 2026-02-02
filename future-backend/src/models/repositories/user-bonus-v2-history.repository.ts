import { EntityRepository } from "typeorm";
import { UserBonusV2HistoryEntity } from "src/models/entities/user-bonus-v2-history.entity";
import { BaseRepository } from "src/models/repositories/base.repository";

@EntityRepository(UserBonusV2HistoryEntity)
export class UserBonusV2HistoryRepository extends BaseRepository<UserBonusV2HistoryEntity> {}

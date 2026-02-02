import { EntityRepository } from "typeorm";
import { UserBonusV2Entity } from "src/models/entities/user-bonus-v2.entity";
import { BaseRepository } from "src/models/repositories/base.repository";

@EntityRepository(UserBonusV2Entity)
export class UserBonusV2Repository extends BaseRepository<UserBonusV2Entity> {}

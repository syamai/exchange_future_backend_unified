import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { UserStatisticEntity } from "../entities/user_statistics.entity";

@EntityRepository(UserStatisticEntity)
export class UserStatisticRepository extends BaseRepository<UserStatisticEntity> {}

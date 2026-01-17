import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { UserRewardFutureEventUsedEntity } from "../entities/user-reward-future-event-used.entity";

@EntityRepository(UserRewardFutureEventUsedEntity)
export class UserRewardFutureEventUsedRepository extends BaseRepository<UserRewardFutureEventUsedEntity> {}

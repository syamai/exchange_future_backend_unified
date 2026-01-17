import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { UserRewardFutureEventEntity } from "../entities/user-reward-future-event.entity";

@EntityRepository(UserRewardFutureEventEntity)
export class UserRewardFutureEventRepository extends BaseRepository<UserRewardFutureEventEntity> {}

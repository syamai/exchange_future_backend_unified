import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { UserRewardFutureEventUsedDetailEntity } from "../entities/user-reward-future-event-used-detail.entity";

@EntityRepository(UserRewardFutureEventUsedDetailEntity)
export class UserRewardFutureEventUsedDetailRepository extends BaseRepository<UserRewardFutureEventUsedDetailEntity> {}

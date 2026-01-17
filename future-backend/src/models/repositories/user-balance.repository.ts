import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { UserBalanceEntity } from "../entities/user_balance.entity";

@EntityRepository(UserBalanceEntity)
export class UserBalanceRepository extends BaseRepository<UserBalanceEntity> {}

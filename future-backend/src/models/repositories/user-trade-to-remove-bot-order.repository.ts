import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { UserTradeToRemoveBotOrderEntity } from "../entities/user-trade-to-remove-bot-order.entity";

@EntityRepository(UserTradeToRemoveBotOrderEntity)
export class UserTradeToRemoveBotOrderRepository extends BaseRepository<UserTradeToRemoveBotOrderEntity> {}

import { EntityRepository } from "typeorm";
import { PositionHistoryBySessionEntity } from "../entities/position_history_by_session.entity";
import { BaseRepository } from "typeorm-transactional-cls-hooked";
import { OrderWithPositionHistoryBySessionEntity } from "../entities/order_with_position_history_by_session.entity";

@EntityRepository(OrderWithPositionHistoryBySessionEntity)
export class OrderWithPositionHistoryBySessionRepository extends BaseRepository<OrderWithPositionHistoryBySessionEntity> {}

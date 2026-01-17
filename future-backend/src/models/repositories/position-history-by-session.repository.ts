import { EntityRepository } from "typeorm";
import { PositionHistoryBySessionEntity } from "../entities/position_history_by_session.entity";
import { BaseRepository } from "typeorm-transactional-cls-hooked";

@EntityRepository(PositionHistoryBySessionEntity)
export class PositionHistoryBySessionRepository extends BaseRepository<PositionHistoryBySessionEntity> {}

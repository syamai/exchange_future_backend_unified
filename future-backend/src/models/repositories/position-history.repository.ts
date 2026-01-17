import { PositionHistoryEntity } from "src/models/entities/position-history.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";

@EntityRepository(PositionHistoryEntity)
export class PositionHistoryRepository extends BaseRepository<PositionHistoryEntity> {
  public async findHistoryBefore(
    date
  ): Promise<PositionHistoryEntity | undefined> {
    return this.createQueryBuilder()
      .where("createdAt < :date", { date })
      .orderBy("createdAt", "DESC")
      .limit(1)
      .getOne();
  }
}

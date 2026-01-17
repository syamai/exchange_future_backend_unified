import { PositionEntity } from "src/models/entities/position.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";

@EntityRepository(PositionEntity)
export class PositionRepository extends BaseRepository<PositionEntity> {
  async findPositionByUserId(userId: number, symbol: string) {
    const position = await this.createQueryBuilder("position")
      .select("*")
      .where("position.userId = :userId", { userId })
      .andWhere("position.currentQty <> 0 ")
      .andWhere("position.symbol = :symbol", { symbol })
      .getRawOne();
    return position;
  }
}

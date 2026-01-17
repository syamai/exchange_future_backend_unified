import { FundingHistoryEntity } from "src/models/entities/funding-history.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";

@EntityRepository(FundingHistoryEntity)
export class FundingHistoryRepository extends BaseRepository<FundingHistoryEntity> {
  public async findHistoryBefore(
    date
  ): Promise<FundingHistoryEntity | undefined> {
    return this.createQueryBuilder()
      .where("time < :date", { date })
      .orderBy("time", "DESC")
      .limit(1)
      .getOne();
  }
}

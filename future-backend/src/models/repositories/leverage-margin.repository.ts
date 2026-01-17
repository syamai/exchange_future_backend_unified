import { EntityRepository } from "typeorm";
import { LeverageMarginEntity } from "src/models/entities/leverage-margin.entity";
import { BaseRepository } from "src/models/repositories/base.repository";

@EntityRepository(LeverageMarginEntity)
export class LeverageMarginRepository extends BaseRepository<LeverageMarginEntity> {
  public async getLeverageMargin(
    args: any
  ): Promise<LeverageMarginEntity | undefined> {
    const query = { ...args };
    const data = this.findOne({
      where: query,
    });
    return data;
  }
}

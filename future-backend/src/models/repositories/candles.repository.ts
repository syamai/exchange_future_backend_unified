import { CandlesEntity } from "src/models/entities/candles.entity";
import { RESOLUTION_MINUTE } from "src/modules/candle/candle.const";
import { EntityRepository, LessThan, Raw, Repository } from "typeorm";

@EntityRepository(CandlesEntity)
export class CandlesRepository extends Repository<CandlesEntity> {
  async getLastCandleBefore(
    symbol: string,
    time: number
  ): Promise<CandlesEntity | undefined> {
    return this.findOne({
      where: {
        symbol,
        resolution: RESOLUTION_MINUTE,
        minute: LessThan(time),
      },
      order: {
        minute: "DESC",
      },
    });
  }

  async getCandlesInRange(
    symbol: string,
    time: number,
    resolution: number
  ): Promise<CandlesEntity[]> {
    return this.find({
      where: {
        symbol,
        resolution: RESOLUTION_MINUTE,
        minute: Raw((alias) => `${alias} >= :start and ${alias} < :end`, {
          start: time,
          end: time + resolution,
        }),
      },
    });
  }

  async getLastCandleOfResolution(
    symbol: string,
    resolution: number
  ): Promise<CandlesEntity | undefined> {
    console.log(
      "............................................................."
    );
    console.log(symbol);
    return this.findOne({
      where: {
        symbol,
        resolution,
      },
      order: {
        minute: "DESC",
      },
    });
  }
}

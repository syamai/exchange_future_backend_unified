import { MigrationInterface, QueryRunner } from "typeorm";

export class removeTakeProfitPriceAndStopLossPriceOfPositionTable1675244220375
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("positions", "takeProfitPrice");
    await queryRunner.dropColumn("positions", "stopLossPrice");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("positions", "takeProfitPrice");
    await queryRunner.dropColumn("positions", "stopLossPrice");
  }
}

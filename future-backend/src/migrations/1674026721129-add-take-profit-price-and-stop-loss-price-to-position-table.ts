import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addSTakeProfitPriceAndStopLossPriceToPositionTable1674026721129
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("positions", [
      new TableColumn({
        name: "takeProfitPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: "0",
      }),
    ]);

    await queryRunner.addColumns("positions", [
      new TableColumn({
        name: "stopLossPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("positions", "takeProfitPrice");
    await queryRunner.dropColumn("positions", "stopLossPrice");
  }
}

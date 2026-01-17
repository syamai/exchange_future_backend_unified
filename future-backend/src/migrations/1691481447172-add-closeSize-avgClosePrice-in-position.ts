import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addCloseSizeAvgClosePriceInPosition1691481447172
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("positions", [
      new TableColumn({
        name: "closeSize",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      }),
      new TableColumn({
        name: "avgClosePrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("positions", ["closeSize", "avgClosePrice"]);
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addOriginalCostOriginalOrderMargin1687166202995
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "originalCost",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      }),
      new TableColumn({
        name: "originalOrderMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("orders", [
      "originalCost",
      "originalOrderMargin",
    ]);
  }
}

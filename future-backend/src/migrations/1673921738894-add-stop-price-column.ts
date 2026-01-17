import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addStopPriceColumn1673921738894 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "stopPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "stopPrice");
  }
}

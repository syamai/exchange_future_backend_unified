import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumn1686537555051 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "orderMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
    ]);

    await queryRunner.addColumns("positions", [
      new TableColumn({
        name: "marBuy",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
      new TableColumn({
        name: "marSel",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
      new TableColumn({
        name: "orderCost",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
      new TableColumn({
        name: "positionMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "orderMargin");
    await queryRunner.dropColumns("positions", [
      "marBuy",
      "marSel",
      "orderCost",
      "positionMargin",
    ]);
  }
}

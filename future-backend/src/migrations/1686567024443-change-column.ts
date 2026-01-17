import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class changeColumn1686565969240 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "orders",
      "orderMargin",
      new TableColumn({
        name: "orderMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: 0,
      })
    );
    await queryRunner.changeColumn(
      "positions",
      "marBuy",
      new TableColumn({
        name: "marBuy",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: 0,
      })
    );
    await queryRunner.changeColumn(
      "positions",
      "marSel",
      new TableColumn({
        name: "marSel",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: 0,
      })
    );
    await queryRunner.changeColumn(
      "positions",
      "orderCost",
      new TableColumn({
        name: "orderCost",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: 0,
      })
    );
    await queryRunner.changeColumn(
      "positions",
      "positionMargin",
      new TableColumn({
        name: "positionMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: 0,
      })
    );
  }

  public async down(): Promise<void> {}
}

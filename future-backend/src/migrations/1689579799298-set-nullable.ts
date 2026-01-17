import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class setNullable1689579799298 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "orders",
      "cost",
      new TableColumn({
        name: "cost",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: false,
        default: "0",
      })
    );
    await queryRunner.changeColumn(
      "orders",
      "originalCost",
      new TableColumn({
        name: "originalCost",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: false,
        default: "0",
      })
    );
    await queryRunner.changeColumn(
      "orders",
      "originalOrderMargin",
      new TableColumn({
        name: "originalOrderMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: false,
        default: "0",
      })
    );
    await queryRunner.changeColumn(
      "orders",
      "orderMargin",
      new TableColumn({
        name: "orderMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: false,
        default: "0",
      })
    );
  }

  public async down(): Promise<void> {}
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class alterTable1677479991430 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("positions", ["riskValue", "riskLimit"]);
    await queryRunner.dropColumns("margin_histories", [
      "riskLimit",
      "riskLimitAfter",
      "riskValue",
      "riskValueAfter",
    ]);
    await queryRunner.dropColumns("instruments", ["riskLimit"]);
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "cost",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      })
    );
  }

  public async down(): Promise<void> {}
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class updateColumnTrade1675671740594 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "trades",
      "buyFee",
      new TableColumn({
        name: "buyFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      })
    );
    await queryRunner.changeColumn(
      "trades",
      "sellFee",
      new TableColumn({
        name: "sellFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      })
    );
  }

  public async down(): Promise<void> {}
}

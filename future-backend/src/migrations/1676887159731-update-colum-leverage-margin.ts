import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class updateColumLeverageMargin1676887159731
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "leverage_margin",
      new TableColumn({
        name: "symbol",
        type: "varchar",
        isNullable: false,
      })
    );
    await queryRunner.dropColumn("leverage_margin", "instrumentId");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("leverage_margin", "symbol");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addTrades1686192254248 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "trades",
      new TableColumn({
        name: "buyEmail",
        type: "varchar",
        default: null,
        isNullable: true,
      })
    );
    await queryRunner.addColumn(
      "trades",
      new TableColumn({
        name: "sellEmail",
        type: "varchar",
        default: null,
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("trades", ["sellEmail", "buyEmail"]);
  }
}

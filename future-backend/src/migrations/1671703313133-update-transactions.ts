import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class updateTransactions1671703313133 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("transactions", [
      new TableColumn({
        name: "symbol",
        type: "varchar",
        isNullable: true,
      }),
      new TableColumn({
        name: "asset",
        type: "varchar",
        isNullable: true,
      }),
    ]);
    await queryRunner.dropColumn("transactions", "accountId");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("transactions", "symbol");
    await queryRunner.dropColumn("transactions", "asset");
  }
}

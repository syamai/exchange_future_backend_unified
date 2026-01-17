import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addAssetTransactions1672309100248 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("transactions", [
      new TableColumn({
        name: "asset",
        type: "varchar",
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("transactions", "asset");
  }
}

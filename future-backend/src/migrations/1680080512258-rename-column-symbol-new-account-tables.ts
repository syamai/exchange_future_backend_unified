import { MigrationInterface, QueryRunner } from "typeorm";

export class renameColumnSymbolNewAccountTables1680080512258
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.renameColumn("new_accounts", "symbol", "asset");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.renameColumn("new_accounts", "asset", "symbol");
  }
}

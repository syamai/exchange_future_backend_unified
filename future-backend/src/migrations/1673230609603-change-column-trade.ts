import { MigrationInterface, QueryRunner } from "typeorm";

export class changeColumnTrade1673230609603 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.renameColumn("trades", "instrumentSymbol", "symbol");
  }

  public async down(): Promise<void> {}
}

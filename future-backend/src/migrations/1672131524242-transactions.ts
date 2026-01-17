import { MigrationInterface, QueryRunner } from "typeorm";

export class transactions1672131524242 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.renameColumn("transactions", "userId", "accountId");
  }

  public async down(): Promise<void> {}
}

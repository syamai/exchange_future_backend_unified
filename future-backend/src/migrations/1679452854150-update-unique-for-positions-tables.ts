import { MigrationInterface, QueryRunner } from "typeorm";

export class updateUniqueForPositionsTables1679452854150
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      "ALTER TABLE positions ADD CONSTRAINT  UC_POSITIONS_ACCOUNT_SYMBOL UNIQUE (accountId,symbol)"
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      "ALTER TABLE positions DROP INDEX CONSTRAINT UC_POSITIONS_ACCOUNT_SYMBOL"
    );
  }
}

import { MigrationInterface, QueryRunner } from "typeorm";

export class updateDataTypeTransactionTable1678350630710
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      "ALTER TABLE transactions Modify column type varchar(30)"
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      "ALTER TABLE transactions Modify column type varchar(20)"
    );
  }
}

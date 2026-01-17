import { MigrationInterface, QueryRunner } from "typeorm";

export class updateDataTypeOrderForTypeColumn1673339080519
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      "ALTER TABLE orders Modify column type varchar(10)"
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query("ALTER TABLE orders Modify column type varchar(6)");
  }
}

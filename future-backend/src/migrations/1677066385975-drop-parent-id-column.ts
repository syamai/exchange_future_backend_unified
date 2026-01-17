import { MigrationInterface, QueryRunner } from "typeorm";

export class dropParentIdColumn1677066385975 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "parentOrderId");
  }

  public async down(): Promise<void> {}
}

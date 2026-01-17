import { MigrationInterface, QueryRunner } from "typeorm";

export class dropContractColumn1676283846435 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("user_margin_mode", "contract");
  }

  public async down(): Promise<void> {}
}

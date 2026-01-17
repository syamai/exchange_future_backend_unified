import { MigrationInterface, QueryRunner } from "typeorm";

export class dropTypeColumn1672393395988 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("instruments", ["type"]);
  }

  public async down(): Promise<void> {}
}

import { MigrationInterface, QueryRunner } from "typeorm";

export class removeDisplayQuantity1673237585718 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("orders", ["displayQuantity"]);
  }

  public async down(): Promise<void> {}
}

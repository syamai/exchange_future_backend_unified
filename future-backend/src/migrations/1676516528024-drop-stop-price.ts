import { MigrationInterface, QueryRunner } from "typeorm";

export class dropStopPrice1676516528024 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "stopPrice");
  }

  public async down(): Promise<void> {}
}

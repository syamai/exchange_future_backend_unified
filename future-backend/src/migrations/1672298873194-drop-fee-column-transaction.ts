import { MigrationInterface, QueryRunner } from "typeorm";

export class dropFeeColumnTransaction1672298873194
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("transactions", ["fee"]);
  }

  public async down(): Promise<void> {}
}

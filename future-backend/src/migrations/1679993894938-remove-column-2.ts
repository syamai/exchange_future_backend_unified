import { MigrationInterface, QueryRunner } from "typeorm";

export class removeColumn21679993894938 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("positions", ["realisedPnl", "netFunding"]);
    await queryRunner.dropColumns("margin_histories", [
      "realisedPnl",
      "realisedPnlAfter",
    ]);
  }

  public async down(): Promise<void> {}
}

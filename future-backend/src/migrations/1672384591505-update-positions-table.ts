import { MigrationInterface, QueryRunner } from "typeorm";

export class updatePositionsTable1672384591505 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("positions", "ownerEmail");
    await queryRunner.dropColumn("positions", "managerEmail");
    await queryRunner.dropColumn("positions", "latestRealisedPnl");
    await queryRunner.dropColumn("positions", "multiplier");
    await queryRunner.dropColumn("positions", "extraMargin");
  }

  public async down(): Promise<void> {}
}

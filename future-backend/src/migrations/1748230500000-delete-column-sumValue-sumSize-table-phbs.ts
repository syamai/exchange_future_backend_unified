import { MigrationInterface, QueryRunner } from "typeorm";

export class deleteColumnSumValueSumSizeTablePhbs1748230500000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("position_history_by_session");
    if (!table) return;

    await queryRunner.dropColumn("position_history_by_session", "sumValue");
    await queryRunner.dropColumn("position_history_by_session", "sumSize");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

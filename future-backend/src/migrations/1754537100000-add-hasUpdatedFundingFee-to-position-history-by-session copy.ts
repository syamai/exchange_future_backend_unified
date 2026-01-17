import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class AddHasUpdatedFundingFeeToPositionHistoryBySession1754537100000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "position_history_by_session",
      new TableColumn({
        name: "hasUpdatedFundingFee",
        type: "boolean",
        default: false,
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("position_history_by_session", "hasUpdatedFundingFee");
  }
}

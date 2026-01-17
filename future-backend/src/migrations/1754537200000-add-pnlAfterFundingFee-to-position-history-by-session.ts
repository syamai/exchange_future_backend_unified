import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class AddPnlAfterFundingFeeToPositionHistoryBySession1754537200000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "position_history_by_session",
      new TableColumn({
        name: "pnlAfterFundingFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
        comment: "not including funding fees",
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("position_history_by_session", "pnlAfterFundingFee");
  }
}

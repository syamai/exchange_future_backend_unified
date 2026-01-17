import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class AddCheckingStatusToPositionHistoryBySession1748230800000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "position_history_by_session",
      new TableColumn({
        name: "checkingStatus",
        type: "varchar",
        length: "255",
        isNullable: true,
        default: "'NORMAL'",
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn(
      "position_history_by_session",
      "checkingStatus"
    );
  }
}

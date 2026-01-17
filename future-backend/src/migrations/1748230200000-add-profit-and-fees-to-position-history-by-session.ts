import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class AddProfitToPositionHistoryBySession1748230200000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "position_history_by_session",
      new TableColumn({
        name: "profit",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
        comment: "not including fees",
      })
    );

    await queryRunner.addColumn(
      "position_history_by_session",
      new TableColumn({
        name: "fundingFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      })
    );

    await queryRunner.addColumn(
      "position_history_by_session",
      new TableColumn({
        name: "openingFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      })
    );

    await queryRunner.addColumn(
      "position_history_by_session",
      new TableColumn({
        name: "closingFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("position_history_by_session", "profit");
    await queryRunner.dropColumn("position_history_by_session", "fundingFee");
    await queryRunner.dropColumn("position_history_by_session", "openingFee");
    await queryRunner.dropColumn("position_history_by_session", "closingFee");
  }
}

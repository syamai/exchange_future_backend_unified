import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnTotalTradeVolumeTableUserStatistic1748229877976 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_statistics");
    const hasColumn = table?.findColumnByName("totalTradeVolume");

    if (table && !hasColumn) {
      await queryRunner.addColumns("user_statistics", [
        new TableColumn({
          name: "totalTradeVolume",
          type: "decimal",
          precision: 30,
          scale: 15,
          default: 0,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_statistics");
    const hasColumn = table?.findColumnByName("totalTradeVolume");

    if (table && hasColumn) {
      await queryRunner.dropColumn("user_statistics", "totalTradeVolume");
    }
  }
} 
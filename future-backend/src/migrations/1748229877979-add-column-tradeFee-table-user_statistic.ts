import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnTradeFeeTableUserStatistic1748229877979 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_statistics");
    const hasColumn = table?.findColumnByName("tradeFee");

    if (table && !hasColumn) {
      await queryRunner.addColumns("user_statistics", [
        new TableColumn({
          name: "tradeFee",
          type: "decimal",
          precision: 30,
          scale: 15,
          default: 0,
          isNullable: true
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_statistics");
    const hasColumn = table?.findColumnByName("tradeFee");

    if (table && hasColumn) {
      await queryRunner.dropColumn("user_statistics", "tradeFee");
    }
  }
} 
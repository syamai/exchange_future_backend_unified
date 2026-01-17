import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnUuidTableTransactions1748229877978 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("transactions");
    const hasColumn = table?.findColumnByName("uuid");

    if (table && !hasColumn) {
      await queryRunner.addColumns("transactions", [
        new TableColumn({
          name: "uuid",
          type: "varchar",
          isNullable: true,      // allow NULL values
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("transactions");
    const hasColumn = table?.findColumnByName("uuid");

    if (table && hasColumn) {
      await queryRunner.dropColumn("transactions", "uuid");
    }
  }
} 
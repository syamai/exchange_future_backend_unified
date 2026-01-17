import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnRewardBalanceTableAccounts1748229877974 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("accounts");
    const hasColumn = table?.findColumnByName("rewardBalance");

    if (table && !hasColumn) {
      await queryRunner.addColumns("accounts", [
        new TableColumn({
          name: "rewardBalance",
          type: "decimal",
          precision: 30,
          scale: 15,
          default: 0,
          isNullable: true,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

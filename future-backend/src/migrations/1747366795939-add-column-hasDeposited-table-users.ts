import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnHasDepositedTableUser1747366795939 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("users");
    const hasColumn = table?.findColumnByName("hasDeposited");

    if (table && !hasColumn) {
      await queryRunner.addColumns("users", [
        new TableColumn({
          name: "hasDeposited",
          type: "boolean",
          default: false,
          isNullable: true,
          comment: `check user has deposited or not`,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnIsBotTableUser1745329648445 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("users");
    const hasColumn = table?.findColumnByName("isBot");

    if (table && !hasColumn) {
      await queryRunner.addColumns("users", [
        new TableColumn({
          name: "isBot",
          type: "boolean",
          default: false,
          isNullable: true,
          comment: `this is user is bot`,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

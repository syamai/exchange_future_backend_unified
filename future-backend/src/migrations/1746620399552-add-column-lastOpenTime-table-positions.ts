import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnLastOpenTimeTablePosition1746620399552 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("positions");
    const hasColumn = table?.findColumnByName("lastOpenTime");

    if (table && !hasColumn) {
      await queryRunner.addColumns("positions", [
        new TableColumn({
          name: "lastOpenTime",
          type: "datetime",
          default: null,
          isNullable: true,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

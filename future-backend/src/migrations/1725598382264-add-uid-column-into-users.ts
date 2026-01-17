import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addUidColumnIntoUsers1725598382264 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "users",
      new TableColumn({
        name: "uid",
        type: "varchar",
        isNullable: true
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("users", "uid");
  }
}

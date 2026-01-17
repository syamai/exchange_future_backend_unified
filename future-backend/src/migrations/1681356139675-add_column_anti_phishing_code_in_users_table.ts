import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnAntiPhishingCodeInUsersTable1681356139675
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "users",
      new TableColumn({
        name: "antiPhishingCode",
        type: "varchar",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("users", "antiPhishingCode");
  }
}

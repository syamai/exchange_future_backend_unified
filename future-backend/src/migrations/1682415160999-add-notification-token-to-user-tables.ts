import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addNotificationTokenToUserTables1682415160999
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "users",
      new TableColumn({
        name: "notification_token",
        type: "TEXT",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("users", "notification_token");
  }
}

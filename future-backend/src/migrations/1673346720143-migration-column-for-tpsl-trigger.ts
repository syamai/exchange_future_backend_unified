import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class migrationColumnForTpslTrigger1673346720143
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("user_settings", [
      new TableColumn({
        name: "time",
        type: "datetime",
        default: "CURRENT_TIMESTAMP",
      }),
    ]);
    await queryRunner.addColumns("user_settings", [
      new TableColumn({
        name: "notificationQuantity",
        type: "int",
        default: 0,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("user_settings", "notificationQuantity");
    await queryRunner.dropColumn("user_settings", "time");
  }
}

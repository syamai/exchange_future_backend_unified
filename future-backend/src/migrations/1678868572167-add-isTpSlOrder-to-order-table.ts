import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addIsTpSlOrderToOrderTable1678868572167
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "isTpSlOrder",
        type: "boolean",
        default: "0",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "isTpSlOrder");
  }
}

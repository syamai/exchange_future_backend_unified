import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class updateIsTpSlOrderOfOrderTable1678870556465
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "orders",
      "isTpSlOrder",
      new TableColumn({
        name: "isTpSlOrder",
        type: "boolean",
        default: "0",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "isTpSlOrder");
  }
}

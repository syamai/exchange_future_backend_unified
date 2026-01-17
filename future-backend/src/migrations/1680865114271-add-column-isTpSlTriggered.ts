import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnIsTpSlTriggered1680865114271
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "isTpSlTriggered",
        type: "boolean",
        default: 0,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "isTpSlTriggered");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addIsTriggeredColumn1677926588019 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "isTriggered",
        type: "boolean",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "isTriggered");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addParentOrderIdColumn1677059064231 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "parentOrderId",
        type: "int",
        unsigned: true,
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "parentOrderId");
  }
}

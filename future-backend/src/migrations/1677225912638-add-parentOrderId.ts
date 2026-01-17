import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addParentOrderId1677225912638 implements MigrationInterface {
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

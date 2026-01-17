import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class orders1671765360887 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "takeProfitOrderId",
        type: "int",
        unsigned: true,
        isNullable: true,
      }),
      new TableColumn({
        name: "stopLossOrderId",
        type: "int",
        unsigned: true,
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "takeProfitOrderId");
    await queryRunner.dropColumn("orders", "stopLossOrderId");
  }
}

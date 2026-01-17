import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addTpSlOrderPosition1676796699158 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("positions", [
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
    await queryRunner.dropColumns("positions", [
      "takeProfitOrderId",
      "stopLossOrderId",
    ]);
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addIsCancelOrderColumn1676986397978 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "isClosePositionOrder",
        type: "boolean",
        default: false,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "isClosePositionOrder");
  }
}

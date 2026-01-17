import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addUnrelisedPnl1672734545255 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "unrealisedPnl",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("orders", ["unrealisedPnl"]);
  }
}

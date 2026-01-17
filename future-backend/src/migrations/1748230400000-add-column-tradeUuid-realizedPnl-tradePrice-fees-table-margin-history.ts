import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnTradeUuidRealizedPnlTradePriceFeesTableMarginHistory1748230400000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("margin_histories");
    if (!table) return;

    await queryRunner.addColumns("margin_histories", [
      new TableColumn({
        name: "tradeUuid",
        type: "varchar(255)",
        isNullable: true,
      }),
      new TableColumn({
        name: "tradePrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
      }),
      new TableColumn({
        name: "realizedPnl",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
      }),
      new TableColumn({
        name: "fee",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
      }),
      new TableColumn({
        name: "openFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
      }),
      new TableColumn({
        name: "closeFee",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

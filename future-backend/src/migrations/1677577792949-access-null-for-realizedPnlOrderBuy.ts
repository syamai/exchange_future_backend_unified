import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class accessNullForRealizedPnlOrderBuy1677577792949
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("trades", "realizedPnlOrderBuy");

    await queryRunner.dropColumn("trades", "realizedPnlOrderSell");

    await queryRunner.addColumns("trades", [
      new TableColumn({
        name: "realizedPnlOrderBuy",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      }),
      new TableColumn({
        name: "realizedPnlOrderSell",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      }),
    ]);
    await queryRunner.dropColumn("margin_histories", "positionUnrealisedPnl");
    await queryRunner.dropColumn(
      "margin_histories",
      "positionUnrealisedPnlAfter"
    );
    await queryRunner.dropColumn("positions", "unrealisedPnl");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("positions", "unrealisedPnl");
  }
}

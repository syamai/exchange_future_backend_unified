import {
  MigrationInterface,
  QueryRunner,
  Table,
  TableIndex,
} from "typeorm";

export class addUserTradeToRemoveBotOrder1748229877979
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_trade_to_remove_bot_order",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            unsigned: true,
            isNullable: false,
          },
          {
            name: "sellOrderId",
            type: "bigint",
            unsigned: true,
            isNullable: false,
          },
          {
            name: "buyOrderId",
            type: "bigint",
            unsigned: true,
            isNullable: false,
          },
        ],
      }),
      true
    );

    // Create index
    await queryRunner.createIndex(
      "user_trade_to_remove_bot_order",
      new TableIndex({
        name: "IDX_USER_TRADE_SELL_ORDER_ID",
        columnNames: ["sellOrderId"],
      })
    );

    await queryRunner.createIndex(
      "user_trade_to_remove_bot_order",
      new TableIndex({
        name: "IDX_USER_TRADE_BUY_ORDER_ID",
        columnNames: ["buyOrderId"],
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("transactions", "user_trade_to_remove_bot_order");
  }
}

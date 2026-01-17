import { MigrationInterface, QueryRunner, Table, TableColumn, TableIndex } from "typeorm";

export class addAverageOrderByTradeTable1745329648446
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "order_average_by_trade",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
            unsigned: true,
          },
          {
            name: "orderId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "type",
            type: "enum",
            enum: ["BUY", "SELL"],
            isNullable: false,
          },
          {
            name: "symbol",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "isCoinM",
            type: "boolean",
            default: false,
            isNullable: false,
          },
          {
            name: "totalQuantityMulOrDivPrice",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "totalQuantity",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "average",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
        ],
      }),
      true
    );

    await queryRunner.createIndex(
      "order_average_by_trade",
      new TableIndex({
        name: "IDX_ORDER_AVERAGE_BY_TRADE_ORDER_ID",
        columnNames: ["orderId"],
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("order_average_by_trade"))
      await queryRunner.dropTable("order_average_by_trade");
  }
}

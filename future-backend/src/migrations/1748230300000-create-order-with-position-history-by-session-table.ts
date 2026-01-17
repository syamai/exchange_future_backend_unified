import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createOrderWithPositionHistoryBySessionTable1748230300000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "order_with_position_history_by_session_tmp",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
          },
          { name: "orderId", type: "bigint", isNullable: false },
          {
            name: "positionHistoryBySessionId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "orderMarginAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "entryPriceAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "currentQtyAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "entryValueAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "isOpenOrder",
            type: "boolean",
            isNullable: false,
            default: true,
          },
          {
            name: "fee",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "closeFee",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "openFee",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "profit",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "tradePriceAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("order_with_position_history_by_session_tmp");
  }
}

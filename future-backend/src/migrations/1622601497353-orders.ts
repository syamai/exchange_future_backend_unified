import { MIN_ORDER_ID } from "src/models/entities/order.entity";
import {
  MarginMode,
  OrderSide,
  OrderStatus,
  OrderTimeInForce,
  OrderTrigger,
  OrderType,
  TpSlType,
} from "src/shares/enums/order.enum";
import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class orders1622601497353 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "orders",
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
            name: "accountId",
            type: "bigint",
            unsigned: true,
            default: 0,
          },
          {
            name: "instrumentSymbol",
            type: "varchar(20)",
            isNullable: true,
          },
          {
            name: "side",
            type: "varchar(4)",
            comment: Object.keys(OrderSide).join(","),
          },
          {
            name: "type",
            type: "varchar(6)",
            comment: Object.keys(OrderType).join(","),
            default: `'${OrderType.LIMIT}'`,
          },
          {
            name: "quantity",
            type: "decimal",
            precision: 22,
            scale: 8,
          },
          {
            name: "price",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "lockPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "orderValue",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "remaining",
            type: "decimal",
            precision: 22,
            scale: 8,
          },
          {
            name: "executedPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "timeInForce",
            type: "varchar(3)",
            isNullable: false,
            comment: Object.keys(OrderTimeInForce).join(","),
          },
          {
            name: "stopType",
            type: "varchar(20)",
            isNullable: true,
            comment: Object.keys(TpSlType).join(","),
          },
          {
            name: "stopPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "trigger",
            type: "varchar(6)",
            isNullable: true,
            comment: Object.keys(OrderTrigger).join(","),
          },
          {
            name: "trailValue",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "vertexPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "status",
            type: "varchar(12)",
            default: "'pending'",
            comment: Object.keys(OrderStatus).join(","),
          },
          {
            name: "isPostOnly",
            type: "boolean",
            default: "0",
          },
          {
            name: "isHidden",
            type: "boolean",
            default: "0",
          },
          {
            name: "displayQuantity",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
          },
          {
            name: "isReduceOnly",
            type: "boolean",
            default: "0",
          },
          {
            name: "note",
            type: "varchar(30)",
            isNullable: true,
          },
          {
            name: "operationId",
            type: "bigint",
            unsigned: true,
            default: 0,
          },
          {
            name: "unrealisedPnl",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
          },
          {
            name: "leverage",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
          },
          {
            name: "marginMode",
            type: "varchar(20)",
            isNullable: true,
            comment: Object.keys(MarginMode).join(","),
          },
          {
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
          {
            name: "updatedAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
        ],
      }),
      true
    );
    await queryRunner.query(
      `ALTER TABLE orders AUTO_INCREMENT = ${MIN_ORDER_ID};`
    );
    await queryRunner.createIndices("orders", [
      new TableIndex({
        columnNames: ["accountId", "instrumentSymbol", "status"],
        isUnique: false,
        name: "IDX-orders-accountId_instrumentSymbol_status",
      }),
      new TableIndex({
        columnNames: ["accountId", "createdAt"],
        isUnique: false,
        name: "IDX-orders-accountId_createdAt",
      }),
      // load open orders to matching engine
      new TableIndex({
        columnNames: ["status"],
        isUnique: false,
        name: "IDX-orders-status",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("orders"))
      await queryRunner.dropTable("orders");
  }
}

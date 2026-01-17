import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class tradingRules1671780906478 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "trading_rules",
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
            name: "instrumentId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "minTradeAmount",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "minOrderPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "minPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "limitOrderPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "floorRatio",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "maxMarketOrder",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "limitOrderAmount",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "numberOpenOrders",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "priceProtectionThreshold",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "liqClearanceFee",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "minNotional",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "marketOrderPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "isReduceOnly",
            type: "boolean",
            default: "0",
          },
          {
            name: "positionsNotional",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "ratioOfPostion",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
          },
          {
            name: "liqMarkPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: true,
            default: null,
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
    await queryRunner.createIndices("trading_rules", [
      new TableIndex({
        columnNames: ["instrumentId"],
        isUnique: false,
        name: "IDX-trading_rules-instrumentId",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("trading_rules")) {
      await queryRunner.dropTable("trading_rules");
    }
  }
}

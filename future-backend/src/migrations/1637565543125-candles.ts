import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class candles1637565543125 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "candles",
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
            name: "symbol",
            type: "varchar(20)",
            isNullable: false,
          },
          {
            name: "minute",
            type: "int",
            unsigned: true,
            isNullable: false,
          },
          {
            name: "resolution",
            type: "int",
            unsigned: true,
            isNullable: false,
          },
          {
            name: "low",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: false,
          },
          {
            name: "high",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: false,
          },
          {
            name: "open",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: false,
          },
          {
            name: "close",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: false,
          },
          {
            name: "volume",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: false,
          },
          {
            name: "lastTradeTime", // last trade of the candle
            type: "int",
            unsigned: true,
            isNullable: false,
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
      })
    );
    await queryRunner.createIndices("candles", [
      new TableIndex({
        columnNames: ["symbol", "resolution", "minute"],
        isUnique: true,
        name: "IDX-candles-symbol_resolution_minute",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("candles")) {
      await queryRunner.dropTable("candles");
    }
  }
}

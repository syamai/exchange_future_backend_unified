import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class marketData1636966231024 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "market_data",
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
            name: "market",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "symbol",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "group",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "bid",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "ask",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "index",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
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
    await queryRunner.createIndices("market_data", [
      new TableIndex({
        columnNames: ["createdAt"],
        isUnique: false,
        name: "IDX-market_data-createdAt",
      }),
      new TableIndex({
        columnNames: ["group", "market"],
        isUnique: false,
        name: "IDX-market_data-group_market",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("market_data")) {
      await queryRunner.dropTable("market_data");
    }
  }
}

import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class fundingHistories1638417979910 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "funding_histories",
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
          },
          {
            name: "accountId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "positionId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "time",
            type: "datetime",
            isNullable: false,
          },
          {
            name: "amount",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "fundingQuantity",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
          },
          {
            name: "fundingRate",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
          },
          {
            name: "operationId",
            type: "bigint",
            unsigned: true,
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

    await queryRunner.createIndices("funding_histories", [
      new TableIndex({
        columnNames: ["accountId", "symbol", "time"],
        isUnique: true,
        name: "IDX-funding_histories-accountId-symbol-time",
      }),
      new TableIndex({
        columnNames: ["operationId"],
        isUnique: false,
        name: "IDX-funding_histories-operationId",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("funding_histories");
  }
}

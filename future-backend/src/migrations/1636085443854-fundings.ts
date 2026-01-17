import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class fundings1636085443854 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "fundings",
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
            type: "varchar",
            isNullable: true,
          },
          {
            name: "time",
            type: "datetime",
            isNullable: false,
          },
          {
            name: "fundingInterval",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "fundingRateDaily",
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
            name: "oraclePrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
          },
          {
            name: "paid",
            type: "boolean",
            isNullable: false,
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

    await queryRunner.createIndices("fundings", [
      new TableIndex({
        columnNames: ["symbol", "time"],
        isUnique: true,
        name: "IDX-fundings-symbol_time",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("fundings"))
      await queryRunner.dropTable("fundings");
  }
}

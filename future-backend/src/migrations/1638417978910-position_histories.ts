import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class positionHistories1638417978910 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "position_histories",
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
            name: "action",
            type: "varchar(20)",
          },
          {
            name: "positionId",
            type: "bigint",
          },
          {
            name: "entryPrice",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
          },
          {
            name: "entryPriceAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
          },
          {
            name: "entryValue",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
          },
          {
            name: "entryValueAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
          },
          {
            name: "currentQty",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
          },
          {
            name: "currentQtyAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
          },
          {
            name: "initMargin",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
          },
          {
            name: "initMarginAfter",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: null,
            isNullable: true,
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

    await queryRunner.createIndices("position_histories", [
      new TableIndex({
        columnNames: ["createdAt"],
        isUnique: false,
        name: "IDX-position_histories-createdAt",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("position_histories");
  }
}

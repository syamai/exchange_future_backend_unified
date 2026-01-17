import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class dexActionHistories1639209748969 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "dex_action_histories",
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
            name: "txid",
            type: "char",
            precision: 90,
          },
          {
            name: "logIndex",
            type: "int",
            unsigned: true,
          },
          {
            name: "address",
            type: "char",
            precision: 50,
          },
          {
            name: "accountId",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "action",
            type: "varchar(20)",
          },
          {
            name: "actionId",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "operationId",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "validStatus",
            type: "varchar(20)",
            default: "'PENDING'",
          },
          {
            name: "oldMargin",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
          },
          {
            name: "newMargin",
            type: "decimal",
            precision: 22,
            scale: 8,
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

    await queryRunner.createIndices("dex_action_histories", [
      new TableIndex({
        columnNames: ["txid", "logIndex"],
        isUnique: true,
        name: "IDX-dex_action_histories-txid_logIndex",
      }),
      new TableIndex({
        columnNames: ["validStatus"],
        isUnique: false,
        name: "IDX-dex_action_histories-validStatus",
      }),
      new TableIndex({
        columnNames: ["accountId"],
        isUnique: false,
        name: "IDX-dex_action_histories-accountId",
      }),
      new TableIndex({
        columnNames: ["actionId"],
        isUnique: false,
        name: "IDX-dex_action_histories-actionId",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("dex_action_histories")) {
      await queryRunner.dropTable("dex_action_histories");
    }
  }
}

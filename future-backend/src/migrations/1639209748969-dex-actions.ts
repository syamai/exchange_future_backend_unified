import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class dexActions1639209748969 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "dex_actions",
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
            name: "actionId",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "kafkaOffset",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "rawParameter",
            type: "json",
          },
          {
            name: "dexParameter",
            type: "json",
          },
          {
            name: "dexActionTransactionId",
            type: "bigint",
            unsigned: true,
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

    await queryRunner.createIndices("dex_actions", [
      new TableIndex({
        columnNames: ["actionId", "action"],
        isUnique: true,
        name: "IDX-dex_actions-actionId_action",
      }),
      new TableIndex({
        columnNames: ["dexActionTransactionId", "id"],
        isUnique: true,
        name: "IDX-dex_actions-dexActionTransactionId_id",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("dex_actions")) {
      await queryRunner.dropTable("dex_actions");
    }
  }
}

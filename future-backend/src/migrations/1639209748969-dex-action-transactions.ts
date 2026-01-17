import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class dexActionTransactions1639209748969 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "dex_action_transactions",
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
            isUnique: true,
          },
          {
            name: "matcherAddress",
            type: "char",
            precision: 50,
          },
          {
            name: "nonce",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "status",
            type: "varchar(20)",
            default: "'PENDING'",
          },
          {
            name: "rawTx",
            type: "mediumtext",
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

    await queryRunner.createIndices("dex_action_transactions", [
      new TableIndex({
        columnNames: ["matcherAddress", "nonce"],
        isUnique: true,
        name: "IDX-dex_action_transactions-matcherAddress_nonce",
      }),
      new TableIndex({
        columnNames: ["status"],
        isUnique: false,
        name: "IDX-dex_action_transactions-status",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("dex_action_transactions")) {
      await queryRunner.dropTable("dex_action_transactions");
    }
  }
}

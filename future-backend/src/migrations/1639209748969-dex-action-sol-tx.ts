import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class dexActionSolTxs1639209748969 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "dex_action_sol_txs",
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
            name: "slot",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "logs",
            type: "text",
            isNullable: true,
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
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("dex_action_sol_txs")) {
      await queryRunner.dropTable("dex_action_sol_txs");
    }
  }
}

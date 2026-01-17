import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class accountHistories1638084692774 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "account_histories",
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
            name: "accountId",
            type: "bigint",
          },
          {
            name: "balance",
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

    await queryRunner.createIndices("account_histories", [
      new TableIndex({
        columnNames: ["accountId", "createdAt"],
        isUnique: true,
        name: "IDX-account_histories-accountId_createdAt",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("account_histories");
  }
}

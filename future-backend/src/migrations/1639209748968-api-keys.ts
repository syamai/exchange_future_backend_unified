import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class apiKeys1639209748968 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "api_keys",
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
            name: "userId",
            type: "bigint",
            unsigned: true,
          },
          {
            name: "key",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "type",
            type: "varchar(20)",
            isNullable: false,
            default: "'API'",
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

    await queryRunner.createIndices("api_keys", [
      new TableIndex({
        columnNames: ["key"],
        isUnique: true,
        name: "IDX-api_keys-key",
      }),
      new TableIndex({
        columnNames: ["userId"],
        name: "IDX-api_keys-userId",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("api_keys"))
      await queryRunner.dropTable("api_keys");
  }
}

import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class latestBlock1637490418642 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "latest_blocks",
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
            name: "blockNumber",
            type: "int",
            isNullable: false,
          },
          {
            name: "status",
            type: "varchar(20)",
            isNullable: true,
          },
          {
            name: "service",
            type: "varchar(50)",
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
      }),
      true
    );

    await queryRunner.createIndices("latest_blocks", [
      new TableIndex({
        columnNames: ["service"],
        isUnique: true,
        name: "IDX-latest_blocks-service",
      }),
      new TableIndex({
        columnNames: ["status"],
        isUnique: false,
        name: "IDX-latest_blocks-status",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("latest_blocks");
  }
}

import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class latestSignature1637490418642 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "latest_signatures",
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
            name: "signature",
            type: "char",
            precision: 90,
          },
          {
            name: "service",
            type: "varchar(50)",
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

    await queryRunner.createIndices("latest_signatures", [
      new TableIndex({
        columnNames: ["service"],
        isUnique: true,
        name: "IDX-latest_signatures-service",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("latest_signatures");
  }
}

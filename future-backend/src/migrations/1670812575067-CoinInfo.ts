import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class CoinInfo1670812575067 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "coin_info",
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
            name: "fullName",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "baseId",
            type: "varchar",
            isUnique: true,
          },
          {
            name: "symbol",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "rank",
            type: "int",
            unsigned: true,
            isNullable: true,
          },
          {
            name: "marketCap",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "cirSupply",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "maxSupply",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "totalSupply",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "issueDate",
            type: "datetime",
            isNullable: true,
          },
          {
            name: "issuePrice",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "explorer",
            type: "varchar",
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
    if (await queryRunner.hasTable("coin_info")) {
      await queryRunner.dropTable("coin_info");
    }
  }
}

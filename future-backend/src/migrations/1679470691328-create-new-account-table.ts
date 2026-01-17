import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createNewAccountTable1679470691328 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "new_accounts",
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
            isNullable: false,
          },
          {
            name: "symbol",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "balance",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "operationId",
            type: "bigint",
            isNullable: false,
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
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("new_accounts"))
      await queryRunner.dropTable("new_accounts");
  }
}

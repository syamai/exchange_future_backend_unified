import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class accounts1635785330158 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "accounts",
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
            name: "usdtBalance",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "usdtAvailableBalance",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "usdBalance",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "usdAvailableBalance",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
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
      }),
      true
    );
    await queryRunner.createIndices("accounts", [
      new TableIndex({
        columnNames: ["usdtBalance"],
        isUnique: false,
        name: "IDX-accounts-usdtBalance",
      }),
      new TableIndex({
        columnNames: ["usdtAvailableBalance"],
        isUnique: false,
        name: "IDX-accounts-usdtAvailableBalance",
      }),
      new TableIndex({
        columnNames: ["usdBalance"],
        isUnique: false,
        name: "IDX-accounts-usdBalance",
      }),
      new TableIndex({
        columnNames: ["usdAvailableBalance"],
        isUnique: false,
        name: "IDX-accounts-usdAvailableBalance",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("accounts"))
      await queryRunner.dropTable("accounts");
  }
}

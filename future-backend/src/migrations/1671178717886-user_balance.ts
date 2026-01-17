import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class userBalance1671178717886 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_balance",
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
            name: "orderId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "isolateBalance",
            type: "decimal",
            precision: 30,
            scale: 15,
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
    await queryRunner.createIndices("user_balance", [
      new TableIndex({
        columnNames: ["userId"],
        isUnique: false,
        name: "IDX-user_balance-userId",
      }),
      new TableIndex({
        columnNames: ["orderId"],
        isUnique: false,
        name: "IDX-user_balance-orderId",
      }),
      new TableIndex({
        columnNames: ["isolateBalance"],
        isUnique: true,
        name: "IDX-user_balance-isolateBalance",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("user_balance")) {
      await queryRunner.dropTable("user_balance");
    }
  }
}

import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class loginHistories1625208077403 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "login_histories",
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
            name: "ip",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "device",
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
      }),
      true
    );

    await queryRunner.createIndices("login_histories", [
      new TableIndex({
        columnNames: ["userId"],
        isUnique: false,
        name: "IDX-login_histories-userId",
      }),
      new TableIndex({
        columnNames: ["ip"],
        isUnique: false,
        name: "IDX-login_histories-ip",
      }),
      new TableIndex({
        columnNames: ["createdAt"],
        isUnique: false,
        name: "IDX-login_histories-createdAt",
      }),
      new TableIndex({
        columnNames: ["updatedAt"],
        isUnique: false,
        name: "IDX-login_histories-udpatedAt",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("login_histories"))
      await queryRunner.dropTable("login_histories");
  }
}

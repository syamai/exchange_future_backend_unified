import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class userSettings1630486781471 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_settings",
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
            name: "key",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "value",
            type: "varchar",
            isNullable: false,
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
    await queryRunner.createIndices("user_settings", [
      new TableIndex({
        columnNames: ["userId"],
        isUnique: false,
        name: "IDX-user_settings-userId",
      }),
      new TableIndex({
        columnNames: ["key"],
        isUnique: false,
        name: "IDX-user_settings-key",
      }),
      new TableIndex({
        columnNames: ["value"],
        isUnique: false,
        name: "IDX-user_settings-value",
      }),

      // getUserFavoriteMarket()
      new TableIndex({
        columnNames: ["userId", "key", "value"],
        isUnique: true,
        name: "IDX-user_settings-userId_key_value",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("user_settings"))
      await queryRunner.dropTable("user_settings");
  }
}

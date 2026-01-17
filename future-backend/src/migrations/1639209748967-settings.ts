import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class settings1639209748967 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "settings",
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
      })
    );

    await queryRunner.createIndex(
      "settings",
      new TableIndex({
        columnNames: ["key"],
        isUnique: true,
        name: "IDX-settings-key",
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("settings"))
      await queryRunner.dropTable("settings");
  }
}

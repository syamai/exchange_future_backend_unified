import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class metadata1637581709574 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "metadata",
        columns: [
          {
            name: "name",
            type: "varchar",
            isNullable: false,
            isPrimary: true,
          },
          {
            name: "data",
            type: "text",
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
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("metadata")) {
      await queryRunner.dropTable("metadata");
    }
  }
}

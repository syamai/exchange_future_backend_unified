import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class tally1638524590599 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "tally",
        columns: [
          {
            name: "_id",
            type: "int",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
            unsigned: true,
          },
        ],
      })
    );
    for (let i = 0; i < 100; i++) {
      await queryRunner.query("INSERT INTO tally VALUE()");
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("tally");
  }
}

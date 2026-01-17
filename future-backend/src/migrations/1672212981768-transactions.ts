import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class transactions1672212981768 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("transactions", [
      new TableColumn({
        name: "operationId",
        type: "bigint",
        unsigned: true,
        default: 0,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("transactions", "operationId");
  }
}

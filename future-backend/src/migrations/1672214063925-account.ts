import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class account1672214063925 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("accounts", [
      new TableColumn({
        name: "operationId",
        type: "bigint",
        unsigned: true,
        default: 0,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("accounts", "operationId");
  }
}

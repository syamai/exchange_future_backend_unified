import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addUserIdTransaction1679909405094 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "transactions",
      new TableColumn({
        name: "userId",
        type: "bigint",
        unsigned: true,
        default: 0,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("transactions", "userId");
  }
}

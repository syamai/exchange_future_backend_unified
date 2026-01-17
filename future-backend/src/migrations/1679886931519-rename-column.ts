import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class renameColumn1679886931519 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("trades", [
      new TableColumn({
        name: "buyUserId",
        type: "bigint",
        unsigned: true,
      }),
      new TableColumn({
        name: "sellUserId",
        type: "bigint",
        unsigned: true,
      }),
    ]);
  }

  public async down(): Promise<void> {}
}

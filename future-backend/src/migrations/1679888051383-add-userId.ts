import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addUserId1679888051383 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "userId",
        type: "bigint",
        unsigned: true,
        default: 0,
      })
    );
    await queryRunner.addColumn(
      "positions",
      new TableColumn({
        name: "userId",
        type: "bigint",
        unsigned: true,
        default: 0,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "userId");
    await queryRunner.dropColumn("positions", "userId");
  }
}

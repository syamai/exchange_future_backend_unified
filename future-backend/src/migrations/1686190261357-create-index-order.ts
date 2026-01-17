import { MigrationInterface, QueryRunner, TableIndex } from "typeorm";

export class createIndexOrder1686190261357 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createIndices("orders", [
      new TableIndex({
        columnNames: ["userId", "status", "symbol"],
        isUnique: false,
        name: "IDX-orders-userId-status-symbol",
      }),
      new TableIndex({
        columnNames: ["createdAt"],
        isUnique: false,
        name: "IDX-orders-createdAt",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("orders"))
      await queryRunner.dropTable("orders");
  }
}

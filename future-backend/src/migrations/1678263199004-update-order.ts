import { MIN_ORDER_ID } from "src/models/entities/order.entity";
import { MigrationInterface, QueryRunner } from "typeorm";

export class updateOrder1678263199004 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `ALTER TABLE orders AUTO_INCREMENT = ${MIN_ORDER_ID};`
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("orders"))
      await queryRunner.dropTable("orders");
  }
}

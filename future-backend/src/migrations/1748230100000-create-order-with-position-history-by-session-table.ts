import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createOrderWithPositionHistoryBySessionTable1748230100000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "order_with_position_history_by_session",
        columns: [
          { name: "orderId", type: "bigint", isPrimary: true },
          { name: "positionHistoryBySessionId", type: "bigint", isNullable: false },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("order_with_position_history_by_session");
  }
} 
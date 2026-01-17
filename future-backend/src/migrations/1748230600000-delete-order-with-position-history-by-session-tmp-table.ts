import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class deleteOrderWithPositionHistoryBySessionTable1748230600000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("order_with_position_history_by_session");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
  }
} 
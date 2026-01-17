import { MigrationInterface, QueryRunner } from "typeorm";

export class renameOrderWithPositionHistoryBySessionTable1748230700000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.renameTable(
      "order_with_position_history_by_session_tmp",
      "order_with_position_history_by_session"
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
  }
} 
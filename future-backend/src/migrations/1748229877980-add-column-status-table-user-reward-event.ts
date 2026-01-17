import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnStatusTableUserRewardFutureEvent1748229877980 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_reward_future_event");
    const hasColumn = table?.findColumnByName("status");

    if (table && !hasColumn) {
      await queryRunner.addColumns("user_reward_future_event", [
        new TableColumn({
          name: "status",
          type: "varchar",
          isNullable: true,      // allow NULL values
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_reward_future_event");
    const hasColumn = table?.findColumnByName("status");

    if (table && hasColumn) {
      await queryRunner.dropColumn("user_reward_future_event", "status");
    }
  }
} 
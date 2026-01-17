import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnRefIdRemainingTableUserRewardFutureEvent1754536869884 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_reward_future_event");
    const hasRefIdColumn = table?.findColumnByName("refId");
    const hasRemainingColumn = table?.findColumnByName("remaining");

    if (table && !hasRefIdColumn) {
      await queryRunner.addColumns("user_reward_future_event", [
        new TableColumn({
          name: "refId",
          type: "varchar",
          isNullable: true, // allow NULL values
        }),
      ]);
    }
    if (table && !hasRemainingColumn) {
      await queryRunner.addColumns("user_reward_future_event", [
        new TableColumn({
          name: "remaining",
          type: "decimal",
          precision: 30,
          scale: 15,
          isNullable: true,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

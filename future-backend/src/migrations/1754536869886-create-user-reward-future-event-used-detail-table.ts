import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createUserRewardFutureEventUsedDetailTable1754536869886 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_reward_future_event_used_detail",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
            unsigned: true,
          },
          {
            name: "userId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "transactionUuid",
            type: "varchar",
            length: "255",
            isNullable: false,
          },
          {
            name: "amount",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "dateUsed",
            type: "datetime",
            isNullable: false,
          },
          {
            name: "symbol",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "transactionType",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "rewardId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "rewardUsedId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "rewardAmountBefore",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "rewardAmountAfter",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
          {
            name: "updatedAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("user_reward_future_event_used_detail")) {
      await queryRunner.dropTable("user_reward_future_event_used_detail");
    }
  }
}

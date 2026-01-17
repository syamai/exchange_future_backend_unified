import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createUserRewardFutureEventUsedTable1748229877977 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_reward_future_event_used",
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
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
          {
            name: "updatedAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
          {
            name: "remainingRewardBalance",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "symbol",
            type: "varchar",
            length: "20",
            isNullable: true,
          },
          {
            name: "transactionType",
            type: "varchar",
            isNullable: true,
          },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("user_reward_future_event_used")) {
      await queryRunner.dropTable("user_reward_future_event_used");
    }
  }
} 
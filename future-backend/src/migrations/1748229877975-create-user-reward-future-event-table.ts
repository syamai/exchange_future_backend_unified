import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createUserRewardFutureEventTable1748229877975 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_reward_future_event",
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
            name: "amount",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "expiredDate",
            type: "varchar",
            length: "255",
            isNullable: false,
          },
          {
            name: "eventName",
            type: "varchar",
            length: "255",
            isNullable: false,
          },
          {
            name: "isRevoke",
            type: "boolean",
            default: false,
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
    if (await queryRunner.hasTable("user_reward_future_event")) {
      await queryRunner.dropTable("user_reward_future_event");
    }
  }
} 
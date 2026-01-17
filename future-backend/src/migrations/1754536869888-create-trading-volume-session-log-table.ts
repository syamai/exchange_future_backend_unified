import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createTradingVolumeSessionLogTable1754536869888 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "trading_volume_session_log",
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
            name: "startDate",
            type: "datetime",
            isNullable: false,
          },
          {
            name: "endDate",
            type: "datetime",
            isNullable: false,
          },
          {
            name: "totalReward",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "currentTradingVolume",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "totalProfit",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "totalLoss",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "totalUsedReward",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "targetTradingVolume",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: false,
          },
          {
            name: "sessionUUID",
            type: "varchar",
            length: "255",
            isNullable: false,
          },
          {
            name: "userId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "logDetail",
            type: "text",
            isNullable: false,
          },
          {
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
            isNullable: false,
          },
          {
            name: "updatedAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
            onUpdate: "CURRENT_TIMESTAMP",
            isNullable: false,
          },
        ],
        indices: [
          {
            name: "IDX_trading_volume_session_log_user_id",
            columnNames: ["userId"],
          },
          {
            name: "IDX_trading_volume_session_log_session_uuid",
            columnNames: ["sessionUUID"],
          },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("trading_volume_session_log")) {
      await queryRunner.dropTable("trading_volume_session_log");
    }
  }
}

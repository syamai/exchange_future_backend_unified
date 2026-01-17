import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createTradingVolumeSessionTable1754536869887 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "trading_volume_session",
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
            isNullable: true,
          },
          {
            name: "totalReward",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: true,
          },
          {
            name: "currentTradingVolume",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: true,
          },
          {
            name: "totalProfit",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: true,
          },
          {
            name: "totalLoss",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: true,
          },
          {
            name: "totalUsedReward",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: true,
          },
          {
            name: "targetTradingVolume",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
            isNullable: true,
          },
          {
            name: "sessionUUID",
            type: "varchar",
            length: "255",
            isNullable: true,
          },
          {
            name: "userId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "status",
            type: "varchar",
            length: "50",
            isNullable: true,
          },
          {
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
            isNullable: true,
          },
          {
            name: "updatedAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
            onUpdate: "CURRENT_TIMESTAMP",
            isNullable: true,
          },
        ],
        indices: [
          {
            name: "IDX_trading_volume_session_user_id",
            columnNames: ["userId"],
            isUnique: true
          },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("trading_volume_session")) {
      await queryRunner.dropTable("trading_volume_session");
    }
  }
}

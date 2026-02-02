import { MigrationInterface, QueryRunner, Table, TableIndex, TableForeignKey } from "typeorm";

export class createFutureEventV2Tables1769785760464 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    // 1. Create event_setting_v2 table
    await queryRunner.createTable(
      new Table({
        name: "event_setting_v2",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
          },
          {
            name: "eventName",
            type: "varchar",
            length: "100",
            isNullable: false,
          },
          {
            name: "eventCode",
            type: "varchar",
            length: "50",
            isNullable: false,
            isUnique: true,
          },
          {
            name: "status",
            type: "enum",
            enum: ["ACTIVE", "INACTIVE"],
            default: "'INACTIVE'",
          },
          {
            name: "bonusRatePercent",
            type: "decimal",
            precision: 10,
            scale: 2,
            default: 100.0,
          },
          {
            name: "minDepositAmount",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "maxBonusAmount",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
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
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
          {
            name: "updatedAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
            onUpdate: "CURRENT_TIMESTAMP",
          },
        ],
      }),
      true
    );

    await queryRunner.createIndices("event_setting_v2", [
      new TableIndex({
        name: "IDX_event_setting_v2_status",
        columnNames: ["status"],
      }),
      new TableIndex({
        name: "IDX_event_setting_v2_date_range",
        columnNames: ["startDate", "endDate"],
      }),
    ]);

    // 2. Create user_bonus_v2 table
    await queryRunner.createTable(
      new Table({
        name: "user_bonus_v2",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
          },
          {
            name: "userId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "accountId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "eventSettingId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "transactionId",
            type: "bigint",
            isNullable: false,
            default: 0,
          },
          {
            name: "bonusAmount",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "originalDeposit",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "currentPrincipal",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "status",
            type: "enum",
            enum: ["ACTIVE", "LIQUIDATED", "EXPIRED", "REVOKED"],
            default: "'ACTIVE'",
          },
          {
            name: "grantedAt",
            type: "datetime",
            isNullable: false,
          },
          {
            name: "liquidatedAt",
            type: "datetime",
            isNullable: true,
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
            onUpdate: "CURRENT_TIMESTAMP",
          },
        ],
      }),
      true
    );

    await queryRunner.createIndices("user_bonus_v2", [
      new TableIndex({
        name: "IDX_user_bonus_v2_userId",
        columnNames: ["userId"],
      }),
      new TableIndex({
        name: "IDX_user_bonus_v2_accountId",
        columnNames: ["accountId"],
      }),
      new TableIndex({
        name: "IDX_user_bonus_v2_status",
        columnNames: ["status"],
      }),
      new TableIndex({
        name: "IDX_user_bonus_v2_eventSettingId",
        columnNames: ["eventSettingId"],
      }),
    ]);

    await queryRunner.createForeignKey(
      "user_bonus_v2",
      new TableForeignKey({
        name: "FK_user_bonus_v2_event_setting",
        columnNames: ["eventSettingId"],
        referencedTableName: "event_setting_v2",
        referencedColumnNames: ["id"],
        onDelete: "RESTRICT",
      })
    );

    // 3. Create user_bonus_v2_history table
    await queryRunner.createTable(
      new Table({
        name: "user_bonus_v2_history",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
          },
          {
            name: "userBonusId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "userId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "changeType",
            type: "varchar",
            length: "30",
            isNullable: false,
          },
          {
            name: "changeAmount",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "principalBefore",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "principalAfter",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "transactionUuid",
            type: "varchar",
            length: "100",
            isNullable: true,
          },
          {
            name: "description",
            type: "varchar",
            length: "200",
            isNullable: true,
          },
          {
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
        ],
      }),
      true
    );

    await queryRunner.createIndices("user_bonus_v2_history", [
      new TableIndex({
        name: "IDX_user_bonus_v2_history_userBonusId",
        columnNames: ["userBonusId"],
      }),
      new TableIndex({
        name: "IDX_user_bonus_v2_history_userId",
        columnNames: ["userId"],
      }),
      new TableIndex({
        name: "IDX_user_bonus_v2_history_changeType",
        columnNames: ["changeType"],
      }),
      new TableIndex({
        name: "IDX_user_bonus_v2_history_createdAt",
        columnNames: ["createdAt"],
      }),
    ]);

    await queryRunner.createForeignKey(
      "user_bonus_v2_history",
      new TableForeignKey({
        name: "FK_user_bonus_v2_history_bonus",
        columnNames: ["userBonusId"],
        referencedTableName: "user_bonus_v2",
        referencedColumnNames: ["id"],
        onDelete: "CASCADE",
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    // Drop foreign keys first
    await queryRunner.dropForeignKey("user_bonus_v2_history", "FK_user_bonus_v2_history_bonus");
    await queryRunner.dropForeignKey("user_bonus_v2", "FK_user_bonus_v2_event_setting");

    // Drop tables
    await queryRunner.dropTable("user_bonus_v2_history", true);
    await queryRunner.dropTable("user_bonus_v2", true);
    await queryRunner.dropTable("event_setting_v2", true);
  }
}

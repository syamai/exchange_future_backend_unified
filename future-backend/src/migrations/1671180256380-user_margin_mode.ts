import { MarginMode } from "src/shares/enums/order.enum";
import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class userMarginMode1671180256380 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_margin_mode",
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
            name: "instrumentId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "contract",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "marginMode",
            type: "varchar(20)",
            isNullable: true,
            comment: Object.keys(MarginMode).join(","),
          },
          {
            name: "leverage",
            type: "decimal",
            precision: 22,
            scale: 8,
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
    await queryRunner.createIndices("user_margin_mode", [
      new TableIndex({
        columnNames: ["userId"],
        isUnique: false,
        name: "IDX-user_margin_mode-userId",
      }),
      new TableIndex({
        columnNames: ["instrumentId"],
        isUnique: false,
        name: "IDX-user_margin_mode-instrumentId",
      }),
      new TableIndex({
        columnNames: ["contract"],
        isUnique: false,
        name: "IDX-user_margin_mode-contract",
      }),
      new TableIndex({
        columnNames: ["marginMode"],
        isUnique: false,
        name: "IDX-user_margin_mode-marginMode",
      }),
      new TableIndex({
        columnNames: ["leverage"],
        isUnique: false,
        name: "IDX-user_margin_mode-leverage",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("user_margin_mode")) {
      await queryRunner.dropTable("user_margin_mode");
    }
  }
}

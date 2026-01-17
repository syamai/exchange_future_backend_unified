import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createPositionHistoryBySessionTable1748230000000
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "position_history_by_session",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
          },
          { name: "userId", type: "bigint", isNullable: false },
          { name: "accountId", type: "bigint", isNullable: false },
          {
            name: "userEmail",
            type: "varchar",
            length: "500",
            isNullable: false,
          },
          { name: "positionId", type: "bigint", isNullable: false },
          { name: "openTime", type: "datetime", isNullable: false },
          { name: "closeTime", type: "datetime", isNullable: true },
          { name: "symbol", type: "varchar", isNullable: false },
          { name: "leverages", type: "varchar", isNullable: false },
          { name: "marginMode", type: "varchar", isNullable: false },
          { name: "side", type: "varchar", length: "10", isNullable: false },
          {
            name: "sumEntryPrice",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          { name: "numOfOpenOrders", type: "int", isNullable: false },
          {
            name: "sumClosePrice",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: true,
          },
          { name: "numOfCloseOrders", type: "int", isNullable: true },
          {
            name: "minMargin",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "maxMargin",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "sumMargin",
            type: "decimal",
            precision: 30,
            scale: 15,
            isNullable: false,
          },
          {
            name: "minSize",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "maxSize",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "sumSize",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "minValue",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "maxValue",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "sumValue",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "pnl",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: true,
          },
          {
            name: "fee",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: true,
          },
          {
            name: "pnlRate",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
            isNullable: true,
          },
          { name: "status", type: "varchar", isNullable: false },
          { name: "createdAt", type: "datetime", default: "CURRENT_TIMESTAMP" },
          { name: "updatedAt", type: "datetime", default: "CURRENT_TIMESTAMP" },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropTable("position_history_by_session");
  }
}

import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class AddTableUser_Statistics1747307653466 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "user_statistics",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            unsigned: true,
          },
          {
            name: "totalDeposit",
            type: "decimal",
            precision: 30,
            scale: 8,
            default: 0,
          },
          {
            name: "totalWithdrawal",
            type: "decimal",
            precision: 30,
            scale: 8,
            default: 0,
          },
          {
            name: "pnlGain",
            type: "decimal",
            precision: 30,
            scale: 8,
            default: 0,
          },
          {
            name: "peakAssetValue",
            type: "decimal",
            precision: 30,
            scale: 8,
            default: 0,
          },
          {
            name: "pnlLoss",
            type: "decimal",
            precision: 30,
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
            onUpdate: "CURRENT_TIMESTAMP",
          },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

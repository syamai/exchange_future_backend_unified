import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class createTableMarketFee1673579292813 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "market_fee",
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
            name: "instrumentId",
            type: "bigint",
            unsigned: true,
            default: null,
          },
          {
            name: "makerFee",
            type: "decimal",
            precision: 22,
            scale: 8,
            default: 0,
          },
          {
            name: "takerFee",
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
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("market_fee"))
      await queryRunner.dropTable("market_fee");
  }
}

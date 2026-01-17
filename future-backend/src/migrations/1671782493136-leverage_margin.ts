import { MigrationInterface, QueryRunner, Table } from "typeorm";

export class leverageMargin1671782493136 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "leverage_margin",
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
            name: "tier",
            type: "int",
            unsigned: true,
            default: 0,
          },
          {
            name: "instrumentId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "min",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "max",
            type: "decimal",
            precision: 30,
            scale: 15,
            default: 0,
          },
          {
            name: "maxLeverage",
            type: "int",
            unsigned: true,
            default: 0,
          },
          {
            name: "maintenanceMarginRate",
            type: "int",
            unsigned: true,
            default: 0,
          },
          {
            name: "maintenanceAmount",
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
    if (await queryRunner.hasTable("leverage_margin"))
      await queryRunner.dropTable("leverage_margin");
  }
}

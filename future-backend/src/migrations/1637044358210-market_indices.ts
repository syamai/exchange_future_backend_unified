import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class marketIndice1637044358210 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "market_indices",
        columns: [
          {
            name: "id",
            type: "int",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
            unsigned: true,
          },
          {
            name: "symbol",
            type: "varchar",
            isNullable: false,
          },
          {
            name: "price",
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
      })
    );
    await queryRunner.createIndices("market_indices", [
      new TableIndex({
        columnNames: ["createdAt"],
        isUnique: false,
        name: "IDX-market_indices-createdAt",
      }),
      new TableIndex({
        columnNames: ["symbol"],
        isUnique: false,
        name: "IDX-market_indices-symbol",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("market_indices")) {
      await queryRunner.dropTable("market_indices");
    }
  }
}

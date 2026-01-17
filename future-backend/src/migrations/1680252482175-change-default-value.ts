import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class changeDefaultValue1680252482175 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await Promise.all([
      queryRunner.changeColumn(
        "instruments",
        "contractType",
        new TableColumn({
          name: "contractType",
          type: "varchar",
          default: "'USD_M'",
          isNullable: true,
        })
      ),
      queryRunner.changeColumn(
        "orders",
        "contractType",
        new TableColumn({
          name: "contractType",
          type: "varchar",
          default: "'USD_M'",
          isNullable: true,
        })
      ),
      queryRunner.changeColumn(
        "positions",
        "contractType",
        new TableColumn({
          name: "contractType",
          type: "varchar",
          default: "'USD_M'",
          isNullable: true,
        })
      ),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("instruments", "contractType");
  }
}

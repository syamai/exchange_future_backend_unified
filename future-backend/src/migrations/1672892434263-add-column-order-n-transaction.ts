import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnOrderNTransaction1672892434263
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("positions", [
      new TableColumn({
        name: "asset",
        type: "varchar",
        isNullable: true,
      }),
    ]);
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "asset",
        type: "varchar",
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("positions", ["asset"]);
    await queryRunner.dropColumns("orders", ["asset"]);
  }
}

import { MigrationInterface, QueryRunner, TableColumn, TableIndex } from "typeorm";

export class addTmpIdColumnIntoOrders1725598382266 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "tmpId",
        type: "varchar(50)",
        isNullable: true
      })
    );

    await queryRunner.createIndex(
      "orders",
      new TableIndex({
        name: "IDX_orders_tmpId",
        columnNames: ["tmpId"]
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "tmpId");
  }
}

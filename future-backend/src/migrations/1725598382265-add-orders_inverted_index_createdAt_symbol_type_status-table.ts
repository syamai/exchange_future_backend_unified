import { MigrationInterface, QueryRunner, Table, TableColumn } from "typeorm";

export class orderInvertedIndexCreatedAtSymbolTypeStatus1725598382265 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "orders_inverted_index_createdAt_symbol_type_status",
        columns: [
          {
            isPrimary: true,
            name: "createdAt",
            type: "varchar(15)",
          },
          {
            name: "value",
            type: "longtext",
            isNullable: false,
          },
        ],
      }),
      true
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("orders_inverted_index_createdAt_symbol_type_status"))
      await queryRunner.dropTable("orders_inverted_index_createdAt_symbol_type_status");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnContractTypeTransaction1680252169840
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "transactions",
      new TableColumn({
        name: "contractType",
        type: "varchar",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("transactions", "contractType");
  }
}

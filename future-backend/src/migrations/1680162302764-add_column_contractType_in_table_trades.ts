import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnContractTypeInTableTrades1680162302764
  implements MigrationInterface {
  name = "addColumnContractTypeInTableTrades1680162302764";

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "trades",
      new TableColumn({
        name: "contractType",
        type: "varchar",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("trades", "contractType");
  }
}

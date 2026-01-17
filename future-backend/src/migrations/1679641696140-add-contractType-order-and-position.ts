import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addContractTypeOrderAndPosition1679641696140
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "contractType",
        type: "varchar",
        default: "'USDM'",
        isNullable: true,
      })
    );
    await queryRunner.addColumn(
      "positions",
      new TableColumn({
        name: "contractType",
        type: "varchar",
        default: "'USDM'",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "contractType");
    await queryRunner.dropColumn("positions", "contractType");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addContractTypeForLeverageMargin1681286807083
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "leverage_margin",
      new TableColumn({
        name: "contractType",
        type: "varchar(7)",
        default: null,
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("leverage_margin", "contractType");
  }
}

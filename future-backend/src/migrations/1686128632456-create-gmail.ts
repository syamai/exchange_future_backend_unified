import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class createGmail1686128632456 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "orders",
      new TableColumn({
        name: "userEmail",
        type: "varchar",
        default: null,
        isNullable: true,
      })
    );
    await queryRunner.addColumn(
      "accounts",
      new TableColumn({
        name: "userEmail",
        type: "varchar",
        default: null,
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "userEmail");
    await queryRunner.dropColumn("accounts", "userEmail");
  }
}

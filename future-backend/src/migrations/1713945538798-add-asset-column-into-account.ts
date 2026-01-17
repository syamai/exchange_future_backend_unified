import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addAssetColumnIntoAccount1713945538798
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "accounts",
      new TableColumn({
        name: "asset",
        type: "varchar(255)",
        isNullable: true,
        default: null,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("accounts", "asset");
  }
}

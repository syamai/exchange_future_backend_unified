import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addAssetToFundingHistory1677063866984
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("funding_histories", [
      new TableColumn({
        name: "asset",
        type: "varchar(10)",
        isNullable: true,
        default: null,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("funding_histories", "asset");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addFundingIntervalInFundingHistory1677206143246
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "funding_histories",
      new TableColumn({
        name: "fundingInterval",
        type: "varchar",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("funding_histories", "fundingInterval");
  }
}

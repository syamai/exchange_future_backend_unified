import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addNextFundingInFundingTable1692778399113
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "fundings",
      new TableColumn({
        name: "nextFunding",
        type: "bigint",
        default: 0,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("fundings", "nextFunding");
  }
}

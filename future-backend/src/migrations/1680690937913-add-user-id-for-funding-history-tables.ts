import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addUserIdForFundingHistoryTables1680690937913
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "funding_histories",
      new TableColumn({
        name: "userId",
        type: "bigint",
        default: 0,
      })
    );

    await queryRunner.addColumn(
      "funding_histories",
      new TableColumn({
        name: "contractType",
        type: "varchar",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("funding_histories", "userId");
    await queryRunner.dropColumn("funding_histories", "contractType");
  }
}

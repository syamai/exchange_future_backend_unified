import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addCoinImageInCoinInfoTable1679047573299
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "coin_info",
      new TableColumn({
        name: "coin_image",
        type: "TEXT",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("coin_info", "coin_image");
  }
}

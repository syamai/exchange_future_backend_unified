import { AssetType } from "src/modules/transaction/transaction.const";
import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnAssetTypeTableAsset1743748147015 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("assets");
    const hasColumn = table?.findColumnByName("assetType");

    if (!hasColumn) {
      await queryRunner.addColumns("assets", [
        new TableColumn({
          name: "assetType",
          type: "varchar(256)",
          default: null,
          isNullable: true,
          comment: `asset type`,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

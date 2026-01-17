import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addEnablePriceChangeFireBaseFavoritedAtToUserSettings1754537000000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_settings");
    const hasEnablePriceChangeFireBaseColumn = table?.findColumnByName("enablePriceChangeFireBase");
    const hasFavoritedAtColumn = table?.findColumnByName("favoritedAt");

    if (table && !hasEnablePriceChangeFireBaseColumn) {
      await queryRunner.addColumns("user_settings", [
        new TableColumn({
          name: "enablePriceChangeFireBase",
          type: "boolean",
          default: true,
          isNullable: true,
        }),
      ]);
    }

    if (table && !hasFavoritedAtColumn) {
      await queryRunner.addColumns("user_settings", [
        new TableColumn({
          name: "favoritedAt",
          type: "timestamp",
          isNullable: true,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("user_settings");
    
    if (table?.findColumnByName("enablePriceChangeFireBase")) {
      await queryRunner.dropColumn("user_settings", "enablePriceChangeFireBase");
    }
    
    if (table?.findColumnByName("favoritedAt")) {
      await queryRunner.dropColumn("user_settings", "favoritedAt");
    }
  }
}

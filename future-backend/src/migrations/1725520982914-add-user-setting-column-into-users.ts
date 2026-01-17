import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addUserSettingColumnIntoUsers1725520982914 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "users",
      new TableColumn({
        name: "allowTrade",
        type: "boolean",
        default: true,
      })
    );

    await queryRunner.addColumn(
      "users",
      new TableColumn({
        name: "enableTradingFee",
        type: "boolean",
        default: true,
      })
    );

    await queryRunner.addColumn(
      "users",
      new TableColumn({
        name: "isMarketMaker",
        type: "boolean",
        default: false,
      })
    );

    await queryRunner.addColumn(
      "users",
      new TableColumn({
        name: "preTradingPair",
        type: "json",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("users", "allowTrade");
    await queryRunner.dropColumn("users", "enableTradingFee");
    await queryRunner.dropColumn("users", "isMarketMaker");
    await queryRunner.dropColumn("users", "preTradingPair");
  }
}

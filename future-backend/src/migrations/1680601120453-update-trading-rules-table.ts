import { MigrationInterface, QueryRunner } from "typeorm";

export class updateTradingRulesTable1680601120453
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    // await queryRunner.renameColumn(
    //   "trading_rules",
    //   "minOrderPrice",
    //   "minOrderAmount"
    // );
    // await queryRunner.renameColumn(
    //   "trading_rules",
    //   "maxOrderPrice",
    //   "maxOrderAmount"
    // );
    await queryRunner.query("ALTER TABLE `trading_rules` CHANGE `minOrderPrice` `minOrderAmount` decimal(22,8) NULL DEFAULT NULL")
    await queryRunner.query("ALTER TABLE `trading_rules` CHANGE `maxOrderPrice` `maxOrderAmount` decimal(22,8) NULL DEFAULT NULL")
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("trading_rules", "minOrderAmount");
    await queryRunner.dropColumn("trading_rules", "maxOrderAmount");
  }
}

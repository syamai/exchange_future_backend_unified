import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class updateUserSetting1680489898786 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      "ALTER TABLE `user_settings` DROP INDEX `IDX-user_settings-userId_key_value`"
    );
    await queryRunner.dropColumn("user_settings", "value");
    await queryRunner.addColumns("user_settings", [
      new TableColumn({
        name: "limitOrder",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "marketOrder",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "stopLimitOrder",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "stopMarketOrder",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "traillingStopOrder",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "takeProfitTrigger",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "stopLossTrigger",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "fundingFeeTriggerValue",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      }),
      new TableColumn({
        name: "fundingFeeTrigger",
        type: "boolean",
        default: "0",
      }),
      new TableColumn({
        name: "isFavorite",
        type: "boolean",
        default: "0",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("user_settings", "limitOrder");
  }
}

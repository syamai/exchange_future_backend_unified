import { MigrationInterface, QueryRunner } from "typeorm";

export class removeColumnTable1679976131405 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("positions", [
      "initMargin",
      "maintainMargin",
      "openOrderMargin",
      "openOrderBuyQty",
      "openOrderSellQty",
      "openOrderBuyValue",
      "openOrderSellValue",
      "openOrderValue",
      "requiredInitMarginPercent",
      "requiredMaintainMarginPercent",
      "maxLiquidationBalance",
      "liquidationOrderId",
      "closedId",
      "closedPrice",
    ]);
    await queryRunner.dropColumns("margin_histories", [
      "initMargin",
      "initMarginAfter",
      "maintainMargin",
      "maintainMarginAfter",
      "openOrderMargin",
      "openOrderMarginAfter",
      "openOrderBuyQty",
      "openOrderSellQty",
      "openOrderBuyValue",
      "openOrderSellValue",
      "openOrderValue",
      "openOrderValueAfter",
      "liquidationOrderId",
      "liquidationOrderIdAfter",
      "crossBalance",
      "crossBalanceAfter",
      "crossMargin",
      "crossMarginAfter",
      "isolatedBalance",
      "isolatedBalanceAfter",
      "maxAvailableBalance",
      "maxAvailableBalanceAfter",
      "orderMargin",
      "orderMarginAfter",
      "extraMargin",
      "extraMarginAfter",
      "latestRealisedPnl",
      "latestRealisedPnlAfter",
      "accountUnrealisedPnl",
      "accountUnrealisedPnlAfter",
      "availableBalance",
      "availableBalanceAfter",
    ]);
    await queryRunner.dropColumns("position_histories", [
      "initMargin",
      "initMarginAfter",
    ]);
  }

  public async down(): Promise<void> {}
}

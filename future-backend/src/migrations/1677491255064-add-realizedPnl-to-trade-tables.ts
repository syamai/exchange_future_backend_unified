import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addRealizedPnlToTradeTables1677491255064
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("trades", [
      new TableColumn({
        name: "realizedPnlOrderBuy",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
      new TableColumn({
        name: "realizedPnlOrderSell",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("trades", "realizedPnlOrderBuy");
    await queryRunner.dropColumn("trades", "realizedPnlOrderSell");
  }
}

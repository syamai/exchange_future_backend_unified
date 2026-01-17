import { TpSlType } from "src/shares/enums/order.enum";
import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class updateOrderTable1672384828737 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "isPostOnly");
    await queryRunner.dropColumn("orders", "isHidden");
    await queryRunner.dropColumn("orders", "unrealisedPnl");
    await queryRunner.dropColumn("orders", "takeProfit");
    await queryRunner.dropColumn("orders", "stopLoss");
    await queryRunner.changeColumn(
      "orders",
      "instrumentSymbol",
      new TableColumn({
        name: "symbol",
        type: "varchar(20)",
        isNullable: true,
      })
    );
    await queryRunner.changeColumn(
      "orders",
      "stopType",
      new TableColumn({
        name: "tpSLType",
        type: "varchar(20)",
        isNullable: true,
        comment: Object.keys(TpSlType).join(","),
      })
    );
    await queryRunner.query("ALTER TABLE `orders` CHANGE `stopPrice` `tpSLPrice` decimal(22,8) NULL");
    // await queryRunner.changeColumn(
    //   "orders",
    //   "stopPrice",
    //   new TableColumn({
    //     name: "tpSlPrice",
    //     type: "decimal",
    //     precision: 22,
    //     scale: 8,
    //     isNullable: true,
    //     // default: null,
    //   })
    // );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "symbol");
  }
}

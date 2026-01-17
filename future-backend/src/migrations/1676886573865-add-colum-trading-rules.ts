import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumTradingRules1676886573865 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("trading_rules", [
      new TableColumn({
        name: "maxNotinal",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
      new TableColumn({
        name: "symbol",
        type: "varchar",
        isNullable: false,
      }),
      new TableColumn({
        name: "maxOrderPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
    ]);
    await queryRunner.dropColumn("trading_rules", "instrumentId");
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("trading_rules", "maxNotinal");
    await queryRunner.dropColumn("trading_rules", "symbol");
    await queryRunner.dropColumn("trading_rules", "maxOrderPrice");
  }
}

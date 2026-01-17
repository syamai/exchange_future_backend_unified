import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addMaxLevarageColumn1672994187054 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("trading_rules", [
      new TableColumn({
        name: "maxLeverage",
        type: "int",
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("trading_rules", "maxLeverage");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class changeTypeMarginRate1676449007048 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "leverage_margin",
      "maintenanceMarginRate",
      new TableColumn({
        name: "maintenanceMarginRate",
        type: "DECIMAL(30,15)",
        isNullable: true,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("leverage_margin", "maintenanceMarginRate");
  }
}

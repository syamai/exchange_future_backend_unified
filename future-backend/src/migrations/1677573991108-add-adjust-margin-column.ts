import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addAdjustMarginColumn1677573991108 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("positions", [
      new TableColumn({
        name: "adjustMargin",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "adjustMargin");
  }
}

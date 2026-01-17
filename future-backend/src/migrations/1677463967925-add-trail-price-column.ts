import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addTrailPriceColumn1677463967925 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "trailPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: "0",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "trailPrice");
  }
}

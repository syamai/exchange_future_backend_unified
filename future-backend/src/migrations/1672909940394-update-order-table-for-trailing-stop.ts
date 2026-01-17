import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class updateOrderTableForTrailingStop1672909940394
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "callbackRate",
        type: "decimal",
        isNullable: true,
        precision: 22,
        scale: 1,
      }),
      new TableColumn({
        name: "activationPrice",
        type: "decimal",
        isNullable: true,
        precision: 22,
        scale: 8,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "callbackRate");
    await queryRunner.dropColumn("orders", "activationPrice");
  }
}

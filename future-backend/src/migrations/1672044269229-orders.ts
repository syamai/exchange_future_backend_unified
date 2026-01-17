import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class orders1672044269229 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "takeProfit",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
      new TableColumn({
        name: "stopLoss",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
        default: null,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("orders", "takeProfit");
    await queryRunner.dropColumn("orders", "stopLoss");
  }
}

import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class assetPositionHistory1672893965245 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("position_histories", [
      new TableColumn({
        name: "asset",
        type: "varchar",
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("position_histories", ["asset"]);
  }
}

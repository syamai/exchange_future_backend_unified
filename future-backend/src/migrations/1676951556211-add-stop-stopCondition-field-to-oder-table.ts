import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addStopStopConditionFieldToOderTable1676951556211
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "stopCondition",
        type: "varchar(10)",
        isNullable: true,
        default: null,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("orders", ["stopCondition"]);
  }
}

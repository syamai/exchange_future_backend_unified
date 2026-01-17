import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addIsHiddenOrderLinkedId1677066585872
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("orders", [
      new TableColumn({
        name: "linkedOrderId",
        type: "int",
        unsigned: true,
        isNullable: true,
      }),
      new TableColumn({
        name: "isHidden",
        type: "boolean",
        isNullable: true,
      }),
    ]);
  }

  public async down(): Promise<void> {}
}

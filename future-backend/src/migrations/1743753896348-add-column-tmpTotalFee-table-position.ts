import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnTmpTotalFeeTablePosition1743753896348 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("positions");
    const hasColumn = table?.findColumnByName("tmpTotalFee");

    if (!hasColumn) {
      await queryRunner.addColumns("positions", [
        new TableColumn({
          name: "tmpTotalFee",
          type: "decimal",
          default: null,
          precision: 22,
          scale: 8,
          isNullable: true,
          comment: `temperature fee`,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

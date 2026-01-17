import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnThumbnailTableInstrument1743753800244 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const table = await queryRunner.getTable("instruments");
    const hasColumn = table?.findColumnByName("thumbnail");

    if (!hasColumn) {
      await queryRunner.addColumns("instruments", [
        new TableColumn({
          name: "thumbnail",
          type: "varchar(1000)",
          default: null,
          isNullable: true,
          comment: `image url`,
        }),
      ]);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {}
}

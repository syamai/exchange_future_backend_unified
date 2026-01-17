import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumsInstrument1676886076629 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("instruments", [
      new TableColumn({
        name: "minPriceMovement",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
      new TableColumn({
        name: "maxFiguresForSize",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
      new TableColumn({
        name: "maxFiguresForPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
      new TableColumn({
        name: "impactMarginNotional",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("instruments", "minPriceMovement");
    await queryRunner.dropColumn("instruments", "maxFiguresForSize");
    await queryRunner.dropColumn("instruments", "maxFiguresForPrice");
    await queryRunner.dropColumn("instruments", "impactMarginNotional");
  }
}

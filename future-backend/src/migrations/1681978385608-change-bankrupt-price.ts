import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class changeBankruptPrice1681978385608 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "positions",
      "bankruptPrice",
      new TableColumn({
        name: "bankruptPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        isNullable: true,
      })
    );
  }

  public async down(): Promise<void> {}
}

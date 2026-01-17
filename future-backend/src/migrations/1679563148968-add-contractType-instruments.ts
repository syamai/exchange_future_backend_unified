import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addContractTypeInstruments1679563148968
  implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumns("instruments", [
      new TableColumn({
        name: "contractType",
        type: "varchar",
        default: "'USDM'",
        isNullable: true,
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("instruments", "contractType");
  }
}

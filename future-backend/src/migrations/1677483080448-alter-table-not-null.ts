import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class alterTableNotNull1677483080448 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "accounts",
      "usdtAvailableBalance",
      new TableColumn({
        name: "usdtAvailableBalance",
        type: "decimal",
        precision: 30,
        scale: 15,
        default: 0,
        isNullable: true,
      })
    );
    await queryRunner.changeColumn(
      "accounts",
      "usdAvailableBalance",
      new TableColumn({
        name: "usdAvailableBalance",
        type: "decimal",
        precision: 30,
        scale: 15,
        default: 0,
        isNullable: true,
      })
    );
    await queryRunner.changeColumn(
      "positions",
      "liquidationPrice",
      new TableColumn({
        name: "liquidationPrice",
        type: "decimal",
        precision: 22,
        scale: 8,
        default: 0,
        isNullable: true,
      })
    );
  }

  public async down(): Promise<void> {}
}

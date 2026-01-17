import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class addColumnRevokeInAccessToken1682414643853
  implements MigrationInterface {
  name = "addColumnRevokeInAccessToken1682414643853";

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.addColumn(
      "access-tokens",
      new TableColumn({
        name: "revoked",
        type: "boolean",
        default: false,
      })
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumn("access-tokens", "revoked");
  }
}

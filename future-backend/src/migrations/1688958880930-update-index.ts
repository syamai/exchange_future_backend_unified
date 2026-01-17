import { MigrationInterface, QueryRunner, TableIndex } from "typeorm";

export class updateIndex1688958880930 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    const tableIndices = await queryRunner.getTable("trades");

    // return;
    for (const index of tableIndices.indices) {
      if ((index.columnNames = ["id"])) {
        console.log(index, "iddd");

        continue;
      }
      await queryRunner.dropIndex(
        "trades",
        new TableIndex({
          columnNames: index.columnNames,
          name: index.name,
        })
      );
    }
    await Promise.all([
      queryRunner.createIndex(
        "trades",
        new TableIndex({
          columnNames: ["sellUserId", "symbol"],
        })
      ),
      queryRunner.createIndex(
        "trades",
        new TableIndex({
          columnNames: ["buyUserId", "symbol"],
        })
      ),
      queryRunner.createIndex(
        "trades",
        new TableIndex({
          columnNames: ["createdAt"],
        })
      ),
      queryRunner.createIndex(
        "trades",
        new TableIndex({
          columnNames: ["updatedAt"],
        })
      ),
    ]);
  }

  public async down(): Promise<void> {}
}

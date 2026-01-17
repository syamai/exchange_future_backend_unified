import { MigrationInterface, QueryRunner } from "typeorm";

export class removeVertexColumn1676969249615 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.dropColumns("orders", ["vertexPrice", "trailValue"]);
  }

  public async down(): Promise<void> {}
}

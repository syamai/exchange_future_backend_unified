import { OrderType } from "src/shares/enums/order.enum";
import { MigrationInterface, QueryRunner, TableColumn } from "typeorm";

export class removeTypePostonly1675761513812 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.changeColumn(
      "orders",
      "type",
      new TableColumn({
        name: "type",
        type: "varchar(6)",
        comment: Object.keys(OrderType).join(","),
        default: `'${OrderType.LIMIT}'`,
      })
    );
  }

  public async down(): Promise<void> {}
}

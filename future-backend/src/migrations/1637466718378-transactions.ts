import {
  TransactionStatus,
  TransactionType,
} from "src/shares/enums/transaction.enum";
import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class transactions1637466718378 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "transactions",
        columns: [
          {
            name: "id",
            type: "bigint",
            isPrimary: true,
            isGenerated: true,
            generationStrategy: "increment",
            unsigned: true,
          },
          {
            name: "userId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "accountId",
            type: "bigint",
            isNullable: false,
          },
          {
            name: "amount",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "fee",
            type: "decimal",
            precision: 22,
            scale: 8,
            isNullable: false,
          },
          {
            name: "status",
            type: "varchar(20)",
            isNullable: false,
            comment: Object.keys(TransactionStatus).join(","),
          },
          {
            name: "type",
            type: "varchar(20)",
            isNullable: false,
            comment: Object.keys(TransactionType).join(","),
          },
          {
            name: "txHash",
            type: "char",
            precision: 90,
            isNullable: true,
          },
          {
            name: "logIndex",
            type: "int",
            unsigned: true,
            isNullable: true,
          },
          {
            name: "operationId",
            type: "bigint",
            unsigned: true,
            default: 0,
          },
          {
            name: "createdAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
          {
            name: "updatedAt",
            type: "datetime",
            default: "CURRENT_TIMESTAMP",
          },
        ],
      })
    );
    await queryRunner.createIndices("transactions", [
      new TableIndex({
        columnNames: ["txHash", "logIndex"],
        isUnique: true,
        name: "IDX-transactions-txHash-logIndex",
      }),
      new TableIndex({
        columnNames: ["userId"],
        isUnique: false,
        name: "IDX-transactions-userId",
      }),
      new TableIndex({
        columnNames: ["accountId", "type"],
        isUnique: false,
        name: "IDX-transactions-accountId_type",
      }),
      new TableIndex({
        columnNames: ["operationId"],
        isUnique: false,
        name: "IDX-transactions-operationId",
      }),
      new TableIndex({
        columnNames: ["createdAt", "type", "accountId"],
        isUnique: false,
        name: "IDX-transactions-createdAt_type_accountId",
      }),

      new TableIndex({
        columnNames: ["type", "status"],
        isUnique: false,
        name: "IDX-transactions-type_status",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("transactions"))
      await queryRunner.dropTable("transactions");
  }
}

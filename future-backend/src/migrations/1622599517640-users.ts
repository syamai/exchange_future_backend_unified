import {
  UserIsLocked,
  UserRole,
  UserStatus,
  UserType,
} from "src/shares/enums/user.enum";
import { MigrationInterface, QueryRunner, Table, TableIndex } from "typeorm";

export class users1622599517640 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.createTable(
      new Table({
        name: "users",
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
            name: "email",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "position",
            type: "varchar",
            isNullable: true,
          },
          {
            name: "role",
            type: "varchar(20)",
            isNullable: false,
            default: `'${UserRole.USER}'`,
            comment: Object.keys(UserRole).join(","),
          },
          {
            name: "userType",
            type: "varchar(20)",
            isNullable: false,
            default: `'${UserType.UNRESTRICTED}'`,
            comment: Object.keys(UserType).join(","),
          },
          {
            name: "isLocked",
            type: "varchar(20)",
            isNullable: true,
            default: `'${UserIsLocked.UNLOCKED}'`,
            comment: Object.keys(UserIsLocked).join(","),
          },
          {
            name: "status",
            type: "varchar(20)",
            default: `'${UserStatus.ACTIVE}'`,
            comment: Object.keys(UserStatus).join(","),
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
      }),
      true
    );
    await queryRunner.createIndices("users", [
      new TableIndex({
        columnNames: ["role"],
        isUnique: false,
        name: "IDX-users-role",
      }),
      new TableIndex({
        columnNames: ["userType"],
        isUnique: false,
        name: "IDX-users-userType",
      }),
      new TableIndex({
        columnNames: ["email"],
        isUnique: true,
        name: "IDX-users-email",
      }),
      new TableIndex({
        columnNames: ["isLocked"],
        isUnique: false,
        name: "IDX-users-isLocked",
      }),
      new TableIndex({
        columnNames: ["status"],
        isUnique: false,
        name: "IDX-users-status",
      }),
    ]);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable("users"))
      await queryRunner.dropTable("users");
  }
}

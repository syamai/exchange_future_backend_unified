import { TypeOrmModuleOptions } from "@nestjs/typeorm";
import { getConfig } from "src/configs/index";

export interface DatabaseConfig {
  type: "mysql";
  host: string;
  port: number;
  username: string;
  password: string;
  database: string;
  entities: string[];
  logging: boolean;
}

export const masterConfig = {
  ...getConfig().get<DatabaseConfig>("_master"),
  name: "master",
  entities: [__dirname + "/../models/entities/**/*{.ts,.js}"],
  autoLoadEntities: true,
  loading: true,
  synchronize: false,
  // Use mysql2 driver for MySQL 8 compatibility
  driver: require("mysql2"),
  // logging: true,
} as TypeOrmModuleOptions;

export const reportConfig = {
  ...getConfig().get<DatabaseConfig>("report"),
  name: "report",
  entities: [__dirname + "/../models/entities/**/*{.ts,.js}"],
  autoLoadEntities: true,
  loading: true,
  synchronize: false,
  // Use mysql2 driver for MySQL 8 compatibility
  driver: require("mysql2"),
  // logging: true,
} as TypeOrmModuleOptions;

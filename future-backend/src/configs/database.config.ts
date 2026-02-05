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
  // Connection pool settings optimized for high TPS with many pods
  extra: {
    connectionLimit: 10, // Reduced from 50 to allow more pods (10 pods × 10 conn × 2 = 200)
    queueLimit: 100, // Limit queue to prevent infinite waiting
    waitForConnections: true,
    connectTimeout: 10000, // 10s connection timeout
    acquireTimeout: 10000, // 10s acquire timeout
    enableKeepAlive: true,
    keepAliveInitialDelay: 10000,
  },
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
  // Connection pool settings optimized for high TPS with many pods
  extra: {
    connectionLimit: 10, // Reduced from 50 to allow more pods
    queueLimit: 100, // Limit queue to prevent infinite waiting
    waitForConnections: true,
    connectTimeout: 10000, // 10s connection timeout
    acquireTimeout: 10000, // 10s acquire timeout
    enableKeepAlive: true,
    keepAliveInitialDelay: 10000,
  },
  // logging: true,
} as TypeOrmModuleOptions;

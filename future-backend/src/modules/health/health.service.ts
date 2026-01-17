import { HttpException, HttpStatus, Injectable, Logger } from "@nestjs/common";
import { InjectConnection } from "@nestjs/typeorm";
import { createClient } from "redis";
import { kafka } from "src/configs/kafka";
import { redisConfig } from "src/configs/redis.config";
import { Connection } from "typeorm";

@Injectable()
export class HealthService {
  constructor(
    @InjectConnection("report")
    private reportConnection: Connection,
    @InjectConnection("master")
    private masterConnection: Connection,
    private readonly logger: Logger
  ) {}

  async getHealth(): Promise<Record<string, unknown>> {
    // check redis health
    const redisClient = createClient(redisConfig.port, redisConfig.host);
    const check = (): Promise<string> => {
      return new Promise<string>((resolve, _reject) => {
        redisClient.set("health", `${new Date().getTime()}`, (err, data) => {
          if (err) {
            throw new HttpException(
              "Failed check health redis",
              HttpStatus.INTERNAL_SERVER_ERROR
            );
          } else if (data) {
            resolve("Success check health redis");
          } else {
            throw new HttpException(
              "Failed check health redis",
              HttpStatus.INTERNAL_SERVER_ERROR
            );
          }
        });
      });
    };
    const redis = await check();
    redisClient.quit();

    // check mysql health
    const query = "SELECT 1";
    let mysql: string;
    try {
      Promise.all([
        this.masterConnection.query(query),
        this.reportConnection.query(query),
      ]);
      mysql = "Success check health mysql";
    } catch (e) {
      this.logger.error(e);
      throw new HttpException(
        "Failed check health mysql",
        HttpStatus.INTERNAL_SERVER_ERROR
      );
    }

    // check mysql health
    let _kafka: string;
    const consumer = kafka.consumer({ groupId: "checkhealth" });
    const producer = kafka.producer();

    try {
      await consumer.connect();
      await consumer.disconnect();
      await producer.connect();
      await producer.disconnect();
      _kafka = "Success check health kafka";
    } catch (e) {
      this.logger.error(e);
      throw new HttpException(
        "Failed check health kafka",
        HttpStatus.INTERNAL_SERVER_ERROR
      );
    }

    return {
      mysql,
      redis,
      kafka: _kafka,
    };
  }
}

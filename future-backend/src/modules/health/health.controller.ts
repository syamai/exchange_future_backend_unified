import { CACHE_MANAGER, Controller, Get, Inject } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { HealthService } from "src/modules/health/health.service";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { Cache } from "cache-manager";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { CommandCode } from "../matching-engine/matching-engine.const";
import * as mysql from 'mysql2/promise';
@ApiTags("health")
@Controller("ping")
export class HealthController {
  @Inject(CACHE_MANAGER) private cacheManager: Cache;
  constructor(private healthService: HealthService, private kafkaClient: KafkaClient) {}

  @Get()
  async getHealth(): Promise<ResponseDto<Record<string, unknown>>> {
    return { data: await this.healthService.getHealth() };
  }

  @Get("/check-load")
  async checkLoad(): Promise<String> {
    return "success";
  }

  @Get("/start-measure-tps")
  async startMeasureTps(): Promise<String> {
    await this.cacheManager.set("numOfActiveOrderSavedToDb", 0, {
      ttl: 3600000000000,
    });
    await this.cacheManager.set("numOfCancelledOrderSavedToDb", 0, {
      ttl: 3600000000000,
    });
    await this.cacheManager.set("numOfFilledOrderSavedToDb", 0, {
      ttl: 3600000000000,
    }); 
    // await this.cacheManager.set("numOfTradesBeforeSendToClient", 0, {
    //   ttl: 3600000000000,
    // });
    // await this.cacheManager.set("numOfTradesSentToClient", 0, {
    //   ttl: 3600000000000,
    // });
    // await this.cacheManager.set("numOfTradesSavedToDb", 0, {
    //   ttl: 3600000000000,
    // });
    // await this.cacheManager.set("numOfOrdersConsumedFromKafka", 0, {
    //   ttl: 3600000000000,
    // });

    // await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
    //   code: CommandCode.START_MEASURE_TPS
    // });

    setTimeout(async () => {
      const numOfActiveOrderSavedToDb = await this.cacheManager.get("numOfActiveOrderSavedToDb");
      const numOfCancelledOrderSavedToDb = await this.cacheManager.get("numOfCancelledOrderSavedToDb");
      const numOfFilledOrderSavedToDb = await this.cacheManager.get("numOfFilledOrderSavedToDb");
      // const numOfTradesBeforeSendToClient = await this.cacheManager.get("numOfTradesBeforeSendToClient");
      // const numOfTradesSentToClient = await this.cacheManager.get("numOfTradesSentToClient");
      // const numOfTradesSavedToDb = await this.cacheManager.get("numOfTradesSavedToDb");
      // const numOfOrdersConsumedFromKafka = await this.cacheManager.get("numOfOrdersConsumedFromKafka");
      
      console.log(`numOfActiveOrderSavedToDb: ${numOfActiveOrderSavedToDb}`);
      console.log(`numOfCancelledOrderSavedToDb: ${numOfCancelledOrderSavedToDb}`);
      console.log(`numOfFilledOrderSavedToDb: ${numOfFilledOrderSavedToDb}`);
      // console.log(`numOfTradesBeforeSendToClient: ${numOfTradesBeforeSendToClient}`);
      // console.log(`numOfTradesSentToClient: ${numOfTradesSentToClient}`);
      // console.log(`numOfTradesSavedToDb: ${numOfTradesSavedToDb}`);
      // console.log(`numOfOrdersConsumedFromKafka: ${numOfOrdersConsumedFromKafka}`);
    }, 10 * 60000);
    return "success";
  }

  private async getQuestionsValue(connection) {
    const [rows] = await connection.execute("SHOW GLOBAL STATUS LIKE 'Questions'");
    return parseInt(rows[0].Value, 10);
  }

  @Get("/start-measure-qps")
  async startMeasureQps(): Promise<String> {
    await this.cacheManager.set("startQuestions", 0, {
      ttl: 3600000000000,
    });
    await this.cacheManager.set("endQuestions", 0, {
      ttl: 3600000000000,
    });

    const connection = await mysql.createConnection({
      host: process.env.MYSQL_HOST,
      port: parseInt(process.env.MYSQL_PORT, 10),
      user: process.env.MYSQL_USERNAME,
      password: process.env.MYSQL_PASSWORD,
      database: process.env.MYSQL_DATABASE
    });

    const startQuestions = await this.getQuestionsValue(connection);
    // await this.cacheManager.set("MEASURE_QPS:startQuestions", startQuestions, {
    //   ttl: 3600000000000,
    // });
    console.log(`[Measure QPS] Start measuring QPS at ${startQuestions}`);

    
    setTimeout(async () => {
      const endQuestions = await this.getQuestionsValue(connection);
      // const startQuestions = await this.cacheManager.get("MEASURE_QPS:startQuestions") as any as number;
      
      const qps = (endQuestions - startQuestions) / 60;
      
      console.log(`[Measure QPS] Queries executed in the last 60 seconds: ${endQuestions - startQuestions}`);
      console.log(`[Measure QPS] QPS (Queries per second): ${qps.toFixed(2)}`);

      await connection.end();
    }, 60 * 1000);
    return "success";
  }
}



import { serialize } from "class-transformer";
import { Producer } from "kafkajs";
import { BATCH_SIZE } from "src/modules/matching-engine/matching-engine.const";
import { OrderRouterService } from "src/shares/order-router/order-router.service";
import { KafkaTopics } from "src/shares/enums/kafka.enum";

export class BaseEngineService {
  public async loadData(
    producer: Producer,
    // eslint-disable-next-line
    getter: (
      fromId: number,
      batchSize: number
    ) => Promise<{ [key: string]: any }[]>,
    code: string,
    topic: string
  ): Promise<void> {
    let entities = [];
    let lastId = 0;
    do {
      entities = await getter(lastId, BATCH_SIZE);
      if (entities.length > 0) {
        await this.sendData(producer, topic, code, entities);
        lastId = entities[entities.length - 1].id;
      }
    } while (entities.length === BATCH_SIZE);
  }

  /**
   * Load data with sharding support - broadcasts to all shard preload topics
   */
  public async loadDataSharded(
    producer: Producer,
    // eslint-disable-next-line
    getter: (
      fromId: number,
      batchSize: number
    ) => Promise<{ [key: string]: any }[]>,
    code: string,
    isTest: boolean,
    orderRouterService: OrderRouterService
  ): Promise<void> {
    let entities = [];
    let lastId = 0;
    do {
      entities = await getter(lastId, BATCH_SIZE);
      if (entities.length > 0) {
        await this.sendDataToPreloadTopics(producer, code, entities, isTest, orderRouterService);
        lastId = entities[entities.length - 1].id;
      }
    } while (entities.length === BATCH_SIZE);
  }

  protected async sendData(
    producer: Producer,
    topic: string,
    code: string,
    // eslint-disable-next-line
    entities: { [key: string]: any }[]
  ): Promise<void> {
    const messages = entities.map((entity) => ({
      value: serialize({ code, data: entity }),
    }));
    await producer.send({ topic, messages });
  }

  /**
   * Send data to preload topics with sharding support
   * When sharding is enabled, broadcasts to all shard preload topics
   */
  protected async sendDataToPreloadTopics(
    producer: Producer,
    code: string,
    // eslint-disable-next-line
    entities: { [key: string]: any }[],
    isTest: boolean,
    orderRouterService: OrderRouterService
  ): Promise<void> {
    const messages = entities.map((entity) => ({
      value: serialize({ code, data: entity }),
    }));

    if (!isTest && orderRouterService.isShardingEnabled()) {
      // Broadcast to all shard preload topics
      const preloadTopics = orderRouterService.getAllPreloadTopics();
      for (const topic of preloadTopics) {
        await producer.send({ topic, messages });
      }
    } else {
      // Legacy single topic
      const topic = isTest
        ? KafkaTopics.test_matching_engine_preload
        : KafkaTopics.matching_engine_preload;
      await producer.send({ topic, messages });
    }
  }
}

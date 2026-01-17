import { serialize } from "class-transformer";
import { Producer } from "kafkajs";
import { BATCH_SIZE } from "src/modules/matching-engine/matching-engine.const";

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
}

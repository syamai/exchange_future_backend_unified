import { Injectable } from "@nestjs/common";
import { serialize } from "class-transformer";
import {
  Consumer,
  EachMessagePayload,
  Producer,
  RecordMetadata,
} from "kafkajs";
import { kafka } from "src/configs/kafka";
import { KafkaGroups } from "../enums/kafka.enum";

@Injectable()
export class KafkaClient {
  private producer: Producer;
  private producerConnected = false;
  private producerConnecting: Promise<void> | null = null;

  constructor() {
    this.producer = kafka.producer();
  }

  /**
   * Ensures producer is connected (lazy connection with singleton pattern)
   */
  private async ensureProducerConnected(): Promise<void> {
    if (this.producerConnected) return;

    // Prevent multiple simultaneous connection attempts
    if (this.producerConnecting) {
      await this.producerConnecting;
      return;
    }

    this.producerConnecting = this.producer.connect().then(() => {
      this.producerConnected = true;
      this.producerConnecting = null;
    });

    await this.producerConnecting;
  }

  /**
   *
   * @param topic Name of the topic to send message to
   * @param data Data to send to Kafka, will be jsonify to JSON string
   * @returns
   */
  async send<T>(topic: string, data: T): Promise<RecordMetadata[]> {
    await this.ensureProducerConnected();
    const result: RecordMetadata[] = await this.producer.send({
      topic: topic,
      messages: [
        {
          value: serialize(data),
        },
      ],
    });
    return result;
  }

  async sendPrice<T>(topic: string, data: T): Promise<RecordMetadata[]> {
    await this.ensureProducerConnected();
    const result: RecordMetadata[] = await this.producer.send({
      topic: topic,
      messages: [
        {
          value: serialize(data),
        },
      ],
    });
    return result;
  }

  /**
   *
   * @param topic Name of topic to create consumer
   * @param groupId Group id of consumer
   * @param callback Callback to handle each data message
   * @param options Options for consumer
   */
  async consume<T>(
    topic: string,
    groupId: string,
    callback: (data: T) => Promise<void>,
    options: { partitionsConsumedConcurrently?: number; [key: string]: any } = {}
  ): Promise<Consumer> {
    const { partitionsConsumedConcurrently = 10, ...subscribeOptions } = options;
    const consumer: Consumer = kafka.consumer({
      groupId: groupId,
    });
    await consumer.connect();
    await consumer.subscribe({
      topic: topic,
      fromBeginning: false,
      ...subscribeOptions,
    });
    await consumer.run({
      partitionsConsumedConcurrently,
      eachMessage: async (payload: EachMessagePayload) => {
        await callback(JSON.parse(payload.message.value.toString()));
      },
    });
    return consumer;
  }

  public async getMessageAtOffset(
    offset: string,
    topic: string
  ): Promise<void> {
    const consumer = kafka.consumer({
      groupId: KafkaGroups.matching_engine_saver_positions,
    });
    const options = {
      topic,
      fromBeginning: true,
    };

    await consumer.connect();
    await consumer.subscribe(options);

    await consumer.run({
      autoCommit: false,
      eachMessage: async (messagePayload: EachMessagePayload) => {
        const { topic, partition, message } = messagePayload;
        const prefix = `${topic}[${partition} | ${message.offset}] / ${message.timestamp}`;
        if (message.offset == offset) {
          console.log(`- ${prefix} ${message.key}#${message.value}`);
          console.log(
            "____MEsssage: ____",
            message.key,
            "_______",
            message.value
          );
        }
      },
    });
    await consumer.seek({
      topic,
      partition: 0,
      offset: offset,
    });
  }
  /**
   *
   * @param topics Name of the topics to delete
   * @returns
   */
  async delete(topics: string[]): Promise<void> {
    const admin = kafka.admin();
    await admin.connect();
    const currentTopics = await admin.listTopics();
    const existedTopic = topics.filter((topic) =>
      currentTopics.includes(topic)
    );
    await admin.deleteTopics({ topics: existedTopic });
    await admin.disconnect();
  }

  async getCombinedLag(topic: string, groupId: string): Promise<number> {
    const admin = kafka.admin();
    await admin.connect();

    const currentTopics = await admin.listTopics();
    if (!currentTopics.includes(topic)) {
      return 0;
    }

    const topicOffsets = this.convertOffsetsToMap(
      await admin.fetchTopicOffsets(topic)
    );
    const consumerOffsets = this.convertOffsetsToMap(
      await admin.fetchOffsets({ groupId, topic })
    );

    let combinedLag = 0;
    for (const partition in topicOffsets) {
      combinedLag += topicOffsets[partition] - consumerOffsets[partition];
    }

    await admin.disconnect();

    return combinedLag;
  }

  private convertOffsetsToMap(
    offsets: { partition: number; offset: string }[]
  ): { [key: string]: number } {
    const map = {};
    for (const offset of offsets) {
      map[offset.partition] = Math.max(Number(offset.offset), 0);
    }
    return map;
  }
}

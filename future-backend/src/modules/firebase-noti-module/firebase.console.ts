import { Injectable, Logger } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { FirebaseSendNotiUseCase } from "./use-case/firebase-send-noti-use-case";
import { PairPriceChangeNotiUseCase } from "./pair-price-change-noti/pair-price-change-noti-use-case";

export interface FireBaseNotiInterface {
  type: any;
  data: {
    user_id: number;
    title: string;
    content: string;
    time: number;
  };
}

@Console()
@Injectable()
export class FirebaseConsole {
  constructor(
    private readonly logger: Logger,

    private readonly kafkaClient: KafkaClient,

    private readonly firebaseSendNotiUseCase: FirebaseSendNotiUseCase,

    private readonly pairPriceChangeNotiUseCase: PairPriceChangeNotiUseCase
  ) {
    this.logger.setContext(FirebaseConsole.name);
  }

  @Command({
    command: "firebase-noti:send-msg-to-user",
    description: "Send firebase notification",
  })
  async sendFirebaseNoti(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.future_firebase_notification,
      KafkaGroups.future_send_firebase_notification,
      async (msg: FireBaseNotiInterface) => {
        await this.firebaseSendNotiUseCase.execute(msg);
      },
      { fromBeginning: false }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "firebase-noti:send-price-change-noti",
    description: "Send firebase notification when symbol price change",
  })
  async sendNoti(): Promise<void> {
    await this.pairPriceChangeNotiUseCase.execute();
    return new Promise(() => {});
  }
}

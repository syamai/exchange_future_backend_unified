import { Injectable, Logger } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { kafka } from "src/configs/kafka";
import { DexService } from "src/modules/dex/service/dex.service";
import { CommandOutput } from "src/modules/matching-engine/matching-engine.const";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";

@Console()
@Injectable()
export class DexConsole {
  constructor(
    private logger: Logger,
    private dexService: DexService,
    public readonly kafkaClient: KafkaClient
  ) {
    this.logger.setContext("DexConsole");
  }

  @Command({
    command: "dex:action",
    description: "Dex Action",
  })
  async dexActions(): Promise<void> {
    const consumer = kafka.consumer({ groupId: KafkaGroups.dex_action });
    await consumer.connect();
    await consumer.subscribe({
      topic: KafkaTopics.matching_engine_output,
      fromBeginning: false,
    });
    await consumer.run({
      eachMessage: async ({ topic, message }) => {
        const commands = JSON.parse(
          message.value.toString()
        ) as CommandOutput[];
        const offset = message.offset;
        await this.dexService.saveDexActions(offset, commands);
        this.logger.log(`DexAction: offset=${offset} topic=${topic}`);
      },
    });

    return new Promise(() => {});
  }

  @Command({
    command: "dex:action-picker",
    description: "Dex Action Picker",
  })
  async dexActionsPicker(): Promise<void> {
    await this.dexService.handlePickDexActions();

    return new Promise(() => {});
  }

  @Command({
    command: "dex:action-sender",
    description: "Dex Action Sender",
  })
  async dexActionsSender(): Promise<void> {
    await this.dexService.handleSendDexActions();

    return new Promise(() => {});
  }

  @Command({
    command: "dex:action-verifier",
    description: "Dex Action Verifier",
  })
  async dexActionsVerifier(): Promise<void> {
    await this.dexService.handleVerifyDexActions();

    return new Promise(() => {});
  }

  @Command({
    command: "dex:action-history",
    description: "Dex Action History",
  })
  async dexActionsHistory(): Promise<void> {
    await this.dexService.handleHistoryDexActions();

    return new Promise(() => {});
  }

  @Command({
    command: "dex:action-balance-checker",
    description: "Dex Action History",
  })
  async dexActionsBalanceChecker(): Promise<void> {
    await this.dexService.handleBalanceCheckerDexActions();

    return new Promise(() => {});
  }
}

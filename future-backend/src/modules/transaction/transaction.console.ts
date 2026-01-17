import { Injectable, Logger } from "@nestjs/common";
import { serialize } from "class-transformer";
import { Producer } from "kafkajs";
import { Command, Console } from "nestjs-console";
import { Dex } from "src/configs/dex.config";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { LatestBlockService } from "src/modules/latest-block/latest-block.service";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { TransactionService } from "src/modules/transaction/transaction.service";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const { provider, dexContract, blockTimeInMs } = Dex;

@Console()
@Injectable()
export class TransactionConsole {
  constructor(
    private readonly logger: Logger,
    public readonly kafkaClient: KafkaClient,
    private readonly latestBlockService: LatestBlockService,
    private readonly transactionService: TransactionService
  ) {}
  private async sendTransactions(
    transactions: TransactionEntity[],
    producer: Producer
  ): Promise<void> {
    if (transactions.length > 0) {
      const messages = transactions
        .filter((transaction) => transaction.accountId > 0)
        .map((transaction) => ({
          value: serialize({ code: CommandCode.DEPOSIT, data: transaction }),
        }));
      await producer.send({
        topic: KafkaTopics.matching_engine_input,
        messages,
      });
      this.logger.log(
        `Sent ${transactions.length} transactions to matching engine.`
      );
    }
  }

  @Command({
    command: "transactions:update-new-account",
    description: "update new account in position",
  })
  async updateTransactions(): Promise<void> {
    await this.transactionService.updateTransactions();
  }
}

import { Console, Command } from "nestjs-console";
import { Injectable, Logger } from "@nestjs/common";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { TransactionType, TransactionStatus } from "src/shares/enums/transaction.enum";
import { FutureEventV2KafkaTopic, FutureEventV2KafkaGroup } from "src/shares/enums/kafka.enum";
import { FutureEventV2Service } from "./future-event-v2.service";

@Console()
@Injectable()
export class FutureEventV2Console {
  private readonly logger = new Logger(FutureEventV2Console.name);

  constructor(
    private readonly futureEventV2Service: FutureEventV2Service,
    private readonly kafkaClient: KafkaClient
  ) {}

  @Command({
    command: "future-event-v2:process-deposit",
    description: "Process deposit transactions and grant bonuses",
  })
  async processDeposit(): Promise<void> {
    this.logger.log("Starting future-event-v2:process-deposit consumer...");

    const topic = FutureEventV2KafkaTopic.future_event_v2_deposit_approved;
    const groupId = FutureEventV2KafkaGroup.future_event_v2_process_deposit;

    await this.kafkaClient.consume<TransactionEntity>(
      topic,
      groupId,
      async (transaction) => {
        try {
          // Only process DEPOSIT transactions
          if (
            transaction.type !== TransactionType.DEPOSIT ||
            transaction.status !== TransactionStatus.APPROVED
          ) {
            return;
          }

          this.logger.log(
            `Processing deposit for user ${transaction.userId}, amount: ${transaction.amount}`
          );

          const bonus = await this.futureEventV2Service.processDeposit(transaction);

          if (bonus) {
            this.logger.log(`Bonus granted: ${bonus.bonusAmount} to user ${transaction.userId}`);
          }
        } catch (error) {
          this.logger.error(`Error processing deposit: ${error.message}`, error.stack);
        }
      },
      { fromBeginning: false }
    );

    // Keep the process running
    return new Promise(() => {});
  }

  @Command({
    command: "future-event-v2:process-principal-deduction",
    description: "Process transactions that should deduct from principal (fees, losses)",
  })
  async processPrincipalDeduction(): Promise<void> {
    this.logger.log("Starting future-event-v2:process-principal-deduction consumer...");

    const topic = FutureEventV2KafkaTopic.future_event_v2_principal_deduction;
    const groupId = FutureEventV2KafkaGroup.future_event_v2_process_principal_deduction;

    const deductionTypes: string[] = [
      TransactionType.TRADING_FEE,
      TransactionType.FUNDING_FEE,
      TransactionType.REALIZED_PNL,
      TransactionType.LIQUIDATION_CLEARANCE,
    ];

    await this.kafkaClient.consume<TransactionEntity>(
      topic,
      groupId,
      async (transaction) => {
        try {
          // Only process specific transaction types
          if (!deductionTypes.includes(transaction.type)) {
            return;
          }

          if (transaction.status !== TransactionStatus.APPROVED) {
            return;
          }

          // Only deduct negative amounts (losses/fees)
          const amount = parseFloat(transaction.amount);
          if (amount >= 0) {
            return;
          }

          const deductAmount = Math.abs(amount).toString();

          this.logger.log(
            `Processing deduction for account ${transaction.accountId}, type: ${transaction.type}, amount: ${deductAmount}`
          );

          await this.futureEventV2Service.deductFromPrincipal(
            transaction.accountId,
            deductAmount,
            transaction.type,
            transaction.uuid
          );
        } catch (error) {
          this.logger.error(`Error processing deduction: ${error.message}`, error.stack);
        }
      },
      { fromBeginning: false }
    );

    // Keep the process running
    return new Promise(() => {});
  }
}

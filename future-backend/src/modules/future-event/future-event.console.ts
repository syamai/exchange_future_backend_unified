import { Injectable } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { FutureEventKafkaGroup, FutureEventKafkaTopic, KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { FutureEventService } from "./future-event.service";
import { FutureEventRevokeRewardService } from "./future-event-revoke-reward.service";
import { CommandOutput } from "../matching-engine/matching-engine.const";
import { UserRewardFutureEventUsedEntity } from "src/models/entities/user-reward-future-event-used.entity";
import { UserRewardFutureEventEntity } from "src/models/entities/user-reward-future-event.entity";
import { UpsertTradingVolumeSessionUseCase } from "./use-case/upsert-trading-volume-use-case";
import { UpdateTradingVolumeCronUseCase } from "./use-case/update-trading-volume-cron-use-case";
@Console()
@Injectable()
export class FutureEventConsole {
  constructor(
    private readonly futureEventService: FutureEventService,
    public readonly kafkaClient: KafkaClient,
    private readonly futureEventRevokeRewardService: FutureEventRevokeRewardService,
    private readonly upsertTradingVolumeSessionUseCase: UpsertTradingVolumeSessionUseCase,
    private readonly updateTradingVolumeCronUseCase: UpdateTradingVolumeCronUseCase
  ) {}

  async saveEntities(
    topic: string,
    groupId: string,
    callback: (transactionEntity: TransactionEntity) => Promise<void>,
    fromBeginning: boolean = true
  ): Promise<void> {
    await this.kafkaClient.consume<TransactionEntity>(
      topic,
      groupId,
      async (transactionEntity) => {
        await callback(transactionEntity);
      },
      { fromBeginning }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "future-event:update-user-used-reward-balance",
    description: "update user used reward balance",
  })
  async updateUserUsedRewardBalance(): Promise<void> {
    await this.saveEntities(
      FutureEventKafkaTopic.transactions_to_process_used_event_rewards,
      FutureEventKafkaGroup.update_user_used_reward_balance,
      (transactionEntity) => this.futureEventService.updateUserUsedRewardBalance(transactionEntity),
      false
    );
  }

  @Command({
    command: "future-event:update-revoking-user-reward-balance",
    description: "update revoking user reward balance",
  })
  async updateRevokingUserRewardBalance(): Promise<void> {
    await this.kafkaClient.consume<CommandOutput[]>(
      KafkaTopics.matching_engine_output,
      FutureEventKafkaGroup.update_revoking_user_reward_balance,
      async (commands: CommandOutput[]) => {
        await this.futureEventRevokeRewardService.updateRevokingRewardBalance(commands);
      },
      { fromBeginning: false }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "future-event:bull-queue-revoke-reward-balance-process",
    description: "bull mq revoking user reward balance process",
  })
  async bullMqRevokeRewardBalanceProcess(): Promise<void> {
    return new Promise(() => {});
  }

  @Command({
    command: "future-event:revoke-reward-when-user-close-position-order",
    description: "Revoke reward balance when user close a position or an order",
  })
  async revokeRewardWhenClosePositionOrder(): Promise<void> {
    await this.kafkaClient.consume<CommandOutput[]>(
      KafkaTopics.matching_engine_output,
      FutureEventKafkaGroup.revoke_reward_when_user_close_position_order,
      async (commands: CommandOutput[]) => {
        await this.futureEventRevokeRewardService.revokeRewardWhenUserClosePositionOrder(commands);
      },
      { fromBeginning: false }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "future-event:update-user-used-reward-balance-detail",
    description: "update user used reward balance",
  })
  async updateUserUsedRewardBalanceDetail(): Promise<void> {
    await this.kafkaClient.consume<UserRewardFutureEventUsedEntity>(
      FutureEventKafkaTopic.reward_balance_used_to_process_used_detail,
      FutureEventKafkaGroup.update_user_used_reward_balance_detail,
      async (userRewardUsed: UserRewardFutureEventUsedEntity) => {
        await this.futureEventService.updateUserUsedRewardBalanceDetail(userRewardUsed);
      },
      { fromBeginning: true }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "future-event:upsert-trading-volume-session",
    description: "process trading volume session",
  })
  async upsertTradingVolumeSession(): Promise<void> {
    await this.kafkaClient.consume<UserRewardFutureEventEntity>(
      FutureEventKafkaTopic.rewards_to_process_trading_volume_session,
      FutureEventKafkaGroup.process_trading_volume_session,
      async (msg: UserRewardFutureEventEntity) => {        
        await this.upsertTradingVolumeSessionUseCase.execute({...msg, createdAt: new Date(msg.createdAt).toISOString()});
      },
      { fromBeginning: true }
    );
    
    return new Promise(() => {});
  }

  @Command({
    command: "future-event:cron-job-update-trading-volume",
    description: "cron job update trading volume",
  })
  async cronJobUpdateTradingVolume(): Promise<void> {
    await this.updateTradingVolumeCronUseCase.execute();
    return new Promise(() => {});
  }
}

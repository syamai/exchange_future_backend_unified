import { Injectable } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { CommandOutput } from "../matching-engine/matching-engine.const";
import { UserStatisticService } from "./user-statistic.service";
import { UpdateUserPeakAssetUsecase } from "./usecase/update-user-peak-asset.usecase";

@Console()
@Injectable()
export class UserStatisticConsole {
  constructor(
    private readonly userStatisticService: UserStatisticService, 
    private readonly kafkaClient: KafkaClient, 
    private readonly updateUserPeakAssetUsecase: UpdateUserPeakAssetUsecase
  ) {}

  async saveEntities(topic: string, groupId: string, callback: (commands: CommandOutput[]) => Promise<void>, fromBeginning: boolean = true): Promise<void> {
    await this.kafkaClient.consume<CommandOutput[]>(
      topic,
      groupId,
      async (commands) => {
        await callback(commands);
      },
      { fromBeginning }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "user-statistic:save-user-gain-loss",
    description: "Saving user gain / loss list",
  })
  async saveUserGainLoss(): Promise<void> {
    await this.saveEntities(
      KafkaTopics.matching_engine_output,
      KafkaGroups.user_statistic_matching_engine_saver_user_gain_loss,
      (commands) => this.userStatisticService.saveUserGainLoss(commands)
    );
  }

  @Command({
    command: "user-statistic:save-user-deposit",
    description: "Saving user deposit activity",
  })
  async saveUserDeposit(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.future_transfer,
      KafkaGroups.user_statistic_future_transfer_saver_user_deposit,
      async (data: any) => this.userStatisticService.saveUserDeposit(data),
      { fromBeginning: true }
    );
    return new Promise(() => {});
  }

  @Command({
    command: "user-statistic:save-user-withdrawal",
    description: "Saving user withdrawal activity",
  })
  async saveUserWithdrawal(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.spot_transfer,
      KafkaGroups.user_statistic_spot_transfer_saver_user_withdraw,
      async (data: any) => this.userStatisticService.saveUserWithdrawal(data),
      { fromBeginning: true }
    );
    return new Promise(() => {});
  }

  @Command({
    command: "user-statistic:update-user-peak-asset",
    description: "Update user peak asset",
  })
  async updateUserPeakAsset(): Promise<void> {
    await this.saveEntities(KafkaTopics.matching_engine_output, KafkaGroups.user_statistic_update_user_peak_asset, (commands) =>
      // this.userStatisticService.updateUserPeakAssetWorker(commands)
      this.updateUserPeakAssetUsecase.execute(commands)
    );
  }

  @Command({
    command: "user-statistic:update-user-total-trade-volume",
    description: "Update user total trading volume",
  })
  async updateUserTotalTradeVolume(): Promise<void> {
    await this.saveEntities(KafkaTopics.matching_engine_output, KafkaGroups.user_statistic_update_user_total_trade_volume, (commands) =>
      this.userStatisticService.updateUserTotalTradeVolume(commands), false
    );
  }
}

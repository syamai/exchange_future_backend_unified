import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { AccountEntity } from "src/models/entities/account.entity";
import { UserStatisticRepository } from "src/models/repositories/user-statistics.repository";
import { BotInMemoryService } from "src/modules/bot/bot.in-memory.service";
import { convertDateFields } from "src/modules/matching-engine/helper";
import {
  CommandCode,
  CommandOutput,
} from "src/modules/matching-engine/matching-engine.const";
import { LinkedQueue } from "src/utils/linked-queue";
import { v4 as uuidv4 } from "uuid";

@Injectable()
export class UpdateUserPeakAssetUsecase {
  constructor(
    private readonly botInMemoryService: BotInMemoryService,
    @InjectRepository(UserStatisticRepository, "master")
    private readonly userStatisticRepoMaster: UserStatisticRepository
  ) {}
  private readonly logger = new Logger(UpdateUserPeakAssetUsecase.name);

  private readonly MAX_QUEUE_SIZE = 100;
  private readonly saveQueue = new LinkedQueue<any>();
  private saveInterval = null;

  private isIntervalHandlerRunningSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;

  public async execute(commands: CommandOutput[]): Promise<void> {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(
      commands,
      CommandCode.STOP_UPDATE_USER_PEAK_ASSET
    );
    this.setInterval();
    this.setCheckExitInterval();

    if (!this.saveInterval) {
      this.saveInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 50);
    }

    for (const c of commands) {
      if (!c.accounts || c.accounts?.length == 0) continue;

      //check max size of account queue
      if (this.saveQueue.size() >= this.MAX_QUEUE_SIZE) {
        this.logger.warn(
          `saveQueue size=${this.saveQueue.size()} is greater than MAX_QUEUE_SIZE, wait 100ms`
        );
        await new Promise((resolve) => setTimeout(resolve, 100));
      }

      this.saveQueue.enqueue(c.accounts);
    }
  }

  private async intervalHandler() {
    const ssId = uuidv4()
    this.isIntervalHandlerRunningSet.add(ssId);

    const batch = 50;
    const saveAccountToProcess = [];
    while (saveAccountToProcess.length < batch && !this.saveQueue.isEmpty()) {
      saveAccountToProcess.push(this.saveQueue.dequeue());
    }

    const accountEntities: AccountEntity[] = [];
    for (const accounts of saveAccountToProcess) {
      for (const account of accounts) {
        const newAccount = convertDateFields(new AccountEntity(), account);
        // filter bot account and usdt asset
        if (newAccount.asset === "USDT") {
          accountEntities.push(newAccount);
        }
      }
    }

    // get bot arr
    const accountIdSet = new Set<number>();
    for (const entity of accountEntities) {
      accountIdSet.add(entity.id);
      accountIdSet.add(entity.id);
    }
    const accountIds = Array.from(accountIdSet);
    const isBotAccArr: boolean[] = await Promise.all(
      accountIds.map(async (accountId) => {
        return this.botInMemoryService.checkIsBotAccountId(accountId);
      })
    );
    const botAccMap = new Map<number, boolean>();
    accountIds.forEach((accountId, index) => {
      botAccMap.set(accountId, isBotAccArr[index]);
    });

    // const userBalanceMap = new Map<number, string>();
    // for (let account of accountEntities) {
    //   const userId = account.userId;
    //   const newBalance = account.balance;
    //   const currentBalance = userBalanceMap.get(userId);
    //   if (!currentBalance) {
    //     userBalanceMap.set(userId, newBalance);
    //     continue;
    //   }
    //   const isNextBalanceGreater = new BigNumber(currentBalance).lt(newBalance);
    //   if (isNextBalanceGreater) {
    //     userBalanceMap.set(userId, newBalance);
    //   }
    // }

    // const userBalances = Array.from(userBalanceMap.entries());
    if (accountEntities.length) {
      await Promise.all(
        accountEntities.map(async (account) => {
          if (!botAccMap.get(account.id)) {
            return this.updateUserPeakAssetValue(
              account.userId,
              account.balance
            );
          }
        })
      );
    }

    this.isIntervalHandlerRunningSet.delete(ssId);
  }

  private async updateUserPeakAssetValue(
    userId: number,
    newPeakAssetValue: string
  ) {
    const query = `update user_statistics SET peakAssetValue = ${newPeakAssetValue} WHERE id = ${userId} and peakAssetValue < ${newPeakAssetValue}`;
    await this.userStatisticRepoMaster.query(query);
  }

  private setInterval() {
    if (!this.saveInterval) {
      this.saveInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 50);
    }
  }

  private checkHaveStopCommand(
    commands: CommandOutput[],
    stopCommandCode: string
  ) {
    if (!this.firstTimeConsumeMessage) this.firstTimeConsumeMessage = Date.now();
    if (
      commands.find((c) => c.code == stopCommandCode) &&
      Date.now() - this.firstTimeConsumeMessage > 10000 // at least 10s from firstTimeConsumeMessage
    ) {
      this.shouldStopConsumer = true;
      this.logger.log(`shouldStopConsumer = true`);
    }
  }

  private setCheckExitInterval() {
    if (this.shouldStopConsumer && !this.checkExitInterval) {
      this.checkExitInterval = setInterval(async () => {
        this.checkExitIntervalHandler();
      }, 500);
    }
  }

  private checkExitIntervalHandler() {
    if (this.isIntervalHandlerRunningSet.size === 0 && this.saveQueue.isEmpty()) {
      this.logger.log(`Exit consumer!`);
      process.exit(0);
    }
  }
}

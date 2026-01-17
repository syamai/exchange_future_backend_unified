import { Injectable, Logger } from "@nestjs/common";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { AccountEntity } from "src/models/entities/account.entity";
import { convertDateFields } from "../helper";
import { OPERATION_ID_DIVISOR } from "src/shares/number-formatter";
import { RedisClient } from "src/shares/redis-client/redis-client";
import BigNumber from "bignumber.js";

@Injectable()
export class SaveAccountToCacheUseCase {
  constructor(private readonly redisClient: RedisClient) {}
  private cleanupAccountInterval: NodeJS.Timeout = null;
  private readonly logger = new Logger(SaveAccountToCacheUseCase.name);
  private isCheckingCleanCachedAccounts = false;
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;

  public async execute(commands: CommandOutput[]): Promise<void> {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1)); // Stop handling message
    }

    this.checkHaveStopCommand(
      commands,
      CommandCode.STOP_SAVE_ACCOUNTS_TO_CACHE
    );
    this.setCleanupAccountInterval();
    this.setCheckExitInterval();

    const accountsToProcess: AccountEntity[] = [];
    for (const command of commands) {
      if (!command.accounts || command.accounts.length === 0) continue;

      for (const account of command.accounts) {
        const newAccount = convertDateFields(new AccountEntity(), account);
        const newAccountOperationId = newAccount?.operationId
          ? new BigNumber(newAccount.operationId.toString())
          : null;

        const existingAccount = accountsToProcess.find(
          (a) => Number(a.id) === Number(newAccount.id)
        );
        const existingAccountOperationId = existingAccount?.operationId
          ? new BigNumber(existingAccount.operationId.toString())
          : null;

        if (
          !existingAccount ||
          existingAccountOperationId == null ||
          newAccountOperationId == null ||
          newAccountOperationId.isGreaterThanOrEqualTo(
            existingAccountOperationId
          )
        ) {
          if (existingAccount) {
            accountsToProcess.splice(
              accountsToProcess.indexOf(existingAccount),
              1
            );
          }
          accountsToProcess.push(newAccount);
        }
      }
    }

    if (accountsToProcess.length === 0) return;

    // Cache accounts to Redis with operationId as score
    for (const account of accountsToProcess) {
      const key = `accounts:userId_${account.userId}:accountId_${account.id}`;
      const accountOperationId = Number(
        (
          BigInt(account.operationId.toString()) % OPERATION_ID_DIVISOR
        ).toString()
      );
      this.redisClient
        .getInstance()
        .zadd(key, accountOperationId, JSON.stringify(account));
      const redisKeyWithAsset = `accounts:userId_${account.userId}:asset_${account.asset}`;
      this.redisClient
        .getInstance()
        .set(redisKeyWithAsset, JSON.stringify(account), "EX", 24 * 60 * 60); // 24 hours
    }
  }

  private async intervalHandler(): Promise<void> {
    if (this.isCheckingCleanCachedAccounts) {
      this.logger.log("Checking in progress. Wait to next turn...");
      return;
    }

    this.isCheckingCleanCachedAccounts = true;
    this.logger.log("=====> Start to clean cached accounts ...");

    let cursor = "0";
    do {
      const [nextCursor, keys] = await this.redisClient
        .getInstance()
        .scan(cursor, "MATCH", "accounts:userId_*", "COUNT", 5000);
      cursor = nextCursor;

      const pipeline = this.redisClient.getInstance().multi();
      for (const key of keys) {
        if (key.includes("asset_")) continue;
        const members = await this.redisClient
          .getInstance()
          .zrevrange(key, 0, 0, "WITHSCORES");
        if (members.length < 2) continue;

        // Keep only the member with highest score
        const highestScore = members[members.length - 1];

        // Remove all members except the one with highest score
        pipeline.zremrangebyscore(
          key,
          0,
          String(BigInt(highestScore) - BigInt(1))
        );
        pipeline.expire(key, 3 * 24 * 60 * 60); // 3 days in seconds
      }
      pipeline.exec();

      // Slight delay to avoid overloading Redis
      await new Promise((resolve) => setTimeout(resolve, 20));
    } while (cursor !== "0");

    this.isCheckingCleanCachedAccounts = false;
  }

  private setCleanupAccountInterval() {
    if (!this.cleanupAccountInterval) {
      this.cleanupAccountInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 5000);
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
    this.logger.log(`Exit consumer!`);
    process.exit(0);
  }

  private checkHaveStopCommand(
    commands: CommandOutput[],
    stopCommandCode: string
  ) {
    if (!this.firstTimeConsumeMessage)
      this.firstTimeConsumeMessage = Date.now();
    if (
      commands.find((c) => c.code == stopCommandCode) &&
      this.firstTimeConsumeMessage &&
      Date.now() - this.firstTimeConsumeMessage > 10000 // at least 10s from firstTimeConsumeMessage
    ) {
      this.shouldStopConsumer = true;
      this.logger.log(`shouldStopConsumer = true`);
    }
  }
}

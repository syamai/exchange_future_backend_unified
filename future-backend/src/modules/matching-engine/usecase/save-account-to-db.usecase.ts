import { Injectable, Logger } from "@nestjs/common";
import { AccountEntity } from "src/models/entities/account.entity";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { convertDateFields } from "../helper";
import { InjectRepository } from "@nestjs/typeorm";
import { AccountRepository } from "src/models/repositories/account.repository";
import { v4 as uuidv4 } from "uuid";
import BigNumber from "bignumber.js";

@Injectable()
export class SaveAccountToDbUseCase {
  constructor(
    @InjectRepository(AccountRepository, "master")
    private readonly accountRepoMaster: AccountRepository
  ) {}

  private readonly logger = new Logger(SaveAccountToDbUseCase.name);
  private accountsWillBeUpdatedOnDb = new Map<number, AccountEntity>();
  private updatedAccountIds = new Set<number>();
  private saveAccountInterval = null;

  private isIntervalHandlerRunningSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;

  public async execute(commands: CommandOutput[]): Promise<void> {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(commands, CommandCode.STOP_SAVE_ACCOUNTS_TO_DB);
    this.setSaveAccountInterval();
    this.setCheckExitInterval();

    // Main process
    const accountsToProcess: AccountEntity[] = [];

    for (const command of commands) {
      if (!command.accounts || command.accounts.length === 0) continue;

      for (const account of command.accounts) {
        const newAccount = convertDateFields(new AccountEntity(), account);
        const existingAccount = accountsToProcess.find(
          (a) => Number(a.id) === Number(newAccount.id)
        );

        if (
          !existingAccount ||
          !existingAccount.operationId ||
          !newAccount.operationId ||
          new BigNumber(newAccount.operationId).isGreaterThanOrEqualTo(
            new BigNumber(existingAccount.operationId)
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

    for (const accountToProcess of accountsToProcess) {
      this.updatedAccountIds.add(accountToProcess.id);
      this.accountsWillBeUpdatedOnDb.set(accountToProcess.id, accountToProcess);
    }
  }

  private setSaveAccountInterval() {
    if (!this.saveAccountInterval) {
      this.saveAccountInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 500);
    }
  }
  private async intervalHandler() {
    if (this.updatedAccountIds.size === 0) return;

    const ssid = uuidv4();
    this.isIntervalHandlerRunningSet.add(ssid);
    const accountIds = Array.from(this.updatedAccountIds);
    this.updatedAccountIds.clear();

    const accountsToSaveDb = accountIds
      .map((id) => this.accountsWillBeUpdatedOnDb.get(id))
      .filter(Boolean);

    await this.accountRepoMaster
      .insertOrUpdate(accountsToSaveDb)
      .catch(async (e1) => {
        this.logger.error(e1);
        if (e1.toString().includes("ER_LOCK_DEADLOCK")) {
          this.logger.error(
            `DEADLOCK accountIds: ${accountsToSaveDb.map(
              (a) => a.id
            )} - Resave ...`
          );
          let shouldBeOutDeadlock = false;
          while (!shouldBeOutDeadlock) {
            try {
              await this.accountRepoMaster.insertOrUpdate(accountsToSaveDb);
              shouldBeOutDeadlock = true;
            } catch (e2) {
              this.logger.error(
                `Retry: DEADLOCK accountIds: ${accountsToSaveDb.map(
                  (p) => p.id
                )}`
              );
              shouldBeOutDeadlock = false;
            }
          }
        }
      });
    this.logger.log(`Save new account ids=${accountIds}`);
    this.isIntervalHandlerRunningSet.delete(ssid);
  }

  private setCheckExitInterval() {
    if (this.shouldStopConsumer && !this.checkExitInterval) {
      this.checkExitInterval = setInterval(async () => {
        this.checkExitIntervalHandler();
      }, 500);
    }
  }
  private checkExitIntervalHandler() {
    if (
      this.isIntervalHandlerRunningSet.size === 0 &&
      this.updatedAccountIds.size === 0
    ) {
      this.logger.log(`Exit consumer!`);
      process.exit(0);
    }
  }

  private checkHaveStopCommand(
    commands: CommandOutput[],
    stopCommandCode: string
  ) {
    if (!this.firstTimeConsumeMessage)
      this.firstTimeConsumeMessage = Date.now();
    if (
      commands.find((c) => c.code == stopCommandCode) &&
      Date.now() - this.firstTimeConsumeMessage > 10000 // at least 10s from firstTimeConsumeMessage
    ) {
      this.shouldStopConsumer = true;
      this.logger.log(`shouldStopConsumer = true`);
    }
  }
}

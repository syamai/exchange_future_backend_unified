import { Injectable, Logger } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { AccountService } from "src/modules/account/account.service";

@Console()
@Injectable()
export class AccountConsole {
  constructor(
    private readonly logger: Logger,
    private readonly accountService: AccountService
  ) {}

  @Command({
    command: "account:daily-balance",
    description: "Saving current account balance to account history",
  })
  async saveDailyBalance(): Promise<void> {
    await this.accountService.saveUserDailyBalance();
  }

  @Command({
    command: "account:gen-insurance-account",
    description: "Saving current account balance to account history",
  })
  async genInsuranceAccount(): Promise<void> {
    // await this.accountService.saveUserDailyBalance();
    await this.accountService.genInsuranceAccounts();
  }

  @Command({
    command: "account:gen-account <asset>",
    description: "Gen new asset account",
  })
  async genNewAssetAccount(asset: string): Promise<void> {
    // await this.accountService.saveUserDailyBalance();
    await this.accountService.genNewAssetAccounts(asset);
  }

  @Command({
    command: "account:sync-email",
    description: "sync email account",
  })
  async syncEmail(): Promise<void> {
    // await this.accountService.saveUserDailyBalance();
    await this.accountService.syncEmail();
  }

  @Command({
    command: "account:deposit-usdt-bot",
    description: "deposit usdt to bot account",
  })
  async depositBot(): Promise<void> {
    // await this.accountService.saveUserDailyBalance();
    await this.accountService.depositUSDTBotAccount();
  }

  @Command({
    command: "account:save-future-event-reward",
    description: "Save reward from event",
  })
  async saveFutureEventReward(): Promise<void> {
    try {
      await this.accountService.saveFutureEventReward()
    } catch (error) {
      this.logger.error(`[AccountConsole][saveFutureEventReward]: ${error}`);
    }
  }
}

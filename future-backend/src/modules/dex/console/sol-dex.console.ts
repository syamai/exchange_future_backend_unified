import { Injectable, Logger } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { SolDexService } from "src/modules/dex/service/sol-dex.service";

@Console()
@Injectable()
export class SolDexConsole {
  constructor(private logger: Logger, private solDexService: SolDexService) {
    this.logger.setContext("SolDexConsole");
  }

  @Command({
    command: "sol-dex:action-picker",
    description: "Dex Action Picker",
  })
  async dexActionsPicker(): Promise<void> {
    await this.solDexService.handlePickDexActions();

    return new Promise(() => {});
  }

  @Command({
    command: "sol-dex:action-sender",
    description: "Dex Action Sender",
  })
  async dexActionsSender(): Promise<void> {
    await this.solDexService.handleSendDexActions();

    return new Promise(() => {});
  }

  @Command({
    command: "sol-dex:action-verifier",
    description: "Dex Action Verifier",
  })
  async dexActionsVerifier(): Promise<void> {
    await this.solDexService.handleVerifyDexActions();

    return new Promise(() => {});
  }

  @Command({
    command: "sol-dex:action-signature",
    description: "Dex Action Crawl Signature",
  })
  async dexActionsSignature(): Promise<void> {
    await this.solDexService.handleCrawlSignature();

    return new Promise(() => {});
  }

  @Command({
    command: "sol-dex:action-history",
    description: "Dex Action History",
  })
  async dexActionsHistory(): Promise<void> {
    await this.solDexService.handleHistoryDexActions();

    return new Promise(() => {});
  }
}

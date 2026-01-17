import { Injectable, Logger } from "@nestjs/common";
import { LinkedQueue } from "src/utils/linked-queue";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { MarginHistoryEntity } from "src/models/entities/margin-history";
import { convertDateFields } from "../helper";
import { InjectRepository } from "@nestjs/typeorm";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { BotInMemoryService } from "src/modules/bot/bot.in-memory.service";
import { v4 as uuidv4 } from "uuid";

@Injectable()
export class SaveMarginHistoriesUseCase {
  constructor(
    @InjectRepository(MarginHistoryRepository, "master")
    private readonly marginHistoryRepoMaster: MarginHistoryRepository,
    private readonly botInMemoryService: BotInMemoryService
  ) {}
  private readonly logger = new Logger(SaveMarginHistoriesUseCase.name);

  private readonly saveQueue = new LinkedQueue<any>();
  private saveInterval = null;
  private readonly MAX_SAVE_QUEUE_SIZE = 100000;

  private isIntervalHandlerRunningSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;


  public async execute(commands: CommandOutput[]): Promise<void> {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(commands, CommandCode.STOP_SAVE_MARGIN_HISTORY);
    this.setSaveInterval();
    this.setCheckExitInterval();

    //check max size of margin histories queue
    while (this.saveQueue.size() >= this.MAX_SAVE_QUEUE_SIZE) {
      this.logger.log(`Save queue size: ${this.saveQueue.size()}`);
      await new Promise((resolve) => setTimeout(resolve, 50));
    }
    for (const c of commands) {
      if (!c.marginHistories || c.marginHistories?.length == 0) continue;
      console.log(c.marginHistories);
      this.saveQueue.enqueue(c.marginHistories);
    }
  }

  private setSaveInterval() {
    if (!this.saveInterval) {
      this.saveInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 50);
    }
  }

  private async intervalHandler() {
    const ssid = uuidv4();
    this.isIntervalHandlerRunningSet.add(ssid);

    const batch = 5000;
    const saveMarginsToProcess = [];
    while (saveMarginsToProcess.length < batch && !this.saveQueue.isEmpty()) {
      saveMarginsToProcess.push(this.saveQueue.dequeue());
    }

    const entities: MarginHistoryEntity[] = [];
    for (const marginHistories of saveMarginsToProcess) {
      for (const margin of marginHistories) {
        const newMargin = convertDateFields(new MarginHistoryEntity(), margin);
        if (newMargin.accountId == null) continue;
        const isBot: boolean = await this.botInMemoryService.checkIsBotAccountId(
          Number(newMargin.accountId)
        );
        if (isBot) continue;

        entities.push(newMargin);
        console.log(`newMargin=${newMargin.id}`);
      }
    }

    await this.marginHistoryRepoMaster
      .insertOrUpdate(entities)
      .catch((e) => {
        this.logger.error(e);
      })
      .finally(() => {
        if (entities?.length) {
          console.log(`Processed: ${entities?.length}`);
        }
      });

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
    if (this.isIntervalHandlerRunningSet.size === 0 && this.saveQueue.isEmpty()) {
      this.logger.log(`Exit consumer!`);
      process.exit(0);
    }
  }

  private checkHaveStopCommand(
    commands: CommandOutput[],
    stopCommandCode: string
  ) {
    if (!this.firstTimeConsumeMessage) this.firstTimeConsumeMessage = Date.now();
    if (
      commands.find((c) => c.code == stopCommandCode)  &&
      Date.now() - this.firstTimeConsumeMessage > 10000 // at least 10s from firstTimeConsumeMessage
    ) {
      this.shouldStopConsumer = true;
      this.logger.log(`shouldStopConsumer = true`);
    }
  }
}

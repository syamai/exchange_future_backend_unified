import { Injectable, Logger } from "@nestjs/common";
import { LinkedQueue } from "src/utils/linked-queue";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { PositionHistoryEntity } from "src/models/entities/position-history.entity";
import { convertDateFields } from "../helper";
import { PositionHistoryRepository } from "src/models/repositories/position-history.repository";
import { InjectRepository } from "@nestjs/typeorm";
import { v4 as uuidv4 } from "uuid";

@Injectable()
export class SavePositionHistoriesUseCase {
  constructor(
    @InjectRepository(PositionHistoryRepository, "master")
    private readonly positionHistoryRepoMaster: PositionHistoryRepository
  ) {}
  private readonly logger = new Logger(SavePositionHistoriesUseCase.name);

  private readonly MAX_QUEUE_SIZE = 100;
  private readonly saveQueue = new LinkedQueue<any>();
  private saveInterval = null;

//   private isIntervalHandlerRunning: boolean = false;
  private isIntervalHandlerRunningSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;


  public async execute(commands: CommandOutput[]): Promise<void> {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(commands, CommandCode.STOP_SAVE_POSITION_HISTORY);
    this.setSaveInterval();
    this.setCheckExitInterval();

    for (const c of commands) {
      if (!c.positionHistories || c.positionHistories?.length == 0) continue;

      //check max size of position histories queue
      if (this.saveQueue.size() >= this.MAX_QUEUE_SIZE) {
        this.logger.warn(
          `saveQueue size=${this.saveQueue.size()} is greater than MAX_QUEUE_SIZE, wait 100ms`
        );
        await new Promise((resolve) => setTimeout(resolve, 100));
      }

      this.saveQueue.enqueue(c.positionHistories);
    }
  }

  private setSaveInterval() {
    if (!this.saveInterval) {
      this.saveInterval = setInterval(async () => {
        await this.saveIntervalHandler();
      }, 50);
    }
  }

  private async saveIntervalHandler() {
    const ssId = uuidv4()
    this.isIntervalHandlerRunningSet.add(ssId);

    const batch = 50;
    const savePositionHistoriesToProcess = [];
    while (
      savePositionHistoriesToProcess.length < batch &&
      !this.saveQueue.isEmpty()
    ) {
      savePositionHistoriesToProcess.push(this.saveQueue.dequeue());
    }

    const entities: PositionHistoryEntity[] = [];
    for (const positionHistories of savePositionHistoriesToProcess) {
      for (const pH of positionHistories) {
        const newPositionHistory = convertDateFields(
          new PositionHistoryEntity(),
          pH
        );
        entities.push(newPositionHistory);
        this.logger.log(`newPositionHistory=${newPositionHistory.id}`);
      }
    }

    await this.positionHistoryRepoMaster
      .insertOrUpdate(entities)
      .catch((e) => {
        this.logger.error(e);
      })
      .finally(() => {
        if (entities?.length) {
          this.logger.log(`Processed: ${entities?.length}`);
        }
      });

      this.isIntervalHandlerRunningSet.delete(ssId);
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
      commands.find((c) => c.code == stopCommandCode) &&
      Date.now() - this.firstTimeConsumeMessage > 10000 // at least 10s from firstTimeConsumeMessage
    ) {
      this.shouldStopConsumer = true;
      this.logger.log(`shouldStopConsumer = true`);
    }
  }
}

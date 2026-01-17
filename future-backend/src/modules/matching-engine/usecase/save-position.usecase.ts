import { Injectable, Logger } from "@nestjs/common";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { PositionEntity } from "src/models/entities/position.entity";
import { convertDateFields } from "../helper";
import { OPERATION_ID_DIVISOR } from "src/shares/number-formatter";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { InjectRepository } from "@nestjs/typeorm";
import { PositionRepository } from "src/models/repositories/position.repository";
import { v4 as uuidv4 } from "uuid";
import { BotInMemoryService } from "src/modules/bot/bot.in-memory.service";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";

@Injectable()
export class SavePositionUseCase {
  constructor(
    private readonly redisClient: RedisClient,
    @InjectRepository(PositionRepository, "master")
    private positionRepoMaster: PositionRepository,
    private readonly botInMemoryService: BotInMemoryService,
    private readonly kafkaClient: KafkaClient,
  ) {}
  private readonly logger = new Logger(SavePositionUseCase.name);

  private positionsWillBeUpdatedOnDb = new Map<number, PositionEntity>();
  private updatedPositionIds = new Set<number>();
  private savePositionInterval = null;

  // private isIntervalHandlerRunning: boolean = false;
  private isIntervalHandlerRunningSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;


  public async execute(commands: CommandOutput[]): Promise<void> {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(commands, CommandCode.STOP_SAVE_POSITIONS);
    this.setInterval();
    this.setCheckExitInterval();

    const positionsToProcess: PositionEntity[] = [];

    for (const command of commands) {
      if (!command.positions || command.positions.length === 0) continue;

      for (const position of command.positions) {
        const newPosition = convertDateFields(new PositionEntity(), position);
        const newPositionOperationId = newPosition?.operationId
          ? Number(
              (
                BigInt(newPosition.operationId.toString()) %
                OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

        const existingPosition = positionsToProcess.find(
          (p) => p.id === newPosition.id
        );
        const existingPositionOperationId = existingPosition?.operationId
          ? Number(
              (
                BigInt(existingPosition.operationId.toString()) %
                OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

        if (
          !existingPosition ||
          existingPositionOperationId == null ||
          newPositionOperationId == null ||
          newPositionOperationId >= existingPositionOperationId
        ) {
          if (existingPosition) {
            positionsToProcess.splice(
              positionsToProcess.indexOf(existingPosition),
              1
            );
          }
          positionsToProcess.push(newPosition);
        }
      }
    }

    if (positionsToProcess.length === 0) return;

    for (const positionToProcess of positionsToProcess) {
      if (await this.botInMemoryService.checkIsBotUserId(positionToProcess.userId)) {
        const redisKey = `${REDIS_COMMON_PREFIX.POSITIONS}:userId_${positionToProcess.userId}:accountId_${positionToProcess.accountId}:positionId_${positionToProcess.id}`;
        this.redisClient
          .getInstance()
          .set(redisKey, JSON.stringify(positionToProcess), "EX", 86400); // 1 day TTL
      } 
      
      // This is user's position
      else {
        await this.kafkaClient.send(KafkaTopics.save_user_position_to_cache, JSON.stringify(positionToProcess));
      }

      this.updatedPositionIds.add(positionToProcess.id);
      this.positionsWillBeUpdatedOnDb.set(
        positionToProcess.id,
        positionToProcess
      );
    }

    if (!this.savePositionInterval) {
      this.savePositionInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 500);
    }
  }

  private setInterval() {
    if (!this.savePositionInterval) {
      this.savePositionInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 50);
    }
  }

  private async intervalHandler() {
    if (this.updatedPositionIds.size === 0) return;

    const ssid = uuidv4();
    this.isIntervalHandlerRunningSet.add(ssid);
    const positionIds = Array.from(this.updatedPositionIds);
    this.updatedPositionIds.clear();

    const positionsToSaveDb = positionIds
      .map((id) => this.positionsWillBeUpdatedOnDb.get(id))
      .filter(Boolean);

    await this.positionRepoMaster
      .insertOrUpdate(positionsToSaveDb)
      .catch(async (e1) => {
        this.logger.error(e1);
        if (e1.toString().includes("ER_LOCK_DEADLOCK")) {
          this.logger.error(
            `DEADLOCK positionIds: ${positionsToSaveDb.map(
              (p) => p.id
            )} - Resave ...`
          );
          let shouldOutDeadlock = false;
          while (!shouldOutDeadlock) {
            try {
              await this.positionRepoMaster.insertOrUpdate(positionsToSaveDb);
              shouldOutDeadlock = true;
            } catch (e2) {
              this.logger.error(
                `Retry: DEADLOCK positionIds: ${positionsToSaveDb.map(
                  (p) => p.id
                )}`
              );
              shouldOutDeadlock = false;
            }
          }
        }
      });
    this.logger.log(`Save new position ids=${positionIds}`);
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
    if (this.isIntervalHandlerRunningSet.size === 0 && this.updatedPositionIds.size === 0) {
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

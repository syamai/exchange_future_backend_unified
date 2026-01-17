import { Injectable, Logger } from "@nestjs/common";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { OrderEntity } from "src/models/entities/order.entity";
import { convertDateFields } from "../helper";
import { OrderStatus } from "src/shares/enums/order.enum";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import { OPERATION_ID_DIVISOR } from "src/shares/number-formatter";

@Injectable()
export class SaveOrderToCacheUseCase {
  constructor(private readonly redisClient: RedisClient) {}

  private cleanupCachedOrderInterval: NodeJS.Timeout = null;
  private readonly logger = new Logger(SaveOrderToCacheUseCase.name);
  private readonly REDIS_TTL = 3 * 24 * 60 * 60;
  private readonly CLEAN_CACHED_ORDERS_TIME_INTERVAL = 5 * 1000; // 5 seconds

  private isCheckingCleanCachedOrders = false;
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;


  // userIdsNeedToCheckCleanCachedOrders
  // - key: userId
  // - value: {
  //   + lastUpdated
  // }
  private userIdsNeedToCheckCleanCachedOrders: Map<
    number,
    { lastUpdated: number }
  > = new Map();

  // checkedUserIds
  // - key: userId
  // - value: {
  //   + lastCheckingTime
  // }
  private checkedUserIds: Map<number, { lastCheckingTime: number }> = new Map();

  public async execute(commands: CommandOutput[]) {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(commands, CommandCode.STOP_SAVE_ORDERS_TO_CACHE);
    this.setInterval();
    this.setCheckExitInterval();

    const ordersToProcess: OrderEntity[] = [];
    for (const command of commands) {
      if (!command.orders || command.orders.length === 0) continue;

      for (const order of command.orders) {
        const newOrder = convertDateFields(new OrderEntity(), order);
        const newOrderOperationId = newOrder?.operationId
          ? Number(
              (
                BigInt(newOrder.operationId.toString()) % OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

        const existingOrder = ordersToProcess.find(
          (a) => Number(a.id) === Number(newOrder.id)
        );
        const existingOrderOperationId = existingOrder?.operationId
          ? Number(
              (
                BigInt(existingOrder.operationId.toString()) %
                OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

        if (
          !existingOrder ||
          newOrderOperationId == null ||
          existingOrderOperationId == null ||
          newOrderOperationId >= existingOrderOperationId
        ) {
          if (existingOrder) {
            ordersToProcess.splice(ordersToProcess.indexOf(existingOrder), 1);
          }
          ordersToProcess.push(newOrder);
        }
      }
    }

    if (ordersToProcess.length === 0) return;

    // Cache orders to Redis with operationId as score
    for (const order of ordersToProcess) {
      const keyWithOrderId = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${order.userId}:orderId_${order.id}`;
      const keyWithTmpId = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${order.userId}:tmpId_${order.tmpId}`;
      const keyWithActiveOrderIds = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${order.userId}:activeOrderIds`;
      const orderOperationId = order?.operationId
        ? Number(
            (
              BigInt(order.operationId.toString()) % OPERATION_ID_DIVISOR
            ).toString()
          )
        : null;

      this.redisClient
        .getInstance()
        .zadd(keyWithOrderId, orderOperationId, JSON.stringify(order));
      this.redisClient.getInstance().expire(keyWithOrderId, this.REDIS_TTL);

      if (order.tmpId)
        this.redisClient
          .getInstance()
          .set(keyWithTmpId, JSON.stringify(order), "EX", this.REDIS_TTL);

      if (
        order.status?.toString() === OrderStatus.ACTIVE.toString() ||
        order.status?.toString() === OrderStatus.UNTRIGGERED.toString()
      ) {
        this.redisClient
          .getInstance()
          .sadd(keyWithActiveOrderIds, Number(order.id));
      } else {
        this.redisClient
          .getInstance()
          .srem(keyWithActiveOrderIds, Number(order.id));
      }

      // Save to check cleaning cached orders
      this.userIdsNeedToCheckCleanCachedOrders.set(Number(order.userId), {
        lastUpdated: Date.now(),
      });
    }
  }

  private setInterval() {
    if (!this.cleanupCachedOrderInterval) {
      this.cleanupCachedOrderInterval = setInterval(async () => {
        try {
          await this.cleanCachedOrderIntervalHandler();
        } catch (error) {
          this.logger.error("Error cleaning up order versions:", error);
        }
      }, this.CLEAN_CACHED_ORDERS_TIME_INTERVAL);
    }
  }

  private async cleanCachedOrderIntervalHandler() {
    if (this.isCheckingCleanCachedOrders) {
      this.logger.log("Checking in progress. Wait to next turn...");
      return;
    }

    this.isCheckingCleanCachedOrders = true;
    this.logger.log("=====> Start to clean cached orders ...");

    // for (const userIdNeedToCheck of this.userIdsNeedToCheckCleanCachedOrders.keys()) {
    // let shouldCheckClean = true;
    // const current = this.userIdsNeedToCheckCleanCachedOrders.get(
    //   Number(userIdNeedToCheck)
    // );
    // const checked = this.checkedUserIds.get(Number(userIdNeedToCheck));

    // if (checked && checked.lastCheckingTime === current.lastUpdated) {
    //   shouldCheckClean = false;
    // }

    // if (shouldCheckClean) {
    // const pattern = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${userIdNeedToCheck}:orderId_*`;
    const pattern = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_*`;
    const removedIds: string[] = [];
    const cleanOldVersionIds: string[] = [];

    let cursor = "0";
    do {
      const [nextCursor, keys] = await this.redisClient
        .getInstance()
        .scan(cursor, "MATCH", pattern, "COUNT", 1000); // Keep COUNT small to reduce Redis CPU spikes
      cursor = nextCursor;

      const pipeline = this.redisClient.getInstance().multi();

      for (const key of keys) {
        pipeline.zrevrange(key, 0, 0, "WITHSCORES");
      }

      const memberResults = await pipeline.exec();

      const delPipeline = this.redisClient.getInstance().multi();
      let countDelPipeline = 0;

      for (let i = 0; i < keys.length; i++) {
        const key = keys[i];
        const members = memberResults[i][1];

        if (members.length >= 2) {
          const order = JSON.parse(members[members.length - 2]);
          const highestScore = members[members.length - 1];
          const keyWithActiveOrderIds = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${order.userId}:activeOrderIds`;

          if (
            order.status?.toString() === OrderStatus.ACTIVE.toString() ||
            order.status?.toString() === OrderStatus.UNTRIGGERED.toString()
          ) {
            delPipeline.zremrangebyscore(
              key,
              0,
              String(BigInt(highestScore) - BigInt(1))
            );
            delPipeline.sadd(keyWithActiveOrderIds, Number(order.id));
            cleanOldVersionIds.push(order.id);
          } else {
            const keyWithOrderId = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${order.userId}:orderId_${order.id}`;
            const keyWithTmpId = order.tmpId
              ? `${REDIS_COMMON_PREFIX.ORDERS}:userId_${order.userId}:tmpId_${order.tmpId}`
              : null;

            delPipeline.del(keyWithOrderId);
            if (keyWithTmpId) delPipeline.del(keyWithTmpId);

            delPipeline.srem(keyWithActiveOrderIds, Number(order.id));
            removedIds.push(order.id);
          }
          countDelPipeline++;
        }

        if (countDelPipeline >= 100) {
          await delPipeline.exec();
          countDelPipeline = 0;
        }
      }

      if (countDelPipeline > 0) {
        await delPipeline.exec();
      }

      // Optional: slight delay to avoid overloading Redis
      await new Promise((res) => setTimeout(res, 20));
    } while (cursor !== "0");

    // this.logger.log(`===== UserId=${userIdNeedToCheck} =====`);
    this.logger.log(`RemovedIds=${removedIds}`);
    this.logger.log(`CleanOldVersionIds=${cleanOldVersionIds}`);

    // const now = Date.now();
    // current.lastUpdated = now;
    // this.checkedUserIds.set(Number(userIdNeedToCheck), {
    //   lastCheckingTime: now,
    // });
    // }

    // // Cleanup old entry
    // const lastUpdated = this.userIdsNeedToCheckCleanCachedOrders.get(
    //   Number(userIdNeedToCheck)
    // ).lastUpdated;
    // const threeDaysAgo = Date.now() - 3 * 24 * 60 * 60 * 1000;

    // if (lastUpdated < threeDaysAgo) {
    //   this.userIdsNeedToCheckCleanCachedOrders.delete(userIdNeedToCheck);
    // }
    // }

    this.isCheckingCleanCachedOrders = false;
  }

  private async cleanCachedOrderIntervalHandlerBackup() {
    if (this.isCheckingCleanCachedOrders) {
      this.logger.log("Checking in progress. Wait to next turn...");
      return;
    }

    this.isCheckingCleanCachedOrders = true;
    this.logger.log("=====> Start to clean cached orders ...");

    for (const userIdNeedToCheck of this.userIdsNeedToCheckCleanCachedOrders.keys()) {
      let shouldCheckClean = true;
      const current = this.userIdsNeedToCheckCleanCachedOrders.get(
        Number(userIdNeedToCheck)
      );
      const checked = this.checkedUserIds.get(Number(userIdNeedToCheck));

      if (checked && checked.lastCheckingTime === current.lastUpdated) {
        shouldCheckClean = false;
      }

      if (shouldCheckClean) {
        const removedIds: string[] = [];
        const cleanOldVersionIds: string[] = [];
        const keyWithActiveOrderIds = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${userIdNeedToCheck}:activeOrderIds`;
        const orderIds = await this.redisClient
          .getInstance()
          .smembers(keyWithActiveOrderIds);

        const batch = 1000;
        for (let i = 0; i < orderIds.length; i += batch) {
          const batchOrderIds = orderIds.slice(i, i + batch);

          const pipeline = this.redisClient.getInstance().multi();
          const delPipeline = this.redisClient.getInstance().multi();
          for (const orderId of batchOrderIds) {
            const keyWithOrderId = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${userIdNeedToCheck}:orderId_${orderId}`;
            pipeline.zrevrange(keyWithOrderId, 0, 0, "WITHSCORES");
          }

          const memberResults = await pipeline.exec();

          for (let j = 0; j < batchOrderIds.length; j++) {
            const members = memberResults[j][1];
            if (!members || members.length < 2) continue;

            const order = JSON.parse(
              members[members.length - 2]
            ) as OrderEntity;
            const highestScore = members[members.length - 1];
            const keyWithOrderId = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${order.userId}:orderId_${order.id}`;

            if (
              order.status?.toString() === OrderStatus.ACTIVE.toString() ||
              order.status?.toString() === OrderStatus.UNTRIGGERED.toString()
            ) {
              delPipeline.zremrangebyscore(
                keyWithOrderId,
                0,
                String(BigInt(highestScore) - BigInt(1))
              );
              delPipeline.sadd(keyWithActiveOrderIds, Number(order.id));
              cleanOldVersionIds.push(String(order.id));
            } else {
              const keyWithTmpId = order.tmpId
                ? `${REDIS_COMMON_PREFIX.ORDERS}:userId_${order.userId}:tmpId_${order.tmpId}`
                : null;

              delPipeline.del(keyWithOrderId);
              if (keyWithTmpId) delPipeline.del(keyWithTmpId);

              delPipeline.srem(keyWithActiveOrderIds, Number(order.id));
              removedIds.push(String(order.id));
            }
          }

          await delPipeline.exec();
          await new Promise((res) => setTimeout(res, 20));
        }

        this.logger.log(`===== UserId=${userIdNeedToCheck} =====`);
        this.logger.log(`RemovedIds=${removedIds}`);
        this.logger.log(`CleanOldVersionIds=${cleanOldVersionIds}`);

        const now = Date.now();
        current.lastUpdated = now;
        this.checkedUserIds.set(Number(userIdNeedToCheck), {
          lastCheckingTime: now,
        });
      }

      // Cleanup old entry
      const lastUpdated = this.userIdsNeedToCheckCleanCachedOrders.get(
        Number(userIdNeedToCheck)
      ).lastUpdated;
      const threeDaysAgo = Date.now() - 3 * 24 * 60 * 60 * 1000;

      if (lastUpdated < threeDaysAgo) {
        this.userIdsNeedToCheckCleanCachedOrders.delete(userIdNeedToCheck);
      }
    }

    this.isCheckingCleanCachedOrders = false;
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

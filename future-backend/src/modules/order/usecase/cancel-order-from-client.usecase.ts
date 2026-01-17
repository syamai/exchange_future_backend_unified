import { Injectable, Logger } from "@nestjs/common";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { LinkedQueue } from "src/utils/linked-queue";
import { CancelOrderFromClientCommand } from "../kafka-command/cancel-order-from-client.command";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { OrderEntity } from "src/models/entities/order.entity";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { OrderStatus } from "src/shares/enums/order.enum";
import { In } from "typeorm";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { InjectRepository } from "@nestjs/typeorm";
import { OrderRepository } from "src/models/repositories/order.repository";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import { OrderRouterService } from "src/shares/order-router/order-router.service";

@Injectable()
export class CancelOrderFromClientUseCase {
  constructor(
    private readonly kafkaClient: KafkaClient,
    private readonly redisClient: RedisClient,
    @InjectRepository(OrderRepository, "report")
    private readonly orderRepoReport: OrderRepository,
    private readonly orderRouter: OrderRouterService
  ) {}

  private static interval = null;
  private readonly MAX_QUEUE_SIZE = 100000;
  private readonly logger = new Logger(CancelOrderFromClientUseCase.name);
  private static cancelOrderCommands = new LinkedQueue<CancelOrderFromClientCommand>();

  public async execute() {
    if (!CancelOrderFromClientUseCase.interval) {
      CancelOrderFromClientUseCase.interval = setInterval(async () => {
        try {
          // console.log(`Interval is running...`);
          const batch = 10;
          const cancelOrderCommandsToProcess: CancelOrderFromClientCommand[] = [];
          while (
            cancelOrderCommandsToProcess.length < batch &&
            !CancelOrderFromClientUseCase.cancelOrderCommands.isEmpty()
          ) {
            cancelOrderCommandsToProcess.push(
              CancelOrderFromClientUseCase.cancelOrderCommands.dequeue()
            );
          }

          await this.processCommands(cancelOrderCommandsToProcess);
        } catch (e) {
          console.log("Error: ", e);
        }
      }, 50);
    }

    await this.kafkaClient.consume(
      KafkaTopics.cancel_order_from_client,
      KafkaGroups.cancel_order_from_client,
      async (command: CancelOrderFromClientCommand) => {
        if (
          CancelOrderFromClientUseCase.cancelOrderCommands.size() >=
          this.MAX_QUEUE_SIZE
        ) {
          this.logger.warn(
            `cancelOrderCommands size=${CancelOrderFromClientUseCase.cancelOrderCommands.size()} is greater than MAX_QUEUE_SIZE, wait 100ms`
          );
          await new Promise((resolve) => setTimeout(resolve, 100));
        }

        CancelOrderFromClientUseCase.cancelOrderCommands.enqueue(command);
      }
    );

    return new Promise(() => {});
  }

  private async processCommands(
    cancelOrderCommandsToProcess: CancelOrderFromClientCommand[]
  ) {
    for (const command of cancelOrderCommandsToProcess) {
      const { userId, orderId } = command;
      if (!orderId || !userId) continue;

      let canceledOrder: OrderEntity | null = null;
      if (orderId.startsWith("uuid-")) {
        const redisKey = `orders:userId_${userId}:tmpId_${orderId}`;
        const redisOrder = await this.redisClient.getInstance().get(redisKey);
        if (redisOrder) {
          canceledOrder = JSON.parse(redisOrder);
        }
      } else {
        const redisKey = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${userId}:orderId_${orderId}`;
        const members = await this.redisClient
          .getInstance()
          .zrevrange(redisKey, 0, 0, "WITHSCORES");

        // Keep only the member with highest score
        if (members.length >= 2) {
          const highestScoreMember = members[members.length - 2];
          canceledOrder = JSON.parse(highestScoreMember);
        }
      }

      // In case this order is not on redis => retrieve from db
      if (!canceledOrder) {
        if (orderId.startsWith("uuid-")) {
          // Find by tmpId (uuid)
          canceledOrder = await this.orderRepoReport.findOne({
            where: {
              tmpId: String(orderId),
              userId: Number(userId),
              status: In([
                OrderStatus.ACTIVE,
                OrderStatus.PENDING,
                OrderStatus.UNTRIGGERED,
              ]),
            },
          });
        } else {
          // Find by id
          canceledOrder = await this.orderRepoReport.findOne({
            where: {
              id: Number(orderId),
              userId: Number(userId),
              status: In([
                OrderStatus.ACTIVE,
                OrderStatus.PENDING,
                OrderStatus.UNTRIGGERED,
              ]),
            },
          });
        }
      }

      if (!canceledOrder) {
        this.logger.warn(`Cancel order fail: ${orderId}`);
        continue;
      }

      this.orderRouter.routeCommand(canceledOrder.symbol, {
        code: CommandCode.CANCEL_ORDER,
        data: canceledOrder,
      });
    }
  }
}

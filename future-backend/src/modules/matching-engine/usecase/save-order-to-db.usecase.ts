import { Injectable, Logger } from "@nestjs/common";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { convertDateFields } from "../helper";
import { OrderEntity } from "src/models/entities/order.entity";
import { InjectRepository } from "@nestjs/typeorm";
import { OrderRepository } from "src/models/repositories/order.repository";
import { OrderStatus } from "src/shares/enums/order.enum";
import BigNumber from "bignumber.js";
import { BotInMemoryService } from "src/modules/bot/bot.in-memory.service";
import { v4 as uuidv4 } from "uuid";
import { OPERATION_ID_DIVISOR } from "src/shares/number-formatter";

@Injectable()
export class SaveOrderToDbUseCase {
  constructor(
    @InjectRepository(OrderRepository, "master")
    private orderRepository: OrderRepository,
    private readonly botInMemoryService: BotInMemoryService
  ) {}
  private readonly logger = new Logger(SaveOrderToDbUseCase.name);

  private readonly BATCH_SIZE = 1000;
  private readonly MAX_PROCESS_TIME = 10000;

  private static saveOrdersToDbTimeout: NodeJS.Timeout = null;
  private checkProcessOrdersWhenStopSendMessageInterval: NodeJS.Timeout = null;
  private static orderMessagesToSaveDb: OrderEntity[] = [];

  // private isProcessing: boolean = false;
  private isProcessingSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;

  // Internal cache to store recently deleted order IDs with a TTL of 1 minute
  private readonly deletedOrderIdCache: Map<number, number> = new Map(); // orderId -> expiresAt (timestamp in ms)
  private readonly DELETED_ORDER_ID_TTL_MS = 60 * 1000; // 1 minute

  public async execute(commands: CommandOutput[]) {
    if (this.shouldStopConsumer) {
      await this.processOrders();
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(commands, CommandCode.STOP_SAVE_ORDERS_TO_DB);
    this.setCheckExitInterval();

    if (!this.checkProcessOrdersWhenStopSendMessageInterval) {
      this.checkProcessOrdersWhenStopSendMessageInterval = setInterval(
        async () => {
          this.checkProcessOrdersWhenStopSendMessageIntervalHandler();
        },
        this.MAX_PROCESS_TIME
      );
    }

    const orders = [];
    for (const c of commands) {
      if (c.orders && c.orders.length !== 0) orders.push(...c.orders);
    }
    if (orders.length === 0) return;

    for (const orderMsg of orders) {
      const order = convertDateFields(new OrderEntity(), orderMsg);
      SaveOrderToDbUseCase.orderMessagesToSaveDb.push(order);
    }

    if (SaveOrderToDbUseCase.orderMessagesToSaveDb.length >= this.BATCH_SIZE) {
      await this.processOrders();
      return;
    }
  }

  private async processOrders() {
    if (SaveOrderToDbUseCase.orderMessagesToSaveDb.length === 0) return;
    if (this.isProcessingSet.size > 0) return;

    const ssid = uuidv4();
    this.isProcessingSet.add(ssid);
    try {
      const ordersToProcess = SaveOrderToDbUseCase.orderMessagesToSaveDb.splice(
        0,
        SaveOrderToDbUseCase.orderMessagesToSaveDb.length
      );

      const entityById: Map<String, OrderEntity> = new Map();
      for (const order of ordersToProcess) {
        const oldOrder = entityById.get(String(order.id));
        if (!oldOrder) {
          entityById.set(String(order.id), order);
          continue;
        }

        const oldOrderOperationId = oldOrder?.operationId
          ? new BigNumber(oldOrder.operationId.toString())
          : null;
        const orderOperationId = order?.operationId
          ? new BigNumber(order.operationId.toString())
          : null;

        if (oldOrderOperationId.isLessThanOrEqualTo(orderOperationId)) {
          entityById.set(String(order.id), order);
        }
      }

      // Filter bot's orders with status CANCELLED
      const orderIdsToDelete = [];
      const ordersToSave = [];
      for (const entity of entityById.values()) {
        if (this.isOrderIdRecentlyDeleted(entity.id)) continue;
        if (
          (await this.botInMemoryService.checkIsBotAccountId(
            entity.accountId
          )) &&
          entity.status === OrderStatus.CANCELED &&
          entity.remaining &&
          entity.quantity &&
          new BigNumber(entity.remaining).eq(new BigNumber(entity.quantity))
        ) {
          orderIdsToDelete.push(entity.id);
          this.saveDeletedOrderId(entity.id);
        } else {
          ordersToSave.push(entity);
        }
      }

      this.logger.log(
        `Save orderIds=${JSON.stringify(ordersToSave.map((o) => o.id))}`
      );
      await this.orderRepository.insertOrUpdate(Array.from(entityById.values()));
      // if (orderIdsToDelete.length) {
      //   this.logger.log(`Delete orderIds: ${JSON.stringify(orderIdsToDelete)}`);
      //   await this.orderRepository.delete(orderIdsToDelete);
      // }
    } catch (e) {
      this.logger.error(`Something went wrong: `);
      this.logger.error(e);
    } finally {
      this.isProcessingSet.delete(ssid);
      this.cleanupDeletedOrderIdCache();
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
    this.logger.log(`this.isProcessingSet.size = ${this.isProcessingSet.size}`);
    this.logger.log(
      `SaveOrderToDbUseCase.orderMessagesToSaveDb.length = ${SaveOrderToDbUseCase.orderMessagesToSaveDb.length}`
    );
    if (
      this.isProcessingSet.size === 0 &&
      SaveOrderToDbUseCase.orderMessagesToSaveDb.length === 0
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

  private async checkProcessOrdersWhenStopSendMessageIntervalHandler() {
    const countCheck1 = SaveOrderToDbUseCase.orderMessagesToSaveDb.length;
    await new Promise((res) => setTimeout(res, 2000));
    const countCheck2 = SaveOrderToDbUseCase.orderMessagesToSaveDb.length;
    if (
      countCheck1 === countCheck2 ||
      new BigNumber(countCheck2 - countCheck1).abs().lte(20)
    ) {
      await this.processOrders();
    }
  }

  public saveDeletedOrderId(orderId: number): void {
    const expiresAt = Date.now() + this.DELETED_ORDER_ID_TTL_MS;
    this.deletedOrderIdCache.set(Number(orderId), expiresAt);
  }

  public isOrderIdRecentlyDeleted(orderId: number): boolean {
    const expiresAt = this.deletedOrderIdCache.get(Number(orderId));
    if (!expiresAt) return false;
    if (Date.now() > expiresAt) {
      this.deletedOrderIdCache.delete(Number(orderId));
      return false;
    }
    return true;
  }

  private cleanupDeletedOrderIdCache(): void {
    const now = Date.now();
    for (const [orderId, expiresAt] of this.deletedOrderIdCache.entries()) {
      if (now > expiresAt) {
        this.deletedOrderIdCache.delete(orderId);
      }
    }
  }
}

import { Injectable, CACHE_MANAGER, Inject } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { OrderService } from "./order.service";
import { logger } from "ethers";
import { InjectRepository } from "@nestjs/typeorm";
import { OrderRepository } from "src/models/repositories/order.repository";
import { OrderEntity } from "src/models/entities/order.entity";
import * as moment from "moment";
import { OrderInvertedIndexCreatedAtSymbolTypeStatusRepository } from "src/models/repositories/order-inverted-index-created-at-symbol-type-status.repository";
import {
  OrderNote,
  OrderSide,
  OrderTimeInForce,
  OrderType,
  TpSlType,
} from "src/shares/enums/order.enum";
import { OrderInvertedIndexCreatedAtSymbolTypeStatusEntity } from "src/models/entities/order_inverted_index_createdAt_symbol_type_status.entity";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { Cache } from "cache-manager";
import { TakeProfitStopLossOrder } from "./tp-sl.type";
import { CreateOrderDto } from "./dto/create-order.dto";
import { IUserAccount } from "./interface/account-user.interface";
import {
  DEFAULT_LEVERAGE,
  DEFAULT_MARGIN_MODE,
} from "../user-margin-mode/user-marging-mode.const";
import { CommandCode } from "../matching-engine/matching-engine.const";
import { plainToClass } from "class-transformer";
import { InstrumentService } from "../instrument/instrument.service";
import { UserMarginModeService } from "../user-margin-mode/user-margin-mode.service";
import { SaveOrderFromClientCommand } from "./kafka-command/save-order-from-client.command";
import { Logger } from "ethers/lib/utils";
import { LinkedQueue } from "src/utils/linked-queue";
import BigNumber from "bignumber.js";
import { ORDER_FILLED_TYPE, ORDER_ID_PREFIX, OrderTypeSendEmail, RECENT_FILLED_ORDER_SENT_MAIL_PREFIX, RECENT_LIQUIDATION_ORDER_SENT_MAIL_PREFIX, RECENT_PARTIAL_FILLED_ORDER_SENT_MAIL_PREFIX, SEND_EMAIL_ORDER_TYPE_TEMPLATE_1, SEND_EMAIL_ORDER_TYPE_TEMPLATE_2, SEND_EMAIL_ORDER_TYPE_TEMPLATE_3, SEND_EMAIL_ORDER_TYPE_TEMPLATE_4, SEND_EMAIL_TEMPLATE } from "./order.const";
import { RedisService } from "nestjs-redis";
import { v4 as uuidv4 } from "uuid";
import { convertDateFields } from "../matching-engine/helper";
import { NotificationService } from "../matching-engine/notifications.service";
import { SaveOrderFromClientV2UseCase } from "./usecase/save-order-from-client-v2.usecase";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { CancelOrderFromClientUseCase } from "./usecase/cancel-order-from-client.usecase";
import { SaveUserMarketOrderUseCase } from "./usecase/save-user-market-order.usecase";

@Console()
@Injectable()
export class OrderConsole {
  private readonly MAX_SAVE_ORDER_FROM_CLIENT_QUEUE_SIZE = 100000;
  private readonly logger = new Logger(OrderConsole.name);

  constructor(
    private orderService: OrderService,
    @InjectRepository(OrderRepository, "report")
    public readonly orderRepoReport: OrderRepository,
    // @InjectRepository(
    //   OrderInvertedIndexCreatedAtSymbolTypeStatusRepository,
    //   "report"
    // )
    // public readonly orderInvertedIndexCreatedAtSymbolTypeStatusRepository: OrderInvertedIndexCreatedAtSymbolTypeStatusRepository,
    public readonly kafkaClient: KafkaClient,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    @InjectRepository(OrderRepository, "master")
    public readonly orderRepoMaster: OrderRepository,
    public readonly instrumentService: InstrumentService,
    public readonly userMarginModeService: UserMarginModeService,
    // private readonly redisService: RedisService,
    private readonly notificationService: NotificationService,
    private readonly saveOrderFromClientV2UseCase: SaveOrderFromClientV2UseCase,
    private readonly saveUserMarketOrderUseCase: SaveUserMarketOrderUseCase,
    private readonly cancelOrderFromClientUseCase: CancelOrderFromClientUseCase,
    private readonly redisClient: RedisClient
  ) {}

  @Command({
    command: "order:update-userId",
    description: "update userId and accountId",
  })
  async insertCoinInfo(): Promise<void> {
    await this.orderService.updateUserIdInOrder();
  }

  @Command({
    command: "order:update-email-order",
    description: "update user email",
  })
  async updateEmailOrder(): Promise<void> {
    await this.orderService.updateUserEmailInOrder();
  }
  // @Command({
  //   command: 'order:test-update-email-order [orderId]',
  //   description: 'update user email',
  // })
  // async testUpdateEmailOrder(orderId: string): Promise<void> {
  //   console.log(orderId);

  //   await this.orderService.testUpdateUserEmailInOrder(orderId);
  // }

  @Command({
    command: "order:enable-create-order [text]",
    description: "enable or disable create order",
  })
  async enableOrDisableCreateOrder(text: string): Promise<void> {
    let status = false;
    if (text === "disable") {
      status = true;
    }
    await this.orderService.setCacheEnableOrDisableCreateOrder(status);
  }

  private static saveOrderCommands = new LinkedQueue<SaveOrderFromClientCommand>();
  private static interval = null;
  @Command({
    command: "order:save-from-client",
    description: "Save order from client",
  })
  async saveOrderFromClient(): Promise<void> {
    if (!OrderConsole.interval) {
      OrderConsole.interval = setInterval(async () => {
        try {
          // console.log(`Interval is running...`);
          const batch = 10;
          const saveOrderCommandsToProcess = [];
          while (
            saveOrderCommandsToProcess.length < batch &&
            !OrderConsole.saveOrderCommands.isEmpty()
          ) {
            saveOrderCommandsToProcess.push(
              OrderConsole.saveOrderCommands.dequeue()
            );
          }

          // Count number of requests received from client
          // const redisClient = (this.cacheManager.store as any).getClient();
          // await redisClient.incrby("numOfOrdersConsumedFromKafka", saveOrderCommandsToProcess.length);
          // await redisClient.expire("numOfOrdersConsumedFromKafka", 3600000000000);

          const stopLossOrdersPromises = [];
          const takeProfitOrdersPromises = [];
          const tmpOrders = [];
          for (const command of saveOrderCommandsToProcess) {
            const {
              createOrderDto,
              accountData,
              tmpOrder,
            }: {
              createOrderDto: CreateOrderDto;
              accountData: IUserAccount;
              tmpOrder: any;
            } = command;
            if (
              createOrderDto == null ||
              accountData == null ||
              tmpOrder == null
            )
              continue;
            console.log(`tmpOrder.id = ${tmpOrder.id}`);
            tmpOrder.tmpId = tmpOrder.id;
            tmpOrder.id = null;
            tmpOrder.originalCost = "0";

            const { side, trigger, orderValue, ...body } = createOrderDto;
            if (body.stopLoss) {
              stopLossOrdersPromises.push(
                this.orderRepoMaster.save({
                  ...body,
                  accountId: accountData.accountId,
                  userId: accountData.userId,
                  side: side === OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY,
                  tpSLPrice: body.stopLoss,
                  trigger: tmpOrder.stopLossTrigger,
                  orderValue: "0",
                  tpSLType: TpSlType.STOP_MARKET,
                  stopLoss: null,
                  takeProfit: null,
                  price: null,
                  type: OrderType.MARKET,
                  asset: tmpOrder.asset,
                  leverage: tmpOrder.leverage,
                  marginMode: tmpOrder.marginMode,
                  timeInForce: OrderTimeInForce.IOC,
                  isHidden: true,
                  stopCondition: tmpOrder.stopLossCondition,
                  isReduceOnly: true,
                  isTpSlOrder: true,
                  contractType: tmpOrder.contractType,
                  isPostOnly: false,
                  userEmail: accountData.email,
                  originalCost: "0",
                  originalOrderMargin: "0",
                  tmpId: tmpOrder.stopLossOrderId,
                })
              );
            } else {
              stopLossOrdersPromises.push(null);
            }

            if (body.takeProfit) {
              takeProfitOrdersPromises.push(
                this.orderRepoMaster.save({
                  ...body,
                  accountId: accountData.accountId,
                  userId: accountData.userId,
                  side: side === OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY,
                  tpSLPrice: body.takeProfit,
                  trigger: tmpOrder.takeProfitTrigger,
                  orderValue: "0",
                  tpSLType: TpSlType.TAKE_PROFIT_MARKET,
                  stopLoss: null,
                  takeProfit: null,
                  price: null,
                  type: OrderType.MARKET,
                  asset: tmpOrder.asset,
                  leverage: tmpOrder.leverage,
                  marginMode: tmpOrder.marginMode,
                  timeInForce: OrderTimeInForce.IOC,
                  isHidden: true,
                  stopCondition: tmpOrder.takeProfitCondition,
                  isReduceOnly: true,
                  isTpSlOder: true,
                  contractType: tmpOrder.contractType,
                  isPostOnly: false,
                  userEmail: accountData.email,
                  originalCost: "0",
                  originalOrderMargin: "0",
                  tmpId: tmpOrder.takeProfitOrderId,
                })
              );
            } else {
              takeProfitOrdersPromises.push(null);
            }

            tmpOrders.push(tmpOrder);
          }

          const [stopLossOrders, takeProfitOrders] = await Promise.all([
            Promise.all(stopLossOrdersPromises),
            Promise.all(takeProfitOrdersPromises),
          ]);

          // Set tpsl orders to redis
          // for (const order of [...stopLossOrders, ...takeProfitOrders]) {
          //   if (!order) continue;
          //   const redisKey = `orders:userId_${order.userId}:orderId_${order.id}`;
          //   await this.redisService.getClient().set(redisKey, JSON.stringify(order), 'EX', 259200); // 3 days TTL
          // }

          const ordersToSavePromises = [];
          for (let z = 0; z < tmpOrders.length; z++) {
            const newTmpOrder = {
              ...tmpOrders[z],
              stopLossOrderId: stopLossOrders[z]?.id ?? null,
              takeProfitOrderId: takeProfitOrders[z]?.id ?? null,
            };
            ordersToSavePromises.push(this.orderRepoMaster.save(newTmpOrder));
          }

          const newOrders = await Promise.all(ordersToSavePromises);
          // Set orders to redis
          // for (const order of newOrders) {
          //   if (!order) continue;
          //   const redisKey = `orders:userId_${order.userId}:orderId_${order.id}`;
          //   await this.redisService.getClient().set(redisKey, JSON.stringify(order), 'EX', 259200); // 3 days TTL
          // }

          for (let z = 0; z < newOrders.length; z++) {
            const newOrder = newOrders[z];
            this.orderService.removeEmptyValues(newOrder);
            this.kafkaClient.send(KafkaTopics.matching_engine_input, {
              code: CommandCode.PLACE_ORDER,
              data: plainToClass(OrderEntity, newOrder),
            });

            if (newOrder.stopLossOrderId) {
              this.orderService.removeEmptyValues(stopLossOrders[z]);
              this.kafkaClient.send(KafkaTopics.matching_engine_input, {
                code: CommandCode.PLACE_ORDER,
                data: plainToClass(OrderEntity, {
                  ...stopLossOrders[z],
                  linkedOrderId: newOrder.takeProfitOrderId,
                  parentOrderId: newOrder.id,
                }),
              });
            }

            if (newOrder.takeProfitOrderId) {
              this.orderService.removeEmptyValues(takeProfitOrders[z]);
              this.kafkaClient.send(KafkaTopics.matching_engine_input, {
                code: CommandCode.PLACE_ORDER,
                data: plainToClass(OrderEntity, {
                  ...takeProfitOrders[z],
                  linkedOrderId: newOrder.stopLossOrderId,
                  parentOrderId: newOrder.id,
                }),
              });
            }
          }
        } catch (e) {
          console.log("Error: ", e);
        }
      }, 50);
    }

    await this.kafkaClient.consume(
      KafkaTopics.save_order_from_client,
      KafkaGroups.save_order_from_client,
      async (command: SaveOrderFromClientCommand) => {
        if (
          OrderConsole.saveOrderCommands.size() >=
          this.MAX_SAVE_ORDER_FROM_CLIENT_QUEUE_SIZE
        ) {
          this.logger.warn(
            `saveOrderCommands size=${OrderConsole.saveOrderCommands.size()} is greater than MAX_SAVE_ORDER_FROM_CLIENT_QUEUE_SIZE, wait 100ms`
          );
          await new Promise((resolve) => setTimeout(resolve, 100));
        }

        OrderConsole.saveOrderCommands.enqueue(command);
      }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "order:save-from-client-v2",
    description: "Save order from client",
  })
  async saveOrderFromClientV2(): Promise<void> {
    await this.saveOrderFromClientV2UseCase.execute();
  }

  @Command({
    command: "order:save-user-market-order-from-client",
    description: "Save order from client for user market order",
  })
  async saveUserMarketOrderFromClient(): Promise<void> {
    await this.saveUserMarketOrderUseCase.execute();
  }

  @Command({
    command: "order:cancel-from-client",
    description: "Cancel order from client",
  })
  async cancelOrderFromClient(): Promise<void> {
    await this.cancelOrderFromClientUseCase.execute();
  }

  private getOrderPartiallyFilledRedisKey(orderId: number): string {
    return `${RECENT_PARTIAL_FILLED_ORDER_SENT_MAIL_PREFIX}:${ORDER_ID_PREFIX}${orderId}`
  }

  private getOrderFullyFilledRedisKey(orderId: number): string {
    return `${RECENT_FILLED_ORDER_SENT_MAIL_PREFIX}:${ORDER_ID_PREFIX}${orderId}`
  }

  private getRecentOrderLiquidationRedisKey(orderId: number): string {
    return `${RECENT_LIQUIDATION_ORDER_SENT_MAIL_PREFIX}:${ORDER_ID_PREFIX}${orderId}`
  }

  @Command({
    command: "order:prepare-order-to-send-mail-and-notify",
  })
  async prepareOrderToSendMail(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.prepare_order_to_send_mail_and_notify,
      KafkaGroups.process_order_to_send_mail,
      async (orderMsg: any) => {
        const order = convertDateFields(new OrderEntity(), orderMsg)
        order['trade'].createdAt = orderMsg.trade?.createdAt ? new Date(orderMsg.trade.createdAt).toISOString() : new Date().toISOString();
        // If this order is LIQUIDATION order (liquidate position)
        if (order.note != null && order.note === OrderNote.LIQUIDATION) {
          const recentLiquiKey = this.getRecentOrderLiquidationRedisKey(order.id)
          const recentLiquiValue = await this.redisClient.getInstance().get(recentLiquiKey);
          if (recentLiquiValue == '1') return;

          const message = {
            templateType: SEND_EMAIL_TEMPLATE.TEMPLATE_4, 
            data: {
              orderType: OrderTypeSendEmail.LIQUIDATION,
              userId: order.userId, 
              symbol: order.symbol?.replace("USDT", "/USDT"),
              positionType: order.side === OrderSide.SELL ? "Long": "Short", 
              leverage: order.leverage,
              price: order.price,
              time: order["trade"]?.createdAt
            }
          }
          await this.kafkaClient.send(KafkaTopics.send_mail_on_spot, message);

          await this.redisClient.getInstance().set(recentLiquiKey, '1', 'EX', 3600);
          return;
        }

        // If this order is fully filled
        if (new BigNumber(order.remaining).isEqualTo(0)) {
          if (!this.isOrderTypeForFilledCase(order)) {
            return;
          }

          // Send message and set redis key
          const filledKey = this.getOrderFullyFilledRedisKey(order.id)
          const recentFilled = await this.redisClient.getInstance().get(filledKey);
          if (recentFilled == '1') return;
          const message = this.getMessageForFilledOrder(order);
          await this.kafkaClient.send(KafkaTopics.send_mail_on_spot, message);
          await this.redisClient.getInstance().set(filledKey, '1', 'EX', 3600);
          await this.firebaseNotifyLimitOrder(message);
          return;
        }
        
        // If this order is partially filled
        if (!new BigNumber(order.remaining).isEqualTo(0) && !new BigNumber(order.remaining).isEqualTo(order.quantity)) {
          // if order is not limit, stop limit, stop market, trailing stop, post only, return
          if (!this.isOrderTypeForPartialFilledCase(order)) {
            return;
          }
          
          const partialKey = this.getOrderPartiallyFilledRedisKey(order.id);
          const filledKey = this.getOrderFullyFilledRedisKey(order.id);
          
          const randomNonce = `nonce_${uuidv4()}`;
          const currentRemainingQty = order.remaining;
          const existValue = await this.redisClient.getInstance().get(partialKey);
          if (existValue) {
            const { remainingQuantity } = JSON.parse(existValue);
            if (new BigNumber(remainingQuantity).gt(currentRemainingQty)) {
              await this.redisClient.getInstance().set(partialKey, JSON.stringify({ remainingQuantity: currentRemainingQty, randomNonce }), "EX", 3600);
            } else return;
          } else {
            await this.redisClient.getInstance().set(partialKey, JSON.stringify({ remainingQuantity: currentRemainingQty, randomNonce }), "EX", 3600);
          }          

          // Set timeout 5s
          setTimeout(async () => {
            const valueAfterAWhile = await this.redisClient.getInstance().get(partialKey);

            const { randomNonce: randomNonceAfterAWhile } = JSON.parse(valueAfterAWhile)
            // Nếu giá trị hiện tại không khớp randomNonce => có event mới => bỏ qua
            if (randomNonceAfterAWhile !== randomNonce) return;

            // Nếu order đã filled => đã gửi mail trước đó => bỏ qua
            const isFilled = await this.redisClient.getInstance().get(filledKey);
            if (isFilled) {
              await this.redisClient.getInstance().del(partialKey);
              return;
            }

            // ✅ Send mail here
            let message = this.getMessageForPartialFilledOrder(order);

            // Send message and set redis key
            await this.kafkaClient.send(KafkaTopics.send_mail_on_spot, message);
            await this.firebaseNotifyLimitOrder(message);
            await this.redisClient.getInstance().del(partialKey);
          }, 5000);
        }
      }
    );

    return new Promise(() => {});
  }

  private getMessageForPartialFilledOrder(order: OrderEntity) {
    const orderType = this.getOrderTypeForMessage(order);
    const templateType = this.getEmailTemplateType(orderType);

    const message = {
      templateType,
      data: {
        orderType,
        filledType: ORDER_FILLED_TYPE.PARTIAL_FILLED,
        userId: order.userId,
        symbol: order.symbol?.replace("USDT", "/USDT"),
        side: order.side, 
        price: order.isLimitOrder() ? order.price : order.executedPrice,
        filledQuantity: new BigNumber(order.quantity).minus(order.remaining).toFixed(),
        remaingQuantity: order.remaining,
        time: order["trade"]?.createdAt
      }
    };

    return message;
  }

  private getMessageForFilledOrder(order: OrderEntity) {
    const orderType = this.getOrderTypeForMessage(order);
    const templateType = this.getEmailTemplateType(orderType);
    const price = order.isStopLossOrder() || order.isTakeProfitMarketOrder() ? order.tpSLPrice : order.price; 

    const message = {
      templateType, 
      data: {
        orderType,
        filledType: ORDER_FILLED_TYPE.FILLED,
        userId: order.userId, 
        symbol: order.symbol?.replace("USDT", "/USDT"),
        side: order.side, 
        price,
        quantity: order.quantity,
        time: order["trade"]?.createdAt
      }
    }

    return message;
  }

  private getOrderTypeForMessage(order: OrderEntity) {
    if (order.isLimitOrder()) {
      return OrderTypeSendEmail.LIMIT;
    }

    if (order.isStopLimitOrder()) {
      return OrderTypeSendEmail.STOP_LIMIT;
    }

    if (order.isStopMarketOrder()) {
      return OrderTypeSendEmail.STOP_MARKET;
    }

    if (order.isTrailingStopOrder()) {
      return OrderTypeSendEmail.TRAILING_STOP;
    }

    if (order.isPostOnlyOrder()) {
      return OrderTypeSendEmail.POST_ONLY;
    }

    if (order.isStopLossOrder()) {
      return OrderTypeSendEmail.STOP_LOSS;
    }

    if (order.isTakeProfitMarketOrder()) {
      return OrderTypeSendEmail.TAKE_PROFIT;
    }
  }

  private getEmailTemplateType(orderType: OrderTypeSendEmail) {
    if (SEND_EMAIL_ORDER_TYPE_TEMPLATE_1.includes(orderType)) {
      return SEND_EMAIL_TEMPLATE.TEMPLATE_1;
    }

    if (SEND_EMAIL_ORDER_TYPE_TEMPLATE_2.includes(orderType)) {
      return SEND_EMAIL_TEMPLATE.TEMPLATE_2;
    }

    if (SEND_EMAIL_ORDER_TYPE_TEMPLATE_3.includes(orderType)) {
      return SEND_EMAIL_TEMPLATE.TEMPLATE_3;
    }

    if (SEND_EMAIL_ORDER_TYPE_TEMPLATE_4.includes(orderType)) {
      return SEND_EMAIL_TEMPLATE.TEMPLATE_4;
    }
  }

  private isOrderTypeForFilledCase(order: OrderEntity) {
    // is order type for partial filled and stop loss, take profit
    if (this.isOrderTypeForPartialFilledCase(order) || order.isStopLossOrder() || order.isTakeProfitMarketOrder()) {
      return true;
    }
    return false;
  }

  private isOrderTypeForPartialFilledCase(order: OrderEntity) {
    // if order is not limit, stop limit, stop market, trailing stop, post only, return
    if (
      order.isLimitOrder() ||
      order.isStopLimitOrder() ||
      order.isStopMarketOrder() ||
      order.isTrailingStopOrder() ||
      order.isPostOnlyOrder()
    ) {
      return true;
    }
    return false;
  }

  private async firebaseNotifyLimitOrder(msg: any) {
    if (msg.data?.orderType === OrderTypeSendEmail.LIMIT) {
      await this.notificationService.firebaseNotifyLimitOrders(msg);
    }
  }
}
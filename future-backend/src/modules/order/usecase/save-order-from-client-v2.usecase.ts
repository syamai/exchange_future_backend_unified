import { CACHE_MANAGER, forwardRef, Inject, Injectable, Logger } from "@nestjs/common";
import { LinkedQueue } from "src/utils/linked-queue";
import { SaveOrderFromClientCommandV2 } from "../kafka-command/save-order-from-client.command";
import { AccountService } from "src/modules/account/account.service";
import { OrderService } from "../order.service";
import {
  ContractType,
  OrderSide,
  OrderStatus,
  OrderTimeInForce,
  OrderType,
  TpSlType,
} from "src/shares/enums/order.enum";
import {
  DEFAULT_LEVERAGE,
  DEFAULT_MARGIN_MODE,
} from "src/modules/user-margin-mode/user-marging-mode.const";
import { USDT } from "src/modules/balance/balance.const";
import BigNumber from "bignumber.js";
import { OrderEntity } from "src/models/entities/order.entity";
import { httpErrors } from "src/shares/exceptions";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import {
  CommandCode,
  NotificationEvent,
  NotificationType,
} from "src/modules/matching-engine/matching-engine.const";
import { plainToClass } from "class-transformer";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { UserMarginModeService } from "src/modules/user-margin-mode/user-margin-mode.service";
import { BotInMemoryService } from "src/modules/bot/bot.in-memory.service";
import { BalanceService } from "src/modules/balance/balance.service";
import { PositionRepository } from "src/models/repositories/position.repository";
import { OrderRepository } from "src/models/repositories/order.repository";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { InjectRepository } from "@nestjs/typeorm";
import { AccountEntity } from "src/models/entities/account.entity";
import { RedisService } from "nestjs-redis";
import { AccountRepository } from "src/models/repositories/account.repository";
import { CreateOrderDto } from "../dto/create-order.dto";
import { removeEmptyField } from "src/utils/remove-empty-field";
import { TradingRulesEntity } from "src/models/entities/trading_rules.entity";
import { ORACLE_PRICE_PREFIX } from "src/modules/index/index.const";
import { Ticker, TICKERS_KEY } from "src/modules/ticker/ticker.const";
import { TradingRulesService } from "src/modules/trading-rules/trading-rule.service";
import { Cache } from "cache-manager";
import { UserMarginModeEntity } from "src/models/entities/user-margin-mode.entity";
import { InstrumentEntity } from "src/models/entities/instrument.entity";
import { SocketEmitter } from "src/shares/helpers/socket-emitter";
import { Notification } from "src/modules/matching-engine/matching-engine.const";
import IORedis from "ioredis";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { v4 as uuidv4 } from "uuid";
import { BOT_STOP_CREATE_ORDER } from "../order.const";
import { OrderRouterService } from "src/shares/order-router/order-router.service";

@Injectable()
export class SaveOrderFromClientV2UseCase {
  constructor(
    protected readonly accountService: AccountService,
    @Inject(forwardRef(() => OrderService))
    protected readonly orderService: OrderService,
    protected readonly instrumentService: InstrumentService,
    @Inject(forwardRef(() => UserMarginModeService))
    protected readonly userMarginModeService: UserMarginModeService,
    protected readonly botInMemoryService: BotInMemoryService,
    @Inject(forwardRef(() => BalanceService))
    protected readonly balanceService: BalanceService,
    @InjectRepository(PositionRepository, "report")
    protected readonly positionRepoReport: PositionRepository,
    @InjectRepository(OrderRepository, "master")
    protected readonly orderRepoMaster: OrderRepository,
    protected readonly kafkaClient: KafkaClient,
    protected readonly redisService: RedisService,
    @InjectRepository(AccountRepository, "report")
    protected readonly accountRepoReport: AccountRepository,
    protected readonly tradingRulesService: TradingRulesService,
    @Inject(CACHE_MANAGER) private cacheService: Cache,
    protected readonly redisClient: RedisClient,
    protected readonly orderRouter: OrderRouterService
  ) {}

  private readonly MAX_SAVE_ORDER_FROM_CLIENT_QUEUE_SIZE = 100000;
  private BATCH_SIZE = 500;
  private readonly logger = new Logger(SaveOrderFromClientV2UseCase.name);
  private readonly saveOrderCommands = new LinkedQueue<SaveOrderFromClientCommandV2>();
  private saveOrderInterval = null;
  private readonly errors = new LinkedQueue<{
    code: string;
    message: string;
    userId: string;
  }>();

  private isIntervalHandlerRunningSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;

  public async execute(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.save_order_from_client_v2,
      KafkaGroups.save_order_from_client_v2,
      async (command: SaveOrderFromClientCommandV2) => {
        if (this.shouldStopConsumer) {
          await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
        }

        this.checkHaveStopCommand(
          command,
          CommandCode.STOP_SAVE_ORDERS_FROM_CLIENT
        );
        this.setInterval();
        this.setCheckExitInterval();

        if (
          this.saveOrderCommands.size() >=
          this.MAX_SAVE_ORDER_FROM_CLIENT_QUEUE_SIZE
        ) {
          this.logger.warn(
            `saveOrderCommands size=${this.saveOrderCommands.size()} is greater than MAX_QUEUE_SIZE, wait 100ms`
          );
          await new Promise((resolve) => setTimeout(resolve, 100));
        }

        // if (command.tmpOrderId == CommandCode.STOP_SAVE_ORDERS_FROM_CLIENT)
        //   return;

        const isBot = await this.botInMemoryService.checkIsBotUserId(
          command.userId
        );
        if (isBot) {
          const botStopCreateOrder = await this.redisClient
            .getInstance()
            .get(`${BOT_STOP_CREATE_ORDER}:botUserId_${command.userId}`);
          if (botStopCreateOrder && String(botStopCreateOrder) === "true")
            return;
        }

        // this.saveOrderCommands.enqueue(command);
        await this.processCommand(command);
      }
    );

    return new Promise(() => {});
  }

  private async intervalHandler() {
    // console.log(`Interval is running...`);
    const saveOrderCommandsToProcess: SaveOrderFromClientCommandV2[] = [];
    while (
      saveOrderCommandsToProcess.length < this.BATCH_SIZE &&
      !this.saveOrderCommands.isEmpty()
    ) {
      saveOrderCommandsToProcess.push(this.saveOrderCommands.dequeue());
    }

    // await this.processCommands(saveOrderCommandsToProcess);
    this.processErrors();
  }

  private setInterval() {
    if (!this.saveOrderInterval) {
      this.saveOrderInterval = setInterval(async () => {
        const ssId = uuidv4();
        this.isIntervalHandlerRunningSet.add(ssId);
        try {
          await this.intervalHandler();
        } catch (e) {
          this.logger.error("Error: ", e);
        } finally {
          this.isIntervalHandlerRunningSet.delete(ssId);
        }
      }, 50);
    }
  }

  // private async processCommands(
  //   saveOrderCommandsToProcess: SaveOrderFromClientCommandV2[]
  // ): Promise<void> {
  //   // Start to process
  //   const stopLossOrdersPromises = [];
  //   const takeProfitOrdersPromises = [];
  //   const unsavedOrders = [];
  //   for (const command of saveOrderCommandsToProcess) {
  //     try {
  //       const {
  //         stopLossOrderPromise,
  //         takeProfitOrderPromise,
  //         unsavedOrder,
  //       } = await this.processCommand(command);

  //       stopLossOrdersPromises.push(stopLossOrderPromise);
  //       takeProfitOrdersPromises.push(takeProfitOrderPromise);
  //       unsavedOrders.push(unsavedOrder);
  //     } catch (e) {
  //       this.logger.error(e);
  //     }
  //   }

  //   // Save stop loss and take profit orders
  //   const [stopLossOrders, takeProfitOrders] = await Promise.all([
  //     Promise.all(stopLossOrdersPromises),
  //     Promise.all(takeProfitOrdersPromises),
  //   ]);

  //   const ordersToSavePromises = [];
  //   for (let z = 0; z < unsavedOrders.length; z++) {
  //     if (!unsavedOrders[z]) continue;
  //     const newTmpOrder = {
  //       ...unsavedOrders[z],
  //       stopLossOrderId: stopLossOrders[z]?.id ?? null,
  //       takeProfitOrderId: takeProfitOrders[z]?.id ?? null,
  //     };
  //     ordersToSavePromises.push(this.orderRepoMaster.save(newTmpOrder));
  //   }

  //   // Save main orders and push to kafka for ME processing
  //   const newOrders = await Promise.all(ordersToSavePromises);
  //   for (let z = 0; z < newOrders.length; z++) {
  //     const newOrder = newOrders[z];
  //     this.orderService.removeEmptyValues(newOrder);
  //     this.kafkaClient.send(KafkaTopics.matching_engine_input, {
  //       code: CommandCode.PLACE_ORDER,
  //       data: plainToClass(OrderEntity, newOrder),
  //     });

  //     if (newOrder.stopLossOrderId) {
  //       this.orderService.removeEmptyValues(stopLossOrders[z]);
  //       this.kafkaClient.send(KafkaTopics.matching_engine_input, {
  //         code: CommandCode.PLACE_ORDER,
  //         data: plainToClass(OrderEntity, {
  //           ...stopLossOrders[z],
  //           linkedOrderId: newOrder.takeProfitOrderId,
  //           parentOrderId: newOrder.id,
  //         }),
  //       });
  //     }

  //     if (newOrder.takeProfitOrderId) {
  //       this.orderService.removeEmptyValues(takeProfitOrders[z]);
  //       this.kafkaClient.send(KafkaTopics.matching_engine_input, {
  //         code: CommandCode.PLACE_ORDER,
  //         data: plainToClass(OrderEntity, {
  //           ...takeProfitOrders[z],
  //           linkedOrderId: newOrder.stopLossOrderId,
  //           parentOrderId: newOrder.id,
  //         }),
  //       });
  //     }
  //   }
  //   if (newOrders.length > 10) this.logger.log(`Processed ${newOrders.length}`);
  // }

  private async processCommand(
    command: SaveOrderFromClientCommandV2
  ): Promise<void> {
    let stopLossOrder: OrderEntity = null;
    let takeProfitOrder: OrderEntity = null;
    let unsavedOrder = null;

    const { createOrderDto, userId, tmpOrderId } = command;
    if (createOrderDto == null || userId == null || tmpOrderId == null) return;

    // Parallel fetch for performance optimization
    const [account, instrument] = await Promise.all([
      this.getAccountByUserIdAndAsset(userId, createOrderDto.asset),
      this.instrumentService.getCachedInstrument(createOrderDto.symbol)
    ]);
    if (!account || !instrument) return;

    const marginMode = await this.userMarginModeService.getCachedMarginMode(
      account.userId,
      instrument.id
    );
    const validatedCreateOrderDto = await this.validateOrder(
      createOrderDto,
      account,
      instrument,
      marginMode
    );
    if (!validatedCreateOrderDto) return;
    const { side, trigger, orderValue, ...body } = validatedCreateOrderDto;

    unsavedOrder = {
      ...validatedCreateOrderDto,
      status: OrderStatus.PENDING,
      accountId: account.id,
      leverage: marginMode ? marginMode.leverage : `${DEFAULT_LEVERAGE}`,
      marginMode: marginMode ? marginMode.marginMode : DEFAULT_MARGIN_MODE,
      orderValue: "0",
      userId: account.userId,
      contractType: instrument.contractType,
      isTpSlTriggered: false,
      userEmail: account.userEmail,
      originalCost: "0",
      originalOrderMargin: "0",
      tmpId: tmpOrderId,
    };

    // Handle stop loss and take profit orders in parallel
    const tpSlPromises: Promise<OrderEntity | null>[] = [];

    if (body.stopLoss) {
      tpSlPromises.push(
        this.orderRepoMaster.save({
          ...body,
          accountId: account.id,
          userId: account.userId,
          side: side === OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY,
          tpSLPrice: body.stopLoss,
          trigger: unsavedOrder.stopLossTrigger,
          orderValue: "0",
          tpSLType: TpSlType.STOP_MARKET,
          stopLoss: null,
          takeProfit: null,
          price: null,
          type: OrderType.MARKET,
          asset: unsavedOrder.asset,
          leverage: unsavedOrder.leverage,
          marginMode: unsavedOrder.marginMode,
          timeInForce: OrderTimeInForce.IOC,
          isHidden: true,
          stopCondition: unsavedOrder.stopLossCondition,
          isReduceOnly: true,
          isTpSlOrder: true,
          contractType: unsavedOrder.contractType,
          isPostOnly: false,
          userEmail: account.userEmail,
          originalCost: "0",
          originalOrderMargin: "0",
        })
      );
    } else {
      tpSlPromises.push(Promise.resolve(null));
    }

    if (body.takeProfit) {
      tpSlPromises.push(
        this.orderRepoMaster.save({
          ...body,
          accountId: account.id,
          userId: account.userId,
          side: side === OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY,
          tpSLPrice: body.takeProfit,
          trigger: unsavedOrder.takeProfitTrigger,
          orderValue: "0",
          tpSLType: TpSlType.TAKE_PROFIT_MARKET,
          stopLoss: null,
          takeProfit: null,
          price: null,
          type: OrderType.MARKET,
          asset: unsavedOrder.asset,
          leverage: unsavedOrder.leverage,
          marginMode: unsavedOrder.marginMode,
          timeInForce: OrderTimeInForce.IOC,
          isHidden: true,
          stopCondition: unsavedOrder.takeProfitCondition,
          isReduceOnly: true,
          isTpSlOder: true,
          contractType: unsavedOrder.contractType,
          isPostOnly: false,
          userEmail: account.userEmail,
          originalCost: "0",
          originalOrderMargin: "0",
        })
      );
    } else {
      tpSlPromises.push(Promise.resolve(null));
    }

    [stopLossOrder, takeProfitOrder] = await Promise.all(tpSlPromises);

    if (unsavedOrder.type === OrderType.MARKET) {
      // Check to pre-creating orders
      this.logger.log(`[createOrder] - Start checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
      const orderNeedCreate: CreateOrderDto[] = await this.orderService.checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder({
        symbol: instrument.symbol,
        asset: unsavedOrder.asset,
        quantityOfMarketOrder: unsavedOrder.quantity,
        marketOrderSide: unsavedOrder.side,
      });
      this.logger.log(`[createOrder] - End checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
      // Create order
      // Push order to kafka
      // => orderbookME on cache will be updated
      this.logger.log(`[createOrder] - Start createOrderForDefaultCreateOrderUser.....`)
      await this.orderService.createOrderForDefaultCreateOrderUser({
        createOrderDtos: orderNeedCreate,
        symbol: instrument.symbol,
      });
      this.logger.log(`[createOrder] - End createOrderForDefaultCreateOrderUser.....`)
    }

    const newTmpOrder = {
      ...unsavedOrder,
      stopLossOrderId: stopLossOrder?.id ?? null,
      takeProfitOrderId: takeProfitOrder?.id ?? null,
    };

    const newOrder = await this.orderRepoMaster.save(newTmpOrder);
    this.orderService.removeEmptyValues(newOrder);
    this.orderRouter.routeCommand(newOrder.symbol, {
      code: CommandCode.PLACE_ORDER,
      data: plainToClass(OrderEntity, newOrder),
    });

    if (newOrder.stopLossOrderId) {
      this.orderService.removeEmptyValues(stopLossOrder);
      this.orderRouter.routeCommand(newOrder.symbol, {
        code: CommandCode.PLACE_ORDER,
        data: plainToClass(OrderEntity, {
          ...stopLossOrder,
          linkedOrderId: newOrder.takeProfitOrderId,
          parentOrderId: newOrder.id,
        }),
      });
    }

    if (newOrder.takeProfitOrderId) {
      this.orderService.removeEmptyValues(takeProfitOrder);
      this.orderRouter.routeCommand(newOrder.symbol, {
        code: CommandCode.PLACE_ORDER,
        data: plainToClass(OrderEntity, {
          ...takeProfitOrder,
          linkedOrderId: newOrder.stopLossOrderId,
          parentOrderId: newOrder.id,
        }),
      });
    }
  }

  private processErrors() {
    while (!this.errors.isEmpty()) {
      const error = this.errors.dequeue();
      const notification = ({
        event: NotificationEvent.OrderCanceled,
        type: NotificationType.error,
        userId: error.userId,
        title: error.code,
        message: error.message,
        code: error.code,
      } as unknown) as Notification;
      SocketEmitter.getInstance().emitNotifications(
        [notification],
        +notification.userId
      );
      this.logger.log(`Send error notification: `);
      this.logger.log(notification);
    }
  }

  protected async getAccountByUserIdAndAsset(
    userId: number,
    asset: string
  ): Promise<AccountEntity> {
    let account: AccountEntity;

    // Get from key with asset
    const redisKeyWithAsset = `accounts:userId_${userId}:asset_${asset}`;
    const accountRedisData = await this.redisClient
      .getInstance()
      .get(redisKeyWithAsset);
    if (accountRedisData)
      account = JSON.parse(accountRedisData) as AccountEntity;

    // Get from db
    if (!account) {
      account = await this.accountRepoReport.findOne({
        where: { userId, asset },
        select: ["id", "userId", "userEmail", "asset", "balance"],
      });
      // Cache to Redis for future requests (TTL: 60 seconds)
      if (account) {
        await this.redisClient
          .getInstance()
          .setex(redisKeyWithAsset, 60, JSON.stringify(account));
      }
    }

    if (!account) {
      this.errors.enqueue({
        ...httpErrors.ACCOUNT_NOT_FOUND,
        userId: userId.toString(),
      });
      return null;
    }
    return account;
  }

  protected async validateOrder(
    createOrder: CreateOrderDto,
    account: AccountEntity,
    instrument: InstrumentEntity,
    marginMode: UserMarginModeEntity
  ): Promise<CreateOrderDto> {
    removeEmptyField(createOrder);
    const order = { ...createOrder, originalCost: "0" };
    if (
      order.quantity == null ||
      (typeof order.quantity === "string" && order.quantity === "")
    ) {
      this.errors.enqueue({
        ...httpErrors.ORDER_QUANTITY_VALIDATION_FAIL,
        userId: account.userId.toString(),
      });
      return null;
    }
    if (typeof order.quantity === "string" && order.quantity.includes("e")) {
      order.quantity = new BigNumber(parseFloat(order.quantity)).toFixed();
    }
    if (typeof order.remaining === "string" && order.remaining.includes("e")) {
      order.remaining = new BigNumber(parseFloat(order.remaining)).toFixed();
    }

    // Parallel fetch: isBot and tradingRule
    const [isBot, tradingRule] = await Promise.all([
      this.botInMemoryService.checkIsBotAccountId(account.id),
      this.tradingRulesService.getTradingRuleByInstrumentId(order.symbol) as Promise<TradingRulesEntity>
    ]);

    if (!isBot) {
      const userLeverage = marginMode
        ? Number(marginMode.leverage)
        : Number(DEFAULT_LEVERAGE);

      // Parallel fetch: availableBalance and position
      const [accountAvailableBalance, position] = await Promise.all([
        this.balanceService.calAvailableBalance(account.balance, account.id, USDT),
        this.positionRepoReport.findOne({
          where: { accountId: account.id, symbol: instrument.symbol },
          select: ["id", "currentQty", "marBuy", "marSel"],
        })
      ]);

      const availBalance = new BigNumber(
        accountAvailableBalance.availableBalance
      );

      // Validate quantity
      if (
        typeof order.quantity === "string" &&
        order.type === OrderType.MARKET &&
        String(order?.quantity)?.includes("%")
      ) {
        let orderQtyInPercent = new BigNumber(
          String(order?.quantity).replace("%", "")
        );
        orderQtyInPercent = orderQtyInPercent.isEqualTo(100)
          ? orderQtyInPercent.minus(3)
          : orderQtyInPercent;
        const balanceFromPercent = availBalance
          .dividedBy(100)
          .multipliedBy(orderQtyInPercent);
        const balanceFromPercentMulLeverage = balanceFromPercent.multipliedBy(
          userLeverage
        );
        const markPrice =
          new BigNumber(
            await this.redisClient
              .getInstance()
              .get(`${ORACLE_PRICE_PREFIX}${instrument.symbol}`)
          ) ?? new BigNumber(0);
        const convertedQuantity = balanceFromPercentMulLeverage.dividedBy(
          markPrice
        );
        order.quantity = convertedQuantity.toFixed(
          +instrument.maxFiguresForSize
        );
      }

      // Validate available balance
      const orderCost = await this.orderService.calcOrderCost({
        order: (order as unknown) as OrderEntity,
        position,
        leverage: userLeverage,
        instrument,
        isCoinM: order.contractType === ContractType.COIN_M,
      });

      if (availBalance.isLessThanOrEqualTo(orderCost)) {
        this.errors.enqueue({
          ...httpErrors.NOT_ENOUGH_BALANCE,
          userId: account.userId.toString(),
        });
        return null;
      }
      if (!isNaN(orderCost as any)) {
        order.originalCost = orderCost?.toString() ?? "0";
      }
    }

    order.remaining = order.quantity;
    order.status = OrderStatus.PENDING;
    order.timeInForce = order.timeInForce || OrderTimeInForce.GTC;
    const num = parseInt("1" + "0".repeat(+instrument.maxFiguresForSize));

    const minimumQty = new BigNumber(
      `${(1 / num).toFixed(+instrument.maxFiguresForSize)}`
    );
    const maximumQty = new BigNumber(tradingRule.maxOrderAmount);

    // FOR ALL ORDER TYPE VALIDATION
    // validate minimum quantity
    if (minimumQty.gt(order.quantity)) {
      this.errors.enqueue({
        ...httpErrors.ORDER_MINIMUM_QUANTITY_VALIDATION_FAIL,
        userId: account.userId.toString(),
      });
      return null;
    }
    // validate maximum quantity
    if (maximumQty.lt(order.quantity)) {
      this.errors.enqueue({
        ...httpErrors.ORDER_MAXIMUM_QUANTITY_VALIDATION_FAIL,
        userId: account.userId.toString(),
      });
      return null;
    }

    // validate precision
    if (this.validatePrecision(order.quantity, instrument.maxFiguresForSize)) {
      this.errors.enqueue({
        ...httpErrors.ORDER_QUANTITY_PRECISION_VALIDATION_FAIL,
        userId: account.userId.toString(),
      });
      return null;
    }
    if (order.price) {
      if (this.validatePrecision(order.price, instrument.maxFiguresForPrice)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_PRICE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }
    }
    await this.validateMinMaxPrice(createOrder, account.userId);

    // TPSL
    let checkPrice;
    if (order.type === OrderType.MARKET && !order.tpSLType) {
      const tickers = await this.cacheService.get<Ticker[]>(TICKERS_KEY);
      const ticker = tickers.find((ticker) => ticker.symbol === order.symbol);
      checkPrice = ticker?.lastPrice ?? null;
    } else if (
      order.type === OrderType.MARKET &&
      order.tpSLType === TpSlType.STOP_MARKET
    ) {
      checkPrice = order.tpSLPrice;
    }
    if (order.takeProfit || order.takeProfitTrigger) {
      if (order.takeProfit && order.takeProfitTrigger) {
        if (
          order.side == OrderSide.BUY &&
          Number(order.takeProfit) <= Number(checkPrice)
        ) {
          this.errors.enqueue({
            ...httpErrors.TAKE_PROFIT_TRIGGER_OR_PRICE_NOT_VALID,
            userId: account.userId.toString(),
          });
          return null;
        }
        if (
          order.side == OrderSide.SELL &&
          Number(order.takeProfit) >= Number(checkPrice)
        ) {
          this.errors.enqueue({
            ...httpErrors.TAKE_PROFIT_TRIGGER_OR_PRICE_NOT_VALID,
            userId: account.userId.toString(),
          });
          return null;
        }
      } else {
        this.errors.enqueue({
          ...httpErrors.TAKE_PROFIT_TRIGGER_OR_PRICE_NOT_VALID,
          userId: account.userId.toString(),
        });
        return null;
      }
      if (!order.takeProfitCondition) {
        this.errors.enqueue({
          ...httpErrors.TAKE_PROFIT_CONDITION_UNDEFINED,
          userId: account.userId.toString(),
        });
        return null;
      }
    }
    if (order.stopLoss || order.stopLossTrigger) {
      if (order.stopLoss && order.stopLossTrigger) {
        if (
          order.side == OrderSide.BUY &&
          Number(order.stopLoss) >= Number(checkPrice)
        ) {
          this.errors.enqueue({
            ...httpErrors.STOP_LOSS_TRIGGER_OR_PRICE_NOT_VALID,
            userId: account.userId.toString(),
          });
          return null;
        }
        if (
          order.side == OrderSide.SELL &&
          Number(order.stopLoss) <= Number(checkPrice)
        ) {
          this.errors.enqueue({
            ...httpErrors.STOP_LOSS_TRIGGER_OR_PRICE_NOT_VALID,
            userId: account.userId.toString(),
          });
          return null;
        }
      } else {
        this.errors.enqueue({
          ...httpErrors.STOP_LOSS_TRIGGER_OR_PRICE_NOT_VALID,
          userId: account.userId.toString(),
        });
        return null;
      }
      if (!order.stopLossCondition) {
        this.errors.enqueue({
          ...httpErrors.STOP_LOSS_CONDITION_UNDEFINED,
          userId: account.userId.toString(),
        });
        return null;
      }
    }
    // TRAILING_STOP
    if (order.tpSLType == TpSlType.TRAILING_STOP) {
      order.type = OrderType.MARKET;
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      delete order.takeProfit;
      delete order.takeProfitTrigger;
      delete order.stopLoss;
      delete order.stopLossTrigger;
      if (!order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (
        !order.activationPrice ||
        new BigNumber(order.activationPrice).lte(0)
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_ACTIVATION_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.callbackRate) {
        this.errors.enqueue({
          ...httpErrors.CALLBACK_RATE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }
      if (
        this.validatePrecision(
          order.activationPrice,
          instrument.maxFiguresForPrice
        )
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRAIL_VALUE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (order.type !== OrderType.MARKET) {
        this.errors.enqueue({
          ...httpErrors.TRAILING_STOP_ORDER_TYPE_NOT_VALID,
          userId: account.userId.toString(),
        });
        return null;
      }

      return order;
    }

    // POST_ONLY
    if (order.type === OrderType.LIMIT && order.isPostOnly) {
      order.timeInForce = OrderTimeInForce.GTC;
      delete order.isHidden;
      if ((order.stopLoss || order.takeProfit) && !order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }
      return order;
    }

    // STOP_LIMIT
    if (
      order.type == OrderType.LIMIT &&
      order.tpSLType == TpSlType.STOP_LIMIT
    ) {
      if (!order.price) {
        this.errors.enqueue({
          ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.tpSLPrice || new BigNumber(order.tpSLPrice).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.stopCondition) {
        this.errors.enqueue({
          ...httpErrors.NOT_HAVE_STOP_CONDITION,
          userId: account.userId.toString(),
        });
        return null;
      }
      if (
        this.validatePrecision(order.tpSLPrice, instrument.maxFiguresForPrice)
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      delete order.activationPrice;
      return order;
    }

    // STOP_MARKET
    if (
      order.type == OrderType.MARKET &&
      order.tpSLType == TpSlType.STOP_MARKET
    ) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      if (!order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.tpSLPrice || new BigNumber(order.tpSLPrice).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (
        this.validatePrecision(order.tpSLPrice, instrument.maxFiguresForPrice)
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.stopCondition) {
        this.errors.enqueue({
          ...httpErrors.NOT_HAVE_STOP_CONDITION,
          userId: account.userId.toString(),
        });
        return null;
      }
      delete order.activationPrice;
      return order;
    }

    // TAKE_PROFIT_LIMIT
    if (
      order.type == OrderType.LIMIT &&
      order.takeProfit &&
      new BigNumber(order.takeProfit).gt(0)
    ) {
      if (!order.price || new BigNumber(order.price).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.takeProfit || new BigNumber(order.takeProfit).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (
        this.validatePrecision(order.takeProfit, instrument.maxFiguresForPrice)
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      delete order.activationPrice;
      return order;
    }
    //STOP_LOSS_LIMIT
    if (
      order.type == OrderType.LIMIT &&
      order.stopLoss &&
      new BigNumber(order.stopLoss).gt(0)
    ) {
      if (!order.price || new BigNumber(order.price).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.stopLoss || new BigNumber(order.stopLoss).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (
        this.validatePrecision(order.stopLoss, instrument.maxFiguresForPrice)
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      delete order.activationPrice;
      return order;
    }
    // TAKE_PROFIT_MARKET
    if (
      order.type == OrderType.MARKET &&
      order.takeProfit &&
      new BigNumber(order.takeProfit).gt(0)
    ) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      if (!order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.takeProfit || new BigNumber(order.takeProfit).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (
        this.validatePrecision(order.takeProfit, instrument.maxFiguresForPrice)
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      delete order.activationPrice;
      return order;
    }

    // STOP_LOSS_MARKET
    if (
      order.type == OrderType.MARKET &&
      order.stopLoss &&
      new BigNumber(order.stopLoss).gt(0)
    ) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      if (!order.trigger) {
        this.errors.enqueue({
          ...httpErrors.ORDER_TRIGGER_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (!order.stopLoss || new BigNumber(order.stopLoss).eq(0)) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      if (
        this.validatePrecision(order.stopLoss, instrument.maxFiguresForPrice)
      ) {
        this.errors.enqueue({
          ...httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      delete order.activationPrice;
      return order;
    }
    // LIMIT
    if (order.type == OrderType.LIMIT) {
      if (!order.price) {
        this.errors.enqueue({
          ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
          userId: account.userId.toString(),
        });
        return null;
      }

      delete order.trigger;
      delete order.activationPrice;
      return order;
    }

    // MARKET
    if (order.type == OrderType.MARKET) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      delete order.trigger;
      delete order.activationPrice;
      return order;
    }

    this.logger.debug("ORDER_UNKNOWN_VALIDATION_FAIL");
    this.logger.debug(createOrder);
    this.logger.debug(order);
    this.errors.enqueue({
      ...httpErrors.ORDER_UNKNOWN_VALIDATION_FAIL,
      userId: account.userId.toString(),
    });
    return null;
  }

  private validatePrecision(
    value: string | BigNumber,
    precision: string | BigNumber
  ): boolean {
    const numberOfDecimalFigures = value.toString().split(".")[1];
    if (!numberOfDecimalFigures) {
      return false;
    }
    return numberOfDecimalFigures.length > +precision.toString();
    // return new BigNumber(value).dividedToIntegerBy(precision).multipliedBy(precision).lt(new BigNumber(value));
  }

  private async validateMinMaxPrice(
    createOrderDto: CreateOrderDto,
    userId: number
  ) {
    const order = { ...createOrderDto };
    const [tradingRules, instrument, markPrice] = await Promise.all([
      this.tradingRulesService.getTradingRuleByInstrumentId(
        order.symbol
      ) as any,
      this.instrumentService.getCachedInstrument(order.symbol),
      this.redisClient
        .getInstance()
        .get(`${ORACLE_PRICE_PREFIX}${order.symbol}`),
    ]);
    let price: BigNumber;
    let minPrice: BigNumber;
    let maxPrice: BigNumber;
    switch (order.side) {
      case OrderSide.SELL: {
        // validate minPrice
        maxPrice = new BigNumber(instrument?.maxPrice);
        minPrice = new BigNumber(tradingRules?.minPrice);
        if (
          (order.type == OrderType.LIMIT && !order.tpSLType) ||
          order.isPostOnly == true
        ) {
          price = new BigNumber(markPrice).times(
            new BigNumber(1).minus(
              new BigNumber(tradingRules?.floorRatio).dividedBy(100)
            )
          );
          minPrice = BigNumber.maximum(
            new BigNumber(tradingRules?.minPrice),
            price
          );
        }

        if (order.tpSLType == TpSlType.STOP_LIMIT) {
          price = new BigNumber(order.tpSLPrice).times(
            new BigNumber(1).minus(
              new BigNumber(tradingRules?.floorRatio).dividedBy(100)
            )
          );
          minPrice = BigNumber.maximum(
            new BigNumber(tradingRules?.minPrice),
            price
          );
        }
        if (new BigNumber(order.price).isLessThan(minPrice)) {
          console.log("minPrice", minPrice);
          console.log("order.price", order.price);
          this.errors.enqueue({
            ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
            userId: userId.toString(),
          });
          return null;
        }
        // validate max Price:
        if (new BigNumber(order.price).isGreaterThan(instrument.maxPrice)) {
          this.errors.enqueue({
            ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
            userId: userId.toString(),
          });
          return null;
        }
        break;
      }
      //limitOrderPrice = cap ratio
      case OrderSide.BUY: {
        maxPrice = new BigNumber(instrument?.maxPrice);
        minPrice = new BigNumber(tradingRules?.minPrice);
        if (
          (order.type == OrderType.LIMIT && !order.tpSLType) ||
          order.isPostOnly == true
        ) {
          price = new BigNumber(markPrice).times(
            new BigNumber(1).plus(
              new BigNumber(tradingRules?.limitOrderPrice).dividedBy(100)
            )
          );
          maxPrice = BigNumber.minimum(
            (new BigNumber(instrument?.maxPrice), price)
          );
        }
        if (order.tpSLType == TpSlType.STOP_LIMIT) {
          price = new BigNumber(order.tpSLPrice).times(
            new BigNumber(1).plus(
              new BigNumber(tradingRules?.limitOrderPrice).dividedBy(100)
            )
          );
          maxPrice = BigNumber.minimum(
            (new BigNumber(instrument?.maxPrice), price)
          );
        }
        if (new BigNumber(order.price).isLessThan(minPrice)) {
          this.errors.enqueue({
            ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
            userId: userId.toString(),
          });
          return null;
        }
        if (new BigNumber(order.price).isGreaterThan(maxPrice)) {
          this.errors.enqueue({
            ...httpErrors.ORDER_PRICE_VALIDATION_FAIL,
            userId: userId.toString(),
          });
          return null;
        }
        break;
      }
      default:
        break;
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
    this.logger.log(
      `this.isIntervalHandlerRunningSet.size = ${this.isIntervalHandlerRunningSet.size}`
    );
    this.logger.log(
      `this.saveOrderCommands.length = ${this.saveOrderCommands.size()}`
    );
    if (
      this.isIntervalHandlerRunningSet.size === 0 &&
      this.saveOrderCommands.isEmpty()
    ) {
      this.logger.log(`Exit consumer!`);
      process.exit(0);
    }
  }

  private checkHaveStopCommand(
    command: SaveOrderFromClientCommandV2,
    stopCommandCode: string
  ) {
    if (!this.firstTimeConsumeMessage)
      this.firstTimeConsumeMessage = Date.now();
    if (
      command.tmpOrderId == stopCommandCode &&
      Date.now() - this.firstTimeConsumeMessage > 10000 // at least 10s from firstTimeConsumeMessage
    ) {
      this.shouldStopConsumer = true;
      this.logger.log(`shouldStopConsumer = true`);
      this.BATCH_SIZE = 5000;
    }
  }
}

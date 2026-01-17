import { Injectable, Logger } from "@nestjs/common";
import { SaveOrderFromClientV2UseCase } from "./save-order-from-client-v2.usecase";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { SaveOrderFromClientCommandV2 } from "../kafka-command/save-order-from-client.command";
import { CreateOrderDto } from "../dto/create-order.dto";
import {
  ContractType,
  MarginMode,
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
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { plainToClass } from "class-transformer";
import { OrderEntity } from "src/models/entities/order.entity";
import { Orderbook } from "src/modules/orderbook/orderbook.const";
import { OrderbookService } from "src/modules/orderbook/orderbook.service";
import BigNumber from "bignumber.js";
import { USDT } from "src/modules/balance/balance.const";
import { BOT_STOP_CREATE_ORDER } from "../order.const";

@Injectable()
export class SaveUserMarketOrderUseCase extends SaveOrderFromClientV2UseCase {
  private readonly saveUserMarketOrderLogger = new Logger(
    SaveUserMarketOrderUseCase.name
  );

  public async execute(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.save_order_from_client_v2_for_user_market,
      KafkaGroups.save_order_from_client_v2_for_user_market,
      async (command: SaveOrderFromClientCommandV2) => {
        await this.processUserMarketOrderCommand(command);
      }
    );

    return new Promise(() => {});
  }

  private async processUserMarketOrderCommand(
    command: SaveOrderFromClientCommandV2
  ) {
    let unsavedOrder = null;

    const { createOrderDto, userId, tmpOrderId } = command;
    if (createOrderDto == null || userId == null || tmpOrderId == null) return;
    if (await this.botInMemoryService.checkIsBotUserId(userId)) return;

    // this.logger.log(`tmpOrderId = ${tmpOrderId}`);
    const account = await this.getAccountByUserIdAndAsset(
      userId,
      createOrderDto.asset
    );
    const instrument = await this.instrumentService.getCachedInstrument(
      createOrderDto.symbol
    );
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

    // Stop bot to creating new order
    const botUserId = this.botInMemoryService.getBotUserIdFromSymbol(
      validatedCreateOrderDto.symbol
    );
    await this.redisClient
      .getInstance()
      .set(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`, "true");

    // Create bot orders and push to kafka
    let botOrders = await this.checkAndCreateBotOrdersForBinancePrice(
      validatedCreateOrderDto,
      botUserId,
      userId
    );

    if (botOrders && botOrders.length > 0) {
      const savedBotOrders = await Promise.all(
        botOrders.map((o) => this.orderRepoMaster.save(o))
      );
      for (const savedBotOrder of savedBotOrders) {
        await this.orderRouter.routeCommand(savedBotOrder.symbol, {
          code: CommandCode.PLACE_ORDER,
          data: plainToClass(OrderEntity, savedBotOrder),
        });
      }
    }

    // Handle for user order
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

    // Handle for stop loss order
    let stopLossOrder = null;
    if (body.stopLoss) {
      stopLossOrder = await this.orderRepoMaster.save({
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
      });
      await this.orderRouter.routeCommand(validatedCreateOrderDto.symbol, {
        code: CommandCode.PLACE_ORDER,
        data: plainToClass(OrderEntity, stopLossOrder),
      });
    }

    // Handle for take profit order
    let takeProfitOrder = null;
    if (body.takeProfit) {
      takeProfitOrder = await this.orderRepoMaster.save({
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
      });
      await this.orderRouter.routeCommand(validatedCreateOrderDto.symbol, {
        code: CommandCode.PLACE_ORDER,
        data: plainToClass(OrderEntity, takeProfitOrder),
      });
    }

    const newTmpOrder = {
      ...unsavedOrder,
      stopLossOrderId: stopLossOrder?.id ?? null,
      takeProfitOrderId: takeProfitOrder?.id ?? null,
    };
    const savedOrder = await this.orderRepoMaster.save(newTmpOrder);
    await this.orderRouter.routeCommand(savedOrder.symbol, {
      code: CommandCode.PLACE_ORDER,
      data: plainToClass(OrderEntity, savedOrder),
    });

    // Allow bot to create order
    await this.redisClient
      .getInstance()
      .del(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`);
  }

  public async checkAndCreateBotOrdersForBinancePrice(
    validatedCreateOrderDto: CreateOrderDto,
    botUserId: number,
    userId: number
  ): Promise<any[]> {
    // Get binance last price
    const binancePrice = await this.getBinanceLastPrice(
      validatedCreateOrderDto.symbol, 
      userId
    );
    if (!binancePrice || binancePrice.isEqualTo(0)) return;

    // Get current orderbook
    let orderbook: Orderbook = null;
    const orderbookStr = await this.redisClient
      .getInstance()
      .get(OrderbookService.getOrderbookKey(validatedCreateOrderDto.symbol));
    if (typeof orderbookStr === "string") {
      orderbook = JSON.parse(orderbookStr) as Orderbook;
    } else {
      orderbook = orderbookStr as Orderbook;
    }

    // Get market price that user market order will match with
    const { highestBid, lowestAsk } = this.getGoodPriceFromOrderbook(orderbook);
    // if (highestBid.isEqualTo(0) || lowestAsk.isEqualTo(0)) return;
    const marketPrice =
      validatedCreateOrderDto.side === OrderSide.BUY ? lowestAsk : highestBid;

    const botOrders = [];

    let sumQuantity = new BigNumber(0);
    if (binancePrice.isLessThan(marketPrice) && orderbook.bids && orderbook.bids.length > 0) {
      for (const bid of orderbook.bids) {
        const bidPrice = new BigNumber(bid[0]);
        if (bidPrice.isLessThan(binancePrice)) break;

        const bidQuantity = new BigNumber(bid[1]);
        sumQuantity = sumQuantity.plus(bidQuantity);
      }

      // const botMarketOrder = await this.createBotMarketOrder({
      //   userId: botUserId,
      //   symbol: validatedCreateOrderDto.symbol,
      //   quantity: sumQuantity.plus(sumQuantity.multipliedBy(0.1)),
      //   asset: validatedCreateOrderDto.asset,
      //   side: OrderSide.SELL,
      // });
      // botOrders.push(botMarketOrder);
    }

    if (binancePrice.isGreaterThan(marketPrice) && orderbook.asks && orderbook.asks.length > 0) {
      for (const ask of orderbook.asks) {
        const askPrice = new BigNumber(ask[0]);
        if (askPrice.isGreaterThan(binancePrice)) break;

        const askQuantity = new BigNumber(ask[1]);
        sumQuantity = sumQuantity.plus(askQuantity);
      }

      // const botMarketOrder = await this.createBotMarketOrder({
      //   userId: botUserId,
      //   symbol: validatedCreateOrderDto.symbol,
      //   quantity: sumQuantity.plus(sumQuantity.multipliedBy(0.1)),
      //   asset: validatedCreateOrderDto.asset,
      //   side: OrderSide.BUY,
      // });
      // botOrders.push(botMarketOrder);
    }

    const userOrderQuantity = new BigNumber(validatedCreateOrderDto.quantity);
    const botLimitOrder = await this.createBotLimitOrder({
      userId: botUserId,
      symbol: validatedCreateOrderDto.symbol,
      price: binancePrice,
      quantity: sumQuantity.plus(userOrderQuantity).plus(userOrderQuantity.multipliedBy(0.1)),
      asset: validatedCreateOrderDto.asset,
      side:
        validatedCreateOrderDto.side === OrderSide.BUY
          ? OrderSide.SELL
          : OrderSide.BUY,
    });
    botOrders.push(botLimitOrder);

    console.log(
      `Hit binancePrice=${
        botOrders[botOrders.length - 1].price
      } instead of marketPrice=${marketPrice} for userId=${userId}`
    );
    return botOrders;
  }

  private async getBinanceLastPrice(symbol: string, userId: number): Promise<BigNumber> {
    let lastPrice = null;

    if (Number(userId) === 805) {
      const fakeLastPriceRedis = await this.redisClient
        .getInstance()
        .get(`fake_last_price:${symbol}:${userId}`);
      const fakeLastPrice = new BigNumber(fakeLastPriceRedis ?? 0);
      console.log(`fakeLastPriceRedis`, fakeLastPriceRedis);
      console.log(`fakeLastPrice`, fakeLastPrice);
      if (fakeLastPrice.isGreaterThan(0)) {
        return fakeLastPrice;
      }
    }

    try {
      const res = await fetch(
        `https://fapi.binance.com/fapi/v1/trades?symbol=${symbol.toUpperCase()}&limit=1`
      );
      if (res.ok) {
        const trades = await res.json();
        if (Array.isArray(trades) && trades.length > 0 && trades[0].price) {
          lastPrice = new BigNumber(trades[0].price);
        }
      } else {
        this.saveUserMarketOrderLogger.warn(
          `Failed to fetch Binance price for symbol ${symbol}: ${res.status}`
        );
      }
    } catch (err) {
      this.saveUserMarketOrderLogger.error(
        `Error fetching Binance price for symbol ${symbol}:`,
        err
      );
    }

    return lastPrice;
    // return new BigNumber("114271.60000000");
  }

  private getGoodPriceFromOrderbook(
    orderbook: Orderbook
  ): { highestBid: BigNumber; lowestAsk: BigNumber } {
    let highestBid = new BigNumber(0);
    if (orderbook.bids && orderbook.bids.length > 0) {
      highestBid = new BigNumber(orderbook.bids[0][0]);
      // highestBid = orderbook.bids.reduce((max, bid) => {
      //   const price = new BigNumber(bid[0]);
      //   return price.isGreaterThan(max) ? price : max;
      // }, new BigNumber(0));
    }

    let lowestAsk = new BigNumber(0);
    if (orderbook.asks && orderbook.asks.length > 0) {
      lowestAsk = new BigNumber(orderbook.asks[0][0]);
      // lowestAsk = orderbook.asks.reduce((min, ask) => {
      //   const price = new BigNumber(ask[0]);
      //   return min.isZero() || price.isLessThan(min) ? price : min;
      // }, new BigNumber(0));
    }

    return { highestBid, lowestAsk };
  }

  private async createBotMarketOrder(data: {
    userId: number;
    symbol: string;
    quantity: BigNumber;
    asset: string;
    side: OrderSide;
  }) {
    const order = {
      userId: data.userId,
      accountId: await this.botInMemoryService.getBotAccountIdByUserIdAndAsset(
        data.userId,
        USDT
      ),
      side: data.side,
      quantity: data.quantity.toFixed(),
      type: OrderType.MARKET,
      symbol: data.symbol,
      timeInForce: OrderTimeInForce.IOC,
      status: OrderStatus.PENDING,
      asset: data.asset,
      marginMode: MarginMode.ISOLATE,
      leverage: "20",
      remaining: data.quantity.toFixed(),
      isClosePositionOrder: false,
      isReduceOnly: false,
      contractType: ContractType.USD_M,
      userEmail: await this.botInMemoryService.getBotEmailByUserId(data.userId),
      originalCost: "0",
      originalOrderMargin: "0",
      cost: "0",
      orderMargin: "0",
    };
    return order;
    // return await this.orderRepoMaster.save(order);
  }

  private async createBotLimitOrder(data: {
    userId: number;
    symbol: string;
    price: BigNumber;
    quantity: BigNumber;
    asset: string;
    side: OrderSide;
  }) {
    const order = {
      side: data.side,
      contractType: ContractType.USD_M,
      symbol: data.symbol,
      type: OrderType.LIMIT,
      quantity: data.quantity.toFixed(),
      price: data.price.toFixed(),
      timeInForce: OrderTimeInForce.GTC,
      asset: USDT,
      isPostOnly: false,
      originalCost: "0",
      remaining: data.quantity.toFixed(),
      status: OrderStatus.PENDING,
      accountId: await this.botInMemoryService.getBotAccountIdByUserIdAndAsset(
        data.userId,
        USDT
      ),
      leverage: "20",
      marginMode: MarginMode.ISOLATE,
      orderValue: "0",
      userId: data.userId,
      isTpSlTriggered: false,
      userEmail: await this.botInMemoryService.getBotEmailByUserId(data.userId),
      originalOrderMargin: "0",
      cost: "0",
      orderMargin: "0",
    };
    return order;
    // return await this.orderRepoMaster.save(order);
  }
}

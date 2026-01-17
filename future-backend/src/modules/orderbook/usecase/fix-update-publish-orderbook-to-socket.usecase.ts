import { Inject, Injectable, CACHE_MANAGER } from "@nestjs/common";
import { Cache } from "cache-manager";
import { Logger } from "ethers/lib/utils";
import {
  Orderbook,
  ORDERBOOK_TTL,
  OrderbookMEBinance,
} from "../orderbook.const";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { InstrumentEntity } from "src/models/entities/instrument.entity";
import { OrderbookService } from "../orderbook.service";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { BOT_STOP_CREATE_ORDER } from "src/modules/order/order.const";
import { InjectRepository } from "@nestjs/typeorm";
import { OrderRepository } from "src/models/repositories/order.repository";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { plainToClass } from "class-transformer";
import { OrderEntity } from "src/models/entities/order.entity";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import BigNumber from "bignumber.js";
import { USDT } from "src/modules/balance/balance.const";
import {
  ContractType,
  MarginMode,
  OrderSide,
  OrderStatus,
  OrderTimeInForce,
  OrderType,
} from "src/shares/enums/order.enum";
import { SocketEmitter } from "src/shares/helpers/socket-emitter";
import { BotInMemoryService } from "src/modules/bot/bot.in-memory.service";

@Injectable()
export class FixUpdatePublishOrderbookToSocketUsecase {
  constructor(
    private readonly instrumentService: InstrumentService,
    private readonly orderbookService: OrderbookService,
    @Inject(CACHE_MANAGER)
    public cacheManager: Cache,
    private readonly redisClient: RedisClient,
    @InjectRepository(OrderRepository, "master")
    public readonly orderRepoMaster: OrderRepository,
    public readonly kafkaClient: KafkaClient,
    protected readonly botInMemoryService: BotInMemoryService
  ) {}
  private readonly logger = new Logger(
    FixUpdatePublishOrderbookToSocketUsecase.name
  );
  private lastCachedOrderbookBySymbol: {
    [symbol: string]: Orderbook;
  } = {};
  private isOrderbookInvalidBySymbol: {
    [symbol: string]: boolean;
  } = {};

  public async execute(): Promise<void> {
    const instruments = await this.instrumentService.getAllInstruments();
    for (const instrument of instruments) {
      this.lastCachedOrderbookBySymbol[instrument.symbol] = {
        bids: [],
        asks: [],
      };
      this.isOrderbookInvalidBySymbol[instrument.symbol] = false;
    }

    while (true) {
      for (const instrument of instruments) {
        try {
          await this.handleInstrumentWhenPublishOrderbookToSocket(instrument);
        } catch (e) {
          console.log(`Error handleInstrumentWhenPublishOrderbookToSocket: `);
          console.log(e);
        }
      }
    }
  }

  private async handleInstrumentWhenPublishOrderbookToSocket(
    instrument: InstrumentEntity
  ) {
    const symbol = instrument.symbol;
    let orderbookME: Orderbook = (await this.getCachedOrderbookME({
      symbol,
    })) ?? {
      bids: [],
      asks: [],
    };
    const lastCachedOrderbook = this.lastCachedOrderbookBySymbol[symbol];
    if (
      JSON.stringify(orderbookME) === JSON.stringify(lastCachedOrderbook) &&
      this.isOrderbookInvalidBySymbol[instrument.symbol] === false
    ) {
      await this.cacheAndPublishBinanceOrderbookToSocket({ symbol });
      return;
    }

    // Get orderbook of binance
    let orderbookBinance: Orderbook = await this.orderbookService.getOrderbookFromBinance(
      symbol
    );

    // Check if the orderbook is invalid
    const {
      ordersNeedCreate,
    } = await this.checkToCreateBotOrderToFixInvalidDataOfOrderbookME({
      orderbookME,
      orderbookBinance,
      symbol,
    });

    // ordersNeedCreate > 0 => orderbookME is invalid and need to fix
    if (ordersNeedCreate && ordersNeedCreate.length > 0) {
      const botUserId = this.botInMemoryService.getBotUserIdFromSymbol(symbol);
      // Stop bot to creating new order
      await this.redisClient
        .getInstance()
        .set(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`, "true");

      // Save orders to db
      const savedBotOrders = await Promise.all(
        ordersNeedCreate.map((o) => this.orderRepoMaster.save(o))
      );
      // console.log(`savedBotOrders`, savedBotOrders);

      for (const savedBotOrder of savedBotOrders) {
        await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
          code: CommandCode.PLACE_ORDER,
          data: plainToClass(OrderEntity, savedBotOrder),
        });
      }

      // Allow bot to create order
      await this.redisClient
        .getInstance()
        .del(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`);

      await this.cacheAndPublishBinanceOrderbookToSocket({ symbol });
      this.lastCachedOrderbookBySymbol[symbol] = orderbookME;
      this.isOrderbookInvalidBySymbol[instrument.symbol] = false;
      return;
    }

    // Combine them
    const orderbookMEBinance = await this.orderbookService.combineOrderbookMEBinance(
      {
        orderbookME,
        orderbookBinance,
      }
    );

    // Cache orderbook ME Binance
    if (orderbookMEBinance) {
      await this.cacheManager.set(
        OrderbookService.getOrderbookMEBinanceKey(symbol),
        orderbookMEBinance,
        { ttl: ORDERBOOK_TTL }
      );
    }

    // Compute orderbook
    const orderbook: Orderbook = await this.computeOrderbookForPublishOrderbookToSocket(
      { orderbookMEBinance }
    );

    // Cache orderbook
    if (orderbook) {
      await this.cacheManager.set(
        OrderbookService.getOrderbookKey(symbol),
        orderbook,
        { ttl: ORDERBOOK_TTL }
      );
    }
    this.lastCachedOrderbookBySymbol[symbol] = orderbookME;

    // calculate bidPercent and askPercent
    await this.publishOrderbookWithTicksize(orderbook, symbol);
    this.isOrderbookInvalidBySymbol[instrument.symbol] = true;
  }

  private async checkToCreateBotOrderToFixInvalidDataOfOrderbookME(data: {
    orderbookME: Orderbook;
    orderbookBinance: Orderbook;
    symbol: string;
  }): Promise<{
    ordersNeedCreate: any[];
  }> {
    const lowestMEAskPrice = new BigNumber(
      data?.orderbookME?.asks?.[0]?.[0] ?? 0
    );
    const highestMEBidPrice = new BigNumber(
      data?.orderbookME?.bids?.[0]?.[0] ?? 0
    );

    const lowestBAskPrice = data?.orderbookBinance?.asks
      ? new BigNumber(data?.orderbookBinance?.asks?.[0]?.[0] ?? 0)
      : new BigNumber(0);
    const highestBBidPrice = data?.orderbookBinance?.bids
      ? new BigNumber(
          data?.orderbookBinance?.bids?.[
            data?.orderbookBinance?.bids?.length - 1
          ]?.[0] ?? 0
        )
      : new BigNumber(0);

    const botUserId = this.botInMemoryService.getBotUserIdFromSymbol(
      data.symbol
    );
    const ordersNeedCreate = [];
    if (
      !lowestMEAskPrice.isEqualTo(0) &&
      !lowestBAskPrice.isEqualTo(0) &&
      lowestMEAskPrice.isLessThan(lowestBAskPrice)
    ) {
      let sumQuantity = new BigNumber(0);
      for (const ask of data.orderbookME.asks) {
        const askPrice = new BigNumber(ask[0]);
        if (askPrice.isGreaterThan(lowestBAskPrice)) break;

        const askQuantity = new BigNumber(ask[1]);
        sumQuantity = sumQuantity.plus(askQuantity);
      }

      if (sumQuantity.isGreaterThan(0)) {
        this.logger.info(
          `lowestMEAskPrice=${lowestMEAskPrice} - lowestBAskPrice=${lowestBAskPrice} - sumQuantity=${sumQuantity}`
        );
        const botLimitOrder = await this.createBotLimitOrder({
          userId: botUserId,
          symbol: data.symbol,
          price: lowestBAskPrice,
          quantity: sumQuantity.plus(sumQuantity.multipliedBy(0.1)),
          asset: USDT,
          side: OrderSide.BUY,
        });
        ordersNeedCreate.push(botLimitOrder);
        this.logger.info(
          `Create [side=BUY - ${data.symbol} - price=${botLimitOrder.price} - qty=${botLimitOrder.quantity}] bot order to fix orderbook data`
        );
      }
    }

    if (
      !highestMEBidPrice.isEqualTo(0) &&
      !highestBBidPrice.isEqualTo(0) &&
      highestMEBidPrice.isGreaterThan(highestBBidPrice)
    ) {
      let sumQuantity = new BigNumber(0);
      for (const bid of data.orderbookME.bids) {
        const bidPrice = new BigNumber(bid[0]);
        if (bidPrice.isLessThan(highestBBidPrice)) break;

        const bidQuantity = new BigNumber(bid[1]);
        sumQuantity = sumQuantity.plus(bidQuantity);
      }

      if (sumQuantity.isGreaterThan(0)) {
        this.logger.info(
          `highestMEBidPrice=${highestMEBidPrice} - highestBBidPrice=${highestBBidPrice} - sumQuantity=${sumQuantity}`
        );
        const botLimitOrder = await this.createBotLimitOrder({
          userId: botUserId,
          symbol: data.symbol,
          price: highestBBidPrice,
          quantity: sumQuantity.plus(sumQuantity.multipliedBy(0.1)),
          asset: USDT,
          side: OrderSide.SELL,
        });
        ordersNeedCreate.push(botLimitOrder);
        this.logger.info(
          `Create [side=SELL - ${data.symbol} - price=${botLimitOrder.price} - qty=${botLimitOrder.quantity}] bot order to fix orderbook data`
        );
      }
    }

    return {
      ordersNeedCreate,
    };
  }

  private async getCachedOrderbookME(data: {
    symbol: string;
  }): Promise<Orderbook> {
    return await this.cacheManager.get<Orderbook>(
      OrderbookService.getOrderbookMEKey(data.symbol)
    );
  }

  private async cacheAndPublishBinanceOrderbookToSocket(data: {
    symbol: string;
  }) {
    const orderbookBinance = (await this.orderbookService.getOrderbookFromBinance(
      data.symbol
    )) ?? {
      bids: [],
      asks: [],
    };

    // Sort bids & asks
    orderbookBinance.bids?.sort((a, b) => {
      return Number(b[0]) - Number(a[0]);
    });
    orderbookBinance.asks?.sort((a, b) => {
      return Number(a[0]) - Number(b[0]);
    });

    await this.cacheManager.set(
      OrderbookService.getOrderbookKey(data.symbol),
      orderbookBinance,
      { ttl: ORDERBOOK_TTL }
    );
    await this.publishOrderbookWithTicksize(orderbookBinance, data.symbol);
  }

  private async computeOrderbookForPublishOrderbookToSocket(data: {
    orderbookMEBinance: OrderbookMEBinance;
  }): Promise<Orderbook> {
    const orderbook: Orderbook = {
      bids: [],
      asks: [],
    };
    data.orderbookMEBinance?.bids?.forEach((bid) => {
      orderbook.bids.push([bid[0], bid[1]]);
    });
    data.orderbookMEBinance?.asks?.forEach((ask) => {
      orderbook.asks.push([ask[0], ask[1]]);
    });
    return orderbook;
  }

  private async publishOrderbookWithTicksize(
    orderbook: Orderbook,
    symbol: string
  ) {
    // calculate bidPercent and askPercent
    const { bidPercent, askPercent } = this.orderbookService.calcBidAskPercent(
      orderbook
    );

    // tick size in instrument
    let tickSize = await this.orderbookService.getSymbolTickSize(symbol);
    let tickSizeBigNumber = new BigNumber(tickSize);

    for (let i = 0; i < 4; i++) {
      const groupOrderbook = this.orderbookService.groupBasedOnTicksize(
        orderbook,
        tickSizeBigNumber.toFixed()
      );
      const orderbookResponse = {
        orderbook: {
          ...groupOrderbook,
          lastUpdatedAt: new Date().getTime(),
        },
        bidPercent,
        askPercent,
      };
      // SocketEmitter.getInstance().emitOrderbook(orderbookResponse, symbol, tickSizeBigNumber.toFixed());
      SocketEmitter.getInstance().emitOrderbookNew(
        orderbookResponse,
        symbol,
        tickSizeBigNumber.toFixed()
      );
      tickSizeBigNumber = tickSizeBigNumber.multipliedBy(10);
    }
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
      timeInForce: OrderTimeInForce.IOC,
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
  }
}

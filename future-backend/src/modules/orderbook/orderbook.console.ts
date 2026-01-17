import { CACHE_MANAGER, Inject, Injectable, Logger } from "@nestjs/common";
import { Cache } from "cache-manager";
import { Command, Console } from "nestjs-console";
import { RedisService } from "nestjs-redis";
import {
  ORDERBOOK_PREVIOUS_TTL,
  ORDERBOOK_TTL,
  Orderbook,
  OrderbookEvent,
  OrderbookMEBinance,
  OrderbookResponse,
} from "src/modules/orderbook/orderbook.const";
import { OrderbookService } from "src/modules/orderbook/orderbook.service";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { SocketEmitter } from "src/shares/helpers/socket-emitter";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { InstrumentService } from "../instrument/instrument.service";
import { AccountEntity } from "src/models/entities/account.entity";
import { AccountService } from "../account/account.service";
import { OrderService } from "../order/order.service";
import BigNumber from "bignumber.js";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { INDEX_PRICE_PREFIX, LAST_PRICE_PREFIX, ORACLE_PRICE_PREFIX } from "../index/index.const";
import { FixUpdatePublishOrderbookToSocketUsecase } from "./usecase/fix-update-publish-orderbook-to-socket.usecase";

@Console()
@Injectable()
export class OrderbookConsole {
  constructor(
    @Inject(CACHE_MANAGER)
    public cacheManager: Cache,
    public readonly kafkaClient: KafkaClient,
    private readonly redisService: RedisService,
    private readonly instrumentService: InstrumentService,
    private readonly orderbookService: OrderbookService,
    private readonly orderService: OrderService,
    private readonly accountService: AccountService,
    private readonly logger: Logger,
    private readonly redisClient: RedisClient,
    private readonly fixUpdatePublishOrderbookToSocketUsecase: FixUpdatePublishOrderbookToSocketUsecase
  ) {}

  // @Command({
  //   command: "orderbook:publish",
  //   description: "Publish orderbook",
  // })
  // async publish(): Promise<void> {
  //   const topic = KafkaTopics.orderbook_output;
  //   await this.kafkaClient.consume<OrderbookEvent>(
  //     topic,
  //     KafkaGroups.orderbook,
  //     async (data) => {
  //       try {
  //         const { symbol, orderbook, changes } = data;
  //         await this.cacheManager.set(
  //           OrderbookService.getOrderbookKey(symbol),
  //           orderbook,
  //           { ttl: ORDERBOOK_TTL }
  //         );
  //         // Setting interval for Moving Average (30-minute Basis)
  //         const dt = Math.floor(new Date().getTime() / 1000);
  //         await this.cacheManager.set(
  //           `${OrderbookService.getOrderbookKey(symbol)}${String(
  //             dt - (dt % 60)
  //           )}`,
  //           orderbook,
  //           {
  //             ttl: ORDERBOOK_PREVIOUS_TTL,
  //           }
  //         );

  //         // calculate bidPercent and askPercent
  //         const {
  //           bidPercent,
  //           askPercent,
  //         } = this.orderbookService.calcBidAskPercent(orderbook);

  //         // tick size in instrument
  //         let tickSize = await this.orderbookService.getSymbolTickSize(symbol);
  //         let tickSizeBigNumber = new BigNumber(tickSize);

  //         for (let i = 0; i < 4; i++) {
  //           const groupOrderbook = this.orderbookService.groupBasedOnTicksize(
  //             orderbook,
  //             tickSizeBigNumber.toFixed()
  //           );
  //           const orderbookResponse = {
  //             orderbook: {
  //               ...groupOrderbook,
  //               lastUpdatedAt: changes?.lastUpdatedAt,
  //             },
  //             bidPercent,
  //             askPercent,
  //           };
  //           // SocketEmitter.getInstance().emitOrderbook(orderbookResponse, symbol, tickSizeBigNumber.toFixed());
  //           SocketEmitter.getInstance().emitOrderbookNew(
  //             orderbookResponse,
  //             symbol,
  //             tickSizeBigNumber.toFixed()
  //           );
  //           tickSizeBigNumber = tickSizeBigNumber.multipliedBy(10);
  //         }
  //       } catch (e) {
  //         this.logger.error(e);
  //         this.logger.error(`Message: ${JSON.stringify(data)}`);
  //       }
  //     }
  //   );
  //   return new Promise(() => {});
  // }

  @Command({
    command: "orderbook:publish",
    description: "Publish orderbook",
  })
  async publish(): Promise<void> {
    const topic = KafkaTopics.orderbook_output;
    await this.kafkaClient.consume<OrderbookEvent>(topic, KafkaGroups.orderbook, async (data) => {
      const { symbol, orderbook, changes } = data;
      // Cache orderbook from ME
      await this.cacheManager.set(OrderbookService.getOrderbookMEKey(symbol), orderbook, { ttl: ORDERBOOK_TTL });
    });
    return new Promise(() => {});
  }

  private async validateOrderbook(
    orderbook: Orderbook,
    symbol: string
  ): Promise<Orderbook> {
    let cachedLastPrice: BigNumber = new BigNumber(
      (await this.redisClient
        .getInstance()
        .get(`${ORACLE_PRICE_PREFIX}${symbol}`)) ?? "0"
    );
    if (!cachedLastPrice || cachedLastPrice.isEqualTo(0)) {
      cachedLastPrice = new BigNumber(
        (await this.redisClient
          .getInstance()
          .get(`${INDEX_PRICE_PREFIX}${symbol}`)) ?? "0"
      );
    }

    if (!cachedLastPrice || cachedLastPrice.isEqualTo(0)) return orderbook;
    let lastPrice: BigNumber = null;

    // Remove invalid items from asks (price should be a valid number and asks should be in ascending order)
    if (orderbook && Array.isArray(orderbook.asks)) {
      const validatedAsks: string[][] = [];
      for (let i = orderbook.asks.length - 1; i >= 0; i--) {
        const ask = orderbook.asks[i];
        // Check if ask is an array and has at least one element
        if (!Array.isArray(ask) || ask.length < 2) continue;
        const price = new BigNumber(ask[0]);
        // Check if price is a valid number and greater than or equal to zero
        if (price.isLessThanOrEqualTo(0)) continue;
        // Check ascending order
        if (lastPrice !== null && price.isGreaterThanOrEqualTo(lastPrice)) continue;
        if (price.isLessThan(cachedLastPrice)) continue;

        lastPrice = price;
        validatedAsks.unshift(ask);
      }
      orderbook.asks = validatedAsks;
    }

    // Remove invalid items from bids (price should be a valid number and bids should be in descending order)
    if (orderbook && Array.isArray(orderbook.bids)) {
      const validatedBids: string[][] = [];
      for (let i = 0; i < orderbook.bids.length; i++) {
        const bid = orderbook.bids[i];
        // Check if ask is an array and has at least one element
        if (!Array.isArray(bid) || bid.length < 2) continue;
        const price = new BigNumber(bid[0]);
        // Check if price is a valid number and greater than or equal to zero
        if (price.isLessThanOrEqualTo(0)) continue;
        // Check ascending order
        if (lastPrice !== null && price.isGreaterThanOrEqualTo(lastPrice)) continue;
        if (price.isGreaterThan(cachedLastPrice)) continue;

        lastPrice = price;
        validatedBids.push(bid);
      }
      orderbook.bids = validatedBids;
    }

    return orderbook;
  }

  @Command({
    command: "orderbook:publish-to-socket",
    description: "Publish orderbook to socket",
  })
  async publishToSocket(): Promise<void> {
    await this.fixUpdatePublishOrderbookToSocketUsecase.execute();
    // const instruments = await this.instrumentService.getAllInstruments();

    // const isPreviousPublishBySymbol: { [symbol: string]: boolean } = {};
    // for (const instrument of instruments) {
    //   isPreviousPublishBySymbol[instrument.symbol] = true;
    // }

    // while (true) {
    //   for (const instrument of instruments) {
    //     const symbol = instrument.symbol;
    //     // if (symbol !== "BTCUSDT") continue;

    //     let orderbookME: Orderbook = null;
    //     let orderbookBinance: Orderbook = null;
    //     let orderbookMEBinance: OrderbookMEBinance = null;

    //     // Get current orderbookME from cache and Get orderbookBinance from Binance API (then cache it)
    //     orderbookME = await this.getCachedOrderbookME({ symbol });
    //     if (isPreviousPublishBySymbol[symbol] === true) {
    //       orderbookBinance = await this.orderbookService.getOrderbookFromBinance(
    //         symbol
    //       );
    //       // if (symbol == "BNBUSDT") {
    //       //   console.log(`[DEBUG] isPreviousPublishBySymbol[symbol] = true, orderbookBinance: `);
    //       //   console.log(orderbookBinance);
    //       // }
    //       if (orderbookBinance) {
    //         // orderbookBinance null in case websocket is still not connected
    //         await this.cacheManager.set(
    //           OrderbookService.getOrderbookBinanceKey(symbol),
    //           orderbookBinance,
    //           { ttl: ORDERBOOK_TTL }
    //         );
    //       }
    //     } else {
    //       // Re-get orderbookBinance from cache
    //       orderbookBinance = await this.cacheManager.get<Orderbook>(
    //         OrderbookService.getOrderbookBinanceKey(symbol)
    //       );
    //       // if (symbol == "BNBUSDT") {
    //       //   console.log(`[DEBUG] isPreviousPublishBySymbol[symbol] = false, orderbookBinance: `);
    //       //   console.log(orderbookBinance);
    //       // }
    //     }

    //     // Combine them
    //     orderbookMEBinance = await this.orderbookService.combineOrderbookMEBinance(
    //       { orderbookME, orderbookBinance }
    //     );

    //     // Fix invalid data
    //     // Create order
    //     // Push order to kafka
    //     // => orderbookME on cache will be updated
    //     if (
    //       !this.orderbookService.checkValidDataOfOrderbookMEBinance({
    //         orderbookMEBinance,
    //         symbol,
    //       })
    //     ) {
    //       await this.orderbookService.fixInvalidDataOfOrderbookMEBinance({
    //         orderbookMEBinance,
    //         symbol: symbol,
    //       });

    //       await new Promise((resolve) => setTimeout(resolve, 10));
    //       isPreviousPublishBySymbol[symbol] = false;
    //       await this.publishOrderbookTmpToSocket({ symbol });
    //       // console.log(`Fix invalid data: orderbookMEBinance: `);
    //       // console.log(orderbookMEBinance);
    //       continue;
    //     }

    //     /// PUSH DATA TO SOCKET
    //     // Compute 'changes'
    //     // const changes: Orderbook = await this.computeChangesForPublishOrderbookToSocket({
    //     //   orderbookMEBinance,
    //     //   symbol: symbol,
    //     // });

    //     // Cache orderbook ME Binance
    //     if (orderbookMEBinance) {
    //       await this.cacheManager.set(
    //         OrderbookService.getOrderbookMEBinanceKey(symbol),
    //         orderbookMEBinance,
    //         { ttl: ORDERBOOK_TTL }
    //       );
    //     }

    //     // Compute orderbook
    //     const orderbook: Orderbook = await this.computeOrderbookForPublishOrderbookToSocket(
    //       { orderbookMEBinance }
    //     );

    //     // Cache orderbook
    //     if (orderbook) {
    //       await this.cacheManager.set(
    //         OrderbookService.getOrderbookKey(symbol),
    //         orderbook,
    //         { ttl: ORDERBOOK_TTL }
    //       );
    //     }

    //     // For debug
    //     // if (
    //     //   // orderbook &&
    //     //   // orderbook.asks &&
    //     //   // orderbook.asks.length !== 0 &&
    //     //   // orderbook.bids &&
    //     //   // orderbook.bids.length !== 0 &&
    //     //   symbol === "BTCUSDT"
    //     // ) {
    //     //   console.log(`BTCUSDT Orderbook: `);
    //     //   console.log(orderbook.asks);
    //     //   console.log(orderbook.bids);
    //     // }
    //     // console.log(changes);


    //     // calculate bidPercent and askPercent
    //     await this.publishOrderbookWithTicksize(orderbook, symbol);

    //     isPreviousPublishBySymbol[symbol] = true;
    //     // const orderbookResponse = this.handleOrderbookResponse(orderbook);
    //     // SocketEmitter.getInstance().emitOrderbook(orderbookResponse, symbol);
    //   }
    // }
  }

  private async publishOrderbookTmpToSocket(data: {
    symbol: string;
  }) {
    const orderbookBinance = await this.orderbookService.getOrderbookFromBinance(data.symbol);
    const orderbookMEBinance = await this.orderbookService.combineOrderbookMEBinance({ orderbookME: { bids: [], asks: [] }, orderbookBinance });

    // Compute 'changes'
    // const changes: Orderbook = await this.computeChangesForPublishOrderbookToSocket({
    //   orderbookMEBinance,
    //   symbol: data.symbol,
    // });

    // Cache orderbook ME Binance
    // if (orderbookMEBinance) {
    //   await this.cacheManager.set(OrderbookService.getOrderbookMEBinanceKey(data.symbol), orderbookMEBinance, { ttl: ORDERBOOK_TTL });
    // }

    // Compute and cache orderbook
    const orderbook: Orderbook = await this.computeOrderbookForPublishOrderbookToSocket({ orderbookMEBinance });
    if (orderbook) {
      await this.cacheManager.set(OrderbookService.getOrderbookKey(data.symbol), orderbook, { ttl: ORDERBOOK_TTL });
    }
    await this.publishOrderbookWithTicksize(orderbook, data.symbol);
    // const orderbookResponse = this.handleOrderbookResponse(orderbook) 
    // SocketEmitter.getInstance().emitOrderbook(orderbookResponse, data.symbol);
  }

  private async computeOrderbookForPublishOrderbookToSocket(data: { orderbookMEBinance: OrderbookMEBinance }): Promise<Orderbook> {
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

  private async getCachedOrderbookME(data: { symbol: string }): Promise<Orderbook> {
    return await this.cacheManager.get<Orderbook>(OrderbookService.getOrderbookMEKey(data.symbol));
  }

  private async publishOrderbookWithTicksize(orderbook: Orderbook, symbol: string) {
    // calculate bidPercent and askPercent
    const {
      bidPercent,
      askPercent,
    } = this.orderbookService.calcBidAskPercent(orderbook);

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

  @Command({
    command: "del:orderbook:all",
    description: "delete orderbook cache",
  })
  public async delOrderbookCache() {
    const keyOrderbookCache = await this.redisClient
      .getInstance()
      .keys("*orderbook_*");
    if (keyOrderbookCache?.length) {
      await Promise.all([
        keyOrderbookCache.map((item) =>
          this.redisClient.getInstance().del(item)
        ),
      ]);
    }
  }

  @Command({
    command: "del:orderbook <symbol>",
    description: "delete orderbook cache",
  })
  public async delOrderbookCacheBySymbol(symbol: string) {
    await this.redisClient.getInstance().del(`orderbook_${symbol}`);
  }
}

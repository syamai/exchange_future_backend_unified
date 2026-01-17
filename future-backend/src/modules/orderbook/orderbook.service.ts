import { CACHE_MANAGER, Inject, Injectable, Logger } from "@nestjs/common";
import axios from "axios";
import { Cache } from "cache-manager";
import { OrderEntity } from "src/models/entities/order.entity";
import {
  Orderbook,
  OrderbookMEBinance,
} from "src/modules/orderbook/orderbook.const";
import { CreateOrderDto } from "../order/dto/create-order.dto";
import {
  ContractType,
  OrderSide,
  OrderStatus,
  OrderTimeInForce,
  OrderType,
} from "src/shares/enums/order.enum";
import { AccountEntity } from "src/models/entities/account.entity";
import { AccountService } from "../account/account.service";
import { OrderService } from "../order/order.service";
import WebSocket = require("ws");
import { InstrumentService } from "../instrument/instrument.service";
import BigNumber from "bignumber.js";

@Injectable()
export class OrderbookService {
  constructor(
    @Inject(CACHE_MANAGER)
    public cacheManager: Cache,
    private readonly accountService: AccountService,
    private readonly orderService: OrderService,
    private readonly instrumentService: InstrumentService,
  ) {}

  public static getOrderbookKey(symbol: string): string {
    return `orderbook_${symbol}`;
  }

  public static getOrderbookMEKey(symbol: string): string {
    return `orderbook_me_${symbol}`;
  }

  public static getOrderbookBinanceKey(symbol: string): string {
    return `orderbook_binance_${symbol}`;
  }

  public static getOrderbookMEBinanceKey(symbol: string): string {
    return `orderbook_me_binance_${symbol}`;
  }

  private getSymbolTickSizeKey(symbol: string): string {
    return `tick_size_${symbol}`;
  }

  public async getSymbolTickSize(symbol: string): Promise<string> {
    const minTickSize = await this.cacheManager.get<string>(this.getSymbolTickSizeKey(symbol));
    if(!minTickSize) {
      // Get min tick size from database
      try {
        const instrument = await this.instrumentService.getInstrumentsBySymbol(symbol);
        await this.cacheManager.set(this.getSymbolTickSizeKey(symbol), instrument.tickSize, { ttl: 60 * 60 });
        return instrument.tickSize;
      } catch (error) {
        console.error(`Error getting min tick size for symbol ${symbol}:`, error);
      }
    }
    return minTickSize || '0.001';
  }

  async getOrderbook(symbol: string): Promise<Orderbook> {
    const orderbook = await this.cacheManager.get<Orderbook>(
      OrderbookService.getOrderbookKey(symbol)
    );
    if (!orderbook) {
      return {
        bids: [],
        asks: [],
      };
    } else {
      return orderbook;
    }
  }

  async getGroupOrderbook(symbol: string, tickSize: string) {
    const orderbook = await this.getOrderbook(symbol);
    const { bidPercent, askPercent } = this.calcBidAskPercent(orderbook)
    const groupOrderbook = this.groupBasedOnTicksize(orderbook, tickSize);
    const orderbookResponse = { orderbook: groupOrderbook, bidPercent, askPercent }
    return orderbookResponse
  }

  private orderbookFromBnbBySymbols: {
    [symbol: string]: {
      orderbook: Orderbook;
      wsForGetOrderbookFromBinance: WebSocket;
      isSocketConnected: boolean;
      lastCachedOrderbook: string;
      lastCheckCachedOrderbookTime: number;
    };
  } = {};

  public async getOrderbookFromBinance(symbol: string): Promise<Orderbook> {
    // if (symbol !== 'BTCUSDT') return;

    let isSocketConnected = this.orderbookFromBnbBySymbols[symbol]?.isSocketConnected;
    if (isSocketConnected) {
      if (Date.now() - this.orderbookFromBnbBySymbols[symbol].lastCheckCachedOrderbookTime >= 30000) { // 30s
        if (JSON.stringify(this.orderbookFromBnbBySymbols[symbol].orderbook) === this.orderbookFromBnbBySymbols[symbol].lastCachedOrderbook) { // Socket is disconnect
          console.error(`Socket of ${symbol} is disconnected, trying to reconnect...`);
          isSocketConnected = false;
          delete this.orderbookFromBnbBySymbols[symbol];
        } else {
          this.orderbookFromBnbBySymbols[symbol].lastCheckCachedOrderbookTime = Date.now();
          this.orderbookFromBnbBySymbols[symbol].lastCachedOrderbook = JSON.stringify(this.orderbookFromBnbBySymbols[symbol].orderbook);
        }
      }
    }

    if (isSocketConnected) {
      return this.orderbookFromBnbBySymbols[symbol].orderbook;
    }

    if (this.orderbookFromBnbBySymbols[symbol]) {
      return { bids: [], asks: [] };
    }

    const url = symbol.includes("USDM") 
      ? `wss://dstream.binance.com/ws/${symbol.replace("USDM", "USD_PERP").toLowerCase()}@depth` 
      : `wss://fstream.binance.com/ws/${symbol.toLowerCase()}@depth`;
    this.orderbookFromBnbBySymbols[symbol] = {
      orderbook: { bids: [], asks: [] },
      wsForGetOrderbookFromBinance: new WebSocket(url),
      isSocketConnected: false,
      lastCachedOrderbook: JSON.stringify({ bids: [], asks: [] }),
      lastCheckCachedOrderbookTime: Date.now()
    };

    this.orderbookFromBnbBySymbols[symbol].wsForGetOrderbookFromBinance.on(
      "open",
      () => {
        console.log(`Symbol ${symbol} connected to the BNB WebSocket server.`);
        this.orderbookFromBnbBySymbols[symbol].isSocketConnected = true;
      }
    );

    this.orderbookFromBnbBySymbols[symbol].wsForGetOrderbookFromBinance.on(
      "message",
      (data) => {
        try {
          if (!this.orderbookFromBnbBySymbols[symbol]) {
            // Maybe the socket is closed and it removed this.orderbookFromBnbBySymbols[symbol]
            console.error(
              `Socket of ${symbol} is closed or something went wrong`
            );
            return;
          }
          
          this.orderbookFromBnbBySymbols[symbol].orderbook.bids = JSON.parse(
            data as string
          ).b;
          this.orderbookFromBnbBySymbols[symbol].orderbook.asks = JSON.parse(
            data as string
          ).a;
  
          // Remove bids and asks with 0 size
          if (
            this.orderbookFromBnbBySymbols[symbol]?.orderbook?.bids?.length !== 0
          ) {
            const bidsNonZeroSize = this.orderbookFromBnbBySymbols[
              symbol
            ]?.orderbook?.bids?.filter((b) => Number(b[1]) > 0);
            this.orderbookFromBnbBySymbols[
              symbol
            ].orderbook.bids = bidsNonZeroSize;
          }
  
          if (
            this.orderbookFromBnbBySymbols[symbol]?.orderbook?.asks?.length !== 0
          ) {
            const asksNonZeroSize = this.orderbookFromBnbBySymbols[
              symbol
            ]?.orderbook?.asks?.filter((a) => Number(a[1]) > 0);
            this.orderbookFromBnbBySymbols[
              symbol
            ].orderbook.asks = asksNonZeroSize;
          }
        } catch(err) {
          console.error(`Something went wrong when handling bnb data:`);
          console.error(err);
        }
      }
    );

    this.orderbookFromBnbBySymbols[symbol].wsForGetOrderbookFromBinance.on(
      "close",
      () => {
        console.log(
          `Symbol ${symbol} disconnected to the BNB WebSocket server.`
        );
        delete this.orderbookFromBnbBySymbols[symbol];
      }
    );

    this.orderbookFromBnbBySymbols[symbol].wsForGetOrderbookFromBinance.on(
      "error",
      (error) => {
        console.error(`Symbol ${symbol}: WebSocket error: `, error);
      }
    );
    return null;
  }

  public async combineOrderbookMEBinance(data: {
    orderbookME: Orderbook;
    orderbookBinance: Orderbook;
  }): Promise<OrderbookMEBinance> {
    // If orderbook from binance is null => return orderbook from ME
    if (
      data.orderbookBinance == null
      // !data.orderbookBinance.asks ||
      // !data.orderbookBinance.bids ||
      // data.orderbookBinance.asks.length === 0 ||
      // data.orderbookBinance.bids.length === 0
    ) {
      // console.log(`[DEBUG] orderbookBinance is null, return orderbookME`);
      // console.log(data.orderbookME);
      return data.orderbookME;
    }

    const orderbookMEBinance: OrderbookMEBinance = { bids: [], asks: [] };
    data.orderbookBinance?.asks?.forEach((askB) => {
      const askME: string[] = data.orderbookME?.asks?.find(
        (askME) => askME[0] === askB[0]
      );
      if (askME) {
        orderbookMEBinance.asks.push([
          askB[0],
          String(Number(askB[1]) + Number(askME[1])),
          askME[1],
          askB[1],
        ]);
      } else {
        orderbookMEBinance.asks.push([askB[0], askB[1], "0", askB[1]]);
      }
    });

    data.orderbookBinance?.bids?.forEach((bidB) => {
      const bidME: string[] = data.orderbookME?.bids?.find(
        (bidME) => bidME[0] === bidB[0]
      );
      if (bidME) {
        orderbookMEBinance.bids.push([
          bidB[0],
          String(Number(bidB[1]) + Number(bidME[1])),
          bidME[1],
          bidB[1],
        ]);
      } else {
        orderbookMEBinance.bids.push([bidB[0], bidB[1], "0", bidB[1]]);
      }
    });

    data.orderbookME?.asks?.forEach((askME) => {
      const askB: string[] = data.orderbookBinance?.asks?.find(
        (askB) => askB[0] === askME[0]
      );
      if (!askB) {
        orderbookMEBinance.asks.push([askME[0], askME[1], askME[1], "0"]);
      }
    });

    data.orderbookME?.bids?.forEach((bidME) => {
      const bidB: string[] = data.orderbookBinance?.bids?.find(
        (bidB) => bidB[0] === bidME[0]
      );
      if (!bidB) {
        orderbookMEBinance.bids.push([bidME[0], bidME[1], bidME[1], "0"]);
      }
    });

    // Sort bids & asks
    orderbookMEBinance.bids?.sort((a, b) => {
      return Number(b[0]) - Number(a[0]);
    });
    orderbookMEBinance.asks?.sort((a, b) => {
      return Number(a[0]) - Number(b[0]);
    });

    // console.log(`[DEBUG] orderbookMEBinance: `);
    // console.log(orderbookMEBinance);
    return orderbookMEBinance;
  }

  public checkValidDataOfOrderbookMEBinance(data: {
    orderbookMEBinance: OrderbookMEBinance;
    symbol: string;
  }): boolean {
    if (
      data.orderbookMEBinance == null ||
      data.orderbookMEBinance.asks == null ||
      data.orderbookMEBinance.bids == null ||
      data.orderbookMEBinance.asks.length == 0 ||
      data.orderbookMEBinance.bids.length == 0
    ) {
      return true;
    }
    const lowestAsk: string[] = data.orderbookMEBinance.asks[0];
    const highestBid: string[] = data.orderbookMEBinance.bids[0];
    
    // If data is valid => Do not need to check any more
    if (Number(lowestAsk[0]) >= Number(highestBid[0])) return true;

    Logger.log(`[checkValidDataOfOrderbookMEBinance][${data.symbol}]-orderbook is invalid - lowestAsk: ${lowestAsk[0]}, highestBid: ${highestBid[0]}`);
    return false;
  }

  async fixInvalidDataOfOrderbookMEBinance(data: {
    orderbookMEBinance: OrderbookMEBinance;
    symbol: string;
  }): Promise<{
    validOrderbookMEBinance: OrderbookMEBinance;
    ordersNeedCreate: CreateOrderDto[];
  }> {
    // Clone orderbookMEBinance
    const orderbookMEBinanceCopy: OrderbookMEBinance = {
      ...data.orderbookMEBinance,
    };
    if (
      !orderbookMEBinanceCopy ||
      !orderbookMEBinanceCopy.asks ||
      orderbookMEBinanceCopy.asks.length == 0 ||
      !orderbookMEBinanceCopy.bids ||
      orderbookMEBinanceCopy.bids.length == 0
    ) {
      // if (data.symbol == "BNBUSDT") {
      //   console.log(`[DEBUG] orderbookMEBinance is null, return orderbookMEBinance`);
      //   console.log(data.orderbookMEBinance);
      // }
      return {
        validOrderbookMEBinance: data.orderbookMEBinance,
        ordersNeedCreate: [],
      };
    }

    // Begin to check
    let asset = "";
    if (data.symbol.includes("USDM")) {
      asset = data.symbol.split("USDM")[0];
    } else if (data.symbol.includes("USDT")) {
      asset = "USDT";
    } else {
      asset = "USD";
    }
    const buyOrderNeedCreate: CreateOrderDto[] = [];
    const sellOrderNeedCreate: CreateOrderDto[] = [];
    while (true) {
      // Get lowest ask
      const lowestAsk: string[] = orderbookMEBinanceCopy.asks[0];
      if (!lowestAsk) {
        return {
          validOrderbookMEBinance: data.orderbookMEBinance,
          ordersNeedCreate: [],
        };
      }

      const [
        lowestAskPriceStr,
        lowestAskSizeStr,
        lowestAskMESizeStr,
        lowestAskBSizeStr,
      ] = lowestAsk;
      const lowestAskPrice = Number(lowestAskPriceStr);
      const lowestAskSize = Number(lowestAskSizeStr);
      const lowestAskMESize = Number(lowestAskMESizeStr);
      const lowestAskBSize = Number(lowestAskBSizeStr);

      // Get highest bid
      const highestBid: string[] = orderbookMEBinanceCopy.bids[0];
      if (!highestBid) {
        return {
          validOrderbookMEBinance: data.orderbookMEBinance,
          ordersNeedCreate: [],
        };
      }

      const [
        highestBidPriceStr,
        highestBidSizeStr,
        highestBidMESizeStr,
        highestBidBSizeStr,
      ] = highestBid;
      const highestBidPrice = Number(highestBidPriceStr);
      const highestBidSize = Number(highestBidSizeStr);
      const highestBidMESize = Number(highestBidMESizeStr);
      const highestBidBSize = Number(highestBidBSizeStr);

      // If data is valid => Do not need to check any more
      if (lowestAskPrice >= highestBidPrice) break;

      // Match fully lowestAsk and highestBid
      if (lowestAskSize === highestBidSize) {
        // Check to create buy/sell order
        if (highestBidBSize > 0) {
          // Create buy order with size=highestBidBSize and price=highestBidPrice
          const createdBuyOrder: CreateOrderDto = await this.createBuyLimitOrderDtoForDefaultUser(
            {
              symbol: data.symbol,
              quantity: highestBidBSize,
              price: highestBidPrice,
              asset,
            }
          );
          buyOrderNeedCreate.push(createdBuyOrder);
        }
        if (lowestAskBSize > 0) {
          // Create buy order with size=lowestAskBSize and price=lowestAskPrice
          const createdSellOrder: CreateOrderDto = await this.createSellLimitOrderDtoForDefaultUser(
            {
              symbol: data.symbol,
              quantity: lowestAskBSize,
              price: lowestAskPrice,
              asset,
            }
          );
          sellOrderNeedCreate.push(createdSellOrder);
        }

        // Remove lowestAsk, highestBid from orderbookMEBinanceCopy
        orderbookMEBinanceCopy.asks.splice(0, 1);
        orderbookMEBinanceCopy.bids.splice(0, 1);
      }

      // Match fully lowestAsk and partially highestBid
      else if (lowestAskSize < highestBidSize) {
        // Check to create buy/sell order
        if (highestBidBSize > 0) {
          // Create buy order with size=highestBidBSize and price=highestBidPrice
          const createdBuyOrder: CreateOrderDto = await this.createBuyLimitOrderDtoForDefaultUser(
            {
              symbol: data.symbol,
              quantity: highestBidBSize,
              price: highestBidPrice,
              asset,
            }
          );
          buyOrderNeedCreate.push(createdBuyOrder);
        }
        if (lowestAskBSize > 0) {
          // Create buy order with size=lowestAskBSize and price=lowestAskPrice
          const createdSellOrder: CreateOrderDto = await this.createSellLimitOrderDtoForDefaultUser(
            {
              symbol: data.symbol,
              quantity: lowestAskBSize,
              price: lowestAskPrice,
              asset,
            }
          );
          sellOrderNeedCreate.push(createdSellOrder);
        }

        // Remove lowestAsk from orderbookMEBinanceCopy
        orderbookMEBinanceCopy.asks.splice(0, 1);
        // Update highestBid
        const highestBidUpdatedSize = highestBidSize - lowestAskSize;
        orderbookMEBinanceCopy.bids[0] = [
          String(highestBidPrice),
          String(highestBidUpdatedSize),
          String(highestBidUpdatedSize),
          "0",
        ];
      }

      // Match partially lowestAsk and fully highestBid
      else if (lowestAskSize > highestBidSize) {
        // Check to create buy/sell order
        if (highestBidBSize > 0) {
          // Create buy order with size=highestBidBSize and price=highestBidPrice
          const createdBuyOrder: CreateOrderDto = await this.createBuyLimitOrderDtoForDefaultUser(
            {
              symbol: data.symbol,
              quantity: highestBidBSize,
              price: highestBidPrice,
              asset,
            }
          );
          buyOrderNeedCreate.push(createdBuyOrder);
        }
        if (lowestAskBSize > 0) {
          // Create buy order with size=lowestAskBSize and price=lowestAskPrice
          const createdSellOrder: CreateOrderDto = await this.createSellLimitOrderDtoForDefaultUser(
            {
              symbol: data.symbol,
              quantity: lowestAskBSize,
              price: lowestAskPrice,
              asset,
            }
          );
          sellOrderNeedCreate.push(createdSellOrder);
        }

        // Remove highestBid from orderbookMEBinanceCopy
        orderbookMEBinanceCopy.bids.splice(0, 1);
        // Update lowestAsk
        const lowestAskUpdatedSize = lowestAskSize - highestBidSize;
        orderbookMEBinanceCopy.asks[0] = [
          String(lowestAskPrice),
          String(lowestAskUpdatedSize),
          String(lowestAskUpdatedSize),
          "0",
        ];
      }
    }

    // Create needed order
    const ordersNeedCreate: CreateOrderDto[] = [
      ...buyOrderNeedCreate,
      ...sellOrderNeedCreate,
    ];

    // if (data.symbol == "BNBUSDT") {
    //   console.log(`[DEBUG] ordersNeedCreate: `);
    //   console.log(ordersNeedCreate);
    // }

    // Create order
    // Push order to kafka
    // => orderbookME on cache will be updated
    await this.orderService.createOrderForDefaultCreateOrderUser({
      createOrderDtos: ordersNeedCreate,
      symbol: data.symbol,
    });

    return {
      validOrderbookMEBinance: orderbookMEBinanceCopy,
      ordersNeedCreate,
    };
  }

  public async createBuyLimitOrderDtoForDefaultUser(data: {
    symbol: string;
    quantity: number;
    price: number;
    asset: string;
  }): Promise<CreateOrderDto> {
    return {
      side: OrderSide.BUY,
      contractType: ContractType.USD_M,
      symbol: data.symbol,
      type: OrderType.LIMIT,
      quantity: String(data.quantity),
      price: String(data.price),
      remaining: String(data.quantity),

      // nullable
      executedPrice: null,
      tpSLType: null,
      tpSLPrice: null,
      stopCondition: null,
      takeProfitCondition: null,
      stopLossCondition: null,
      takeProfit: null,
      stopLoss: null,
      trigger: null,
      timeInForce: OrderTimeInForce.GTC,
      callbackRate: null,
      activationPrice: null,
      takeProfitTrigger: null,
      stopLossTrigger: null,
      isPostOnly: false,
      asset: data.asset,
      status: OrderStatus.PENDING,
      isHidden: null,
      // nullable
      isReduceOnly: false,
      // nullable
      isMam: null,
      // nullable
      pairType: null,
      // nullable
      referenceId: null,
      // nullable
      note: null,
      lockPrice: null,
      orderValue: null,
    };
  }

  public async createSellLimitOrderDtoForDefaultUser(data: {
    symbol: string;
    quantity: number;
    price: number;
    asset: string;
  }): Promise<CreateOrderDto> {
    return {
      side: OrderSide.SELL,
      contractType: ContractType.USD_M,
      symbol: data.symbol,
      type: OrderType.LIMIT,
      quantity: String(data.quantity),
      price: String(data.price),
      remaining: String(data.quantity),

      // nullable
      executedPrice: null,
      tpSLType: null,
      tpSLPrice: null,
      stopCondition: null,
      takeProfitCondition: null,
      stopLossCondition: null,
      takeProfit: null,
      stopLoss: null,
      trigger: null,
      timeInForce: OrderTimeInForce.GTC,
      callbackRate: null,
      activationPrice: null,
      takeProfitTrigger: null,
      stopLossTrigger: null,
      isPostOnly: false,
      asset: data.asset,
      status: OrderStatus.PENDING,
      isHidden: null,
      // nullable
      isReduceOnly: false,
      // nullable
      isMam: null,
      // nullable
      pairType: null,
      // nullable
      referenceId: null,
      // nullable
      note: null,
      lockPrice: null,
      orderValue: null,
    };
  }

  public groupBasedOnTicksize(orderbook: Orderbook, tickSize: string): Orderbook {
    const groupedOrderbook = this.groupOrderBook({
      bids: orderbook.bids, 
      asks: orderbook.asks
    }, tickSize);
    groupedOrderbook.asks = groupedOrderbook.asks.reverse();

    return groupedOrderbook
  }

  private groupOrderBook(
    orderBook: Orderbook,
    step: string
  ) {
    const transformPrice = (price: string, step: string): string => {
      if (parseFloat(step) < 1) {
        const decimaLength = step.toString().split(".")[1].length;
        
        const [priceInteger, priceDecimal] = price.split(".");
        
        if (!priceDecimal) {
          return price;
        }
        
        return `${priceInteger}.${priceDecimal.slice(0, decimaLength)}`;
      } else {
        const numPrice = Number(price);
        const groupPrice = numPrice - (numPrice % Number(step))
        if (groupPrice === 0) {
          return price;
        }
        return String(groupPrice);
      }
    };

    const group = (orders: string[][]) => {
      const grouped = new Map<string, number>();
      const maxGroupSize = 30;
      for (const [priceStr, amountStr] of orders) {
        const amount = parseFloat(amountStr);
        const groupedPrice = transformPrice(priceStr, step);

        if (grouped.has(groupedPrice)) {
          grouped.set(groupedPrice, grouped.get(groupedPrice)! + amount);
        } else {
          // check max group order size
          if (grouped.size >= maxGroupSize) {
            break;
          }
          grouped.set(groupedPrice, amount);
        }
      }

      return Array.from(grouped.entries()).map(([price, totalAmount]) => [
        price,
        parseFloat(totalAmount.toFixed(8)).toString()
      ]);
    };

    const asks = group(orderBook.asks);
    const bids = group(orderBook.bids);

    return {
      asks,
      bids,
    };
  }

  public calcBidAskPercent (orderbook: Orderbook): { bidPercent: number, askPercent: number } {
    try {
      const { bids, asks } = orderbook
      const totalBid = bids.reduce((acc, curVal) => acc + Number(curVal[1]), 0);
      const totalAsk = asks.reduce((acc, curVal) => acc + Number(curVal[1]), 0);
      const bidPercent = Number((totalBid / (totalAsk + totalBid)).toFixed(4))
      const askPercent = Number((1 - bidPercent).toFixed(4))
      return { bidPercent, askPercent }
    } catch (e) {
      return { bidPercent: 0, askPercent: 0 }
    }
  }
}

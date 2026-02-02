import { CACHE_MANAGER, Inject, Injectable, Logger } from "@nestjs/common";
import { Cache } from "cache-manager";
import { Command, Console } from "nestjs-console";
import { kafka } from "src/configs/kafka";
import { FundingService } from "src/modules/funding/funding.service";
import { IndexService } from "src/modules/index/index.service";
import {
  Ticker,
  TICKER_LAST_PRICE_TTL,
  TICKER_TTL,
  TICKERS_KEY,
} from "src/modules/ticker/ticker.const";
import { TickerService } from "src/modules/ticker/ticker.service";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { SocketEmitter } from "src/shares/helpers/socket-emitter";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { LAST_PRICE_PREFIX, LAST_PRICE_TTL } from "../index/index.const";
import { COINM, LIST_SYMBOL_COINM } from "../transaction/transaction.const";
import { ContractType } from "src/shares/enums/order.enum";
import { RedisService } from "nestjs-redis";
import { BINANCE_COINM_RECENT_TRADES, BINANCE_COINM_LAST_TRADE_PRICE, BINANCE_COINM_MARKET_PRICE } from "../trade/binance-coinm.const";
import BigNumber from "bignumber.js";
import { TickerTrade } from "./model/ticker-trade.model";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { BinanceTickerService } from "./binance-ticker.service";

interface Map<T> {
  [key: string]: T;
}

@Console()
@Injectable()
export class TickerConsole {
  constructor(
    private readonly tickerService: TickerService,
    @Inject(CACHE_MANAGER) public cacheManager: Cache,
    public readonly kafkaClient: KafkaClient,
    private readonly fundingService: FundingService,
    private readonly indexService: IndexService,
    private readonly redisService: RedisService,
    private readonly logger: Logger,
    private readonly redisClient: RedisClient,
    private readonly binanceTickerService: BinanceTickerService,
  ) {}

  @Command({
    command: "ticker:load",
    description: "Load data into ticker engine",
  })
  async load(): Promise<void> {
    await this.kafkaClient.delete([KafkaTopics.ticker_engine_preload]);

    const producer = kafka.producer();
    await producer.connect();
    await this.tickerService.loadInstruments(producer);
    // await this.tickerService.loadTrades(producer);
    await this.tickerService.startEngine(producer);
    await producer.disconnect();
  }

  private static pendingTickerTrades = new Array<TickerTrade>();
  private static readonly SEND_TRADE_DURATION: number = 950;
  private static lastSendTradeTime: number = 0;
  // @Command({
  //   command: 'ticker:publish',
  //   description: 'Publish ticker',
  // })
  // async publish(): Promise<void> {
  //   await this.kafkaClient.consume<Ticker[]>(KafkaTopics.ticker_engine_output, KafkaGroups.ticker, async (tickers) => {
  //     try {
  //       // console.log(tickers);
  //       // console.log('============================================');
  //       await this.addExtraInfoToTickers(tickers);
  //       const groupByPriceTickers = this.groupTradesByPrice(tickers);
  //       SocketEmitter.getInstance().emitTickers(groupByPriceTickers);
  //       // console.log('groupByPriceTickers ', groupByPriceTickers);
  //       await this.cacheManager.set(TICKERS_KEY, tickers, { ttl: TICKER_TTL });
  //     } catch (e) {
  //       this.logger.error(e);
  //       this.logger.error(`Message: ${JSON.stringify(tickers)}`);
  //     }
  //   });
  //   return new Promise(() => {});
  // }

  @Command({
    command: "ticker:publish",
    description: "Publish ticker",
  })
  async publish(): Promise<void> {
    await this.kafkaClient.consume<Ticker[]>(KafkaTopics.ticker_engine_output, KafkaGroups.ticker, async (tickers) => {
      await this.addExtraInfoToTickers(tickers);
      const tmpTickers = await this.addCoinMBinanceData(tickers);
      console.log(tmpTickers);
      console.log("============================================");
      // SocketEmitter.getInstance().emitTickers(tmpTickers);
      // if (tickers && tickers.length !== 0) {
      //   for (const ticker of tickers) {
      //     if (
      //       ticker.symbol !== "BTCUSDT" || 
      //       !ticker.trades ||
      //       ticker.trades.length === 0
      //     )
      //       continue;
      //     console.log(
      //       "BTCUSDT Highest Bids: " +
      //         ticker.trades[ticker.trades.length - 1].price
      //     );
      //   }
      // }
      for (const ticker of tickers) {
        this.cacheManager.set(`${LAST_PRICE_PREFIX}${ticker.symbol}`, ticker.lastPrice, {
          ttl: TICKER_LAST_PRICE_TTL,
        });
      }
      await this.cacheManager.set(TICKERS_KEY, tickers, { ttl: TICKER_TTL });
    });
    return new Promise(() => {});
  }

  private async addExtraInfoToTickers(tickers: Ticker[]): Promise<void> {
    const symbols = tickers.map((ticker) => ticker.symbol);
    const [indexPrices, oraclePrices, fundingRates, nextFunding, oldTickers] = await Promise.all([
      this.indexService.getIndexPrices(symbols),
      this.indexService.getOraclePrices(symbols),
      this.fundingService.getFundingRates(symbols),
      this.fundingService.getNextFunding(symbols[0]),
      // this.cacheManager.get<number>(`${FUNDING_PREFIX}next_funding`),
      this.cacheManager.get<Ticker[]>(TICKERS_KEY),
    ]);

    for (let i = 0; i < tickers.length; i++) {
      const ticker = tickers[i];
      const cacheLastPrice = await this.cacheManager.get<string>(`${LAST_PRICE_PREFIX}${ticker.symbol}`);
      const isCoinM = LIST_SYMBOL_COINM.includes(ticker.symbol);
      const contractType = isCoinM ? ContractType.COIN_M : ContractType.USD_M;
      //const isNewTickerLastPriceZero = new BigNumber(newTicker.lastPrice).isZero();

      // if (cacheLastPrice) {
      //   const newTicker = oldTickers?.find((item) => item.symbol == ticker.symbol) || ticker;
      //   ticker.priceChange = newTicker.priceChange;
      //   ticker.priceChangePercent = newTicker.priceChangePercent;
      //   ticker.lastPriceChange = cacheLastPrice ? `${+ticker.lastPrice - +cacheLastPrice}` : ticker.lastPriceChange;
      //   ticker.lastPrice = newTicker.lastPrice;
      //   ticker.highPrice = newTicker.highPrice;
      //   ticker.lowPrice = newTicker.lowPrice;
      //   ticker.volume = newTicker.volume;
      //   ticker.quoteVolume = newTicker.quoteVolume;
      //   await this.cacheManager.del(`${LAST_PRICE_PREFIX}${ticker.symbol}`);
      // }

      ticker.indexPrice = indexPrices[i];
      ticker.oraclePrice = oraclePrices[i];
      ticker.fundingRate = fundingRates[i];
      ticker.nextFunding = +nextFunding;
      ticker.contractType = contractType;

      // Cache last price
      if (ticker.lastPrice) {
        await this.cacheManager.set(`${LAST_PRICE_PREFIX}${ticker.symbol}`, ticker.lastPrice, { ttl: LAST_PRICE_TTL });
      }

      const binanceTicker24hr = await this.tickerService.getBinance24hrTicker(ticker.symbol);
      // update 24h change and 24h volume from Binance if no one is trading
      if (new BigNumber(ticker.priceChangePercent).eq('0') && binanceTicker24hr) {
        ticker.priceChangePercent = binanceTicker24hr.priceChangePercent;
      }
      if (new BigNumber(ticker.quoteVolume).eq('0') && binanceTicker24hr) {
        ticker.quoteVolume = binanceTicker24hr.quoteVolume;
      }
      if (new BigNumber(ticker.volume).eq('0') && binanceTicker24hr) {
        ticker.volume = binanceTicker24hr.volume;
      }
      if (new BigNumber(ticker.priceChange).eq('0') && binanceTicker24hr) {
        ticker.priceChange = binanceTicker24hr.priceChange;
      }
    }
  }

  private groupTradesByPrice(tickers: Ticker[]): Ticker[] {
    if (!tickers) return [];
    tickers.forEach(ticker => {
      if (!ticker.trades || ticker.trades.length === 0) return;
      ticker.trades.forEach(trade => {
        TickerConsole.pendingTickerTrades.push({
          symbol: trade.symbol,
          quantity: trade.quantity,
          createdAt: trade.createdAt,
          buyerIsTaker: trade.buyerIsTaker,
          buyFee: trade.buyFee,
          sellFee: trade.sellFee,
          buyFeeRate: trade.buyFeeRate,
          sellFeeRate: trade.sellFeeRate,
          buyOrderId: trade.buyOrderId,
          sellOrderId: trade.sellOrderId,
          price: trade.price,
          id: trade.id
        });
      });
      ticker.trades = [];
    });

    // Check if it should send trades to client 
    const now = Date.now();
    const isShouldAddPendingTickerTrades: boolean = now - TickerConsole.lastSendTradeTime < TickerConsole.SEND_TRADE_DURATION;
    if (isShouldAddPendingTickerTrades) return tickers;

    // Group trades by symbol and price
    const tradesBySymbolAndPrice: Map<TickerTrade> = {};
    for (const trade of TickerConsole.pendingTickerTrades) {
      const tradeBySymbolAndPrice = tradesBySymbolAndPrice[`${trade.symbol}|${trade.price}`];
      if (!tradeBySymbolAndPrice) {
        tradesBySymbolAndPrice[`${trade.symbol}|${trade.price}`] = trade;
        continue;
      }

      // Compute quantity
      const oldQuantity = new BigNumber(tradeBySymbolAndPrice.quantity);
      const tradeQuantity = new BigNumber(trade.quantity);
      tradeBySymbolAndPrice.quantity = oldQuantity.plus(tradeQuantity).toString();

      // Compute buyFee
      const oldBuyFee = new BigNumber(tradeBySymbolAndPrice.buyFee);
      const tradeBuyFee = new BigNumber(trade.buyFee);
      tradeBySymbolAndPrice.buyFee = oldBuyFee.plus(tradeBuyFee).toString();

      // Compute sellFee
      const oldSellFee = new BigNumber(tradeBySymbolAndPrice.sellFee);
      const tradeSellFee = new BigNumber(trade.sellFee);
      tradeBySymbolAndPrice.sellFee = oldSellFee.plus(tradeSellFee).toString();

      // Compute buyFeeRate
      const oldBuyFeeRate = new BigNumber(tradeBySymbolAndPrice.buyFeeRate);
      const tradeBuyFeeRate = new BigNumber(trade.buyFeeRate);
      tradeBySymbolAndPrice.buyFeeRate = oldBuyFeeRate.plus(tradeBuyFeeRate).toString();

      // Compute sellFeeRate
      const oldSellFeeRate = new BigNumber(tradeBySymbolAndPrice.sellFeeRate);
      const tradeSellFeeRate = new BigNumber(trade.sellFeeRate);
      tradeBySymbolAndPrice.sellFeeRate = oldSellFeeRate.plus(tradeSellFeeRate).toString();

      tradeBySymbolAndPrice.createdAt = trade.createdAt;
      tradeBySymbolAndPrice.buyerIsTaker = trade.buyerIsTaker;
      tradeBySymbolAndPrice.buyOrderId = trade.buyOrderId;
      tradeBySymbolAndPrice.sellOrderId = trade.sellOrderId;
      tradeBySymbolAndPrice.symbol = trade.symbol;
      tradeBySymbolAndPrice.price = trade.price;
      tradeBySymbolAndPrice.id = trade.id;

      tradesBySymbolAndPrice[`${trade.symbol}|${trade.price}`] = tradeBySymbolAndPrice;
    }

    const tradeListsBySymbol: Map<TickerTrade[]> = {};
    for (const symbolAndPrice in tradesBySymbolAndPrice) {
      const trade = tradesBySymbolAndPrice[symbolAndPrice];
      trade.createdAt = now;
      const symbol = symbolAndPrice.split('|')[0];

      const trades: TickerTrade[] = tradeListsBySymbol[symbol] || [];
      trades.push(trade)
      tradeListsBySymbol[symbol] = trades;
    }

    for (const symbol in tradeListsBySymbol) {
      tickers.forEach(ticker => {
        if (!ticker.symbol || ticker.symbol !== symbol) return;
        ticker.trades = tradeListsBySymbol[symbol];
      });
    }

    TickerConsole.pendingTickerTrades = [];
    TickerConsole.lastSendTradeTime = now;
    return tickers;
  }

  private async addCoinMBinanceData(tickers: Ticker[]) {
    const cpTickers = JSON.parse(JSON.stringify(tickers));

    //debug
    const btcUsdtTicker = JSON.parse(JSON.stringify((cpTickers.find((ticker) => ticker.symbol === 'BTCUSDT'))))
    btcUsdtTicker.symbol = 'BTCUSDM'
    btcUsdtTicker.contractType = "COIN_M"
    for (const trade of btcUsdtTicker.trades) {
      trade.symbol = "BTCUSDM"
      trade.contractType = "COIN_M"
    }
    // debug

    for (let i = 0; i < cpTickers.length; i++) {
      const ticker = cpTickers[i];
      // get trades data, market price, last trade price from binance if symbol is coin_m
      if (ticker.contractType === COINM) {
        const [binanceRecentTrades, binanceLastTradePrice, binanceMarketPrice] = await Promise.all([
          this.redisClient.getInstance().get(`${BINANCE_COINM_RECENT_TRADES}${ticker.symbol}`),
          this.redisClient.getInstance().get(`${BINANCE_COINM_LAST_TRADE_PRICE}${ticker.symbol}`),
          this.redisClient.getInstance().get(`${BINANCE_COINM_MARKET_PRICE}${ticker.symbol}`),
        ]);
        ticker.lastPrice = binanceLastTradePrice;
        ticker.oraclePrice = binanceMarketPrice;
        // ticker.trades =
        //   JSON.parse(binanceRecentTrades)?.map((trade) => {
        //     return {
        //       symbol: ticker.symbol,
        //       buyAccountId: 2,
        //       sellAccountId: 2,
        //       buyUserId: 1,
        //       sellUserId: 1,
        //       buyOrderId: 1000000712,
        //       sellOrderId: 1000000711,
        //       buyerIsTaker: !trade.isBuyerMaker,
        //       quantity: `${getRandomDecimal()}`,
        //       price: trade.price,
        //       buyFee: "0.0534255",
        //       sellFee: "0.0178085",
        //       buyFeeRate: "0.00075",
        //       sellFeeRate: "0.00025",
        //       contractType: "COIN_M",
        //       buyEmail: "bot1@gmail.com",
        //       sellEmail: "bot1@gmail.com",
        //       id: trade.id,
        //       operationId: "13974273500000000",
        //       createdAt: trade.time,
        //       updatedAt: trade.time,
        //     };
        //   }) ?? [];

        if (ticker.symbol === 'BTCUSDM') {
          cpTickers[i] = btcUsdtTicker
        }
      }
    }
    return cpTickers;
  }

  @Command({
    command: "ticker:publish-binance-ticker",
    description: "Publish binance ticker",
  })
  async publishBinanceTicker(): Promise<void> {
    this.logger.log("Start publish binance ticker...");
    try {
      this.binanceTickerService.connectAll();
      while (true) {
        const tickers = await this.binanceTickerService.getAllTickerData();
        // console.log(JSON.stringify(tickers));

        SocketEmitter.getInstance().emitTickers(tickers);
        // Cache tickers for API access
        await this.cacheManager.set(TICKERS_KEY, tickers, { ttl: TICKER_TTL });
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
    } catch (e) {
      this.logger.error(`[publish-binance-ticker]-error: , ${e}`);
    }
    return new Promise(() => {});
  }
}

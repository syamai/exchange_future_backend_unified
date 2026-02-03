import { CACHE_MANAGER, Inject, Injectable, Logger, forwardRef } from "@nestjs/common";
import { Cache } from "cache-manager";
import { serialize } from "class-transformer";
import { Producer } from "kafkajs";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { BaseEngineService } from "src/modules/matching-engine/base-engine.service";
import {
  BATCH_SIZE,
  CommandCode,
} from "src/modules/matching-engine/matching-engine.const";
import { Binance24hrTicker, Ticker, TICKERS_KEY } from "src/modules/ticker/ticker.const";
import { TradeService } from "src/modules/trade/trade.service";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { FundingService } from "../funding/funding.service";
import {
  INDEX_PRICE_PREFIX,
  LAST_PRICE_PREFIX,
  ORACLE_PRICE_PREFIX,
} from "../index/index.const";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { InjectRepository } from "@nestjs/typeorm";
import WebSocket = require("ws");
import axios from "axios";
import { ContractType } from "src/shares/enums/order.enum";
import * as moment from 'moment';

@Injectable()
export class TickerService extends BaseEngineService {
  private logger: Logger;
  constructor(
    @Inject(CACHE_MANAGER) public cacheManager: Cache,
    private readonly instrumentService: InstrumentService,
    private readonly tradeService: TradeService,
    @InjectRepository(InstrumentRepository, "report")
    private readonly instrumentRepoReport: InstrumentRepository,
    @Inject(forwardRef(() => FundingService))
    private readonly fundingService: FundingService
  ) {
    super();
    this.logger = new Logger(TickerService.name);
  }

  async getTickers(contractType?: string, symbol?: string): Promise<Ticker[]> {
    // lấy dữ liệu mã cổ phiếu
    let [tickers, nextFunding] = await Promise.all([
      this.cacheManager.get<Ticker[]>(TICKERS_KEY), // lấy dữ liệu từ machine engineer
      this.fundingService.getNextFunding(symbol),

      // this.cacheManager.get<number>(`${FUNDING_PREFIX}next_funding`),
    ]);
    // tickers = tickers ?? [];
    const instrumentSymbol = await this.instrumentService.getAllTickerInstrument(); // lấy tất cả ký hiệu mã cổ phiếu

    tickers?.forEach((ticker) => {
      ticker.contractType = instrumentSymbol?.find(
        (symbol) => symbol.symbol == ticker.symbol
      )?.contractType;
      ticker.nextFunding = +nextFunding;
    });

    const existingTickers = tickers || [];
    const existingTickerSymbols = new Set(
      existingTickers.map((item) => item.symbol)
    );

    const newTickers = instrumentSymbol
      .filter((item) => !existingTickerSymbols.has(item.symbol))
      .map((item) => ({
        symbol: item.symbol,
        priceChange: "0",
        priceChangePercent: "0",
        lastPrice: "",
        lastPriceChange: null,
        highPrice: "",
        lowPrice: "",
        volume: "",
        quoteVolume: "",
        indexPrice: "",
        oraclePrice: "",
        fundingRate: "",
        nextFunding: +nextFunding,
        contractType: item.contractType,
        name: item.name,
      }));

    let updatedTickers = existingTickers.concat(newTickers);

    await Promise.all(
      updatedTickers.map(async (item) => {
        const [
          lastPrice,
          indexPrice,
          oraclePrice,
          fundingRate,
        ] = await Promise.all([
          this.cacheManager.get<string>(`${LAST_PRICE_PREFIX}${item.symbol}`),
          this.cacheManager.get<string>(`${INDEX_PRICE_PREFIX}${item.symbol}`),
          this.cacheManager.get<string>(`${ORACLE_PRICE_PREFIX}${item.symbol}`),
          this.fundingService.fundingRate(item.symbol),
          // this.cacheManager.get<string>(`${FUNDING_PREFIX}${item.symbol}`),
        ]);
        item.lastPrice = lastPrice ? lastPrice : item.lastPrice;
        // Use lastPrice as fallback for indexPrice and oraclePrice if not available
        const effectiveLastPrice = item.lastPrice || "0";
        item.indexPrice = indexPrice || effectiveLastPrice;
        item.oraclePrice = oraclePrice || item.indexPrice;
        item.fundingRate = fundingRate || "";
        item.updatedAt = Date.now();
        item.lastUpdateAt = Date.now();

         // last check fundingRate, if not good, get data from binance
        if (!item?.fundingRate || !item?.nextFunding) {
          try {
            const binanceFundingDataResponse = await axios.get(
              item.contractType == ContractType.USD_M 
              ? `https://fapi.binance.com/fapi/v1/premiumIndex?symbol=${item.symbol}`
              : `https://dapi.binance.com/dapi/v1/premiumIndex?symbol=${item.symbol.replace('USDM', 'USD_PERP')}`
            );
  
            const binanceFundingData = binanceFundingDataResponse.data;
  
            item.fundingRate = binanceFundingData.lastFundingRate;
            item.nextFunding = binanceFundingData.nextFundingTime;
          } catch (e) {
            this.logger.log(`[tickerService][getTickers] - get funding rate or next funding binance error: ${e}`)
            
            item.fundingRate = "0.00008390"
            item.nextFunding = this.getNextFundingTime();
          }
        }
      })
    );

    if (contractType) {
      updatedTickers = updatedTickers.filter((item) => {
        return item.contractType == contractType;
      });
    }

    if (symbol) {
      updatedTickers = updatedTickers.filter((item) => {
        return item.symbol == symbol;
      });
    }

    // get next funding time
    const nextFundingTime = this.getNextFundingTime();
    updatedTickers.forEach((item) => {
      item.nextFunding = nextFundingTime;
    });

    return updatedTickers;
  }

  public async loadInstruments(producer: Producer): Promise<void> {
    const instruments = await this.instrumentService.find();
    const data = instruments.map((instrument) => {
      return { data: instrument, code: CommandCode.UPDATE_INSTRUMENT };
    });
    await producer.send({
      topic: KafkaTopics.ticker_engine_preload,
      messages: [{ value: JSON.stringify(data) }],
    });
  }

  public async loadTrades(producer: Producer): Promise<void> {
    const startTime: number = new Date().getTime();
    const yesterday = new Date(Date.now() - 86400000);
    // await this.loadYesterdayTrades(producer, yesterday);

    let trades = [];
    let index = 0;
    const instruments = await this.instrumentRepoReport.find({
      select: ["symbol"],
    });
    let symbolsNotHaveTrade = instruments.map((e) => e.symbol);
    console.log("before: ", symbolsNotHaveTrade.length);
    do {
      const listIndexes = [];
      for (let i = 0; i < 160; i++) {
        listIndexes.push(index + i * BATCH_SIZE);
      }
      const listPromiseTrades = await Promise.all(
        listIndexes.map(async (i) => {
          return await this.tradeService.findTodayTrades(
            yesterday,
            i,
            BATCH_SIZE
          );
        })
      );

      for (const promiseTrades of listPromiseTrades) {
        trades = promiseTrades;
        const tradeSymbol = [...new Set(trades.map((trade) => trade.symbol))];
        symbolsNotHaveTrade = symbolsNotHaveTrade.filter(
          (trade) => !tradeSymbol.includes(trade)
        );
        index += trades.length;
        const command = { code: CommandCode.PLACE_ORDER, trades };
        producer.send({
          topic: KafkaTopics.ticker_engine_preload,
          messages: [{ value: serialize([command]) }],
        });
        console.log("index: " + index);
      }
    } while (trades.length > 0);
    console.log("after: ", symbolsNotHaveTrade.length);

    console.log({ symbolsNotHaveTrade });
    for (const symbol of symbolsNotHaveTrade) {
      const lastTrade = await this.tradeService.getLastTrade(symbol);
      console.log({ lastTrade });
      if (lastTrade) {
        const command = {
          code: CommandCode.PLACE_ORDER,
          trades: lastTrade ? lastTrade : null,
        };
        await producer.send({
          topic: KafkaTopics.ticker_engine_preload,
          messages: [{ value: serialize([command]) }],
        });
      }
    }

    const endTime: number = new Date().getTime();
    console.log("Processing time: " + Number(endTime - startTime));
  }

  // public async loadInstrument;

  public async startEngine(producer: Producer): Promise<void> {
    const command = { code: CommandCode.START_ENGINE };
    await producer.send({
      topic: KafkaTopics.ticker_engine_preload,
      messages: [{ value: JSON.stringify([command]) }],
    });
  }

  private async loadYesterdayTrades(
    producer: Producer,
    date: Date
  ): Promise<void> {
    const instruments = await this.instrumentService.getAllInstruments();
    const yesterdayTrades = [];
    for (const instrument of instruments) {
      const trade = await this.tradeService.findYesterdayTrade(
        date,
        instrument.symbol
      );
      if (trade) {
        yesterdayTrades.push(trade);
      }
    }

    if (yesterdayTrades.length > 0) {
      // simulate output of matching engine
      const command = {
        code: CommandCode.PLACE_ORDER,
        trades: yesterdayTrades,
      };
      await producer.send({
        topic: KafkaTopics.ticker_engine_preload,
        messages: [{ value: serialize([command]) }],
      });
    }
  }

  private binance24hrTickers: {
    [symbol: string]: {
      isInitialized: boolean;
      ticker24hrData: Binance24hrTicker;
      binance24hrTickerFStream: WebSocket;
      isWsConnected: boolean;
    };
  } = {};

  public async getBinance24hrTicker(symbol: string): Promise<Binance24hrTicker> {
    if (this.binance24hrTickers[symbol]?.isWsConnected) {
      return this.binance24hrTickers[symbol].ticker24hrData;
    }

    if (this.binance24hrTickers[symbol]?.isInitialized) {
      return null;
    }

    const binanceStreamUrl = symbol.includes('USDM') 
      ? `wss://dstream.binance.com/ws/${symbol.replace('USDM', 'USD_PERP').toLowerCase()}@ticker`
      : `wss://fstream.binance.com/ws/${symbol.toLowerCase()}@ticker`;
  
    this.binance24hrTickers[symbol] = {
      isInitialized: true,
      ticker24hrData: null,
      binance24hrTickerFStream: new WebSocket(binanceStreamUrl),
      isWsConnected: false,
    };

    this.binance24hrTickers[symbol].binance24hrTickerFStream.on("open", () => {
      console.log(`[${symbol}] Connected to the Binance stream 24hr ticker.`);
      this.binance24hrTickers[symbol].isWsConnected = true;
    });

    this.binance24hrTickers[symbol].binance24hrTickerFStream.on("message", (data) => {
      const tickerData = JSON.parse(data.toString());

      const newTickerData: Binance24hrTicker = {
        symbol: symbol,
        priceChange: tickerData?.p || "0",
        priceChangePercent: tickerData?.P || "0",
        weightedAvgPrice: tickerData?.w || "0",
        prevClosePrice: tickerData?.x || "0",
        lastPrice: tickerData?.c || "0",
        lastQty: tickerData?.Q || "0",
        bidPrice: tickerData?.b || "0",
        askPrice: tickerData?.a || "0",
        openPrice: tickerData?.o || "0",
        highPrice: tickerData?.h || "0",
        lowPrice: tickerData?.l || "0",
        volume: tickerData?.v || "0",
        quoteVolume: tickerData?.q || "0",
        openTime: tickerData?.O || 0,
        closeTime: tickerData?.C || 0,
        firstId: tickerData?.F || 0,
        lastId: tickerData?.L || 0,
        count: tickerData?.n || 0,
      };

      // console.log(`[${symbol}] New 24hr ticker data: ${JSON.stringify(newTickerData)}`);

      this.binance24hrTickers[symbol].ticker24hrData = newTickerData;
    });

    this.binance24hrTickers[symbol].binance24hrTickerFStream.on("close", () => {
      console.log(`[${symbol}] Disconnected to the Binance stream 24hr ticker.`);
      this.binance24hrTickers[symbol].isWsConnected = false;
      this.binance24hrTickers[symbol].isInitialized = false;
    });

    this.binance24hrTickers[symbol].binance24hrTickerFStream.on("error", (error) => {
      console.log(`[${symbol}] Error on Binance stream 24hr ticker: ${error.message}`);
      this.binance24hrTickers[symbol].isWsConnected = false;
      this.binance24hrTickers[symbol].isInitialized = false;
    });

    return null;
  }

  private getNextFundingTime(): number {
    const fundingHours = [0, 8, 16];
    const currentHour = moment().utc().hour();
    let nextHour: number;
    for (const fH of fundingHours) {
      if (fH > currentHour) {
        nextHour = fH;
        break;
      }
    }
    if (!nextHour) {
      return moment().utc().add(1, 'day').set({ hour: 0, minute: 0, second: 0, millisecond: 0 }).valueOf();
    }
    return moment().utc().set({ hour: nextHour, minute: 0, second: 0, millisecond: 0 }).valueOf();
  }
}

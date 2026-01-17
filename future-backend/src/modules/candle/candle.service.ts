/**
 * Assumed that we have flow of data in sequence (Ordered)
 * Candle is stored by minutes
 * it has low, high, open, close, year, month, day(_in_month), hour(_in_day), min(_in_hour),
 *  Skip year, month, day(_in_month), hour(_in_day) since data is small an can be query easily not costing
 *  much of computation, we only have to index symbol and min
 *
 * Requires: Storing mass data from kafka to sql,
 *
 * Solution:
 *  - mysql design:
 *      Partition by symbol, store by minutes,
 *          => 1 yrs = 365 * 24 * 60 rows data = 525600 rows data, and ordered
 *      => Indexed by month, day, hour, min since each 1 min we insert a record - and maybe update it price
 *         therefore it should make no problem with I/O
 *  - Flow to digest data:
 *        - receive data from kafka put on redis by key = epoch rounds to minute
 *        - another async function run
 *
 *
 */
import { CACHE_MANAGER, Inject, Injectable, Logger } from "@nestjs/common";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { Cache } from "cache-manager";
import { RedisService } from "nestjs-redis";
import { kafka } from "src/configs/kafka";
import { CandlesEntity } from "src/models/entities/candles.entity";
import { CandlesRepository } from "src/models/repositories/candles.repository";
import { MetadataRepository } from "src/models/repositories/metadata.repository";
import * as CONST from "src/modules/candle/candle.const";
import {
  Candle,
  KEY_CACHE_HEALTHCHECK_SYNC_CANDLE,
  RESOLUTION_15MINUTES,
  RESOLUTION_HOUR,
  RESOLUTION_MINUTE,
} from "src/modules/candle/candle.const";
import { CandleData, TradeData } from "src/modules/candle/candle.dto";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { CommandOutput } from "src/modules/matching-engine/matching-engine.const";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { Between, Connection, Equal } from "typeorm";
import { LAST_PRICE_PREFIX } from "../index/index.const";
import { SYMBOL_CACHE } from "../instrument/instrument.const";
import { Ticker, TICKERS_KEY } from "../ticker/ticker.const";
import axios from "axios";
import * as moment from "moment";
import { RedisClient } from "src/shares/redis-client/redis-client";

@Injectable()
export class CandleService {
  private readonly logger = new Logger(CandleService.name);

  private resolutions = [RESOLUTION_15MINUTES, RESOLUTION_HOUR];
  private readonly baseUnit = 60000;
  private readonly resolutionMap = {
    "1": this.baseUnit,
    "3": this.baseUnit * 3,
    "5": this.baseUnit * 5,
    "15": this.baseUnit * 15,
    "30": this.baseUnit * 30,
    "60": this.baseUnit * 60,
    "120": this.baseUnit * 60 * 2,
    "240": this.baseUnit * 60 * 4,
    "360": this.baseUnit * 60 * 6,
    "480": this.baseUnit * 60 * 8,
    "720": this.baseUnit * 60 * 12,
    "1d": this.baseUnit * 60 * 24,
    "1D": this.baseUnit * 60 * 24,
    D: this.baseUnit * 60 * 24,
    "3D": this.baseUnit * 60 * 24 * 3,
    "7D": this.baseUnit * 60 * 24 * 7,
    "30D": this.baseUnit * 60 * 24 * 30,
  };

  constructor(
    private readonly instrumentService: InstrumentService,
    @InjectRepository(CandlesRepository, "master")
    private candleRepositoryMaster: CandlesRepository,
    @InjectRepository(CandlesRepository, "report")
    private candleRepositoryReport: CandlesRepository,
    @InjectRepository(MetadataRepository, "master")
    private metadataRepositoryMaster: MetadataRepository,
    @InjectRepository(MetadataRepository, "report")
    private metadataRepositoryReport: MetadataRepository,
    @Inject(CACHE_MANAGER) private readonly cacheService: Cache,
    private readonly redisService: RedisService,
    @InjectConnection("master") private connection: Connection,
    private readonly redisClient: RedisClient
  ) {}

  getMinute(epoch: number): number {
    if (epoch > Math.pow(10, 10)) {
      epoch = Math.floor(epoch / 1000); // to second from milis
    }
    // Just to be more secure we throw some kind of invalid data for debug
    // Thursday, January 10, 2008 9:20:00 PM => 1200000000
    // Saturday, November 20, 2286 5:46:40 PM 10000000000
    if (epoch < 1200000000 || epoch > 10000000000)
      throw "Epoch time input error!!! " + __filename;

    return epoch - (epoch % 60);
  }

  getSecond(epoch: number): number {
    if (epoch > Math.pow(10, 10)) {
      epoch = Math.floor(epoch / 1000); // to second from milis
    }
    return epoch;
  }

  async storeCandle(symbols: string[]): Promise<void> {
    await Promise.all(
      symbols.map(async (symbol) => {
        // get the latest minute
        console.log("===========================================");
        console.log(symbol);
        const lastCachedCandle = await this.cacheService.get<CandleData>(this.getLastCandleKey(symbol));
        let latest = lastCachedCandle?.minute || 0;
        const now = Math.floor(Date.now() / 60000) * 60;
        const twoDaysAgo = now - 86400 * 2;
        latest = Math.max(latest - (latest % 60), twoDaysAgo);
        latest = Math.min(latest, now - 60); // maybe latest candle is being updated, so we don't save it to database

        let lastCandle = await this.getLastCandleFromDatabase(symbol, RESOLUTION_MINUTE);
        const fromTime = lastCandle.minute > 0 ? lastCandle.minute + 60 : latest;
        for (let i = fromTime; i <= latest; i += 60) {
          let candleData = await this.cacheService.get<CandleData>(this.getCandleKey(symbol, i));
          if (!candleData) {
            candleData = {
              symbol: symbol,
              minute: i,
              resolution: RESOLUTION_MINUTE,
              low: lastCandle.close,
              high: lastCandle.close,
              open: lastCandle.close,
              close: lastCandle.close,
              lastTradeTime: lastCandle.lastTradeTime,
              volume: "0",
            };
          }
          lastCandle = candleData;

          //check exist
          const candleExist = await this.candleRepositoryReport.findOne({ where: {
            symbol: candleData.symbol,
            resolution: candleData.resolution,
            minute: candleData.minute
          }})

          if (candleExist) {
            console.log(`Candle existed, continue`);
            continue;
          }

          console.log(`Candle not exist, save to db - symbol: ${candleData.symbol}, resolution: ${candleData.resolution}, minute: ${candleData.minute}`);
          try {
            await this.candleRepositoryMaster.save(candleData);
            await this.saveExtraResolutions(candleData);
          }
          catch (e) {
            console.log(e);
            continue;
          }
        }
      }),
    );
  }

  async handleMessage(commandOutputs: CommandOutput[]): Promise<void> {
    for (const element of commandOutputs) {
      if (element.trades != undefined) {
        for (const trade of element.trades) {
          this.logger.log(`Processing trade ${JSON.stringify(trade)}`);
          // transform to needed data.
          const data = {
            price: trade.price as string,
            volume: new BigNumber(trade.price as string)
              .times(trade.quantity as string)
              .toString(),
            updatedAt: Math.floor((trade.updatedAt as number) / 1000),
            symbol: trade.symbol as string,
          };
          await this.handleTrade(data);
        }
      }
    }
  }

  async handleTrade(data: TradeData): Promise<void> {
    const minute = this.getMinute(Number(data.updatedAt));

    const cachedCandle = await this.cacheService.get<CandleData>(
      this.getCandleKey(data.symbol, minute)
    );

    let candle: CandleData;

    if (!cachedCandle) {
      const lastCandle = await this.getLastCandle(
        data.symbol,
        RESOLUTION_MINUTE
      );
      candle = {
        symbol: data.symbol,
        minute: minute,
        resolution: RESOLUTION_MINUTE,
        low: BigNumber.min(data.price, lastCandle.close).toString(),
        high: BigNumber.max(data.price, lastCandle.close).toString(),
        open: lastCandle.close,
        close: data.price,
        volume: data.volume,
        lastTradeTime: data.updatedAt,
      };
    } else {
      if (cachedCandle.lastTradeTime > data.updatedAt) {
        // Skip this message  since it's been processed before
        return;
      }

      candle = {
        symbol: data.symbol,
        minute: minute,
        resolution: RESOLUTION_MINUTE,
        low: BigNumber.min(data.price, cachedCandle.low).toString(),
        high: BigNumber.max(data.price, cachedCandle.high).toString(),
        open: cachedCandle.open,
        close: data.price,
        volume: new BigNumber(cachedCandle.volume).plus(data.volume).toString(),
        lastTradeTime: data.updatedAt,
      };
    }

    this.logger.log(`Save candle ${JSON.stringify(candle)}`);

    await this.cacheService.set(
      this.getCandleKey(data.symbol, minute),
      candle,
      { ttl: CONST.CANDLE_TTL }
    );
    await this.cacheService.set(this.getLastCandleKey(data.symbol), candle, {
      ttl: CONST.CANDLE_TTL,
    });
  }

  async getCandles(
    symbol: string,
    from: number,
    to: number,
    resolution: string
  ): Promise<Candle[]> {
    const convertedResolution = this.resolutionMap[resolution];
    if (convertedResolution) {
      return await this._getCandles(
        symbol,
        this.standardizeCandleTime(from, convertedResolution),
        this.standardizeCandleTime(to, convertedResolution) +
          convertedResolution -
          1,
        convertedResolution
      );
    } else {
      return [];
    }
  }

  async getMergeCandles(
    symbol: string,
    from: number,
    to: number,
    resolution: string
  ): Promise<Candle[]> {
    const timeline = moment(process.env.TIMELINE_GET_CANDLES_FROM_BINANCE);

    // if 'from' and 'to' are both after timeline 
    if (
      (moment(from).isAfter(timeline) || 
        moment(from).isSame(timeline)) &&
        moment(to).isAfter(timeline)
      ) {
        return await this.getCandles(symbol, from, to, resolution);
    }

    const resCandles: Candle[] = [];
    let { oldestCachedTime, oldestCachedBinanceCandle } = await this.getOldestCachedBinanceCandle(symbol, resolution);
    if (!oldestCachedTime) oldestCachedTime = timeline.toDate().getTime();

    // if 'from' and 'to' are both before timeline
    if (moment(from).isBefore(timeline) &&
        (moment(to).isBefore(timeline) || 
        moment(to).isSame(timeline))
    ) {
      // if 'from' and 'to' are both before oldestCachedTime
      if (
        moment(from).isBefore(moment(oldestCachedTime)) &&
        (moment(to).isBefore(moment(oldestCachedTime)) || 
        moment(to).isSame(moment(oldestCachedTime)))
      ) {
        const binanceCandles: Candle[] = await this.getCandlesFromBinance(symbol, from, oldestCachedTime, resolution);
        for (const bc of binanceCandles) {
          await this.saveCachedBinanceCandle(symbol, resolution, bc.time, bc);
          if (moment(bc.time).isBefore(moment(to)) || moment(bc.time).isSame(moment(to))) {
            resCandles.push(bc);
          }
        }
        this.logger.log(`Hit save cache binance candles symbol=${symbol} resolution=${resolution} newOldestCachedTime=${binanceCandles?.[0].time}`);
        return resCandles;
      }

      // if 'from' and 'to' are both after oldestCachedTime
      if (
        (moment(from).isAfter(moment(oldestCachedTime)) || 
        moment(from).isSame(moment(oldestCachedTime)) &&
        moment(to).isAfter(moment(oldestCachedTime)))
      ) {
        const cachedBinanceCandles: Candle[] = await this.getCachedBinanceCandles(symbol, resolution, from, to);
        resCandles.push(...cachedBinanceCandles);
        return resCandles;
      }

      // if 'from' is before and 'to' is after oldestCachedTime
      if (
        moment(from).isBefore(moment(oldestCachedTime)) &&
        moment(to).isAfter(moment(oldestCachedTime))
      ) {
        const binanceCandles: Candle[] = await this.getCandlesFromBinance(symbol, from, oldestCachedTime, resolution);
        for (const bc of binanceCandles) {
          await this.saveCachedBinanceCandle(symbol, resolution, bc.time, bc);
          resCandles.push(bc);
        }
        this.logger.log(`Hit save cache binance candles symbol=${symbol} resolution=${resolution} newOldestCachedTime=${binanceCandles?.[0].time}`);

        const cachedBinanceCandles: Candle[] = await this.getCachedBinanceCandles(symbol, resolution, oldestCachedTime, to);
        for (const bc of cachedBinanceCandles) {
          if (resCandles.findIndex(rc => rc.time === bc.time) !== -1) continue;
          resCandles.push(bc);
        }
        return resCandles;
      }
    }

    // if 'from' is before timeline and 'to' is after timeline
    if (
      moment(from).isBefore(timeline) &&
      moment(to).isAfter(timeline)
    ) {
      // if 'from' is after oldestCachedTime
      if (moment(from).isSame(oldestCachedTime) ||
          moment(from).isAfter(oldestCachedTime)) {
        const cachedBinanceCandles: Candle[] = await this.getCachedBinanceCandles(symbol, resolution, from, timeline.toDate().getTime());
        resCandles.push(...cachedBinanceCandles);
      }

      // if 'from' is before oldestCachedTime
      if (moment(from).isBefore(oldestCachedTime)) {
        const binanceCandles: Candle[] = await this.getCandlesFromBinance(symbol, from, oldestCachedTime, resolution);
        for (const bc of binanceCandles) {
          await this.saveCachedBinanceCandle(symbol, resolution, bc.time, bc);
          resCandles.push(bc);
        }
        this.logger.log(`Hit save cache binance candles symbol=${symbol} resolution=${resolution} newOldestCachedTime=${binanceCandles?.[0].time}`);

        const cachedBinanceCandles: Candle[] = await this.getCachedBinanceCandles(symbol, resolution, oldestCachedTime, timeline.toDate().getTime());
        resCandles.push(...cachedBinanceCandles);
      }

      const candles: Candle[] = await this.getCandles(symbol, timeline.toDate().getTime(), to, resolution);
      resCandles.push(...candles);
      return resCandles;
    }

    return await this.getCandles(symbol, from, to, resolution);

    // // to < timeline, get data from binance
    // if (moment(to).isBefore(timeline)) {      
    //   return await this.getCandlesFromBinance(symbol, from, to, resolution);
    // }

    // // from < timeline && to >= timeline, get data from binance and db
    // if (moment(from).isBefore(timeline) && moment(to).isSameOrAfter(timeline)) {      
    //   const [binanceCandles, dbCandles] = await Promise.all([
    //     this.getCandlesFromBinance(symbol, from, timeline.subtract(1, "second").valueOf(), resolution),
    //     this.getCandles(symbol, timeline.valueOf(), to, resolution),
    //   ]);
    //   return [...binanceCandles, ...dbCandles];
    // }

    // // from >= timeline, get data from db
    // return await this.getCandles(symbol, from, to, resolution);
  }

  async getCachedBinanceCandles(
    symbol: string,
    resolution: string,
    from: number,
    to: number
  ): Promise<Candle[]> {
    // const client = this.redisService.getClient();
    const key = `${CONST.PREFIX_BINANCE_CACHE}:${symbol}:${resolution}`;
    
    const cachedCandles = await this.redisClient.getInstance().zrangebyscore(
      key,
      from,
      to,
    );

    const candles: Candle[] = [];
    for (const candle of cachedCandles) {
      candles.push(JSON.parse(candle));
    }

    return candles;
  }

  async saveCachedBinanceCandle(symbol: string, resolution: string, score: number, bc: Candle): Promise<void> {
    await this.redisClient.getInstance().zadd(
      `${CONST.PREFIX_BINANCE_CACHE}:${symbol}:${resolution}`,
      score,
      JSON.stringify(bc)
    );
    await this.redisClient.getInstance().expire(
      `${CONST.PREFIX_BINANCE_CACHE}:${symbol}:${resolution}`,
      CONST.BINANCE_CANDLE_TTL
    );
  }

  async getOldestCachedBinanceCandle(symbol: string, resolution: string): Promise<{ oldestCachedTime: number, oldestCachedBinanceCandle: Candle }> {
    // const client = this.redisService.getClient();
    let oldestCachedTime = null;
    let oldestCachedBinanceCandle = null;
    const key = `${CONST.PREFIX_BINANCE_CACHE}:${symbol}:${resolution}`;
    const firstCandle = await this.redisClient.getInstance().zrange(key, 0, 0, 'WITHSCORES');
    if (firstCandle && firstCandle.length > 0) {
      oldestCachedBinanceCandle = JSON.parse(firstCandle[0]);
      oldestCachedTime = parseInt(firstCandle[1]);
    }
    return { oldestCachedTime, oldestCachedBinanceCandle };
  }

  async getCandlesFromBinance(
    symbol: string,
    from: number,
    to: number,
    resolution: string
  ): Promise<Candle[]> {
    resolution = resolution === '7D' ? '1W' : resolution === '30D' ? '1M' : resolution
    const binanceResolutionMap = {
      "1": "1m",
      "3": "3m",
      "5": "5m",
      "15": "15m",
      "30": "30m",
      "60": "1h",
      "120": "2h",
      "240": "4h",
      "360": "6h",
      "720": "12h",
      "1D": "1d",
      "1W": "1w",
      "1M": "1M",
    };

    const convertedResolution = binanceResolutionMap[resolution] || "1h";
    const results = [];
    let response = null;
    let count = 0;
    try {
      let newFrom = from;
      do {
        // Send a GET request to Kakao API's user info endpoint with the provided access token.
        response = await axios.get(
          symbol.includes("USDM") 
          ? `https://dapi.binance.com/dapi/v1/klines?symbol=${symbol.replace("USDM", "USD_PERP")}&interval=${convertedResolution}&startTime=${newFrom}&endTime=${to}`
          : `https://fapi.binance.com/fapi/v1/klines?symbol=${symbol}&interval=${convertedResolution}&startTime=${newFrom}&endTime=${to}`
        );
        // console.log(`count ${count}: response.data: ${JSON.stringify(response.data)}`);
        
        results.push(...response.data);
        newFrom = results[results.length - 1][0];
        count++
      } while (response.data.length == 500 && newFrom < to);
    } catch (e) {
      // If an error occurs during the user info retrieval process, log the error and throw an exception.
      Logger.error(`Error: ${e}`);
      return [];
    }

    if (!results || results?.length === 0) return [];

    const candles: Candle[] = [];
    for (const result of results) {
      candles.push({
        time: result[0],
        open: result[1],
        high: result[2],
        low: result[3],
        close: result[4],
        volume: result[7],
      });
    }
    return candles;
  }

  async getCandlesData(
    symbol: string,
    from: number,
    to: number,
    resolution: number
  ): Promise<CandlesEntity[]> {
    from = this.getMinute(from);
    to = this.getMinute(to);

    const resolutionInSeconds = resolution / 1000;
    let queryResolution = RESOLUTION_MINUTE;
    for (const supportedResolution of this.resolutions) {
      if (resolutionInSeconds > supportedResolution) {
        queryResolution = supportedResolution;
      }
    }

    return await this.candleRepositoryReport.find({
      select: [
        "symbol",
        "low",
        "high",
        "open",
        "close",
        "volume",
        "minute",
        "lastTradeTime",
        "createdAt",
      ],
      where: {
        symbol: Equal(symbol),
        resolution: queryResolution,
        minute: Between(from, to),
      },
      order: {
        minute: "ASC",
      },
    });
  }

  async syncCandles(): Promise<void> {
    //const instruments = await this.instrumentService.getAllInstruments();
    //const symbols = instruments.map((instrument) => instrument.symbol);
    // loop this one
    const instruments = await this.instrumentService.getAllInstruments();
    const symbols = instruments.map((instrument) => instrument.symbol);
    while (true) {
      try {
        console.log("-----------------------------------------");
        console.log(symbols);
        await this.storeCandle(symbols);
        await this.setLastUpdate();
        // ttl 2 minutes
        await this.cacheService.set(KEY_CACHE_HEALTHCHECK_SYNC_CANDLE, true, {
          ttl: 60 + 60,
        });

        await new Promise((resolve) => setTimeout(resolve, 60000));
      } catch (e) {
        console.log(e);
        await new Promise((resolve) => setTimeout(resolve, 10000)); // Wait 10s before retry if error
      }
    }
  }

  public async setLastUpdate(): Promise<void> {
    await this.redisService
      .getClient()
      .set(`candle_sync_last_update`, Date.now());
  }

  public async getLastUpdate(): Promise<number | undefined> {
    const value = await this.redisService
      .getClient()
      .get(`candle_sync_last_update`);
    return value ? Number(value) : 0;
  }

  async syncTrades(): Promise<void> {
    const consumer = kafka.consumer({ groupId: KafkaGroups.candles });
    await consumer.connect();
    await consumer.subscribe({ topic: KafkaTopics.matching_engine_output });
    await consumer.run({
      // consider eachBatch
      eachMessage: async ({ message }) => {
        // console.log(message.value.toString());
        let data = JSON.parse("{}");
        try {
          // console.log(message.value.toString());
          data = JSON.parse(message.value.toString());
        } catch {
          console.log("invalid data");
          return;
        }

        await this.handleMessage(data);
      },
    });

    return new Promise(() => {});
  }

  private async saveExtraResolutions(candleData: CandleData): Promise<void> {
    for (const resolution of this.resolutions) {
      await this.saveCandleInResolution(candleData, resolution);
    }
  }

  private async saveCandleInResolution(
    candleData: CandleData,
    resolution: number
  ): Promise<void> {
    const candleTime = candleData.minute - (candleData.minute % resolution);
    const lastCandleBefore = await this.candleRepositoryReport.getLastCandleBefore(
      candleData.symbol,
      candleTime
    );
    const candles = await this.candleRepositoryReport.getCandlesInRange(
      candleData.symbol,
      candleTime,
      resolution
    );

    const lastCandleOfResolution = await this.candleRepositoryReport.getLastCandleOfResolution(
      candleData.symbol,
      resolution
    );

    if (lastCandleOfResolution) {
      for (
        let time = lastCandleOfResolution.minute + resolution;
        time < candleTime;
        time += resolution
      ) {
        await this.candleRepositoryMaster.save({
          symbol: candleData.symbol,
          minute: time,
          resolution: resolution,
          low: lastCandleOfResolution.close,
          high: lastCandleOfResolution.close,
          open: lastCandleOfResolution.close,
          close: lastCandleOfResolution.close,
          lastTradeTime: lastCandleOfResolution.lastTradeTime,
          volume: "0",
        });
      }
    }

    const open = this.getOpenPrice(lastCandleBefore, candles);
    const close = this.getClosePrice(lastCandleBefore, candles);
    const { low, high, volume } = this.combineCandlesData(
      lastCandleBefore,
      candles
    );

    if (lastCandleOfResolution?.minute === candleTime) {
      await this.candleRepositoryMaster.update(
        {
          symbol: candleData.symbol,
          minute: candleTime,
          resolution,
        },
        {
          low,
          high,
          open,
          close,
          volume,
        }
      );
    } else {
      await this.candleRepositoryMaster.save({
        symbol: candleData.symbol,
        minute: candleTime,
        resolution,
        low,
        high,
        open,
        close,
        lastTradeTime: 0,
        volume,
      });
    }
  }

  private getOpenPrice(
    lastCandleBefore: CandleData,
    candles: CandleData[]
  ): string {
    if (lastCandleBefore) {
      return lastCandleBefore.close;
    } else {
      if (candles.length > 0) {
        return candles[0].open;
      }
    }
    return "0";
  }

  private getClosePrice(
    lastCandleBefore: CandleData,
    candles: CandleData[]
  ): string {
    if (candles.length > 0) {
      return candles[candles.length - 1].close;
    } else {
      if (lastCandleBefore) {
        return lastCandleBefore.close;
      }
    }
    return "0";
  }

  private combineCandlesData(
    lastCandleData: CandleData,
    candles: CandleData[]
  ): { low: string; high: string; volume: string } {
    if (!lastCandleData && candles.length === 0) {
      return { low: "0", high: "0", volume: "0" };
    }

    let low = Number.MAX_SAFE_INTEGER.toString();
    let high = "0";
    let volume = "0";
    for (const candle of candles) {
      low = BigNumber.min(low, candle.low).toString();
      high = BigNumber.max(high, candle.high).toString();
      volume = new BigNumber(volume).plus(candle.volume).toString();
    }
    if (lastCandleData) {
      low = BigNumber.min(low, lastCandleData.close).toString();
      high = BigNumber.max(high, lastCandleData.close).toString();
    }
    return { low, high, volume };
  }

  private async getLastCandle(
    symbol: string,
    resolution: number
  ): Promise<CandleData> {
    const lastCandle = await this.cacheService.get<CandleData>(
      this.getLastCandleKey(symbol)
    );
    if (!lastCandle) {
      return this.getLastCandleFromDatabase(symbol, resolution);
    }

    return lastCandle;
  }

  private async getLastCandleFromDatabase(
    symbol: string,
    resolution: number
  ): Promise<CandleData> {
    const lastCandle = await this.candleRepositoryReport.findOne({
      where: { symbol, resolution },
      order: { minute: "DESC" },
    });

    if (lastCandle) {
      return lastCandle;
    } else {
      const [tickers] = await Promise.all([
        this.cacheService.get<Ticker[]>(TICKERS_KEY),
      ]);
      const lastPriceFromIndex = await this.cacheService.get<string>(
        `${LAST_PRICE_PREFIX}${symbol}`
      );
      let lastPrice =
        tickers.find((item) => item.symbol == symbol)?.lastPrice || 0;
      if (lastPriceFromIndex) {
        lastPrice = lastPriceFromIndex;
      }
      const candle = {
        symbol: "",
        minute: 0,
        resolution: RESOLUTION_MINUTE,
        low: new BigNumber(lastPrice).toString(),
        high: new BigNumber(lastPrice).toString(),
        open: new BigNumber(lastPrice).toString(),
        close: new BigNumber(lastPrice).toString(),
        volume: "0",
        lastTradeTime: 0,
      };
      return { ...candle, symbol };
    }
  }

  private getCandleKey(symbol: string, minute: number): string {
    return `${CONST.PREFIX_CACHE}${symbol}${String(minute)}`;
  }

  private getLastCandleKey(symbol: string): string {
    return `${CONST.PREFIX_CACHE}${symbol}latest`;
  }

  private async _getCandles(
    symbol: string,
    from: number,
    to: number,
    resolution: number
  ): Promise<Candle[]> {
    let rows: CandleData[] = await this.getCandlesData(
      symbol,
      from,
      to,
      resolution
    );

    // Candles in database maybe behind candles in cache (maximum 2 minutes)
    rows = await this.addCandlesFromCache(rows, symbol, from, to);

    let candles: Candle[] = [];

    if (rows.length > 0) {
      let currentCandle = this.getCandleFromEntity(rows.shift(), resolution);
      for (const row of rows) {
        const candleTime = this.getCandleTime(row.minute, resolution);
        if (candleTime === currentCandle.time) {
          currentCandle.low = Math.min(currentCandle.low, Number(row.low));
          currentCandle.high = Math.max(currentCandle.high, Number(row.high));
          currentCandle.close = Number(row.close);
          currentCandle.volume = currentCandle.volume + Number(row.volume);
        } else {
          candles.push(currentCandle);
          currentCandle = this.getCandleFromEntity(row, resolution);
        }
      }
      candles.push(currentCandle);
    }

    candles = await this.addMissingHeadCandles(
      from,
      to,
      resolution,
      candles,
      symbol
    );
    candles = this.addMissingTailCandles(from, to, resolution, candles);

    return candles;
  }

  private getCandleTime(minute: number, resolution: number): number {
    const timeInMilliSeconds = Number(minute) * 1000;
    return this.standardizeCandleTime(timeInMilliSeconds, resolution);
  }

  private standardizeCandleTime(time: number, resolution: number): number {
    let res = time - (time % resolution);
    if (resolution === this.resolutionMap['7D']) {
      res += this.resolutionMap['3D'] + this.resolutionMap['1D'] + resolution;
    }
    return res;
  }

  private async addCandlesFromCache(
    rows: CandleData[],
    symbol: string,
    from: number,
    to: number
  ): Promise<CandleData[]> {
    const lastCandle = await this.cacheService.get<CandleData>(
      this.getLastCandleKey(symbol)
    );
    let previousCandle;
    if (lastCandle) {
      previousCandle = await this.cacheService.get<CandleData>(
        this.getCandleKey(symbol, lastCandle.minute - 60)
      );
    }

    rows = this.addCandleFromCache(rows, from, to, previousCandle);
    rows = this.addCandleFromCache(rows, from, to, lastCandle);
    return rows;
  }

  private addCandleFromCache(
    rows: CandleData[],
    from: number,
    to: number,
    cachedCandle: CandleData
  ): CandleData[] {
    if (!cachedCandle) {
      return rows;
    }
    if (cachedCandle.minute > from / 1000 && cachedCandle.minute < to / 1000) {
      if (rows.length === 0) {
        rows.push(cachedCandle);
      } else if (cachedCandle.minute > rows[rows.length - 1].minute) {
        const lastClose = rows[rows.length - 1].close;
        const startTime = rows[rows.length - 1].minute + 60;
        for (let i = startTime; i < cachedCandle.minute; i += 60) {
          rows.push({
            symbol: "",
            open: lastClose,
            close: lastClose,
            low: lastClose,
            high: lastClose,
            volume: "0",
            minute: i,
            resolution: RESOLUTION_MINUTE,
            lastTradeTime: 0,
          });
        }
        rows.push(cachedCandle);
      }
    }
    return rows;
  }

  private getCandleFromEntity(entity: CandleData, resolution: number): Candle {
    return {
      open: parseFloat(entity.open),
      close: parseFloat(entity.close),
      low: parseFloat(entity.low),
      high: parseFloat(entity.high),
      volume: parseFloat(entity.volume),
      time: this.getCandleTime(entity.minute, resolution),
    };
  }

  private async addMissingHeadCandles(
    from: number,
    to: number,
    resolution: number,
    candles: Candle[],
    symbol?: string
  ): Promise<Candle[]> {
    const startTime = from;
    const endTime = candles.length > 0 ? candles[0].time - resolution : to;
    const missingCandles: Candle[] = [];
    const [tickers] = await Promise.all([
      this.cacheService.get<Ticker[]>(TICKERS_KEY),
    ]);
    const lastPriceFromIndex = await this.cacheService.get<string>(
      `${LAST_PRICE_PREFIX}${symbol}`
    );
    let lastPrice =
      tickers?.find((item) => item.symbol == symbol)?.lastPrice || 0;
    if (lastPriceFromIndex) {
      lastPrice = lastPriceFromIndex;
    }
    for (let i = startTime; i <= endTime; i += resolution) {
      missingCandles.push({
        open: new BigNumber(lastPrice).toNumber(),
        close: new BigNumber(lastPrice).toNumber(),
        low: new BigNumber(lastPrice).toNumber(),
        high: new BigNumber(lastPrice).toNumber(),
        volume: 0,
        time: i,
      });
    }
    if (missingCandles.length > 0 && candles.length > 0) {
      candles[0].open = missingCandles[missingCandles.length - 1].close;
    }
    candles = missingCandles.concat(candles);
    return candles;
  }

  private addMissingTailCandles(
    from: number,
    to: number,
    resolution: number,
    candles: Candle[]
  ): Candle[] {
    const lastClose =
      candles.length > 0 ? candles[candles.length - 1].close : 0;
    const startTime =
      candles.length > 0 ? candles[candles.length - 1].time + resolution : from;
    const endTime = to;
    const missingCandles = [];
    for (let i = startTime; i <= endTime; i += resolution) {
      missingCandles.push({
        open: lastClose,
        close: lastClose,
        low: lastClose,
        high: lastClose,
        volume: 0,
        time: i,
      });
    }
    candles = candles.concat(missingCandles);

    return candles;
  }

  private getRandomVolumeBySymbol(symbol: string, minute: string): number {
    const COIN_VOLUME = {
      BTCUSDT: {
        from: 150000,
        to: 400000,
      },
      ETHUSDT: {
        from: 50000,
        to: 120000,
      },
      SOLUSDT: {
        from: 150000,
        to: 300000,
      },
      BNBUSDT: {
        from: 10000,
        to: 20000,
      },
      XRPUSDT: {
        from: 30000,
        to: 70000,
      },
      DOGEUSDT: {
        from: 20000,
        to: 50000,
      },
      LINKUSDT: {
        from: 15000,
        to: 30000,
      },
      "1000SHIBUSDT": {
        from: 5000,
        to: 10000,
      },
      "1000PEPEUSDT": {
        from: 3000,
        to: 5000,
      },
      TRUMPUSDT: {
        from: 10000,
        to: 20000,
      },
      RENDERUSDT: {
        from: 2000,
        to: 5000,
      },
      ONDOUSDT: {
        from: 5000,
        to: 10000,
      },
      ADAUSDT: {
        from: 15000,
        to: 35000,
      },
      TRXUSDT: {
        from: 3000,
        to: 5000,
      },
      SUIUSDT: {
        from: 7000,
        to: 15000,
      },
      AVAXUSDT: {
        from: 7000,
        to: 15000,
      },
      XLMUSDT: {
        from: 1500,
        to: 3000,
      },
      TONUSDT: {
        from: 1000,
        to: 2000,
      },
      HBARUSDT: {
        from: 2000,
        to: 4000,
      },
    };
    const coinVolume = COIN_VOLUME[symbol];
    
    const RANDOM_MINUTE = {
      "1": {
        from: 1,
        to: 1
      },
      "15": {
        from: 5, 
        to: 15
      },
      "60": {
        from: 30,
        to: 60
      },
      "3": {
        from: 1,
        to: 3
      },
      "5": {
        from: 2,
        to: 5
      },
      "30": {
        from: 15,
        to: 30
      },
      "120": {
        from: 60,
        to: 120
      },
      "240": {
        from: 120,
        to: 240
      },
      "360": {
        from: 180,
        to: 360
      },
      "720": {
        from: 360,
        to: 720
      },
      "1D": {
        from: 720,
        to: 1440
      },
      "1W": {
        from: 6000,
        to: 10080
      },
      "1M": {
        from: 25000,
        to: 43829
      },
    }

    return this.getRandomNumber(coinVolume) * this.getRandomNumber(RANDOM_MINUTE[minute]);
  }

  private getRandomNumber(data: { from: number; to: number }): number {
    let { from, to } = data;
    if (from > to) {
      [from, to] = [to, from]; // swap to ensure valid range
    }
    const random = Math.floor(Math.random() * (to - from + 1)) + from;
    return random;
  }

  public async replaceBinanceCandles(data: { symbol: string, fromTimeStr: string, toTimeStr: string, resolution: number }) {
    const { symbol, fromTimeStr, toTimeStr, resolution } = data;

    if (!fromTimeStr || !toTimeStr || !resolution) {
      return 'Pls input fromTimeStr, toTimeStr, resolution';
    }

    let symbols = [symbol];
    if (!symbol) {
      const instruments = await this.instrumentService.getAllInstruments();
      symbols = instruments.filter((instrument) => instrument.symbol.includes('USDT')).map((instrument) => instrument.symbol);
    }
    console.log(`Symbols will be replace candles: ${symbols}`);
    
    await Promise.all(
      symbols.map(async (symbol) => {
        const fromTime = new Date(fromTimeStr).getTime()
        const toTime = new Date(toTimeStr).getTime()
        const results = await this.getRecordsFromBinance(fromTime, toTime, `${resolution}`, symbol);

        for (const result of results) {
          const candleData = {
            symbol: symbol,
            minute: result.time / 1000,
            resolution: resolution * 60,
            low: result.low,
            high: result.high,
            open: result.open,
            close: result.close,
            lastTradeTime: result.closeTime,
            volume: new BigNumber(result.volume).dividedBy(40).toString(),
            createdAt: new Date(),
            updatedAt: new Date(),
          };
          try {
            // find it first, if not exist, save it
            const candleExist = await this.candleRepositoryMaster.findOne({ where: {
              symbol: candleData.symbol,
              resolution: candleData.resolution,
              minute: candleData.minute
            }})
            
            if (candleExist) {
              console.log('candleExist: ', JSON.stringify(candleExist));
              Object.assign(candleExist, candleData);
              await this.candleRepositoryMaster.save(candleExist);
            }
          } catch (e) {
            console.log(e);
          }
        }
      }),
    );

    return 'ok!'
  }
  
  static totalRequest = 0;
  private async getRecordsFromBinance(fromTime: number, toTime: number, resolution: string, symbol: string) {
    const binanceResolutionMap = {
      "1": "1m",
      "3": "3m",
      "5": "5m",
      "15": "15m",
      "30": "30m",
      "60": "1h",
      "120": "2h",
      "240": "4h",
      "360": "6h",
      "720": "12h",
      "1D": "1d",
      "1W": "1w",
      "1M": "1M",
    };
    const convertedResolution = binanceResolutionMap[resolution];
    const results = [];
    let newFrom = fromTime;
    let response = null;
    do {
      console.log(CandleService.totalRequest, ': candleservice.totalRequest');
      
      if (CandleService.totalRequest >= 240) {
        console.log("Hit the total request. Waiting for 5 second...");
        await new Promise((resolve) => setTimeout(resolve, 5000));
        CandleService.totalRequest = 0;
      }
      console.log(`Requesting data for symbol ${symbol} from ${newFrom} to ${toTime}`);
      
      response = await axios.get(
        `https://fapi.binance.com/fapi/v1/klines?symbol=${symbol}&interval=${convertedResolution}&startTime=${newFrom}&endTime=${toTime}&limit=1500`
      );
      results.push(...response.data);
      newFrom = results[results.length - 1][0];
      await new Promise((resolve) => setTimeout(resolve, 5000));
      CandleService.totalRequest++;
      console.log(`length: ${response.data.length}`);
      
    } while (response.data.length === 1500 && newFrom < toTime);

    const candles: any[] = [];
    for (const result of results) {
      candles.push({
        time: result[0],
        open: result[1],
        high: result[2],
        low: result[3],
        close: result[4],
        volume: result[7],
        closeTime: Math.floor(result[6] /1000),
      });
    }
    console.log(`Got ${candles.length} candles for symbol ${symbol} from ${fromTime} to ${toTime}`);
    
    return candles;
  }

  async getCandlesFromCacheV2(symbol: string, from: number, to: number, resolution: string): Promise<Candle[]> {
    // 1. Load from Redis
    const cached = await this.getCachedBinanceCandles(symbol, resolution, from, to);

    // If none cached → fetch everything
    if (cached.length === 0) {
      const fresh = await this.fetchAndCache(symbol, resolution, from, to, true);
      return fresh;
    }

    // 2. Find cached min/max
    const cachedFrom = cached[0].time;
    const cachedTo = cached[cached.length - 1].time;

    const missingRanges: { start: number; end: number, isRightRange?: boolean }[] = [];

    // 3. Detect missing left range
    if (from < cachedFrom) {
      missingRanges.push({ start: from, end: cachedFrom - 1 });
    }

    // 4. Detect missing right range
    if (to > cachedTo) {
      missingRanges.push({ start: cachedTo + 1, end: to, isRightRange: true });
    }

    // 5. Fetch each missing range
    const freshList: Candle[][] = [];

    for (const r of missingRanges) {
      const fresh = await this.fetchAndCache(symbol, resolution, r.start, r.end, r.isRightRange);
      freshList.push(fresh);
    }

    // 6. Merge all
    const merged = [...cached];
    for (const arr of freshList) {
      merged.push(...arr);
    }

    // 7. Sort by openTime (important)
    merged.sort((a, b) => a.time - b.time);

    return merged;
  }

  async fetchAndCache(symbol: string, resolution: string, from: number, to: number, isRightRange?: boolean): Promise<Candle[]> {
    const fresh = await this.getCandlesFromBinance(symbol, from, to, resolution);
    // console.log(`fresh: ${JSON.stringify(fresh)}`);

    const freshLength = isRightRange ? fresh.length - 1 : fresh.length;
    for (let i = 0; i < freshLength; i++) {
      await this.saveCachedBinanceCandle(symbol, resolution, fresh[i].time, fresh[i]);
    }

    return fresh;
  }
}

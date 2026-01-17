import { CACHE_MANAGER, Inject, Injectable, Logger, forwardRef } from "@nestjs/common";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { Cache } from "cache-manager";
import { Command, Console } from "nestjs-console";
import { RedisService } from "nestjs-redis";
import { InstrumentEntity } from "src/models/entities/instrument.entity";
import { MetadataEntity } from "src/models/entities/metadata.entity";
import { MarketDataRepository } from "src/models/repositories/market-data.repository";
import { MarketIndexRepository } from "src/models/repositories/market-indices.repository";
import { MetadataRepository } from "src/models/repositories/metadata.repository";
import { FundingService } from "src/modules/funding/funding.service";
import {
  BaseMarketResponseDTO,
  MetadataCandleDTO,
  MetadataMarketDTO,
  MetadataWeightGroupDTO,
} from "src/modules/index/dto/index.dto";
import * as CONST from "src/modules/index/index.const";
import {
  INDEX_PRICE_PREFIX,
  ORACLE_PRICE_PREFIX,
  TICK_SIZE_DEFAULT,
} from "src/modules/index/index.const";
import { MarketCrawler } from "src/modules/index/markets/base";
import { Binance } from "src/modules/index/markets/base.binance";
import { OKX } from "src/modules/index/markets/base.okx";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { Orderbook } from "src/modules/orderbook/orderbook.const";
import { OrderbookService } from "src/modules/orderbook/orderbook.service";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { getRandomDeviateNumber } from "src/shares/helpers/utils";
import { HttpClient } from "src/shares/http-clients/https.client";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { Connection, In } from "typeorm";
import {
  FUNDING_INTERVAL,
  FUNDING_TTL,
  KEY_CACHE_HEALTHCHECK_GET_FUNDING,
} from "../funding/funding.const";
import { Ticker, TICKERS_KEY } from "../ticker/ticker.const";
import { LIST_SYMBOL_COINM } from "../transaction/transaction.const";
import { Huobi } from "./markets/base.huobi";
import { RedisClient } from "src/shares/redis-client/redis-client";

@Console()
@Injectable()
export class IndexService {
  private readonly logger = new Logger(IndexService.name);
  private metaMarket;
  private metaCandle;
  private metaWeight;
  private instrumentMap: { [key: string]: InstrumentEntity } = {};

  constructor(
    @InjectRepository(MarketDataRepository, "master")
    private marketDataRepositoryMaster: MarketDataRepository,
    @InjectRepository(MarketDataRepository, "report")
    private marketDataRepositoryReport: MarketDataRepository,
    @InjectRepository(MarketIndexRepository, "master")
    private marketIndexRepositoryMaster: MarketIndexRepository,
    @InjectRepository(MarketIndexRepository, "report")
    private marketIndexRepositoryReport: MarketIndexRepository,
    @InjectRepository(MetadataRepository, "master")
    private metaRepositoryMaster: MetadataRepository,
    @InjectRepository(MetadataRepository, "report")
    private metaRepositoryReport: MetadataRepository,
    @Inject(CACHE_MANAGER) private cacheService: Cache,
    private readonly redisService: RedisService,
    @InjectConnection("master") private connection: Connection,
    private readonly httpClient: HttpClient,
    private readonly kafkaClient: KafkaClient,
    private readonly instrumentService: InstrumentService,
    @Inject(forwardRef(() => FundingService))
    private readonly fundingService: FundingService,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly redisClient: RedisClient
  ) {}

  @Command({
    command: "start-get-index-price",
    description:
      "Start the job to get market prices,\
                   the job will start and loop infinite, \
                   only stop when system crash",
  })
  async syncMarketData(): Promise<void> {
    await this.loadMeta();
    await this.loadInstruments();

    // loop this one
    while (true) {
      // This function should execute get data from source by promise
      this.getCurrentFuturePrice();
      this.updateIndexPrice();
      // ttl 10 seconds
      await this.cacheManager.set(KEY_CACHE_HEALTHCHECK_GET_FUNDING, true, {
        ttl: 10,
      });
      // Let this sleep for about 5 seconds each run
      await new Promise((resolve) => setTimeout(resolve, 1000));
    }
  }

  getBase(market: string): MarketCrawler {
    let base: MarketCrawler;
    switch (String(market).toLowerCase().trim()) {
      case "binance":
        base = new Binance();
        break;
      case "huobi":
        base = new Huobi();
        break;
      case "okx":
        base = new OKX();
        break;
      default:
        break;
    }
    return base;
  }

  async getCurrentFuturePrice(): Promise<void> {
    for (const market in this.metaMarket) {
      const base = this.getBase(market);

      try {
        const requestUrls = base.createRequestStringForMarket(
          this.metaMarket[market]
        );
        const taskRequest = [];
        for (const index in requestUrls) {
          // Make it a promise
          taskRequest.push(
            this.requestIndexPrice(
              base,
              market,
              Number(index),
              requestUrls[index]
            )
          );
        }
        await Promise.all([...taskRequest]);
      } catch (error) {}
    }
  }

  async requestIndexPrice(
    base: MarketCrawler,
    market: string,
    index: number,
    requestUrl: string
  ): Promise<void> {
    try {
      const timeCanRequest = await this.redisService
        .getClient()
        .get(`${CONST.NEXT_TIME_MARKET}${market}`);
      const currentTime = new Date().getTime();
      if (
        timeCanRequest &&
        market !== "binance" &&
        currentTime <= Number(timeCanRequest)
      ) {
        return;
      }
      const resp = await this.httpClient.client.get<
        BaseMarketResponseDTO,
        BaseMarketResponseDTO
      >(requestUrl);
      const nextTime = new Date().getTime() + 2000;
      await this.redisService
        .getClient()
        .set(`${CONST.NEXT_TIME_MARKET}${market}`, nextTime, "EX", 5);
      const marketPrice = await base.transformResponseMaketIndex(resp);

      marketPrice.group = this.metaMarket[market].pairs[index].group;
      marketPrice.symbol = this.metaMarket[market].pairs[index].symbol;
      if (marketPrice.symbol.startsWith(`1000PEPEUSD`) || marketPrice.symbol.startsWith(`1000SHIBUSD`)) {
        marketPrice.index *= 1000;
        marketPrice.bid *= 1000;
        marketPrice.ask *= 1000;
      } 

      await Promise.all([
        this.marketDataRepositoryMaster.save({
          market: marketPrice.market,
          symbol: marketPrice.symbol,
          group: marketPrice.group,
          bid: String(marketPrice.bid),
          ask: String(marketPrice.ask),
          index: String(marketPrice.index),
        }),
        this.marketDataRepositoryMaster.save({
          market: marketPrice.market,
          symbol: marketPrice.symbol.slice(0, -1),
          group: marketPrice.group.slice(0, -1),
          bid: String(
            getRandomDeviateNumber(
              Number(marketPrice.bid),
              CONST.deviatePercentUSDTUSD[0],
              CONST.deviatePercentUSDTUSD[1]
            )
          ),
          ask: String(
            getRandomDeviateNumber(
              Number(marketPrice.ask),
              CONST.deviatePercentUSDTUSD[0],
              CONST.deviatePercentUSDTUSD[1]
            )
          ),
          index: String(
            getRandomDeviateNumber(
              Number(marketPrice.index),
              CONST.deviatePercentUSDTUSD[0],
              CONST.deviatePercentUSDTUSD[1]
            )
          ),
        }),
      ]);
      // save usd pairs
    } catch (err) {
      console.log(err);
      await this.redisService
        .getClient()
        .set(`${INDEX_PRICE_PREFIX}error`, "true", "EX", 120);
    }
  }

  async updateIndexPrice(): Promise<void> {
    const timeframe =
      process.env.TEST_INDEX_TIMEFRAME == undefined
        ? CONST.MetaCommon.timeframe
        : Number(process.env.TEST_INDEX_TIMEFRAME);
    const rows = await this.marketDataRepositoryReport.query(
      "select mdr.group, mdr.market, mdr.bid AS `index`, mdr.createdAt as createtime " +
        "from market_data mdr " +
        "inner join( select `group`, `market`, max(id) as latest from market_data group by `group`, `market`) t " +
        "on mdr.group = t.group and mdr.market = t.market and mdr.id = t.latest " +
        `where mdr.createdAt > '${new Date(
          Date.now() - timeframe * 1000
        ).toISOString()}' ` +
        "order by mdr.group, mdr.index"
    );
    // transform to array to calcualte out of bound and weighted indices
    const groups = {};
    for (const indexRow in rows) {
      const group = String(rows[indexRow].group).trim();
      if (!(group in groups)) {
        groups[group] = { market: [], index: [], weight: [], length: 0 };
      }

      groups[group].market.push(rows[indexRow].market);
      groups[group].index.push(rows[indexRow].index);
      groups[group].weight.push(this.metaWeight[group][rows[indexRow].market]);
      groups[group].length += 1;
    }

    const lastIndexInserted = [];
    let updatedIndex = 0;
    // Check outbound with median
    for (const group in groups) {
      if (group === "LTCUSDT") continue; // TODO:: Sheep: need to comment

      const coinGroup = groups[group];

      // calculate median
      let median: number;
      if (coinGroup.length % 2 === 0) {
        //length is even
        const firstPrice = coinGroup.index[Math.floor(coinGroup.length / 2)];
        const secondPrice =
          coinGroup.index[Math.floor(coinGroup.length / 2) - 1];
        median = new BigNumber(firstPrice).plus(secondPrice).div(2).toNumber();
      } else {
        // length is odd
        median = coinGroup.index[Math.floor(coinGroup.length / 2)];
      }

      let totalWeight = 0;
      let totalWeightedPrice = 0;
      /*
       * Single price source deviation: When the latest price of a certain exchange deviates more than 5% from the median price of all price sources, the exchange weight will be set to zero for weighting purposes.
       * Multi price source deviation: If more than one exchange shows greater than 5% deviation, the median price of all price sources will be used as the index value instead of the weighted average.
       */
      let totalExchangeOutOfBound = 0;
      for (let i = 0; i < coinGroup.length; i++) {
        // 5% is out of bound
        const currentIndexPrice = coinGroup.index[i];
        const diviationRatio = currentIndexPrice / median;
        if (diviationRatio <= 1.05 && diviationRatio >= 0.95) {
          totalWeightedPrice += coinGroup.weight[i] * currentIndexPrice;
          totalWeight += coinGroup.weight[i];
        } else {
          totalExchangeOutOfBound += 1;
          if (totalExchangeOutOfBound > 1) break;
          continue;
        }
      }

      let priceWithoutPrecision: number;
      if (totalExchangeOutOfBound === 0 || totalExchangeOutOfBound === 1) {
        priceWithoutPrecision = totalWeightedPrice / totalWeight;
      } else {
        priceWithoutPrecision = median;
      }

      console.log("++++++++++++++++++++++++++");
      console.log("priceWithoutPrecision ", priceWithoutPrecision);
      console.log("median ", median);
      console.log("symbol ", group);

      const instrument = this.instrumentMap[group];
      const tickSize = parseFloat(instrument?.tickSize)
        ? parseFloat(instrument?.tickSize)
        : TICK_SIZE_DEFAULT;
      const precision = -Math.ceil(Math.log10(tickSize));
      let price = priceWithoutPrecision.toFixed(precision);
      price = price === "NaN" ? '0' : price;

      const insertedEntity = await this.marketIndexRepositoryMaster.save({
        symbol: group,
        price,
      });
      const symbolCoinM = group.replace("USDT", "USDM");
      const isCoinM = LIST_SYMBOL_COINM.includes(symbolCoinM);
      if (isCoinM) {
        const insertedEntityCoinM = await this.marketIndexRepositoryMaster.save(
          {
            symbol: symbolCoinM,
            price,
          }
        );
        // await this.calculateOraclePrice(symbolCoinM, priceWithoutPrecision);
        lastIndexInserted.push(insertedEntityCoinM);
      }

      await this.calculateOraclePrice(group, priceWithoutPrecision, price);
      lastIndexInserted.push(insertedEntity);

      updatedIndex++;
    }
    await this.redisService
      .getClient()
      .set(
        `${INDEX_PRICE_PREFIX}last_inserted`,
        JSON.stringify(lastIndexInserted)
      );
    await this.redisService
      .getClient()
      .set(`${INDEX_PRICE_PREFIX}update_count`, updatedIndex);
    await this.redisService
      .getClient()
      .set(`${INDEX_PRICE_PREFIX}last_update`, Date.now());
  }

  public async getIndexPrices(symbols: string[]): Promise<string[]> {
    if (!symbols.length) {
      return [];
    }
    const keys = symbols.map((symbol) => `${INDEX_PRICE_PREFIX}${symbol}`);
    return await this.redisClient.getInstance().mget(keys);
  }

  public async getOraclePrices(symbols: string[]): Promise<string[]> {
    if (!symbols.length) {
      return [];
    }
    const keys = symbols.map((symbol) => `${ORACLE_PRICE_PREFIX}${symbol}`);
    return await this.redisClient.getInstance().mget(keys);
  }

  public async getLastUpdate(): Promise<number | undefined> {
    const lastUpdate = await this.redisService
      .getClient()
      .get(`${INDEX_PRICE_PREFIX}last_update`);
    return lastUpdate ? Number(lastUpdate) : 0;
  }

  public async getUpdateCount(): Promise<number | undefined> {
    const updateCount = await this.redisService
      .getClient()
      .get(`${INDEX_PRICE_PREFIX}update_count`);
    return updateCount ? Number(updateCount) : 0;
  }

  public async getUpdateError(): Promise<string | undefined> {
    return this.redisClient.getInstance().get(`${INDEX_PRICE_PREFIX}error`);
  }

  public async saveIndexPrice(symbol: string, price: string): Promise<void> {
    // const interval = (IndicesConfig.interval / 1000) * 2;
    const symbolUsdm = symbol.replace("USDT", "USDM");
    await Promise.all([
      this.redisService
        .getClient()
        .set(`${INDEX_PRICE_PREFIX}${symbol}`, price),
      this.redisService
        .getClient()
        .set(`${INDEX_PRICE_PREFIX}${symbolUsdm}`, price),
    ]);
  }

  public async saveOraclePrice(symbol: string, price: string): Promise<void> {
    // const interval = (IndicesConfig.interval / 1000) * 2;
    const symbolUsdm = symbol.replace("USDT", "USDM");
    await Promise.all([
      this.redisService
        .getClient()
        .set(`${ORACLE_PRICE_PREFIX}${symbolUsdm}`, price),
      this.redisService
        .getClient()
        .set(`${ORACLE_PRICE_PREFIX}${symbol}`, price),
    ]);
  }

  async getMetaCandle(): Promise<MetadataCandleDTO> {
    const data: MetadataEntity = await this.metaRepositoryReport.findOne({
      name: "MetaCandle",
    });
    return data == undefined || data.data == undefined
      ? undefined
      : JSON.parse(data.data);
  }

  async getMetaWeight(): Promise<MetadataWeightGroupDTO> {
    const data: MetadataEntity = await this.metaRepositoryReport.findOne({
      name: "MetaWeight",
    });
    return data == undefined || data.data == undefined
      ? undefined
      : JSON.parse(data.data);
  }

  async getMetaMarket(): Promise<MetadataMarketDTO> {
    const data: MetadataEntity = await this.metaRepositoryReport.findOne({
      name: "MetaMarket",
    });
    return data == undefined || data.data == undefined
      ? undefined
      : JSON.parse(data.data);
  }

  async initMeta(name: string, data: string): Promise<void> {
    await this.metaRepositoryMaster.save({ name: name, data: data });
  }

  async _updateMeta(name: string, data: string): Promise<void> {
    await this.metaRepositoryMaster.update({ name: name }, { data: data });
  }

  @Command({
    command: "start-update-meta",
    description: "Start the job to update metadata for crawling markets",
  })
  async updateMeta(): Promise<void> {
    await this._updateMeta("MetaMarket", JSON.stringify(CONST.MetaMarket));
    await this._updateMeta("MetaCandle", JSON.stringify(CONST.MetaCandle));
    await this._updateMeta("MetaWeight", JSON.stringify(CONST.MetaWeightGroup));
  }

  async loadMeta(): Promise<void> {
    this.metaMarket = await this.getMetaMarket();
    this.metaCandle = await this.getMetaCandle();
    this.metaWeight = await this.getMetaWeight();

    if (this.metaMarket == undefined) {
      this.metaMarket = CONST.MetaMarket;
      await this.initMeta("MetaMarket", JSON.stringify(this.metaMarket));
    }
    if (this.metaCandle == undefined) {
      this.metaCandle = CONST.MetaCandle;
      await this.initMeta("MetaCandle", JSON.stringify(this.metaCandle));
    }
    if (this.metaWeight == undefined) {
      this.metaWeight = CONST.MetaWeightGroup;
      await this.initMeta("MetaWeight", JSON.stringify(this.metaWeight));
    }
  }

  private async loadInstruments(): Promise<void> {
    const instruments = await this.instrumentService.find();
    for (const instrument of instruments) {
      this.instrumentMap[instrument.symbol] = instrument;
    }
  }

  private async getMovingAveragePrice(
    symbol: string,
    indexPrice: number
  ): Promise<number> {
    /*
     *   Price 2 = Price Index + Moving Average (30-minute Basis)*
     *   *Moving Average (30-minute Basis) = Moving Average ((Bid1+Ask1)/2- Price Index), which measures every minute in a 30-minute interval
     */
    let time = Math.floor(new Date().getTime() / 1000);
    time = time - (time % 60);
    let total = 0;
    let count = 0;
    let previousOrderbook = await this.cacheService.get<Orderbook>(
      OrderbookService.getOrderbookKey(symbol)
    );
    for (let i = 0; i < 30; i++) {
      let difference;
      let orderbook = await this.cacheService.get<Orderbook>(
        `${OrderbookService.getOrderbookKey(symbol)}${time - 60 * i}`
      );
      if (!orderbook) {
        orderbook = previousOrderbook;
      } else {
        previousOrderbook = orderbook;
      }
      if (orderbook?.bids.length == 0 && orderbook?.asks.length == 0) continue;

      if (orderbook?.bids.length > 0 && orderbook?.asks.length == 0) {
        difference = Number(orderbook?.bids[0][0]) - indexPrice;
      } else if (orderbook?.bids.length == 0 && orderbook?.asks.length > 0) {
        difference = Number(orderbook?.asks[0][0]) - indexPrice;
      } else {
        difference =
          (Number(orderbook?.asks[0][0]) + Number(orderbook?.bids[0][0])) / 2 -
          indexPrice;
      }

      total += difference;
      count += 1;
    }
    if (count == 0) {
      return indexPrice;
    }

    return indexPrice + total / count;
  }

  private async calculateOraclePrice(
    symbol: string,
    indexPrice: number,
    price: string
  ): Promise<void> {
    /**
     * refer to: https://www.binance.com/en/support/faq/360033525071
     */

    let price1;
    const fundingRateWithPercent = await this.fundingService.getFundingRates([
      symbol,
    ]);
    if (
      fundingRateWithPercent == undefined ||
      fundingRateWithPercent.length == 0
    ) {
      price1 = indexPrice;
    } else {
      // const nextFunding = await this.redisService.getClient().get(`${FUNDING_PREFIX}next_funding`);
      const nextFunding = await this.fundingService.getNextFunding(symbol);

      const remainingHour = Number(nextFunding) - Date.now();
      const fundingRate = Number(fundingRateWithPercent[0]) / 100;
      // calculate price1 in here
      price1 =
        indexPrice * (1 + (fundingRate * remainingHour) / FUNDING_INTERVAL);

      console.log("................................................");
      console.log("symbol ", symbol);
      console.log("indexPrice ", indexPrice);
      console.log("fundingRate ", fundingRate);
      console.log("remainingHour ", remainingHour);
      console.log("FUNDING_TTL ", FUNDING_TTL);
      console.log("price1 ", price1);
    }

    const price2 = await this.getMovingAveragePrice(symbol, indexPrice);

    const instrument = this.instrumentMap[symbol];
    const tickSize = parseFloat(instrument?.tickSize)
      ? parseFloat(instrument?.tickSize)
      : TICK_SIZE_DEFAULT;
    const precision = -Math.ceil(Math.log10(tickSize));
    let roundedIndexPrice = indexPrice.toFixed(precision);
    // const oraclePrice = price1.toFixed(precision);
    const tickers = await this.cacheService.get<Ticker[]>(TICKERS_KEY);
    const ticker = tickers?.find((ticker) => ticker.symbol === symbol);
    const lastPrice = ticker?.lastPrice ?? null;

    let medians: number[] = [price1];
    if (price2 != null && !isNaN(price2)) {
      medians.push(price2);
    }
    if (lastPrice != null) {
      medians.push(Number(lastPrice));
    }
    console.log(`Oracle candidates: ${[symbol, ...medians]}`);
    medians.sort(function (a, b) {
      return a - b;
    });
    let oraclePrice =
      medians.length > 1
        ? medians[1].toFixed(precision)
        : medians[0].toFixed(precision);

    await Promise.all([
      this.saveIndexPrice(symbol, price),
      this.saveOraclePrice(symbol, oraclePrice.toString()),
    ]);

    console.log("----------------------------------------");
    console.log(symbol);

    oraclePrice = oraclePrice === "NaN" ? null : oraclePrice
    roundedIndexPrice = roundedIndexPrice === "NaN" ? null : roundedIndexPrice

    const data = {
      code: CommandCode.LIQUIDATE,
      data: { symbol, oraclePrice, indexPrice: roundedIndexPrice, lastPrice },
    };

    const symbolUsdm = symbol.replace("USDT", "USDM");

    const dataUsdm = {
      code: CommandCode.LIQUIDATE,
      data: {
        symbol: symbolUsdm,
        oraclePrice,
        indexPrice: roundedIndexPrice,
        lastPrice,
      },
    };

    this.logger.log(`Sending data to matching engine ${JSON.stringify(data)}`);

    await this.kafkaClient.send(KafkaTopics.matching_engine_input, data);
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, dataUsdm);
  }

  async fakeMarkPrice(oraclePrice, symbol, isTesting?: boolean) {
    const data = {
      code: CommandCode.LIQUIDATE,
      data: { symbol, oraclePrice, indexPrice: oraclePrice },
    };

    await this.saveOraclePriceBySymbol(symbol, oraclePrice.toString(), isTesting);

    this.logger.log(`Sending data to matching engine ${JSON.stringify(data)}`);

    await this.kafkaClient.sendPrice(KafkaTopics.matching_engine_input, data);
    return "success";
  }

  public async saveOraclePriceBySymbol(
    symbol: string,
    price: string,
    isTesting?: boolean
  ): Promise<void> {
    await this.redisService
      .getClient()
      .set(`${ORACLE_PRICE_PREFIX}${symbol}`, price);

    if (isTesting) {
      if (symbol === "LTCUSDT") {
        await this.redisService
          .getClient()
          .set(`${INDEX_PRICE_PREFIX}${symbol}`, price);
      }
    }
  }

  @Command({
    command: "cron-job:remove-market-data",
    description: "Remove market data older than 5 hours",
  })
  async removeMarketDataJob(): Promise<void> {
    while (true) {
      try {
        // Calculate timestamp for 5 hours ago
        const timestamp = new Date(Date.now() - 5 * 60 * 60 * 1000);
        // const fiveHoursAgo = new Date(Date.now() - 5 * 1000);
        
        // Get 500 records at a time that are older than 5 hours
        const records = await this.marketDataRepositoryMaster
          .createQueryBuilder('marketData')
          .where('marketData.createdAt <= :timestamp', { timestamp })
          .orderBy('marketData.id', 'ASC')
          .limit(500)
          .select(['marketData.id'])
          .getMany();

        if (records.length === 0) {
          this.logger.log('No more records to delete, wait to next turn ...');
          await new Promise(resolve => setTimeout(resolve, 2 * 60 * 1000));
          continue;
        }

        // Delete the records
        const recordIds = records.map(r => r.id);
        await this.marketDataRepositoryMaster.delete({ id: In(recordIds) });
        this.logger.log(`Deleted ${records.length} records`);
      } catch (error) {
        this.logger.error('Error removing market data:', error);
        // Wait 2 minutes before retrying on error
        await new Promise(resolve => setTimeout(resolve, 2 * 60 * 1000));
      }
    }
  }

  @Command({
    command: "cron-job:remove-market-indices",
    description: "Remove market indices older than 1 week",
  })
  async removeMarketIndicesJob(): Promise<void> {
    while (true) {
      try {
        // Calculate timestamp for 1 week ago
        const timestamp = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        
        // Get 500 records at a time that are older than 1 week
        const records = await this.marketIndexRepositoryMaster
          .createQueryBuilder('marketIndex')
          .where('marketIndex.createdAt <= :timestamp', { timestamp })
          .orderBy('marketIndex.id', 'ASC')
          .limit(500)
          .select(['marketIndex.id'])
          .getMany();

        if (records.length === 0) {
          this.logger.log('No more market indices to delete, wait to next turn ...');
          await new Promise(resolve => setTimeout(resolve, 2 * 60 * 1000));
          continue;
        }

        // Delete the records
        const recordIds = records.map(r => r.id);
        await this.marketIndexRepositoryMaster.delete({ id: In(recordIds) });
        this.logger.log(`Deleted ${records.length} market indices records`);
      } catch (error) {
        this.logger.error('Error removing market indices:', error);
        // Wait 2 minutes before retrying on error
        await new Promise(resolve => setTimeout(resolve, 2 * 60 * 1000));
      }
    }
  }
}

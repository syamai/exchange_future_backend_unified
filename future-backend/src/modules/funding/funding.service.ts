import { CACHE_MANAGER, Inject, Injectable, Logger } from "@nestjs/common";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import { Cache } from "cache-manager";
import * as moment from "moment";
import { RedisService } from "nestjs-redis";
import { FundingHistoryEntity } from "src/models/entities/funding-history.entity";
import { FundingEntity } from "src/models/entities/funding.entity";
import { FundingHistoryRepository } from "src/models/repositories/funding-history.repository";
import { FundingRepository } from "src/models/repositories/funding.repository";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { MarketIndexRepository } from "src/models/repositories/market-indices.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { UserSettingRepository } from "src/models/repositories/user-setting.repository";
import {
  FUNDING_PREFIX,
  FUNDING_TTL,
  NEXT_FUNDING,
} from "src/modules/funding/funding.const";
import { ImpactPrice, MarketIndex } from "src/modules/funding/funding.dto";
import { INDEX_PRICE_PREFIX } from "src/modules/index/index.const";
import { Orderbook } from "src/modules/orderbook/orderbook.const";
import { OrderbookService } from "src/modules/orderbook/orderbook.service";
import { FromToDto } from "src/shares/dtos/from-to.dto";
import { IMN_COINM } from "src/shares/enums/funding.enum";
import { ContractType } from "src/shares/enums/order.enum";
import { Between, Connection } from "typeorm";
import { LeverageMarginService } from "../leverage-margin/leverage-margin.service";
import { MailService } from "../mail/mail.service";
import { NOTIFICATION_TYPE } from "../matching-engine/matching-engine.const";
import { NotificationService } from "../matching-engine/notifications.service";
import { RedisClient } from "src/shares/redis-client/redis-client";

@Injectable()
export class FundingService {
  private readonly logger = new Logger(FundingService.name);

  constructor(
    @InjectRepository(FundingRepository, "master")
    private fundingRepositoryMaster: FundingRepository,
    @InjectRepository(FundingRepository, "report")
    private fundingRepositoryReport: FundingRepository,
    @InjectRepository(InstrumentRepository, "master")
    private instrumentRepositoryMaster: InstrumentRepository,
    @InjectRepository(InstrumentRepository, "report")
    private instrumentRepositoryReport: InstrumentRepository,
    @InjectRepository(FundingHistoryRepository, "report")
    private fundingHistoryRepositoryReport: FundingHistoryRepository,
    @InjectRepository(MarketIndexRepository, "master")
    private marketIndexRepositoryMaster: MarketIndexRepository,
    @InjectRepository(MarketIndexRepository, "report")
    private marketIndicesRepositoryReport: MarketIndexRepository,
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepoReport: PositionRepository,
    @InjectRepository(UserSettingRepository, "report")
    public readonly userSettingRepoReport: UserSettingRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly redisService: RedisService,
    @InjectConnection("master") private connection: Connection,
    private readonly leverageMarginService: LeverageMarginService,
    private readonly mailService: MailService,
    private readonly notificationService: NotificationService,
    private readonly redisClient: RedisClient
  ) {}

  /**
   * Funding Payment Calculation
   At the start of each hour, an account receives USDC (if F is positive) or pays USDC (if F is negative) in an amount equal to:

   F = (-1) × S × P × R

   Where:

   S is the size of the position (positive if long, negative if short)
   P is the oracle price for the market
   R is the funding rate (as a 1-hour rate)
   *
   */

  /**
   * Funding Rate Calculation
   The main component of the funding rate is a premium that takes into account market activity for the perpetual. It is calculated for each market, every minute (at a random point within the minute) using the formula:

   Premium = (Max(0, Impact Bid Price - Index Price) - Max(0, Index Price - Impact Ask Price)) / Index Price
   Where the impact bid and impact ask prices are defined as:

   Impact Bid Price = Average execution price for a market sell of the impact notional value
   Impact Ask Price = Average execution price for a market buy of the impact notional value
   And the impact notional amount for a market is:

   Impact Notional Amount = 500 USDC / Initial Margin Fraction
   For example, for a market with a 10% initial margin fraction, the impact notional value is 5,000 USDC.

   At the end of each hour, the premium component is calculated as the simple average (i.e. TWAP) of the 60 premiums calculated over the course of the last hour. In addition to the premium component, each market has a fixed interest rate component that aims to account for the difference in interest rates of the base and quote currencies. The funding rate is then:

   Funding Rate = (Premium Component / 8) + Interest Rate Component
   Currently, the interest rate component for all dYdX markets is 0.00125% (equivalent to 0.01% per 8 hours).
   *
   *
   */
  fundingRateCaculation(
    impactBidPrice: number,
    impactAskPrice: number,
    indexPrice: number,
    interestRate: number,
    maintainMargin: number
  ): string {
    if (indexPrice == 0) {
      throw `Throw to avoid 0 in caculation ${__filename}`;
    }
    if (impactBidPrice == 0) {
      impactBidPrice = indexPrice;
    }
    if (impactAskPrice == 0) {
      impactAskPrice = indexPrice;
    }
    const premium =
      ((Math.max(0, impactBidPrice - indexPrice) -
        Math.max(0, indexPrice - impactAskPrice)) /
        indexPrice) *
      100;
    console.log(
      "premium",
      premium,
      impactBidPrice,
      impactAskPrice,
      indexPrice,
      interestRate,
      maintainMargin
    );

    const medianValues = [interestRate - premium, 0.05, -0.05];
    medianValues.sort(function (a, b) {
      return a - b;
    });

    const fundingRate = premium + medianValues[1];
    const capRate = 0.75 * maintainMargin;
    const floorRate = -0.75 * maintainMargin;

    const finalFundingRateValues = [fundingRate, capRate, floorRate];

    finalFundingRateValues.sort(function (a, b) {
      return a - b;
    });
    return finalFundingRateValues[1].toFixed(6);
  }

  caculatePriceImpact(
    asksOrBids: string[][],
    marginAmount: number,
    indexPrice: number
  ): number {
    let totalUsd = 0;
    let totalQuantity = 0;
    for (const element of asksOrBids) {
      if (marginAmount <= 0) {
        break;
      }

      const quantity =
        marginAmount < Number(element[0]) * Number(element[1])
          ? Number(element[1])
          : marginAmount / Number(element[0]);
      totalQuantity += quantity;
      totalUsd += Number(element[0]) * quantity;
      marginAmount -= Number(element[0]) * Number(element[1]);
    }
    if (totalUsd == 0) return indexPrice;
    else return totalUsd / totalQuantity;
  }

  caculateCoinMImpactPrice(
    asksOrBids: string[][],
    marginAmount: number,
    indexPrice: number
  ): number {
    let totalPrice = 0;
    let totalQuantity = 0;
    let totalAccumulatedValue = 0;
    let isDone = false;
    let lastPrice: number;
    for (const element of asksOrBids) {
      const notionalValue = Number(element[1]) / Number(element[0]);
      totalAccumulatedValue += notionalValue;
      totalPrice += Number(element[0]);
      totalQuantity += Number(element[1]);
      if (totalAccumulatedValue > marginAmount) {
        isDone = true;
        totalAccumulatedValue -= notionalValue;
        lastPrice = Number(element[0]);
        totalQuantity -= Number(element[1]);
        break;
      }
    }
    if (!isDone) {
      totalQuantity -= Number(asksOrBids[asksOrBids.length - 1][1]);
      lastPrice = Number(asksOrBids[asksOrBids.length - 1][0]);
      totalAccumulatedValue -=
        Number(asksOrBids[asksOrBids.length - 1][1]) /
        Number(asksOrBids[asksOrBids.length - 1][0]);
    }
    if (totalPrice == 0) {
      return indexPrice;
    }
    const impactPrice =
      ((marginAmount - totalAccumulatedValue) * lastPrice + totalQuantity) /
      marginAmount;
    return impactPrice;
  }

  async getImpactPrice(symbol: string, price: number): Promise<ImpactPrice> {
    const leverageMargins = await this.leverageMarginService.findAllByContract(
      ContractType.USD_M
    );
    const listMaxLeverage = leverageMargins
      .map((lm) => {
        if (lm.symbol === symbol) {
          return +lm.maxLeverage;
        }
      })
      .filter((l) => l !== undefined);
    const result = leverageMargins.find(
      (lm) =>
        lm.symbol === symbol && +lm.maxLeverage === Math.max(...listMaxLeverage)
    );

    if (!result) {
      return {
        impactBidPrice: price,
        impactAskPrice: price,
        interestRate: 0.01,
        maintainMargin: 0,
      };
    }
    const initMargin = 1 / result.maxLeverage;
    // Fixed with 500
    const marginAmount = 500 / initMargin;

    const orderbook = await this.cacheManager.get<Orderbook>(
      `${OrderbookService.getOrderbookKey(symbol)}`
    );

    if (
      orderbook == undefined ||
      orderbook == null ||
      (orderbook.bids.length == 0 && orderbook.asks.length == 0)
    ) {
      return {
        impactBidPrice: price,
        impactAskPrice: price,
        interestRate: 0.01,
        maintainMargin:
          result.maintenanceMarginRate == null
            ? null
            : Number(result.maintenanceMarginRate),
      };
    } else if (orderbook.bids.length > 0 && orderbook.asks.length == 0) {
      return {
        impactBidPrice: this.caculatePriceImpact(
          orderbook.bids,
          marginAmount,
          price
        ),
        impactAskPrice: price,
        interestRate: 0.01,
        maintainMargin:
          result.maintenanceMarginRate == null
            ? null
            : Number(result.maintenanceMarginRate),
      };
    } else if (orderbook.bids.length == 0 && orderbook.asks.length > 0) {
      return {
        impactBidPrice: this.caculatePriceImpact(
          orderbook.asks,
          marginAmount,
          price
        ),
        impactAskPrice: price,
        interestRate: 0.01,
        maintainMargin:
          result.maintenanceMarginRate == null
            ? null
            : Number(result.maintenanceMarginRate),
      };
    } else {
      return {
        impactBidPrice: this.caculatePriceImpact(
          orderbook.bids,
          marginAmount,
          price
        ),
        impactAskPrice: this.caculatePriceImpact(
          orderbook.asks,
          marginAmount,
          price
        ),
        interestRate: 0.01,
        maintainMargin:
          result.maintenanceMarginRate == null
            ? null
            : Number(result.maintenanceMarginRate),
      };
    }
  }

  async getImpactPriceCoinM(symbol: string, price: number) {
    const leverageMargins = await this.leverageMarginService.findAllByContract(
      ContractType.COIN_M
    );
    const listMaxLeverage = leverageMargins
      .map((lm) => {
        if (lm.symbol === symbol) {
          return +lm.maxLeverage;
        }
      })
      .filter((l) => l !== undefined);
    const result = leverageMargins.find(
      (lm) =>
        lm.symbol === symbol && +lm.maxLeverage === Math.max(...listMaxLeverage)
    );
    const orderbook = await this.cacheManager.get<Orderbook>(
      `${OrderbookService.getOrderbookKey(symbol)}`
    );

    if (
      orderbook == undefined ||
      orderbook == null ||
      (orderbook.bids.length == 0 && orderbook.asks.length == 0)
    ) {
      return {
        impactBidPrice: price,
        impactAskPrice: price,
        interestRate: 0.01,
        maintainMargin:
          result.maintenanceMarginRate == null
            ? null
            : Number(result.maintenanceMarginRate),
      };
    } else if (orderbook.bids.length > 0 && orderbook.asks.length == 0) {
      return {
        impactBidPrice: this.caculateCoinMImpactPrice(
          orderbook.bids,
          IMN_COINM.VALUE,
          price
        ),
        impactAskPrice: price,
        interestRate: 0.01,
        maintainMargin:
          result.maintenanceMarginRate == null
            ? null
            : Number(result.maintenanceMarginRate),
      };
    } else if (orderbook.bids.length == 0 && orderbook.asks.length > 0) {
      return {
        impactBidPrice: this.caculatePriceImpact(
          orderbook.asks,
          IMN_COINM.VALUE,
          price
        ),
        impactAskPrice: price,
        interestRate: 0.01,
        maintainMargin:
          result.maintenanceMarginRate == null
            ? null
            : Number(result.maintenanceMarginRate),
      };
    }
    return {
      impactBidPrice: this.caculateCoinMImpactPrice(
        orderbook.bids,
        IMN_COINM.VALUE,
        price
      ),
      impactAskPrice: this.caculateCoinMImpactPrice(
        orderbook.asks,
        IMN_COINM.VALUE,
        price
      ),
      interestRate: 0.01,
      maintainMargin:
        result.maintenanceMarginRate == null
          ? null
          : Number(result.maintenanceMarginRate),
    };
  }

  async getMarketIndex(): Promise<MarketIndex[]> {
    // check cached data
    const lastInserted = await this.redisService
      .getClient()
      .get(`${INDEX_PRICE_PREFIX}last_inserted`);
    if (lastInserted) return JSON.parse(lastInserted);

    // const query =
    //   'select symbol, price from market_indices ' +
    //   'where id in (select max(`id`) as `latest` from `market_indices` group by `symbol`)';
    // optimize query (where id in (select) do not use index due to stupid mysql)
    
    // Query to get the latest market index data for each symbol
    // Uses a self-join to find the most recent record per symbol
    const query = `
      SELECT m1.symbol, m1.price 
      FROM market_indices m1
      INNER JOIN (
        SELECT symbol, MAX(id) as latest_id
        FROM market_indices 
        GROUP BY symbol
      ) m2 ON m1.id = m2.latest_id
    `;
    const output = await this.marketIndicesRepositoryReport.query(query);

    return output;
  }

  async getFundingRates(symbols: string[]): Promise<string[]> {
    if (!symbols.length) {
      return [];
    }
    const keys = symbols.map((symbol) => `${FUNDING_PREFIX}${symbol}`);
    const values = await this.redisClient.getInstance().mget(keys);
    const results = values.filter((v) => v != null);
    return results;
  }

  async saveFundingRate(symbol: string, fundingRate: string): Promise<void> {
    const date = new Date();
    date.setMinutes(0, 0, 0);
    await Promise.all([
      this.cacheManager.set(`${FUNDING_PREFIX}${symbol}`, +fundingRate, {
        ttl: FUNDING_TTL,
      }),
      // this.redisService.getClient().set(`${FUNDING_PREFIX}${symbol}`, fundingRate, 'EX', FUNDING_TTL)
      // this.redisService.getClient().set(`${FUNDING_PREFIX}next_funding`, date.getTime() + 8 * 60 * 60 * 1000),
      this.cacheManager.set(
        `${FUNDING_PREFIX}next_funding`,
        Date.now() + NEXT_FUNDING
      ),
    ]);
  }

  async getNextFunding(symbol: string) {
    const nextFundingCache = await this.redisService
      .getClient()
      .get(`${FUNDING_PREFIX}next_funding`);
    if (nextFundingCache) {
      return +nextFundingCache;
    } else {
      const nextFundingDb = await this.fundingRepositoryReport
        .createQueryBuilder("f")
        .select("f.nextFunding as nextFunding")
        .where("f.symbol = :symbol", { symbol })
        .andWhere("f.time >= :date", {
          date: new Date().toISOString().split("T")[0],
        })
        .orderBy("f.id", "DESC")
        .getRawOne();
      if (nextFundingDb) {
        await this.redisService
          .getClient()
          .set(`${FUNDING_PREFIX}next_funding`, +nextFundingDb.nextFunding);
        return +nextFundingDb.nextFunding;
      }
    }
  }

  async fundingRate(symbol: string) {
    try {
      const fundingRateCache = await this.cacheManager.get(
        `${FUNDING_PREFIX}${symbol}`
      );
      if (fundingRateCache) {
        return fundingRateCache;
      } else {
        const fundingRateDb = await this.fundingRepositoryReport
          .createQueryBuilder("f")
          .select("f.fundingRate as fundingRate")
          .where("f.symbol = :symbol", { symbol })
          .andWhere("f.time >= :date", {
            date: new Date().toISOString().split("T")[0],
          })
          .orderBy("f.id", "DESC")
          .getRawOne();
        if (fundingRateDb?.fundingRate) {
          this.cacheManager.set(
            `${FUNDING_PREFIX}${symbol}`,
            +fundingRateDb.fundingRate,
            { ttl: FUNDING_TTL }
          );
          return fundingRateDb.fundingRate;
        }
      }
    } catch (e) {
      console.log("===============errr==============");
      console.log(e);
    }
  }

  public async setLastPay(): Promise<void> {
    await this.redisService
      .getClient()
      .set(`${FUNDING_PREFIX}last_pay`, Date.now());
  }

  public async setLastUpdate(): Promise<void> {
    await this.redisService
      .getClient()
      .set(`${FUNDING_PREFIX}last_update`, Date.now());
  }

  public async getLastPay(): Promise<number | undefined> {
    const value = await this.redisService
      .getClient()
      .get(`${FUNDING_PREFIX}last_pay`);
    return value ? Number(value) : 0;
  }

  public async getLastUpdate(): Promise<number | undefined> {
    const value = await this.redisService
      .getClient()
      .get(`${FUNDING_PREFIX}last_update`);
    return value ? Number(value) : 0;
  }

  async getFundingHistoryByAccountId(symbol?: string) {
    const startDate = moment()
      .subtract(13, "days")
      .format("YYYY-MM-DD 00:00:00");
    const endDate = moment().format("YYYY-MM-DD 23:59:59");
    return await this.fundingRepositoryReport
      .createQueryBuilder("f")
      .select([
        "f.id as id",
        "f.time as time",
        "f.fundingRate as fundingRate",
        "f.fundingInterval as fundingInterval",
        "f.symbol as symbol",
      ])
      .where("f.symbol = :symbol", { symbol })
      .andWhere("f.createdAt BETWEEN :startDate AND :endDate", {
        startDate,
        endDate,
      })
      .orderBy("f.createdAt", "DESC")
      .getRawMany();
  }

  async getFundingRatesFromTo(
    symbol: string,
    { from, to }: FromToDto
  ): Promise<FundingEntity[]> {
    const fundingRates = await this.fundingRepositoryReport.find({
      select: ["id", "symbol", "fundingRate", "createdAt"],
      where: {
        symbol: symbol,
        time: Between(new Date(from), new Date(to)),
      },
      order: {
        time: "ASC",
      },
    });
    return fundingRates;
  }

  async findHistoryBefore(
    date: Date
  ): Promise<FundingHistoryEntity | undefined> {
    return await this.fundingHistoryRepositoryReport.findHistoryBefore(date);
  }

  async findHistoryBatch(
    fromId: number,
    count: number
  ): Promise<FundingHistoryEntity[]> {
    return await this.fundingHistoryRepositoryReport.findBatch(fromId, count);
  }

  async sendMailFundingFee(dataFundingRates: any[]) {
    const userSettings = await this.userSettingRepoReport.getUserSettingToSendFundingFeeMail();
    for (const userSetting of userSettings) {
      const symbols = [];
      for (const dataFundingRate of dataFundingRates) {
        const position = await this.positionRepoReport.findPositionByUserId(
          userSetting.userId,
          dataFundingRate.symbol
        );
        if (!position) {
          continue;
        }
        if (
          Number(position.currentQty) > 0 &&
          Number(dataFundingRate.fundingRate) >
            Number(userSetting.fundingFeeTriggerValue)
        ) {
          symbols.push({
            symbol: position.symbol,
            fundingRate: Number(dataFundingRate.fundingRate),
          });
        }
        if (
          Number(position.currentQty) < 0 &&
          Number(dataFundingRate.fundingRate) <
            -Number(userSetting.fundingFeeTriggerValue)
        ) {
          symbols.push({
            symbol: position.symbol,
            fundingRate: Number(dataFundingRate.fundingRate),
          });
        }
      }

      if (symbols.length > 0 && userSetting.fundingFeeTrigger) {
        this.mailService.sendMailFundingFee(
          userSetting.email,
          userSetting.fundingFeeTriggerValue,
          symbols
        );
        this.notificationService.genDataNotificationFirebase(
          NOTIFICATION_TYPE.FUNDING_FEE,
          userSetting.userId
        );
      }
    }
  }
}

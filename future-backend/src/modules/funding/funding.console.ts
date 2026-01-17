import { InjectQueue } from "@nestjs/bull";
import { CACHE_MANAGER, Inject, Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Queue } from "bull";
import { Cache } from "cache-manager";
// import * as moment from 'moment';
import { Command, Console } from "nestjs-console";
import { RedisService } from "nestjs-redis";
// import { FundingEntity } from 'src/models/entities/funding.entity';
import { FundingRepository } from "src/models/repositories/funding.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { UserSettingRepository } from "src/models/repositories/user-setting.repository";
import { UserRepository } from "src/models/repositories/user.repository";
// import { SLEEPING_INTERVAL } from 'src/modules/funding/funding.const';
import { FundingService } from "src/modules/funding/funding.service";
import { ORACLE_PRICE_PREFIX } from "src/modules/index/index.const";
import {
  CommandCode,
  CommandOutput,
  FUNDING_HISTORY_TIMESTAMP_KEY,
  FUNDING_INTERVAL,
  POSITION_HISTORY_TIMESTAMP_KEY,
} from "src/modules/matching-engine/matching-engine.const";
import { FUNDING_RATE } from "src/shares/enums/funding.enum";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { InstrumentService } from "../instrument/instrument.service";
import { MailService } from "../mail/mail.service";
import { NotificationService } from "../matching-engine/notifications.service";
import { Orderbook } from "../orderbook/orderbook.const";
import { OrderbookService } from "../orderbook/orderbook.service";
import { LIST_SYMBOL_COINM } from "../transaction/transaction.const";
import {
  CLOSE_INSURANCE,
  CLOSE_INSURANCE_TTL,
  // FUNDING_PREFIX,
  KEY_CACHE_HEALTHCHECK_GET_FUNDING,
  KEY_CACHE_HEALTHCHECK_GET_FUNDING_TTL,
  NEXT_FUNDING,
  // KEY_CACHE_HEALTHCHECK_PAY_FUNDING,
  START_FUNDING_RATE,
  START_FUNDING_RATE_TTL,
} from "./funding.const";
import { ImpactPrice } from "./funding.dto";
// const lodash = require('lodash');
@Console()
@Injectable()
export class FundingConsole {
  private readonly logger = new Logger(FundingConsole.name);

  constructor(
    private readonly fundingService: FundingService,
    private readonly mailService: MailService,
    @InjectRepository(FundingRepository, "master")
    private fundingRepositoryMaster: FundingRepository,
    @InjectRepository(FundingRepository, "report")
    private fundingRepositoryReport: FundingRepository,
    public readonly kafkaClient: KafkaClient,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly redisService: RedisService,
    @InjectRepository(UserSettingRepository, "report")
    public readonly userSettingRepoReport: UserSettingRepository,
    @InjectRepository(UserSettingRepository, "master")
    public readonly userSettingMasterReport: UserSettingRepository,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    @InjectRepository(UserRepository, "master")
    public readonly userMasterReport: UserRepository,
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepoReport: PositionRepository,
    private readonly instrumentService: InstrumentService,
    private readonly notificationService: NotificationService,
    @InjectQueue("mail") private readonly emailQueue: Queue
  ) {}

  @Command({
    command: "start-get-funding-rate",
    description: "Start the job to get funding rate",
  })
  async calculateFundingRate(): Promise<void> {
    // This function will be executed one time by AWS cron calling
    // It will calculate the latest funding rate

    // Check if cache exists -> return
    const getCache = await this.cacheManager.get(START_FUNDING_RATE);
    if (getCache) {
      return;
    }

    const marketIndices = await this.fundingService.getMarketIndex();
    const dataFundingRates = [];
    const listSymbol = await this.instrumentService.getAllSymbolInstrument();

    const nextFunding = Date.now() + NEXT_FUNDING;
    for (const rowIndex in marketIndices) {
      const symbol = marketIndices[rowIndex].symbol;
      const isExistInstrument = listSymbol.includes(symbol);
      if (!isExistInstrument) {
        continue;
      }
      const orderbook = await this.cacheManager.get<Orderbook>(
        `${OrderbookService.getOrderbookKey(symbol)}`
      );
      let fundingRate: string = FUNDING_RATE.DEFAULT;

      if (orderbook && orderbook.bids.length && orderbook.asks.length) {
        const isCoim = LIST_SYMBOL_COINM.includes(symbol);
        let impact: ImpactPrice;

        if (isCoim) {
          impact = await this.fundingService.getImpactPriceCoinM(
            symbol,
            marketIndices[rowIndex].price
          );
        } else {
          impact = await this.fundingService.getImpactPrice(
            symbol,
            marketIndices[rowIndex].price
          );
        }

        fundingRate = this.fundingService.fundingRateCaculation(
          impact.impactBidPrice,
          impact.impactAskPrice,
          marketIndices[rowIndex].price,
          impact.interestRate,
          impact.maintainMargin == null ? 1 : impact.maintainMargin
        );
      }

      const now = Date.now();
      const time = new Date(now - (now % FUNDING_RATE.NEXT_TIME));
      const oraclePrice =
        (await this.redisService
          .getClient()
          .get(`${ORACLE_PRICE_PREFIX}${symbol}`)) || "0";

      await this.saveFundingToDB(
        symbol,
        fundingRate,
        time,
        oraclePrice,
        nextFunding
      );

      await this.fundingService.saveFundingRate(symbol, fundingRate);
      dataFundingRates.push({ symbol, fundingRate });

      await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.PAY_FUNDING,
        data: {
          symbol,
          fundingRate: fundingRate,
          oraclePrice: oraclePrice,
          time: time.getTime(),
        },
      });
    }

    if (dataFundingRates.length > 0) {
      // await this.emailQueue.add('sendFundingMailAndAddToQueue', { dataFundingRates });
      await this.kafkaClient.send(KafkaTopics.send_mail, {
        code: CommandCode.MAIL_FUNDING,
        data: dataFundingRates,
      });
    }

    await this.fundingService.setLastUpdate();

    await this.cacheManager.set(START_FUNDING_RATE, true, {
      ttl: START_FUNDING_RATE_TTL,
    });

    // ttl 9 hours
    await this.cacheManager.set(KEY_CACHE_HEALTHCHECK_GET_FUNDING, true, {
      ttl: KEY_CACHE_HEALTHCHECK_GET_FUNDING_TTL,
    });
  }

  async saveFundingToDB(symbol, fundingRate, time, oraclePrice, nextFunding) {
    const fundingInterval = FUNDING_INTERVAL as string;

    await this.fundingRepositoryMaster.save({
      symbol,
      time,
      fundingRate,
      fundingInterval,
      oraclePrice,
      nextFunding,
    });
  }

  // @Command({
  //   command: 'funding:pay [timestamp]',
  //   description: 'Pay funding',
  // })
  // async payFunding(timestamp: number | undefined): Promise<void> {
  //   this.logger.debug(`Timestamp: ${timestamp}`);
  //
  //   // Check if cache exists -> return
  //
  //   const marketIndices = await this.fundingService.getMarketIndex();
  //   //const listSymbol = await this.instrumentService.getAllSymbolInstrument();
  //   // if (!timestamp) {
  //   //   for (const marketIndex of marketIndices) {
  //   //     const isExistInstrument = listSymbol.includes(marketIndex.symbol);
  //   //     if (!isExistInstrument) {
  //   //       continue;
  //   //     }
  //   //     const symbol = marketIndex.symbol;
  //   //     const now = Date.now();
  //   //     const time = new Date(now - (now % FUNDING_RATE.NEXT_TIME));
  //   //     await this.updateFundingRate(symbol, time, marketIndex.price);
  //   //   }
  //   // }
  //
  //   for (const marketIndex of marketIndices) {
  //     const symbol = marketIndex.symbol;
  //     await this.payFundingForContract(symbol, timestamp);
  //   }
  //
  //   await this.fundingService.setLastPay();
  //
  //   // ttl 9 hours
  //   await this.cacheManager.set(KEY_CACHE_HEALTHCHECK_PAY_FUNDING, true, { ttl: 60 * 60 + 9 });
  // }

  private async payFundingForContract(
    symbol: string,
    timestamp: number | undefined
  ): Promise<void> {
    let time: Date;
    if (timestamp) {
      time = new Date(timestamp - (timestamp % FUNDING_RATE.NEXT_TIME));
    } else {
      const now = Date.now();
      time = new Date(now - (now % FUNDING_RATE.NEXT_TIME));
    }
    const fundingRate = await this.getFundingRate(symbol);

    const positionHistoryTimestamp = await this.getPositionHistoryTimestamp();
    if (
      !positionHistoryTimestamp ||
      time.getTime() < positionHistoryTimestamp
    ) {
      this.logger.error(
        `Cannot pay funding before (position history) ${new Date(
          positionHistoryTimestamp || 0
        )}`
      );
      return;
    }

    const fundingHistoryTimestamp = await this.getFundingHistoryTimestamp();
    if (!fundingHistoryTimestamp || time.getTime() < fundingHistoryTimestamp) {
      this.logger.error(
        `Cannot pay funding before (funding history) ${new Date(
          fundingHistoryTimestamp || 0
        )}`
      );
      return;
    }

    if (!fundingRate) {
      this.logger.error(
        `Cannot find funding rate for symbol ${symbol} at ${time}`
      );
      return;
    }
    const oraclePrice = await this.redisService
      .getClient()
      .get(`${ORACLE_PRICE_PREFIX}${symbol}`);
    if (!oraclePrice) {
      this.logger.error(`Cannot get oracle price of symbol ${symbol}`);
      return;
    }
    // if (funding.paid) {
    //   this.logger.error(`Funding for symbol ${symbol} at ${time} is already paid`);
    //   return;
    // }

    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.PAY_FUNDING,
      data: {
        symbol,
        fundingRate: fundingRate,
        oraclePrice: oraclePrice,
        time: time.getTime(),
      },
    });
  }

  private async getPositionHistoryTimestamp(): Promise<number | undefined> {
    return this.cacheManager.get<number>(POSITION_HISTORY_TIMESTAMP_KEY);
  }

  private async getFundingHistoryTimestamp(): Promise<number | undefined> {
    return this.cacheManager.get<number>(FUNDING_HISTORY_TIMESTAMP_KEY);
  }

  private async getFundingRate(symbol: string): Promise<string | undefined> {
    return await this.fundingService.fundingRate(symbol);
  }

  private async updateFundingRate(
    symbol: string,
    time: Date,
    indexPrice: number
  ): Promise<void> {
    let impact: ImpactPrice;
    const isCoim = LIST_SYMBOL_COINM.includes(symbol);
    if (isCoim) {
      impact = await this.fundingService.getImpactPriceCoinM(
        symbol,
        indexPrice
      );
    } else {
      impact = await this.fundingService.getImpactPrice(symbol, indexPrice);
    }
    const funding = await this.getFundingRate(symbol);
    if (funding) {
      this.logger.log(
        `Funding rate of symbol ${symbol} at ${time} is already calculated`
      );
      return;
    }

    this.logger.log(
      `Calculate funding rate of symbol ${symbol} at ${time} and `
    );
    const fundingRate = this.fundingService.fundingRateCaculation(
      impact.impactBidPrice,
      impact.impactAskPrice,
      indexPrice,
      impact.interestRate,
      impact.maintainMargin == null ? 1 : impact.maintainMargin
    );

    const oraclePrice = await this.redisService
      .getClient()
      .get(`${ORACLE_PRICE_PREFIX}${symbol}`);
    if (!oraclePrice) {
      this.logger.error(`Cannot get oracle price of symbol ${symbol}`);
      return;
    }
    const fundingInterval = FUNDING_INTERVAL as string;
    await this.fundingRepositoryMaster.save({
      symbol: symbol,
      time: time,
      fundingRate: fundingRate,
      fundingInterval: fundingInterval,
      oraclePrice,
    });
  }

  @Command({
    command: "close-insurance",
    description: "Start the job to get close insurance",
  })
  async closeInsurance(): Promise<void> {
    const checkCloseInsurance = await this.cacheManager.get(CLOSE_INSURANCE);
    if (checkCloseInsurance) {
      return;
    }
    console.log("Start the job to get close insurance");
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.CLOSE_INSURANCE,
    });
    await this.cacheManager.set(CLOSE_INSURANCE, true, {
      ttl: CLOSE_INSURANCE_TTL,
    });
  }

  async sendMail(
    groupId: string,
    callback: (command: CommandOutput) => Promise<void>
  ): Promise<void> {
    await this.kafkaClient.consume<CommandOutput>(
      KafkaTopics.send_mail,
      groupId,
      async (command) => {
        await callback(command);
      },
      { fromBeginning: true }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "funding:send-mail-funding-fee",
    description: "send mail funding fee",
  })
  async sendMailFundingFee(): Promise<void> {
    await this.sendMail(KafkaGroups.send_mail, (command) =>
      this.fundingService.sendMailFundingFee(command.data as any)
    );
  }
}

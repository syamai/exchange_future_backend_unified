import {
  CACHE_MANAGER,
  HttpException,
  HttpStatus,
  Inject,
  Injectable,
} from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { TradingRulesEntity } from "src/models/entities/trading_rules.entity";
import { TradingRulesRepository } from "src/models/repositories/trading-rules.repository";
import { httpErrors } from "src/shares/exceptions";
import { TradingRulesModeDto } from "./dto/trading-rules.dto";
const lodash = require("lodash");
import { Cache } from "cache-manager";
import {
  TRADING_RULES_CACHE,
  TRADING_RULES_CACHE_NO_LIMIT,
  TRADING_RULES_TTL,
} from "./trading-rules.constants";
import _ from "lodash";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { CommandCode } from "../matching-engine/matching-engine.const";

@Injectable()
export class TradingRulesService {
  constructor(
    @InjectRepository(TradingRulesRepository, "master")
    private tradingRulesMaster: TradingRulesRepository,
    @InjectRepository(TradingRulesRepository, "report")
    private readonly tradingRulesReport: TradingRulesRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepoReport: InstrumentRepository,
    @InjectRepository(InstrumentRepository, "master")
    public readonly instrumentRepoMaster: InstrumentRepository,
    public readonly kafkaClient: KafkaClient
  ) {}

  async insertOrUpdateTradingRules(
    input: TradingRulesModeDto
  ): Promise<TradingRulesEntity> {
    try {
      const findTradingRule = await this.tradingRulesReport.findOne({
        symbol: input.symbol,
      });

      let data = input;
      if (input.isReduceOnly == false) {
        data = lodash.omit(data, [
          "positionsNotional",
          "ratioOfPostion",
          "liqMarkPrice",
        ]);
      }
      let tradingRule;
      if (!findTradingRule) {
        tradingRule = await this.tradingRulesMaster.save(data);
      } else {
        await this.tradingRulesMaster.update({ symbol: input.symbol }, data);
        tradingRule = await this.tradingRulesReport.findOne({
          symbol: input.symbol,
        });
      }
      const tradingRuleCache = await this.tradingRulesReport.find();
      const instruments = await this.instrumentRepoReport.find();
      tradingRuleCache.forEach((tradingRule) => {
        const ins = instruments.find((i) => i.symbol === tradingRule.symbol);
        (tradingRule["maxPrice"] = ins?.maxPrice),
          (tradingRule["maxFiguresForPrice"] = ins?.maxFiguresForPrice),
          (tradingRule["maxFiguresForSize"] = ins?.maxFiguresForSize);
      });

      await Promise.all([
        this.cacheManager.set(TRADING_RULES_CACHE, tradingRuleCache, {
          ttl: TRADING_RULES_TTL,
        }),
        this.cacheManager.set(TRADING_RULES_CACHE_NO_LIMIT, tradingRuleCache, {
          ttl: TRADING_RULES_TTL,
        }),
      ]);

      await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.LOAD_TRADING_RULE,
        data: tradingRule,
      });
      return tradingRule;
    } catch (error) {
      throw new Error(error);
    }
  }

  async getAllTradingRules(input: PaginationDto) {
    const tradingRulesCache = (await this.cacheManager.get(
      TRADING_RULES_CACHE
    )) as any[];
    if (tradingRulesCache) {
      const list = tradingRulesCache.slice(
        (input.page - 1) * input.size,
        input.page * input.size
      );
      return { list, count: tradingRulesCache.length };
    }
    const tradingRules = await this.tradingRulesReport.find();
    await this.cacheManager.set(TRADING_RULES_CACHE, tradingRules, {
      ttl: TRADING_RULES_TTL,
    });

    return {
      list: tradingRules.slice(
        (input.page - 1) * input.size,
        input.page * input.size
      ),
      count: tradingRules.length,
    };
  }

  async getTradingRuleByInstrumentId(symbol: string) {
    try {
      const tradingRulesCache = await this.cacheManager.get(
        `${TRADING_RULES_CACHE}_${symbol}`
      );
      if (tradingRulesCache) {
        return tradingRulesCache;
      }

      // Parallel fetch: tradingRule and instrument for performance optimization
      const [tradingRule, instrument] = await Promise.all([
        this.tradingRulesReport.findOne({ symbol }),
        this.instrumentRepoReport.findOne({ symbol })
      ]);

      if (!tradingRule) {
        throw new HttpException(
          httpErrors.TRADING_RULES_DOES_NOT_EXIST,
          HttpStatus.NOT_FOUND
        );
      }
      if (!instrument) {
        throw new HttpException(
          httpErrors.INSTRUMENT_DOES_NOT_EXIST,
          HttpStatus.NOT_FOUND
        );
      }
      const data = { ...tradingRule, ...instrument };

      await this.cacheManager.set(`${TRADING_RULES_CACHE}_${symbol}`, data, { ttl: TRADING_RULES_TTL });
      return data;
    } catch (e) {
      console.error('Error in getTradingRuleByInstrumentId:', e);
      throw e;
    }
  }

  async getAllTradingRulesNoLimit(): Promise<TradingRulesEntity[]> {
    const tradingRulesCache = (await this.cacheManager.get(
      TRADING_RULES_CACHE_NO_LIMIT
    )) as TradingRulesEntity[];
    if (tradingRulesCache) return tradingRulesCache;
    const tradingRules = await this.tradingRulesReport.find();
    const instruments = await this.instrumentRepoReport.find();
    tradingRules.forEach((tradingRule) => {
      const ins = instruments.find((i) => i.symbol === tradingRule.symbol);
      (tradingRule["maxPrice"] = ins?.maxPrice),
        (tradingRule["maxFiguresForPrice"] = ins?.maxFiguresForPrice),
        (tradingRule["maxFiguresForSize"] = ins?.maxFiguresForSize);
    });
    await this.cacheManager.set(TRADING_RULES_CACHE_NO_LIMIT, tradingRules, {
      ttl: TRADING_RULES_TTL,
    });
    return tradingRules;
  }
}

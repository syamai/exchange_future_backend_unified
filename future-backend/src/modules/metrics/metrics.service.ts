import { CACHE_MANAGER, Inject, Injectable } from "@nestjs/common";
import { Registry, collectDefaultMetrics } from "prom-client";
import { Cache } from "cache-manager";
import {
  KEY_CACHE_HEALTHCHECK_GET_FUNDING,
  KEY_CACHE_HEALTHCHECK_PAY_FUNDING,
} from "../funding/funding.const";
import { KEY_CACHE_HEALTHCHECK_INDEX_PRICE } from "../index/index.const";
import { KEY_CACHE_HEALTHCHECK_COIN_INFO } from "../coin-info/coin-info.constants";
import { KEY_CACHE_HEALTHCHECK_SYNC_CANDLE } from "../candle/candle.const";
import { RedisService } from "nestjs-redis";
import { RedisClient } from "src/shares/redis-client/redis-client";

@Injectable()
export class MetricsService {
  private registry: Registry;

  constructor(
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    // private readonly redisService: RedisService
    private readonly redisClient: RedisClient
  ) {
    this.registry = new Registry();
    collectDefaultMetrics({ register: this.registry });
  }

  async getMetrics(): Promise<any> {
    return this.registry.metrics();
  }

  async healthcheckService(): Promise<any> {
    const [
      healthcheckGetFunding,
      healthcheckPayFunding,
      healthcheckIndexPrice,
      healthcheckCoinInfo,
      healthcheckSyncCandle,
    ] = await Promise.all([
      this.cacheManager.get(KEY_CACHE_HEALTHCHECK_GET_FUNDING),
      this.cacheManager.get(KEY_CACHE_HEALTHCHECK_PAY_FUNDING),
      this.cacheManager.get(KEY_CACHE_HEALTHCHECK_INDEX_PRICE),
      this.cacheManager.get(KEY_CACHE_HEALTHCHECK_COIN_INFO),
      this.cacheManager.get(KEY_CACHE_HEALTHCHECK_SYNC_CANDLE),
    ]);

    return {
      healthcheck_get_funding: healthcheckGetFunding ?? false,
      healthcheck_pay_funding: healthcheckPayFunding ?? false,
      healthcheck_index_price: healthcheckIndexPrice ?? false,
      healthcheck_coin_info: healthcheckCoinInfo ?? false,
      healthcheck_sync_candle: healthcheckSyncCandle ?? false,
    };
  }

  async healcheckRedis(): Promise<any> {
    const value = await this.redisClient.getInstance().keys("*");
    return value ? value.length : 0;
  }
}

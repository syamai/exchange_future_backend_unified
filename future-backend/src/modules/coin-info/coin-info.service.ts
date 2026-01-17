import { CACHE_MANAGER, HttpException, HttpStatus, Inject, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import * as config from "config";
import fetch from "node-fetch";
import { CoinInfoEntity } from "../../models/entities/coin-info.entity";
import { CoinInfoRepository } from "../../models/repositories/coin-info.repository";
import {
  COIN_INFO_CACHE,
  COIN_INFO_ID,
  COIN_INFO_TTL,
  CURRENT_PRICE_COIN_INFO_CACHE,
  CURRENT_PRICE_COIN_INFO_TTL,
} from "./coin-info.constants";
import { Cache } from "cache-manager";
import { httpErrors } from "src/shares/exceptions";
import { KEY_CACHE_HEALTHCHECK_GET_FUNDING } from "../funding/funding.const";

@Injectable()
export class CoinInfoService {
  constructor(
    @InjectRepository(CoinInfoRepository, "master")
    private coinInfoRepository: CoinInfoRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache
  ) {}

  async getInfo() {
    const coins = COIN_INFO_ID;
    const coingeckoUrl = config.get<string>("coin_info.coingeckoUrl");
    for (const c in coins) {
      try {
        const response = await fetch(
          `${coingeckoUrl}/coins/${coins[c]}?localization=false&tickers=false&community_data=false&developer_data=false&sparkline=false`,
          {
            method: "get",
          }
        );
        if (!response.ok) {
          throw new Error("Error call coingeckco");
        }
        const data = await response.json();
        await this.saveData(data);
      } catch (err) {
        console.log(err);
      }
      // coigecko limit 10rq/minute
      await this.delay(30000);
    }
    // ttl 5 hours
    await this.cacheManager.set(KEY_CACHE_HEALTHCHECK_GET_FUNDING, true, {
      ttl: 60 * 60 + 5,
    });
  }

  async findCoin(coinId: string) {
    return await this.coinInfoRepository.find({
      baseId: coinId,
    });
  }

  async saveData(data: any) {
    const checkCoin = await this.findCoin(data.id);
    if (checkCoin.length) {
      await this.coinInfoRepository.manager
        .createQueryBuilder()
        .update(CoinInfoEntity)
        .set({
          fullName: data?.name || null,
          baseId: data.id,
          symbol: data?.symbol || null,
          rank: data?.market_cap_rank || null,
          marketCap: data?.market_data?.market_cap.usd || null,
          cirSupply: data?.market_data?.circulating_supply || null,
          maxSupply: data?.market_data?.max_supply || null,
          totalSupply: data?.market_data?.total_supply || null,
          issueDate: null,
          issuePrice: null,
          explorer: data?.links?.homepage[0] || "",
        })
        .where("baseId = :baseId", { baseId: data.id })
        .execute();
    } else {
      await this.coinInfoRepository.manager
        .createQueryBuilder()
        .insert()
        .into(CoinInfoEntity)
        .values({
          fullName: data?.name || null,
          baseId: data.id,
          symbol: data?.symbol || null,
          rank: data?.coingecko_rank || null,
          marketCap: data?.market_data?.market_cap.usd || null,
          cirSupply: data?.market_data?.circulating_supply || null,
          maxSupply: data?.market_data?.max_supply || null,
          totalSupply: data?.market_data?.total_supply || null,
          issueDate: null,
          issuePrice: null,
          explorer: data?.links?.homepage[0] || "",
        })
        .execute();
    }
  }

  async getCoinInfo(coin: string) {
    return await this.coinInfoRepository.findOne({
      symbol: coin,
    });
  }

  async getAllCoinInfo(): Promise<CoinInfoEntity[]> {
    const coinInfoCache = (await this.cacheManager.get(COIN_INFO_CACHE)) as CoinInfoEntity[];
    if (coinInfoCache) return coinInfoCache;
    const coinInfo = await this.coinInfoRepository.find();
    await this.cacheManager.set(COIN_INFO_CACHE, coinInfo, {
      ttl: COIN_INFO_TTL,
    });
    return coinInfo;
  }

  async delay(milliseconds: number) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
  }

  async insertCoinImage() {
    const matadata = await fetch(`${process.env.SPOT_URL_API}/api/v1/masterdata`, {
      method: "get",
    });
    const resp = await matadata.json();
    const coinImage = resp.data.coins;
    for (const item of coinImage) {
      const checkCoinInfo = await this.getCoinInfo(item.coin);
      const image = item.icon_image;
      if (checkCoinInfo && !checkCoinInfo.coin_image) {
        await this.coinInfoRepository.update({ symbol: item.coin }, { coin_image: image });
      }
    }
  }

  async getCurrentPriceWithBTC(symbol: string) {
    symbol = symbol.toLowerCase();
    const priceInCache = await this.cacheManager.get(`${CURRENT_PRICE_COIN_INFO_CACHE}_${symbol}`);
    if (priceInCache) {
      return priceInCache;
    }
    const coingeckoUrl = config.get<string>("coin_info.coingeckoUrl");
    const response = await fetch(
      `${coingeckoUrl}/coins/markets?vs_currency=btc&order=market_cap_desc&per_page=100&page=1&sparkline=false&locale=en`,
      {
        method: "get",
      }
    );
    const data = await response.json();
    const price = data?.find((d) => d.symbol?.toLowerCase() === symbol)?.current_price;
    if (!price) {
      throw new HttpException(httpErrors.SYMBOL_DOES_NOT_EXIST, HttpStatus.NOT_FOUND);
    }
    await this.cacheManager.set(`${CURRENT_PRICE_COIN_INFO_CACHE}_${symbol}`, price, {
      ttl: CURRENT_PRICE_COIN_INFO_TTL,
    });
    return price;
  }
}

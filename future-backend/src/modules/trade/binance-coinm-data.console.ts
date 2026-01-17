import { Injectable, Logger } from "@nestjs/common";
import axios from "axios";
import { Command, Console } from "nestjs-console";
import { RedisService } from "nestjs-redis";
import { InstrumentService } from "../instrument/instrument.service";
import { COINM } from "../transaction/transaction.const";
import { BINANCE_COINM_LAST_TRADE_PRICE, BINANCE_COINM_MARKET_PRICE, BINANCE_COINM_RECENT_TRADES } from "./binance-coinm.const";
import { BINANCE_DATA_TTL } from "./trade.const";
import { RedisClient } from "src/shares/redis-client/redis-client";

@Console()
@Injectable()
export class BinanceCoinmDataConsole {
  private BINANCE_URL_API: string = "https://dapi.binance.com";

  constructor(
    private readonly redisService: RedisService, 
    private readonly instrumentService: InstrumentService,
    private readonly redisClient: RedisClient
  ) {}

  @Command({
    command: "binance-api:get-coinm-trade-data",
    description: "Get recent trades, market price, last trade price from binance api",
  })
  async handleData(): Promise<void> {
    // get instrument with type coin_m
    const instruments = await this.instrumentService.getAllInstruments({ type: COINM });

    while (1) {
      try {
        for (const instrument of instruments) {
          this.handleBinanceCoinMData(instrument.symbol);
        }
        await this.waitInSecond(1);
      } catch (error) {
        Logger.error("Error fetching Binance data:", error.message);
      }
    }
  }

  private async handleBinanceCoinMData(symbol: string) {
    const convertedSymbol = this.convertBinanceSymbol(symbol);
    const [lastTradePrice, marketPrice, recentTrades] = await Promise.all([
      this.getBinanceLastTradePrice(convertedSymbol),
      this.getBinanceMarketPrice(convertedSymbol),
      this.getBinanceRecentTrades(convertedSymbol),
    ]);

    // set to redis
    this.redisClient.getInstance().set(`${BINANCE_COINM_LAST_TRADE_PRICE}${symbol}`, lastTradePrice, "EX", BINANCE_DATA_TTL);
    this.redisClient.getInstance().set(`${BINANCE_COINM_MARKET_PRICE}${symbol}`, marketPrice, "EX", BINANCE_DATA_TTL);
    this.redisService
      .getClient()
      .set(`${BINANCE_COINM_RECENT_TRADES}${symbol}`, JSON.stringify(recentTrades), "EX", BINANCE_DATA_TTL);
  }

  private waitInSecond(second: number): Promise<void> {
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve();
      }, second * 1000); // 1000 milliseconds = 1 second
    });
  }

  private async getBinanceRecentTrades(symbol: string) {
    const url = `${this.BINANCE_URL_API}/dapi/v1/trades?symbol=${symbol}&limit=10`;

    try {
      const response = await axios.get(url);
      const trades = response.data;
      return trades;
    } catch (error) {
      Logger.error("Error fetching Binance recent trades:", error);
    }
  }

  private async getBinanceMarketPrice(symbol: string) {
    const url = `${this.BINANCE_URL_API}/dapi/v1/premiumIndex?symbol=${symbol}`;

    try {
      const response = await axios.get(url);
      const price = response.data;
      return price[0].markPrice;
    } catch (error) {
      Logger.error("Error fetching Binance market price:", error);
    }
  }

  private async getBinanceLastTradePrice(symbol: string) {
    const url = `${this.BINANCE_URL_API}/dapi/v1/ticker/price?symbol=${symbol}`;

    try {
      const response = await axios.get(url);
      const lastPrice = response.data;
      return lastPrice[0].price;
    } catch (error) {
      Logger.error("Error fetching Binance last trade price:", error);
    }
  }

  private convertBinanceSymbol(symbol: string) {
    return symbol.replace("USDM", "USD_PERP");
  }
}

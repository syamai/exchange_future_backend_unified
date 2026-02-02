import { Injectable, OnModuleDestroy, OnModuleInit } from "@nestjs/common";
import { InstrumentTypes } from "src/shares/enums/instrument.enum";
import { FundingService } from "../funding/funding.service";
import { IndexService } from "../index/index.service";
import { InstrumentService } from "../instrument/instrument.service";
import WebSocket = require("ws");
import BigNumber from "bignumber.js";
import { BinanceTradeService } from "../trade/binance/binance-trade.service";
import { RedisClient } from "src/shares/redis-client/redis-client";

interface SymbolSocket {
  symbol: string;
  socket: WebSocket;
  retries: number;
  data: any;
  lastUpdated: number;
}

@Injectable()
export class BinanceTickerService implements OnModuleDestroy {
  private readonly baseUrl = "wss://fstream.binance.com/ws/";
  private connections: Map<string, SymbolSocket> = new Map();

  constructor(
    private readonly instrumentService: InstrumentService,
    private readonly indexService: IndexService,
    private readonly fundingService: FundingService,
    private readonly redisClient: RedisClient
  ) {}

  public async connectAll() {
    const symbols = (await this.instrumentService.getAllInstruments({ type: InstrumentTypes.USD_M })).map((ins) => ins.symbol);
    for (const symbol of symbols) {
      this.connectSymbol(symbol);
    }
  }

  private connectSymbol(symbol: string, retryDelay = 3000) {
    const url = `${this.baseUrl}${symbol.toLowerCase()}@ticker`;
    console.log(`[Binance] Connecting to ${symbol} stream...`);

    const socket = new WebSocket(url);
    const entry: SymbolSocket = { symbol, socket, retries: 0, data: null, lastUpdated: new Date().getTime() };
    this.connections.set(symbol, entry);

    socket.on("open", () => {
      console.log(`[Binance] ✅ Connected: ${symbol}`);
      entry.retries = 0; // reset retry counter
    });

    socket.on("message", async (raw: string) => {
      try {
        const binanceData = JSON.parse(raw);
        const btrData: any = this.mappingBinanceTicker({ ...binanceData, symbol });
        await this.addExtraInfoToTicker(btrData);
        // console.log(`[Binance] Ticker for ${symbol}:`, btrData);
        entry.data = btrData;
        entry.lastUpdated = new Date().getTime();
      } catch (err) {
        console.error(`[Binance] ❌ Parse error for ${symbol}:`, err.message);
      }
    });

    const handleReconnect = () => {
      if (entry.retries >= 10) {
        console.error(`[Binance] ❌ Too many retries for ${symbol}, giving up.`);
        return;
      }

      const delay = Math.min(retryDelay * Math.pow(2, entry.retries), 60000); // exponential backoff (max 60s)
      entry.retries++;

      console.warn(`[Binance] ⚠️ Reconnecting ${symbol} in ${delay / 1000}s...`);
      setTimeout(() => this.connectSymbol(symbol, retryDelay), delay);
    };

    socket.on("close", () => {
      console.warn(`[Binance] ⚠️ ${symbol} stream closed.`);
      handleReconnect();
    });

    socket.on("error", (err) => {
      console.error(`[Binance] ⚠️ ${symbol} stream error:`, err.message);
      socket.close(); // trigger close => reconnect
    });
  }

  private async addExtraInfoToTicker(ticker: any): Promise<void> {
    const [oraclePrices, fundingRates, nextFunding] = await Promise.all([
      this.indexService.getOraclePrices([ticker.symbol]),
      this.fundingService.getFundingRates([ticker.symbol]),
      this.fundingService.getNextFunding(ticker.symbol),
    ]);

    ticker.oraclePrice = oraclePrices[0];
    ticker.fundingRate = fundingRates[0];
    ticker.nextFunding = +nextFunding;
  }

  mappingBinanceTicker(data: any) {
    return {
      symbol: data.symbol,
      priceChange: data?.p || "0",
      priceChangePercent: data?.P || "0",
      lastPrice: data?.c || "0",
      lastPriceChange: new BigNumber(data?.P || "0").gte(0) ? '1' : '-1',
      highPrice: data?.h || "0",
      lowPrice: data?.l || "0",
      volume: data?.v || "0",
      quoteVolume: data?.q || "0",
      indexPrice: data?.c || "0",
      contractType: "USD_M",
      trades: [],
    };
  }

  onModuleDestroy() {
    console.log("[Binance] Closing all WebSocket connections...");
    for (const { socket } of this.connections.values()) {
      socket.close();
    }
  }

  public async getAllTickerData(): Promise<any[]> {
    console.log(`[getAllTickerData] connections size: ${this.connections.size}`);
    for (const connectionValue of this.connections.values()) {
      console.log(`[getAllTickerData] ${connectionValue.symbol}: data=${connectionValue.data ? 'exists' : 'null'}, lastPrice=${connectionValue.data?.lastPrice}`);
      if (new Date().getTime() - connectionValue.lastUpdated > 30000) {
        console.log(`Connection was lost. Reconnect ${connectionValue.symbol}`);

        this.connectSymbol(connectionValue.symbol);
      }
    }
    const symbols = Array.from(this.connections.values()).map((connectionValue) => connectionValue.symbol);

    const trades = await Promise.all(
      symbols.map(async (s) => {
        // Get binance trades data from Redis
        const size = 30
        const tradesStr = await this.redisClient.getInstance().lrange(`binance_trades:${s}`, 0, size - 1);
        return tradesStr.map((t) => JSON.parse(t));
        // return this.binanceTradeService.getTradeData(s, 30);
      })
    );
    const symbolTrades = new Map<string, any[]>();
    for (let i = 0; i < symbols.length; i++) {
      symbolTrades.set(symbols[i], trades[i] || []);
    }

    return Array.from(this.connections.values())
      .map((entry) => {
        return { ...entry.data, trades: symbolTrades.get(entry.symbol) };
      })
      .filter((data) => data !== null);
  }
}

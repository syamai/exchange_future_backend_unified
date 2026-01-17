import { Injectable, OnModuleDestroy, OnModuleInit } from "@nestjs/common";
import { InstrumentTypes } from "src/shares/enums/instrument.enum";
import WebSocket = require("ws");
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { RedisClient } from "src/shares/redis-client/redis-client";
import BigNumber from "bignumber.js";

interface Trade {
  buyerIsTaker: boolean;
  createdAt: number;
  id: number;
  price: string;
  quantity: string;
  symbol: string;
}

interface SymbolSocket {
  symbol: string;
  socket: WebSocket;
  retries: number;
  lastUpdated: number;
}

@Injectable()
export class BinanceTradeService implements OnModuleDestroy {
  private readonly baseUrl = "wss://fstream.binance.com/ws/";
  private connections: Map<string, SymbolSocket> = new Map();

  constructor(private readonly instrumentService: InstrumentService,
    private readonly redisClient: RedisClient) { }

  getRedisKey(symbol: string): string {
    return `binance_trades:${symbol}`;
  }

  public async connectAll() {
    const symbols = (await this.instrumentService.getAllInstruments({ type: InstrumentTypes.USD_M })).map((ins) => ins.symbol);
    for (const symbol of symbols) {
      this.connectSymbol(symbol);
    }
  }

  private connectSymbol(symbol: string, retryDelay = 3000) {
    const url = `${this.baseUrl}${symbol.toLowerCase()}@trade`;
    console.log(`[Binance] Connecting to binance ${symbol} trade stream...`);

    const socket = new WebSocket(url);
    const entry: SymbolSocket = { symbol, socket, retries: 0, lastUpdated: new Date().getTime() };
    this.connections.set(symbol, entry);

    socket.on("open", () => {
      console.log(`[Binance] ✅ Connected: ${symbol}`);
      entry.retries = 0; // reset retry counter
    });

    socket.on("message", async (raw: string) => {
      try {
        const binanceData = JSON.parse(raw);
        if (new BigNumber(binanceData.q).eq(0) || new BigNumber(binanceData.p).eq(0)) {
          return;
        }
        // console.log(`binanceData: `, binanceData);

        const btrTrade: Trade = this.mappingBinanceData({ ...binanceData, symbol });
        const redisKey = this.getRedisKey(symbol);
        // console.log(redisKey);

        await this.redisClient.getInstance().lpush(redisKey, JSON.stringify(btrTrade));
        await this.redisClient.getInstance().ltrim(redisKey, 0, 99);
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

  private mappingBinanceData(data: any) {
    return {
      buyerIsTaker: !data.m,
      createdAt: data.T,
      id: data.t,
      price: data.p,
      quantity: data.q,
      symbol: data.symbol,
    };
  }

  onModuleDestroy() {
    console.log("[Binance] Closing all WebSocket connections...");
    for (const { socket } of this.connections.values()) {
      socket.close();
    }
  }

  public async getTradeData(symbol: string, size: number = 10) {
    const connectionValue = this.connections.get(symbol);
    if (new Date().getTime() - connectionValue.lastUpdated > 30000) {
      console.log(`Connection was lost. Reconnect ${connectionValue.symbol}`);

      this.connectSymbol(connectionValue.symbol);
    }

    const tradesStr = await this.redisClient.getInstance().lrange(this.getRedisKey(symbol), 0, size - 1);

    return tradesStr.map((t) => JSON.parse(t));
  }
}

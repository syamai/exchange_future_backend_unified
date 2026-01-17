import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Command, Console } from "nestjs-console";
import { TradeEntity } from "src/models/entities/trade.entity";

import { TradeRepository } from "src/models/repositories/trade.repository";
import { TradeService } from "src/modules/trade/trade.service";
import { BinanceTradeService } from "./binance/binance-trade.service";
@Console()
@Injectable()
export default class TradeSeedCommand {
  constructor(
    @InjectRepository(TradeRepository, "master")
    public readonly tradeRepository: TradeRepository,
    private readonly tradeService: TradeService,
    private readonly binanceTradeService: BinanceTradeService
  ) {}

  @Command({
    command: "seed:market-trades",
    description: "seed market trades",
  })
  async seedTrades(): Promise<void> {
    await this.tradeRepository
      .createQueryBuilder()
      .insert()
      .into(TradeEntity)
      .values([
        {
          symbol: "BTCUSD",
          price: "17000",
          quantity: "1",
        },
        {
          symbol: "BTCUSD",
          price: "17300",
          quantity: "2",
          buyerIsTaker: true,
        },
        {
          symbol: "ETHUSD",
          price: "1200",
          quantity: "1",
          buyerIsTaker: true,
        },
        {
          symbol: "ETHUSDT",
          price: "1210",
          quantity: "1",
          buyerIsTaker: true,
        },
        {
          symbol: "BNBUSD",
          price: "240",
          quantity: "1",
          buyerIsTaker: true,
        },
        {
          symbol: "BNBUSDT",
          price: "250",
          quantity: "1",
          buyerIsTaker: true,
        },
        {
          symbol: "BTCUSD",
          price: "17350",
          quantity: "2",
          buyerIsTaker: false,
        },
        {
          symbol: "ETHUSD",
          price: "1350",
          quantity: "1",
          buyerIsTaker: false,
        },
        {
          symbol: "ETHUSDT",
          price: "1310",
          quantity: "1",
          buyerIsTaker: false,
        },
        {
          symbol: "BNBUSD",
          price: "231",
          quantity: "1",
          buyerIsTaker: false,
        },
        {
          symbol: "BNBUSDT",
          price: "260",
          quantity: "2",
          buyerIsTaker: false,
        },
      ])
      .execute();
  }

  @Command({
    command: "seed:update-trade",
    description: "update trade",
  })
  async updateTrade(): Promise<void> {
    await this.tradeService.updateTrade();
  }

  @Command({
    command: "trade:update-trade-email",
    description: "update trade-email",
  })
  async updateTradeEmail(): Promise<void> {
    await this.tradeService.updateTradeEmail();
  }

  @Command({
    command: "trade:test-update-trade-email [tradeId]",
    description: "update trade-email",
  })
  async testUpdateTradeEmail(tradeId: string): Promise<void> {
    await this.tradeService.testUpdateTradeEmail(tradeId);
  }

  @Command({
    command: "trade:publish-binance-trade",
    description: "Publish binance trade",
  })
  async publishBinanceTrade(): Promise<void> {
    console.log("Start publish binance trade...");
    try {
      this.binanceTradeService.connectAll();
    } catch (e) {
      console.error(`[publish-binance-trade]-error: , ${e}`);
    }
    return new Promise(() => {});
  }
}

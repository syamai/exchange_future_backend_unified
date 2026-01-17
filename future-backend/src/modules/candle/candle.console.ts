import { Injectable, Logger } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { CandleService } from "src/modules/candle/candle.service";

@Console()
@Injectable()
export class CandleConsole {
  private readonly logger = new Logger(CandleConsole.name);

  constructor(private readonly candleService: CandleService) {}

  @Command({
    command: "candles:sync-candles",
    description: "Save output from matching engine",
  })
  async syncCandles(): Promise<void> {
    await this.candleService.syncCandles();
  }

  @Command({
    command: "candles:sync-trades",
    description: "Save output from matching engine",
  })
  async syncTrades(): Promise<void> {
    await this.candleService.syncTrades();
  }
}

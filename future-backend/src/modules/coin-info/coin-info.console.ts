import { Injectable } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { CoinInfoService } from "./coin-info.service";

@Console()
@Injectable()
export class CoinInfoConsole {
  constructor(private coinInfoService: CoinInfoService) {}

  @Command({
    command: "crawler:coin-info",
    description: "Crawler coin info from coingecko",
  })
  async getCoinInfo(): Promise<void> {
    await this.coinInfoService.getInfo();
  }

  @Command({
    command: "coin-info:insert-image",
    description: "insert image in coin info",
  })
  async insertCoinInfo(): Promise<void> {
    await this.coinInfoService.insertCoinImage();
  }
}

import { Controller, Get, Query } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { CoinInfoService } from "./coin-info.service";

@ApiTags("CoinInfo")
@Controller("coin-info")
export class CoinInfoController {
  constructor(private readonly coinInfoService: CoinInfoService) {}
  @Get("")
  async index(@Query("symbol") coin: string) {
    return await this.coinInfoService.getCoinInfo(coin);
  }

  @Get("get-price-vs-btc")
  async getCurrentPriceWithBTC(@Query("symbol") coin: string) {
    return await this.coinInfoService.getCurrentPriceWithBTC(coin);
  }
}

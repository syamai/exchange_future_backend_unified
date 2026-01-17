import { Controller, Get, Query } from "@nestjs/common";
import { ApiQuery, ApiTags } from "@nestjs/swagger";
import { Ticker } from "src/modules/ticker/ticker.const";
import { TickerService } from "src/modules/ticker/ticker.service";
import { ResponseDto } from "src/shares/dtos/response.dto";

@Controller("ticker")
@ApiTags("Ticker")
export class TickerController {
  constructor(private readonly tickerService: TickerService) {}

  @Get("/24h")
  @ApiQuery({
    name: "contractType",
    required: false,
    example: "USD_M",
    enum: ["USD_M", "COIN_M"],
  })
  @ApiQuery({
    name: "symbol",
    required: false,
    example: "BTCUSDT",
  })
  async get(
    @Query("contractType") contractType: string,
    @Query("symbol") symbol: string
  ): Promise<ResponseDto<Ticker[]>> {
    return {
      data: await this.tickerService.getTickers(contractType, symbol),
    };
  }
}

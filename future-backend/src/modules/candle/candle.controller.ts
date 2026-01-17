import { Controller, Get, Param, Query, UseGuards } from "@nestjs/common";
import { ApiOperation, ApiTags } from "@nestjs/swagger";
import { Candle } from "src/modules/candle/candle.const";
import { CandleService } from "src/modules/candle/candle.service";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";

@Controller("candle")
@ApiTags("Candle")
export class CandlesController {
  constructor(private readonly candleService: CandleService) {}

  // @Get(":symbol/candles")
  // @ApiOperation({
  //   description:
  //     "Get candle data. From, to is timestamp of range time. Symbol get from /api/v1/ticker/24h",
  // })
  // get1m(
  //   @Param("symbol") symbol: string,
  //   @Query("from") from: number,
  //   @Query("to") to: number,
  //   @Query("resolution") resolution: string
  // ): Promise<Candle[]> {
  //   return this.candleService.getMergeCandles(
  //     symbol,
  //     from,
  //     to,
  //     resolution
  //   );
  // }

  @Get('replace-binance-candle')
  @UseGuards(JwtAdminGuard)
  async replaceBinanceCandle(
    @Query("symbol") symbol: string,
    @Query("fromTimeStr") fromTimeStr: string,
    @Query("toTimeStr") toTimeStr: string,
    @Query("resolution") resolution: number
  ) {
    return await this.candleService.replaceBinanceCandles({ symbol, fromTimeStr, toTimeStr, resolution });
  }

  @Get(":symbol/candles")
  @ApiOperation({
    description:
      "Get candle data. From, to is timestamp of range time. Symbol get from /api/v1/ticker/24h",
  })
  get1m(
    @Param("symbol") symbol: string,
    @Query("from") from: number,
    @Query("to") to: number,
    @Query("resolution") resolution: string
  ): Promise<Candle[]> {
    return this.candleService.getCandlesFromCacheV2(
      symbol,
      from,
      to,
      resolution
    );
  }
}

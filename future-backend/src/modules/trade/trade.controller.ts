import { TradesDto } from "./dto/get-trades.dto";
import { AdminTradeDto } from "./dto/admin-trade.dto";
import { Body, Controller, Get, HttpException, HttpStatus, Param, Post, Query, Req, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiParam, ApiTags } from "@nestjs/swagger";
import { TradeEntity } from "src/models/entities/trade.entity";
import { AccountService } from "src/modules/account/account.service";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { FillDto } from "src/modules/trade/dto/get-fills.dto";
import { TradeService } from "src/modules/trade/trade.service";
import { UserID } from "src/shares/decorators/get-user-id.decorator";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { TradeHistoryDto } from "./dto/trade-history.dto";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { GetTradesPartnerDto } from "./dto/get-trades-partner.dto";
import { JwtTokenGuard } from "../auth/guards/jwt-token.guard";
import { httpErrors } from "src/shares/exceptions";
import { BinanceTradeService } from "./binance/binance-trade.service";

@Controller("trade")
@ApiTags("Trade")
@ApiBearerAuth()
export class TradeController {
  constructor(
    private readonly tradeService: TradeService, 
    private readonly accountService: AccountService, 
    private readonly binanceTradeService: BinanceTradeService
  ) {}

  @Post("/fill")
  @UseGuards(JwtAuthGuard)
  async getFillTrade(
    @UserID() userId: number,
    @Query() paging: PaginationDto,
    @Body() tradeHistoryDto: TradeHistoryDto
  ): Promise<ResponseDto<FillDto[]>> {
    const response = await this.tradeService.getFillTrade(userId, paging, tradeHistoryDto);
    return response;
  }

  @Get("/trade-history-for-partner")
  async getTradeForPartner(
    @Query() queries: GetTradesPartnerDto,
    @Body("futureUser") futureUser: string,
    @Body("futurePassword") futurePassword: string
  ) {
    const futureUserEnv = process.env.FUTURE_USER;
    const futurePasswordEnv = process.env.FUTURE_PASSWORD;

    if (futureUser !== futureUserEnv || futurePassword !== futurePasswordEnv) {
      throw new HttpException(httpErrors.UNAUTHORIZED, HttpStatus.UNAUTHORIZED);
    }

    return this.tradeService.getTradesHistoryForPartner(queries);
  }

  // @Get("/:symbol")
  // @ApiParam({
  //   name: "symbol",
  //   example: "BTCUSD",
  //   required: true,
  // })
  // async getRecentTrades(@Param("symbol") symbol: string, @Query() paging: PaginationDto): Promise<ResponseDto<TradeEntity[]>> {
  //   return {
  //     data: await this.tradeService.getRecentTrades(symbol, paging),
  //   };
  // }

  @Get("/:symbol")
  @ApiParam({
    name: "symbol",
    example: "BTCUSD",
    required: true,
  })
  async getRecentTrades(@Req() req, @Param("symbol") symbol: string, @Query() paging: PaginationDto): Promise<ResponseDto<TradeEntity[]>> {
    const forwarded = req.headers['x-forwarded-for'];
    const ip = Array.isArray(forwarded)
      ? forwarded[0]
      : (forwarded ? forwarded.split(',')[0] : req.ip);
    
    console.log('ID: ', ip);
    const trades = await this.tradeService.getBinanceTradeDataFromRedis(symbol, paging.size);
    // const trades = await this.binanceTradeService.getTradeData(symbol, paging.size);
    
    return {
      data: (trades as any) as TradeEntity[]
    };
  }

  @Get()
  @UseGuards(JwtAdminGuard)
  async getTrades(@Query() paging: PaginationDto, @Query() queries: AdminTradeDto): Promise<ResponseDto<TradesDto[]>> {
    const trades = await this.tradeService.getTrades(paging, queries);

    return trades;
  }

  @Get("trade-history/excel")
  @UseGuards(JwtAdminGuard)
  async exportTradesExcel(@Query() paging: PaginationDto, @Query() queries: AdminTradeDto) {
    return await this.tradeService.exportTradeAdminExcelFile(paging, queries);
  }
}

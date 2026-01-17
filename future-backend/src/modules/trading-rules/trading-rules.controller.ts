import { Body, Controller, Get, Post, Query } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { TradingRulesModeDto } from "./dto/trading-rules.dto";
import { TradingRulesService } from "./trading-rule.service";

@Controller("trading-rules")
@ApiTags("tradingRules")
export class TradingRulesController {
  constructor(private readonly tradingRulesService: TradingRulesService) {}

  @Post()
  async updateMarginMode(@Body() input: TradingRulesModeDto) {
    return {
      data: await this.tradingRulesService.insertOrUpdateTradingRules(input),
    };
  }

  @Get("symbol")
  async getTradingRuleByInstrumentId(@Query("symbol") symbol: string) {
    return {
      data: await this.tradingRulesService.getTradingRuleByInstrumentId(symbol),
    };
  }

  @Get()
  async getAllTradingRules(@Query() input: PaginationDto) {
    return {
      data: await this.tradingRulesService.getAllTradingRules(input),
    };
  }
}

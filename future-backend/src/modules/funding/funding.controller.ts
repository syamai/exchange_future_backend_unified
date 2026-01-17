import { Controller, Get, Param, Query } from "@nestjs/common";
import { ApiBearerAuth, ApiParam, ApiQuery, ApiTags } from "@nestjs/swagger";
import { FundingEntity } from "src/models/entities/funding.entity";
import { AccountService } from "src/modules/account/account.service";
import { FundingService } from "src/modules/funding/funding.service";
import { FromToDto } from "src/shares/dtos/from-to.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";

@Controller("funding")
@ApiTags("Funding")
@ApiBearerAuth()
export class FundingController {
  constructor(
    private readonly fundingService: FundingService,
    private readonly accountService: AccountService
  ) {}

  @Get("/history")
  @ApiQuery({
    name: "symbol",
    example: "BTCUSD",
    required: false,
  })
  async getFundingHistoryByAccountId(@Query("symbol") symbol: string) {
    return await this.fundingService.getFundingHistoryByAccountId(symbol);
  }

  @Get("/rate/:symbol")
  @ApiParam({
    name: "symbol",
    example: "BTCUSD",
    required: true,
  })
  async getFundingRatesFromTo(
    @Param("symbol") symbol: string,
    @Query() fromTo: FromToDto
  ): Promise<ResponseDto<FundingEntity[]>> {
    const fundingRates = await this.fundingService.getFundingRatesFromTo(
      symbol,
      fromTo
    );
    return {
      data: fundingRates,
    };
  }
}

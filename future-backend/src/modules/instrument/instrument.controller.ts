import { Body, Controller, Get, Param, Patch, Post, Put, Query, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { InstrumentEntity } from "src/models/entities/instrument.entity";
import { JwtAuthGuard } from "src/modules/auth/guards/jwt-auth.guard";
import { ContractDto, ContractListDto, UpdateContractDto, UpdateContractDto_v2 } from "src/modules/instrument/dto/create-instrument.dto";
import { UpdateInstrumentDto } from "src/modules/instrument/dto/update-instrument.dto";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { AdminAndSuperAdmin } from "src/shares/decorators/roles.decorator";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { GetInstrumentDto } from "./dto/get-instrument.dto";
import { CreateMarketFeeDto } from "./dto/create-market-free.dto";
import { MarketFeeEntity } from "src/models/entities/market_fee.entity";
import { UpdateMarketFeeDto } from "./dto/update-market-fee.dto";
import { JwtAdminGuard } from "../auth/guards/jwt.admin.guard";
import { IsTestingRequest } from "src/shares/decorators/is-testing-request.decorator";

@Controller("instruments")
@ApiTags("Instrument")
export class InstrumentController {
  constructor(private readonly instrumentService: InstrumentService) {}

  @Get()
  async getAllInstruments(@Query() query: GetInstrumentDto, @IsTestingRequest() isTesting: boolean): Promise<ResponseDto<InstrumentEntity[]>> {
    return {
      data: await this.instrumentService.getAllInstruments(query, isTesting),
    };
  }

  @Get("/symbol/:symbol")
  async getInstrumentsBySymbol(@Param("symbol") symbol: string): Promise<ResponseDto<InstrumentEntity>> {
    return {
      data: await this.instrumentService.getInstrumentsBySymbol(symbol),
    };
  }

  @Post()
  @ApiBearerAuth()
  @UseGuards(JwtAdminGuard)
  async createInstrument(@Body() contractDto: ContractDto) {
    return await this.instrumentService.createInstrument(contractDto);
  }

  @Get("list-contract")
  @ApiBearerAuth()
  @UseGuards(JwtAdminGuard)
  async getContractList(@Query() input: ContractListDto) {
    return {
      data: await this.instrumentService.getContractList(input),
    };
  }

  @Get("detail-contract")
  @ApiBearerAuth()
  @UseGuards(JwtAdminGuard)
  async detailContract(@Query("underlyingSymbol") underlyingSymbol: string) {
    return {
      data: await this.instrumentService.detailContract(underlyingSymbol),
    };
  }

  //   @Put("update-contract")
  //   @ApiBearerAuth()
  //   @UseGuards(JwtAdminGuard)
  //   async updateContract(@Body() updateContractDto: UpdateContractDto) {
  //     return {
  //       data: await this.instrumentService.updateContract(updateContractDto),
  //     };
  //   }

  @Put("update-contract")
  @ApiBearerAuth()
  @UseGuards(JwtAdminGuard)
  async updateContract(@Body() updateContractDto: UpdateContractDto_v2) {
    return {
      data: await this.instrumentService.updateContract_v2(updateContractDto),
    };
  }

  @Get('pair-fee')
  async getPairFee(@Query() dto: ContractListDto) {
    return await this.instrumentService.getPairFee(dto);
  }

  @Get("/:instrumentId")
  async getInstrumentsById(@Param("instrumentId") id: number): Promise<ResponseDto<InstrumentEntity>> {
    return {
      data: await this.instrumentService.getInstrumentsById(id),
    };
  }

  @Patch("/:instrumentId")
  @UseGuards(JwtAuthGuard, AdminAndSuperAdmin)
  async updateInstrument(
    @Param("instrumentId") instrumentId: number,
    @Body() updateInstrumentDto: UpdateInstrumentDto
  ): Promise<ResponseDto<InstrumentEntity>> {
    return {
      data: await this.instrumentService.updateInstrument(instrumentId, updateInstrumentDto),
    };
  }

  @Post("create-market-fee")
  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard, AdminAndSuperAdmin)
  async createMarketFeeByInstrument(@Body() createMarketFeeDto: CreateMarketFeeDto): Promise<ResponseDto<MarketFeeEntity>> {
    return {
      data: await this.instrumentService.createMarketFeeByInstrument(createMarketFeeDto),
    };
  }

  @Post("update-market-fee")
  @ApiBearerAuth()
  @UseGuards(JwtAuthGuard, AdminAndSuperAdmin)
  async updateMarketFeeByInstrument(@Body() updateMarketFeeDto: UpdateMarketFeeDto): Promise<ResponseDto<MarketFeeEntity>> {
    return {
      data: await this.instrumentService.updateMarketFeeByInstrument(updateMarketFeeDto),
    };
  }
}

import { CACHE_MANAGER, Inject, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { TpSlType, OrderType } from "src/shares/enums/order.enum";
import { Cache } from "cache-manager";
import { TradingRulesService } from "../trading-rules/trading-rule.service";
import { CoinInfoService } from "../coin-info/coin-info.service";
import { LeverageMarginService } from "../leverage-margin/leverage-margin.service";
import { InstrumentService } from "../instrument/instrument.service";

@Injectable()
export class MasterDataService {
  constructor(
    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepoReport: InstrumentRepository,
    @InjectRepository(InstrumentRepository, "master")
    public readonly instrumentRepoMaster: InstrumentRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly tradingRulesService: TradingRulesService,
    private readonly coinInfoService: CoinInfoService,
    private readonly leverageMarginService: LeverageMarginService,
    private readonly instrumentService: InstrumentService
  ) {}
  async getMasterData() {
    const orderType = { ...OrderType, ...TpSlType };
    const [
      tradingRules,
      coinInfo,
      leverageMargin,
      symbols,
      coinM,
    ] = await Promise.all([
      this.tradingRulesService.getAllTradingRulesNoLimit(),
      this.coinInfoService.getAllCoinInfo(),
      this.leverageMarginService.findAll(),
      this.instrumentService.getAllSymbolInstrument(),
      this.instrumentService.getAllSymbolCoinMInstrument(),
    ]);
    return {
      orderType,
      symbols,
      tradingRules,
      coinInfo,
      leverageMargin,
      coinM,
    };
  }
}

import { CACHE_MANAGER, Inject, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { LeverageMarginEntity } from "src/models/entities/leverage-margin.entity";
import { LeverageMarginRepository } from "src/models/repositories/leverage-margin.repository";
import { LeverageMarginDto } from "./dto/leverage-margin.dto";
import { BaseEngineService } from "../matching-engine/base-engine.service";
import { Cache } from "cache-manager";
import {
  LEVERAGE_MARGIN_CACHE,
  LEVERAGE_MARGIN_TTL,
} from "./leverage-margin.constants";
import _ from "lodash";
import { ContractType } from "src/shares/enums/order.enum";

@Injectable()
export class LeverageMarginService extends BaseEngineService {
  constructor(
    @InjectRepository(LeverageMarginRepository, "report")
    public readonly leverageMarginRepoReport: LeverageMarginRepository,
    @InjectRepository(LeverageMarginRepository, "master")
    public readonly leverageMarginRepoMaster: LeverageMarginRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache
  ) {
    super();
  }
  async findAll(): Promise<LeverageMarginEntity[]> {
    const leverageMarginCache = await this.cacheManager.get<
      LeverageMarginEntity[]
    >(LEVERAGE_MARGIN_CACHE);
    if (leverageMarginCache) {
      return leverageMarginCache;
    }
    const leverageMargin = await this.leverageMarginRepoReport.find();
    await this.cacheManager.set(LEVERAGE_MARGIN_CACHE, leverageMargin, {
      ttl: LEVERAGE_MARGIN_TTL,
    });
    return leverageMargin;
  }

  async findBy(arg: any): Promise<LeverageMarginEntity[]> {
    const leverageMargin = await this.leverageMarginRepoReport.find(arg);
    return leverageMargin;
  }
  async findAllByContract(
    contractType: ContractType
  ): Promise<LeverageMarginEntity[]> {
    const leverageMarginCache = await this.cacheManager.get<
      LeverageMarginEntity[]
    >(`${LEVERAGE_MARGIN_CACHE}_${contractType}`);
    if (leverageMarginCache) {
      return leverageMarginCache;
    }
    const leverageMargin = await this.leverageMarginRepoReport.find({
      where: {
        contractType: contractType,
      },
    });
    await this.cacheManager.set(
      `${LEVERAGE_MARGIN_CACHE}_${contractType}`,
      leverageMargin,
      {
        ttl: LEVERAGE_MARGIN_TTL,
      }
    );
    return leverageMargin;
  }
  async upsertLeverageMargin(
    leverageMarginDto: LeverageMarginDto
  ): Promise<LeverageMarginEntity> {
    const hasLeverageMargin = await this.leverageMarginRepoReport.getLeverageMargin(
      {
        tier: leverageMarginDto.tier,
      }
    );
    if (!hasLeverageMargin) {
      return await this.leverageMarginRepoMaster.save(leverageMarginDto);
    }
    Object.keys(leverageMarginDto).map((item) => {
      hasLeverageMargin[`${item}`] = leverageMarginDto[`${item}`];
    });
    await this.leverageMarginRepoMaster.save(hasLeverageMargin);
    const leverageMargin = await this.leverageMarginRepoReport.findOne({
      tier: leverageMarginDto.tier,
    });
    const leverageMarginCache = await this.leverageMarginRepoReport.find();
    await this.cacheManager.set(LEVERAGE_MARGIN_CACHE, leverageMarginCache, {
      ttl: LEVERAGE_MARGIN_TTL,
    });

    return leverageMargin;
  }

  async findAllAndGetInstrumentData() {
    const leverageMarginCache = await this.cacheManager.get(
      LEVERAGE_MARGIN_CACHE
    );
    if (leverageMarginCache) {
      return leverageMarginCache;
    }
  }
}

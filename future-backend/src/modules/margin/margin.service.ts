import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { AccountRepository } from "src/models/repositories/account.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { BaseEngineService } from "src/modules/matching-engine/base-engine.service";

@Injectable()
export class MarginService extends BaseEngineService {
  constructor(
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepoReport: PositionRepository,
    @InjectRepository(PositionRepository, "master")
    public readonly positionRepoMaster: PositionRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(AccountRepository, "master")
    public readonly accountRepoMaster: AccountRepository
  ) {
    super();
  }
}

import { FundingEntity } from "src/models/entities/funding.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";

@EntityRepository(FundingEntity)
export class FundingRepository extends BaseRepository<FundingEntity> {}

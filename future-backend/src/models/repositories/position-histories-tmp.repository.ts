import { EntityRepository, Repository } from "typeorm";
import { PositionHistoriesTmpEntity } from "../entities/position-histories-tmp.entity";

@EntityRepository(PositionHistoriesTmpEntity)
export class PositionHistoriesTmpRepository extends Repository<PositionHistoriesTmpEntity> {}

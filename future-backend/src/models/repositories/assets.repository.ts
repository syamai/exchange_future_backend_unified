import { EntityRepository } from "typeorm";
import { AssetsEntity } from "../entities/assets.entity";
import { BaseRepository } from "typeorm-transactional-cls-hooked";

@EntityRepository(AssetsEntity)
export class AssetsRepository extends BaseRepository<AssetsEntity> {}

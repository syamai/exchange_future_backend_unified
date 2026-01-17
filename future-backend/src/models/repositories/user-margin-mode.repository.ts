import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository } from "typeorm";
import { UserMarginModeEntity } from "../entities/user-margin-mode.entity";

@EntityRepository(UserMarginModeEntity)
export class UserMarginModeRepository extends BaseRepository<UserMarginModeEntity> {}

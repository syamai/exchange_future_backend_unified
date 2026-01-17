import { SettingEntity } from "src/models/entities/setting.entity";
import { EntityRepository, Repository } from "typeorm";

@EntityRepository(SettingEntity)
export class SettingRepository extends Repository<SettingEntity> {}

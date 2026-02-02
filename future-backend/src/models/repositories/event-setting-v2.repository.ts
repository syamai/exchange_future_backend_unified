import { EntityRepository } from "typeorm";
import { EventSettingV2Entity } from "src/models/entities/event-setting-v2.entity";
import { BaseRepository } from "src/models/repositories/base.repository";

@EntityRepository(EventSettingV2Entity)
export class EventSettingV2Repository extends BaseRepository<EventSettingV2Entity> {}

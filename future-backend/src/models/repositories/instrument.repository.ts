import { EntityRepository, Repository } from "typeorm";
import { InstrumentEntity } from "src/models/entities/instrument.entity";

@EntityRepository(InstrumentEntity)
export class InstrumentRepository extends Repository<InstrumentEntity> {}

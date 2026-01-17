import { EntityRepository, Repository } from "typeorm";
import { MetadataEntity } from "src/models/entities/metadata.entity";

@EntityRepository(MetadataEntity)
export class MetadataRepository extends Repository<MetadataEntity> {}

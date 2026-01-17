import { EntityRepository } from "typeorm";
import { LatestSignatureEntity } from "src/models/entities/latest-signature.entity";
import { AppRepository } from "src/shares/helpers/app.repository";

@EntityRepository(LatestSignatureEntity)
export class LatestSignatureRepository extends AppRepository<LatestSignatureEntity> {}

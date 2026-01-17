import { EntityRepository } from "typeorm";
import { AppRepository } from "src/shares/helpers/app.repository";
import { DexAction } from "src/models/entities/dex-action-entity";

@EntityRepository(DexAction)
export class DexActionRepository extends AppRepository<DexAction> {}

import { EntityRepository } from "typeorm";
import { DexActionSolTxEntity } from "src/models/entities/dex-action-sol-tx.entity";
import { AppRepository } from "src/shares/helpers/app.repository";

@EntityRepository(DexActionSolTxEntity)
export class DexActionSolTxRepository extends AppRepository<DexActionSolTxEntity> {}

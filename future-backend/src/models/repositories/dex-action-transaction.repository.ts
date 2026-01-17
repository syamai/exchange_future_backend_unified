import { EntityRepository } from "typeorm";
import { AppRepository } from "src/shares/helpers/app.repository";
import { DexActionTransaction } from "src/models/entities/dex-action-transaction-entity";

@EntityRepository(DexActionTransaction)
export class DexActionTransactionRepository extends AppRepository<DexActionTransaction> {}

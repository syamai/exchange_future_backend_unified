import { EntityRepository } from "typeorm";
import { AppRepository } from "src/shares/helpers/app.repository";
import { DexActionHistory } from "src/models/entities/dex-action-history";

@EntityRepository(DexActionHistory)
export class DexActionHistoryRepository extends AppRepository<DexActionHistory> {}

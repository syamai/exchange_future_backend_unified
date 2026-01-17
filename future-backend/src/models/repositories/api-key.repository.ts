import { EntityRepository } from "typeorm";
import { AppRepository } from "src/shares/helpers/app.repository";
import { ApiKey } from "src/models/entities/api-key.entity";

@EntityRepository(ApiKey)
export class ApiKeyRepository extends AppRepository<ApiKey> {}

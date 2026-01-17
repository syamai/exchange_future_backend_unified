import { AppRepository } from "src/shares/helpers/app.repository";
import { EntityRepository } from "typeorm";
import { AccessToken } from "../entities/access-tokens.entity";

@EntityRepository(AccessToken)
export class AccessTokenRepository extends AppRepository<AccessToken> {}

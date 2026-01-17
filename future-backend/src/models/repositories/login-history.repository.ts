import { EntityRepository, Repository } from "typeorm";
import { LoginHistoryEntity } from "src/models/entities/login-history.entity";

@EntityRepository(LoginHistoryEntity)
export class LoginHistoryRepository extends Repository<LoginHistoryEntity> {}

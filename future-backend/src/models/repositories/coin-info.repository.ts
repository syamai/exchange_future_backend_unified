import { EntityRepository, Repository } from "typeorm";
import { CoinInfoEntity } from "../entities/coin-info.entity";

@EntityRepository(CoinInfoEntity)
export class CoinInfoRepository extends Repository<CoinInfoEntity> {}

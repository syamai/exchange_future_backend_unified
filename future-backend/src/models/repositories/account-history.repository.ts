import { AccountHistoryEntity } from "src/models/entities/account-history.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository, In, InsertResult } from "typeorm";

@EntityRepository(AccountHistoryEntity)
export class AccountHistoryRepository extends BaseRepository<AccountHistoryEntity> {
  getAccountBalanceFromTo(
    accountId: number,
    from: Date,
    to: Date
  ): Promise<AccountHistoryEntity[]> {
    return this.find({
      accountId: accountId,
      createdAt: In([from, to]),
    });
  }
  async batchSave(entities: AccountHistoryEntity[]): Promise<InsertResult> {
    if (entities.length == 0) {
      return;
    }
    const placeHolders = entities.map(() => "(?, ?)").join(",");
    let sql = "";
    sql += "INSERT INTO `account_histories` (`accountId`,`balance`)";
    sql += ` VALUES ${placeHolders}`;

    const params = [];

    for (const entity of entities) {
      params.push(entity.accountId);
      params.push(entity.balance);
    }
    await this.manager.query(sql, params);
  }
}

import { AccountEntity } from "src/models/entities/account.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { EntityRepository, In } from "typeorm";

@EntityRepository(AccountEntity)
export class AccountRepository extends BaseRepository<AccountEntity> {
  async getFirstAccountByAddress(address: string): Promise<AccountEntity> {
    const result = await this.createQueryBuilder("accounts")
      .select("accounts.*, users.address as address")
      .innerJoin("users", "users", "users.id = accounts.id")
      .andWhere("users.address = :address", { address: address })
      .take(1)
      .execute();
    return result.length > 0 ? result[0] : undefined;
  }

  async getAccountsByIds(ids: string[]): Promise<AccountEntity[]> {
    return this.find({ where: { id: In(ids) } });
  }

  async getAccountsById(id: number): Promise<AccountEntity> {
    return this.findOne({ where: { id } });
  }
}

import { TransactionEntity } from "src/models/entities/transaction.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import {
  TransactionStatus,
  TransactionType,
} from "src/shares/enums/transaction.enum";
import { EntityRepository } from "typeorm";

@EntityRepository(TransactionEntity)
export class TransactionRepository extends BaseRepository<TransactionEntity> {
  async findRecentDeposits(
    date: Date,
    fromId: number,
    count: number
  ): Promise<TransactionEntity[]> {
    return this.createQueryBuilder()
      .where("id > :fromId", { fromId })
      .andWhere("createdAt >= :createdAt", { createdAt: date })
      .andWhere("type = :type", { type: TransactionType.DEPOSIT })
      .orderBy("createdAt", "ASC")
      .take(count)
      .getMany();
  }

  async findPendingWithdrawals(
    fromId: number,
    count: number
  ): Promise<TransactionEntity[]> {
    return this.createQueryBuilder()
      .where("id > :fromId", { fromId })
      .andWhere("type = :type", { type: TransactionType.WITHDRAWAL })
      .andWhere("status = :status", { status: TransactionStatus.PENDING })
      .orderBy("id", "ASC")
      .take(count)
      .getMany();
  }
}

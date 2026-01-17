/* eslint-disable @typescript-eslint/no-unused-vars */
import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import * as moment from "moment";
import { Dex } from "src/configs/dex.config";
import { SolDex } from "src/configs/sol-dex.config";
import { DexActionSolTxEntity } from "src/models/entities/dex-action-sol-tx.entity";
import { LatestBlockEntity } from "src/models/entities/latest-block.entity";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { DexActionSolTxRepository } from "src/models/repositories/dex-action-sol-txs.repository";
import { LatestBlockRepository } from "src/models/repositories/latest-block.repository";
import { LatestSignatureRepository } from "src/models/repositories/latest-signature.repository";
import { SettingRepository } from "src/models/repositories/setting.repository";
import { TransactionRepository as TransactionEntityRepository } from "src/models/repositories/transaction.repository";
import { AccountService } from "src/modules/account/account.service";
import { LatestBlockServices } from "src/modules/latest-block/latest-block.const";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { InstrumentTypes } from "src/shares/enums/instrument.enum";
import {
  TransactionStatus,
  TransactionType,
  TransactionHistory,
  AssetType,
} from "src/shares/enums/transaction.enum";
import { SotaDexWrapper } from "src/shares/helpers/sotadex-wrapper";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { MoreThan, Transaction, TransactionRepository } from "typeorm";
import { COINM } from "../instrument/instrument.const";
import { TransactionHistoryDto } from "./dto/transaction.dto";
import {
  GET_NUMBER_RECORD,
  LIST_COINM,
  LIST_USDM,
  START_CRAWL,
} from "./transaction.const";
import { AdminGetTransactionByUserDto } from "./dto/admin-get-transactions-by-user.dto";
import { getQueryLimit } from "src/shares/pagination-util";
import { MAX_RESULT_COUNT } from "../trade/trade.const";

const { dexProgram, dexId, usdcId } = SolDex;
const amountScale = Math.pow(10, Dex.collateralDecimal);
@Injectable()
export class TransactionService {
  private readonly dexWrapper: SotaDexWrapper;
  private batchSize = 100;

  constructor(
    private readonly logger: Logger,
    private readonly kafkaClient: KafkaClient,
    @InjectRepository(TransactionEntityRepository, "master")
    public readonly transactionRepoMaster: TransactionEntityRepository,
    @InjectRepository(TransactionEntityRepository, "report")
    public readonly transactionRepoReport: TransactionEntityRepository,
    @InjectRepository(SettingRepository, "report")
    public readonly settingRepoReport: SettingRepository,
    @InjectRepository(LatestSignatureRepository, "report")
    private reportLatestSignatureRepo: LatestSignatureRepository,
    @InjectRepository(AccountRepository, "master")
    private accountRepoMaster: AccountRepository,
    @InjectRepository(DexActionSolTxRepository, "report")
    private reportDexActionSolTxRepo: DexActionSolTxRepository,
    private readonly accountService: AccountService
  ) {
    this.dexWrapper = new SotaDexWrapper(dexProgram, dexId, usdcId);
  }

  // @Transaction({ connectionName: 'master' })
  // async processTransactionEvents(
  //   events,
  //   block: number,
  //   @TransactionRepository(LatestBlockEntity) transactionRepositoryLatestBlock?: LatestBlockRepository,
  //   @TransactionRepository(LatestBlockEntity) transactionRepositoryTransaction?: TransactionEntityRepository,
  // ): Promise<TransactionEntity[]> {
  //   const transactions = [];

  //   for (const event of events) {
  //     this.logger.log(`Processing event: ${JSON.stringify(event)}`);
  //     const address = event.args.user.toLowerCase();
  //     let account;
  //     try {
  //       account = await this.accountService.getFirstAccountByAddress(address);
  //     } catch (error) {
  //       this.logger.log(`Cannot find user ${address}`);
  //     }
  //     const transaction = new TransactionEntity();
  //     transaction.userId = account?.ownerId || -1;
  //     transaction.accountId = account?.id || -1;
  //     transaction.amount = new BigNumber(event.args.usdcAmount.toString()).div(amountScale).toString();
  //     transaction.fee = '0';
  //     transaction.status = TransactionStatus.PENDING;
  //     transaction.type = TransactionType.DEPOSIT;
  //     transaction.txHash = event.transactionHash;

  //     const result = await transactionRepositoryTransaction.save(transaction);
  //     transactions.push(result);
  //   }

  //   await transactionRepositoryLatestBlock.saveLatestBlock(LatestBlockServices.TransactionCrawler, block);

  //   return transactions;
  // }

  // async getUnProcessTxs(): Promise<DexActionSolTxEntity[]> {
  //   const service = LatestBlockServices.TransactionCrawler;
  //   this.logger.log(`Crawl deposit events limit=${this.batchSize}`);
  //   const latestSignatureRecord = await this.reportLatestSignatureRepo.findOne({
  //     service,
  //   });
  //   const latestSignature = latestSignatureRecord?.signature || null;
  //   let findOptions = {};
  //   if (latestSignature) {
  //     const signatureRecord = await this.reportDexActionSolTxRepo.findOne({ txid: latestSignature });
  //     if (!signatureRecord) {
  //       throw new Error(`${latestSignature} not found`);
  //     }
  //     findOptions = { id: MoreThan(signatureRecord.id) };
  //   }
  //   const unProcessSignatures = await this.reportDexActionSolTxRepo.find({
  //     where: findOptions,
  //     take: this.batchSize,
  //     order: { id: 'ASC' },
  //   });
  //   this.logger.log(`Processing ${unProcessSignatures.length} signatures`);
  //   return unProcessSignatures;
  // }

  // @Transaction({ connectionName: 'master' })
  // async processSolanaDepositEvents(
  //   @TransactionRepository(LatestBlockEntity) transactionRepositoryLatestSignature?: LatestSignatureRepository,
  //   @TransactionRepository(LatestBlockEntity) transactionRepositoryTransaction?: TransactionEntityRepository,
  // ): Promise<{ transactions: TransactionEntity[]; hasMore: boolean }> {
  //   const solanaTransactions = await this.getUnProcessTxs();
  //   if (solanaTransactions.length === 0) {
  //     console.log(`Empty getUnProcessSignatures`);
  //     return { transactions: [], hasMore: false };
  //   }

  //   const transactions = [];
  //   for (const solanaTransaction of solanaTransactions) {
  //     // if (solanaTransaction.meta.err) {
  //     //   continue;
  //     // }

  //     const logMessages = JSON.parse(solanaTransaction.logs);
  //     if (logMessages.includes('Log truncated')) {
  //       throw new Error(`Log truncated ${solanaTransaction.txid}`);
  //     }
  //     const events = this.dexWrapper.extractEvents(logMessages).filter((e) => e.name === 'DepositEvent');

  //     console.log(`Processing ${events.length} events`);

  //     for (let i = 0; i < events.length; i++) {
  //       const currentEvent = events[i];
  //       const address = currentEvent.data.user.toBase58();
  //       let account;
  //       try {
  //         account = await this.accountService.getFirstAccountByAddress(address);
  //       } catch (error) {
  //         this.logger.log(`Cannot find user ${address}`);
  //       }

  //       const transaction = new TransactionEntity();
  //       transaction.userId = account?.ownerId || -1;
  //       transaction.accountId = account?.id || -1;
  //       transaction.amount = new BigNumber(currentEvent.data.usdcAmount.toString()).div(amountScale).toString();
  //       transaction.fee = '0';
  //       transaction.status = TransactionStatus.PENDING;
  //       transaction.type = TransactionType.DEPOSIT;
  //       transaction.txHash = solanaTransaction.txid;
  //       transaction.logIndex = i;

  //       const result = await transactionRepositoryTransaction.save(transaction);
  //       transactions.push(result);
  //     }
  //   }

  //   const latestSignature = solanaTransactions[solanaTransactions.length - 1];
  //   await transactionRepositoryLatestSignature.insertOnDuplicate(
  //     [{ service: LatestBlockServices.TransactionCrawler, signature: latestSignature.txid }],
  //     ['signature', 'updatedAt'],
  //     ['service'],
  //   );

  //   return { transactions, hasMore: solanaTransactions.length < this.batchSize };
  // }

  async findRecentDeposits(
    date: Date,
    fromId: number,
    count: number
  ): Promise<TransactionEntity[]> {
    return await this.transactionRepoMaster.findRecentDeposits(
      date,
      fromId,
      count
    );
  }

  async findPendingWithdrawals(
    fromId: number,
    count: number
  ): Promise<TransactionEntity[]> {
    return await this.transactionRepoMaster.findPendingWithdrawals(
      fromId,
      count
    );
  }

  async transactionHistory(userId: number, input: TransactionHistoryDto) {
    try {
      const startTime = moment(Number(input.startTime)).format(
        "YYYY-MM-DD HH:mm:ss"
      );
      const endTime = moment(Number(input.endTime)).format(
        "YYYY-MM-DD HH:mm:ss"
      );

      const page = Number(input.page);
      const size = Number(input.size);
      const query = this.transactionRepoReport
        .createQueryBuilder("t")
        .select([
          "t.createdAt as time",
          "t.type as type",
          "t.amount as amount",
          "t.symbol as symbol",
          "t.asset as asset",
        ])
        .where("t.userId = :userId", { userId })
        .andWhere("t.createdAt >= :startTime and t.createdAt <= :endTime ", {
          startTime,
          endTime,
        })
        .andWhere('t.type <> ":typeIgnore"', {
          typeIgnore: TransactionType.REFERRAL,
        })
        .andWhere("t.contractType = :contractType", {
          contractType: input.contractType,
        })
        .orderBy("t.createdAt", "DESC")
        .limit(size)
        .offset(size * (page - 1));
      if (input.type) {
        if (input.type === TransactionType.TRANSFER) {
          query.andWhere(
            "(t.type = :depositType or t.type = :withDrawType) and t.status = :status",
            {
              depositType: TransactionType.DEPOSIT,
              withDrawType: TransactionType.WITHDRAWAL,
              status: TransactionStatus.APPROVED,
            }
          );
        } else {
          query.andWhere("t.type = :type", { type: input.type });
        }
      } else {
        query.andWhere("t.status NOT IN (:status)", {
          status: [TransactionStatus.REJECTED, TransactionStatus.PENDING],
        });
      }
      if (input.asset) {
        query.andWhere("t.asset = :asset", { asset: input.asset });
      }
      if (input.contractType) {
        query.andWhere("t.contractType = :contractType", {
          contractType: input.contractType,
        });
      }
      const [list, count] = await Promise.all([
        query.getRawMany(),
        query.getCount(),
      ]);
      return { list, count };
    } catch (error) {
      throw new Error(error);
    }
  }

  async updateTransactions(): Promise<void> {
    let skip = START_CRAWL;
    const take = GET_NUMBER_RECORD;
    do {
      const data = await this.transactionRepoMaster.find({
        skip,
        take,
      });

      skip += take;

      if (data) {
        for (const item of data) {
          item.userId = item.accountId;
          const account = await this.accountRepoMaster.findOne({
            where: {
              userId: item.userId,
              asset: item.asset.toUpperCase(),
            },
          });
          if (account) {
            item.accountId = account.id;
          } else {
            item.accountId = null;
          }
          await this.transactionRepoMaster.save(item);
        }
      } else {
        break;
      }
    } while (true);
  }

  async adminGetTransactionsByUser(queries: AdminGetTransactionByUserDto) {
    
    const accountIds = (await this.accountRepoMaster
      .createQueryBuilder("a")
      .select("a.id")
      .where("a.userId = :userId", { userId: queries.userId })
      .getMany()).map(a => { return a.id })
      accountIds.push(-1)
    
    queries.page = queries.page ?? 1
    queries.size = queries.size ?? 20

    const { offset, limit } = getQueryLimit({ page: queries.page, size: queries.size }, MAX_RESULT_COUNT);
    const query = this.transactionRepoMaster
      .createQueryBuilder("tr")
      .select("tr.*")
      .where("tr.accountId IN (:...accountIds)", { accountIds })
      .orderBy("tr.createdAt", "DESC")
      .limit(limit)
      .offset(offset);
    
    const [transactions, count] = await Promise.all([query.getRawMany(), query.getCount()]);

    return {
      data: transactions,
      metadata: {
        total: count,
        totalPage: Math.ceil(count / queries.size),
      },
    };
  }
}

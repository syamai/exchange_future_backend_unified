import {
  BadRequestException,
  CACHE_MANAGER,
  HttpException,
  HttpStatus,
  Inject,
  Injectable,
  Logger,
} from "@nestjs/common";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { Cache } from "cache-manager";
import { serialize } from "class-transformer";
import { Producer } from "kafkajs";

import { kafka } from "src/configs/kafka";
import { AccountHistoryEntity } from "src/models/entities/account-history.entity";
import { AccountEntity } from "src/models/entities/account.entity";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { AccountHistoryRepository } from "src/models/repositories/account-history.repository";
import { AccountRepository } from "src/models/repositories/account.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { SettingRepository } from "src/models/repositories/setting.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { WithdrawalDto } from "src/modules/account/dto/body-withdraw.dto";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { FromToDto } from "src/shares/dtos/from-to.dto";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { FutureEventKafkaTopic, KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { AssetOrder, ContractType } from "src/shares/enums/order.enum";
import {
  AssetType,
  TransactionStatus,
  TransactionType,
} from "src/shares/enums/transaction.enum";
import { httpErrors } from "src/shares/exceptions";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { Between, Connection, In, Repository } from "typeorm";
import { MIN_TRANSFER_AMOUNT } from "./account.const";
import { DepositDto } from "./dto/body-deposit.dto";
import { UserRewardFutureEventEntity } from "src/models/entities/user-reward-future-event.entity";
import { FutureEventReward } from "./event/future-event-reward.interface";
import { UserRewardFutureEventRepository } from "src/models/repositories/user-reward-future-event.repository";
import { RedisService } from "nestjs-redis";
import { InjectQueue } from "@nestjs/bull";
import { Queue } from "bull";
import { QUEUE_NAMES } from "../bull-mq/constants/queue-name.enum";
import { JOB_NAMES } from "../bull-mq/constants/job-name.enum";
import * as moment from "moment";
import { RewardStatus } from "../future-event/constants/reward-status.enum";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { FutureEventService } from "../future-event/future-event.service";

@Injectable()
export class AccountService {
  static DEFAULT_7DAY_MS = 7 * 24 * 60 * 60 * 1000;

  constructor(
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(AccountRepository, "master")
    public readonly accountRepoMaster: AccountRepository,
    @InjectRepository(TransactionRepository, "master")
    public readonly transactionRepoMaster: TransactionRepository,
    @InjectRepository(AccountHistoryRepository, "master")
    public readonly accountHistoryRepoMaster: AccountHistoryRepository,
    @InjectRepository(AccountHistoryRepository, "report")
    public readonly accountHistoryRepoReport: AccountHistoryRepository,
    @InjectRepository(TransactionRepository, "report")
    public readonly transactionRepoReport: TransactionRepository,
    @InjectRepository(SettingRepository, "report")
    public readonly settingRepoReport: SettingRepository,
    private readonly kafkaClient: KafkaClient,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly logger: Logger,
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepositoryReport: PositionRepository,
    @InjectConnection("report") private connection: Connection,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    @InjectRepository(UserRewardFutureEventRepository, "master")
    public readonly userRewardFutureEventRepoMaster: UserRewardFutureEventRepository,
    @InjectConnection("master") private connectionMaster: Connection,
    private readonly redisService: RedisService,
    @InjectQueue(QUEUE_NAMES.REVOKE_REWARD_BALANCE) private revokeRewardBalanceQueue: Queue,
    private readonly redisClient: RedisClient,

    private readonly futureEventService: FutureEventService
  ) {}

  async getFirstAccountByOwnerId(
    userId: number,
    asset?: string
  ): Promise<AccountEntity> {
    const account: AccountEntity = await this.accountRepoReport.findOne({
      where: { userId, asset },
    });
    if (!account) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }
    return account;
  }

  public async getFirstAccountByOwnerIdForCreateOrder(
    userId: number,
    asset: string
  ): Promise<AccountEntity> {
    let account: AccountEntity;
    let accountData: string;

    // // Get from key with asset
    // const redisKeyWithAsset = `accounts:userId_${userId}:accountId_*:asset_${asset}`;
    // const redisKeysWithAsset = await this.redisService.getClient().keys(redisKeyWithAsset);
    // if (redisKeysWithAsset.length > 0) {
    //   accountData = await this.redisService.getClient().get(redisKeysWithAsset[0]);
    //   if (accountData) account = JSON.parse(accountData) as AccountEntity;
    // }

    // // Get from key with score
    // if (!account) {
    //   const keyWithScore = `accounts:userId_${userId}:accountId_*`;
    //   const keysWithScore = await this.redisService.getClient().keys(keyWithScore);
      
    //   if (keysWithScore.length > 0) {
    //     // Get the latest account version (highest score) for each account
    //     for (const key of keysWithScore) {
    //       const members = await this.redisService.getClient().zrange(key, 0, -1, 'WITHSCORES');
    //       if (members.length === 0) continue;
    //       const accountData = JSON.parse(members[0]) as AccountEntity;
    //       if (accountData.asset !== asset) continue;
    //       account = accountData;
    //     }
    //   }
    // }

    // Get from db 
    if (!account) {
      account = await this.accountRepoReport.findOne({
        where: { userId, asset },
        select: ["id", "userId", "userEmail", "asset", "balance"],
      });
    }

    if (!account) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }
    return account;
  }

  async getAllAccount(userId: number): Promise<AccountEntity[]> {
    const accounts: AccountEntity[] = await this.accountRepoReport.find({
      where: { userId },
    });
    if (!accounts || accounts.length == 0) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }
    return accounts;
  }

  async withdraw(
    userId: number,
    withdrawalDto: WithdrawalDto
  ): Promise<TransactionEntity> {
    // const account = await this.getFirstAccountByOwnerId(userId);
    if (
      new BigNumber(withdrawalDto.amount).comparedTo(MIN_TRANSFER_AMOUNT) == -1
    ) {
      throw new BadRequestException("amount less than 0.00000001");
    }

    // Use Redis to limit the access of this user to query to db in 5s, with atomicity to avoid race condition
    const redis = this.redisClient.getInstance();
    const redisKey = `withdraw:lock:user:${userId}`;
    // Use SET with NX and EX for atomic check-and-set
    const setResult = await redis.set(redisKey, "1", "EX", 5, "NX");
    if (setResult !== "OK") {
      throw new BadRequestException("Please wait before making another withdrawal request.");
    }

    // Get accounts from cache with highest scores
    const key = `accounts:userId_${userId}:accountId_${withdrawalDto.assetType}`;
    let account = await this.accountRepoReport.findOne({
      where: {
        userId,
        asset: withdrawalDto.assetType,
      },
    });

    const members = await this.redisClient.getInstance().zrevrange(key, 0, 0, 'WITHSCORES');
    if (members && members.length !== 0) {
      const cachedAccount = JSON.parse(members[members.length - 2]);
      account = {...account, ...cachedAccount}
    }
    
    if (!account) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }

    // check real balance account smaller than request withdraw amount
    const balanceWithoutReward = new BigNumber(account.balance).minus(account.rewardBalance);
        
    if (new BigNumber(withdrawalDto.amount).gt(balanceWithoutReward)) {
      throw new BadRequestException(`Can not withdraw reward amount!`);
    }
    
    const lockedProfit = await this.futureEventService.getLockedProfit(account.userId);

    const balanceWithoutLockedProfit = balanceWithoutReward.minus(lockedProfit)

    if (new BigNumber(withdrawalDto.amount).gt(balanceWithoutLockedProfit)) {
      throw new BadRequestException(`You have not reached the target trading volume!`);
    }

    const transaction = new TransactionEntity();
    transaction.accountId = account.id;
    transaction.amount = withdrawalDto.amount;
    transaction.status = TransactionStatus.PENDING;
    transaction.type = TransactionType.WITHDRAWAL;
    transaction.asset = withdrawalDto.assetType.toUpperCase();
    transaction.userId = userId;
    transaction.contractType = ContractType.USD_M;
    // const isCoinM = LIST_COINM.includes(withdrawalDto.assetType);
    if (withdrawalDto.assetType == "USDT" || withdrawalDto.assetType == "USD") {
      transaction.contractType = ContractType.USD_M;
    } else {
      transaction.contractType = ContractType.COIN_M;
    }

    const result = await this.transactionRepoMaster.save(transaction);

    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.WITHDRAW,
      data: transaction,
    });
    return result;
  }

  async findBatch(fromId: number, count: number): Promise<AccountEntity[]> {
    return await this.accountRepoMaster.findBatch(fromId, count);
  }

  async findBalanceFromTo(
    accountId: number,
    ft: FromToDto
  ): Promise<AccountHistoryEntity[]> {
    if (!ft.from)
      ft.from = new Date().getTime() - AccountService.DEFAULT_7DAY_MS;
    if (!ft.to) ft.to = new Date().getTime();
    const accounts = await this.accountHistoryRepoReport.find({
      accountId: accountId,
      createdAt: Between<Date>(new Date(ft.from), new Date(ft.to)),
    });
    return accounts;
  }

  async saveUserDailyBalance(): Promise<void> {
    const today = new Date();
    // get all account info
    const allAccountHistories = await this.accountRepoReport.find();

    // mapping to history balance entity
    const todayUsersBalance = allAccountHistories.map((e) => {
      const newEntity = new AccountHistoryEntity();
      newEntity.accountId = e.id;
      // newEntity.balance = e.balance;
      newEntity.createdAt = today;
      return newEntity;
    });

    // batch insert into account history repo
    try {
      await this.accountHistoryRepoReport.batchSave(todayUsersBalance);
    } catch (error) {
      this.logger.error(`Failed to update daily balance at ${today}`);
    }
  }

  async getTransferHistory(
    accountId: number,
    type: string,
    paging: PaginationDto
  ): Promise<ResponseDto<TransactionEntity[]>> {
    const where = {
      accountId: accountId,
    };
    if (type) {
      where["type"] = type;
    }
    const transfer = await this.transactionRepoReport.find({
      where,
      skip: (paging.page - 1) * paging.size,
      take: paging.size,
      order: {
        id: "DESC",
      },
    });

    const count = await this.transactionRepoReport.count({
      where,
    });

    return {
      data: transfer,
      metadata: {
        totalPage: Math.ceil(count / paging.size),
      },
    };
  }

  async getBalanceV2(userId: number, asset = "usdt") {
    const balance = await this.accountRepoReport.findOne({
      userId,
      asset,
    });

    return {
      balance: new BigNumber(balance.balance).toString(),
    };
  }

  async deposit(
    userId: number,
    body: DepositDto
  ): Promise<{ success: boolean }> {
    const account = await this.accountRepoReport.findOne({
      where: {
        userId,
        asset: body.asset,
      },
    });
    if (!account) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }

    //disable large amount
    // if (new BigNumber(body.amount).gt(1000000)) {
    //   return;
    // }

    const producer = kafka.producer();
    await producer.connect();

    const transaction = new TransactionEntity();
    transaction.accountId = account.id;
    // transaction.accountId = account?.id || -901;
    transaction.asset = body.asset.toUpperCase();
    transaction.amount = body.amount;
    transaction.status = TransactionStatus.PENDING;
    transaction.type = TransactionType.DEPOSIT;
    transaction.userId = userId;

    const transactionDb = await this.transactionRepoMaster.save(transaction);
    await this.sendTransactions(transactionDb, producer);
    await producer.disconnect();
    return { success: true };
  }

  private async sendTransactions(
    transaction: TransactionEntity,
    producer: Producer
  ): Promise<void> {
    const messages = {
      value: serialize({ code: CommandCode.DEPOSIT, data: transaction }),
    };
    await producer.send({
      topic: KafkaTopics.matching_engine_input,
      messages: [messages],
    });
  }

  async genInsuranceAccounts(): Promise<void> {
    const assets = AssetOrder;
    for (const asset in assets) {
      const index = Object.keys(assets).indexOf(asset);
      await this.accountRepoMaster.insert({
        id: 1000 + index,
        asset: asset,
        balance: "100",
        operationId: 0,
      });
    }
  }

  async genNewAssetAccounts(asset: string): Promise<void> {
    if (!asset) {
      console.log("Asset can not be null");
      return;
    }
    const checkAccount = await this.accountRepoReport.findOne({
      where: {
        asset: asset.toUpperCase(),
      },
    });
    if (checkAccount) {
      console.log("Asset found");
      return;
    }
    const data = await this.accountRepoReport
      .createQueryBuilder("accounts")
      .select("DISTINCT userId")
      .execute();
    const userIds = data.map((e) => e.userId).filter((e) => e != "0");
    const taskInsert = [];
    const taskSendToKafka = [];
    for (const userId of userIds) {
      const account = new AccountEntity();
      account.asset = asset.toUpperCase();
      account.balance = "0";
      account.userId = userId;
      taskInsert.push(this.accountRepoMaster.insert(account));
      taskSendToKafka.push(
        this.kafkaClient.send(KafkaTopics.matching_engine_input, {
          code: CommandCode.CREATE_ACCOUNT,
          data: account,
        })
      );
    }
    await Promise.all([...taskInsert, taskSendToKafka]);
  }

  async createNewAccount(userId: number, asset: string) {
    const isExistUser = await this.accountRepoReport.findOne({
      where: {
        userId: userId,
        asset: asset,
      },
    });
    if (isExistUser) {
      throw new HttpException(httpErrors.ACCOUNT_EXISTED, HttpStatus.NOT_FOUND);
    }

    const queryRunner = this.connection.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();
    try {
      const account = new AccountEntity();
      account.asset = asset;
      account.balance = "1000000";
      account.userId = userId;
      await queryRunner.manager.save(AccountEntity, account);
      await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.CREATE_ACCOUNT,
        data: account,
      });
      await queryRunner.commitTransaction();
      return account;
    } catch (error) {
      await queryRunner.rollbackTransaction();
      console.log("SYNC USER FROM SPOT " + error);
    } finally {
      await queryRunner.release();
    }
  }

  async syncEmail(): Promise<void> {
    const users = await this.userRepoReport.find();
    for (const user of users) {
      await this.accountRepoMaster.update(
        { userId: user.id },
        { userEmail: user.email }
      );
    }
  }

  async depositUSDTBotAccount(): Promise<void> {
    const bots = await this.userRepoReport.find({ where: { position: "bot" } });
    const botAccounts = await this.accountRepoReport.find({
      where: { userId: In(bots.map((bot) => bot.id)), asset: AssetType.USDT },
    });
    for (const account of botAccounts) {
      const producer = kafka.producer();
      await producer.connect();

      const transaction = new TransactionEntity();
      transaction.accountId = account.id;
      // transaction.accountId = account?.id || -901;
      transaction.asset = account.asset.toUpperCase();
      transaction.amount = "10000000";
      transaction.status = TransactionStatus.PENDING;
      transaction.type = TransactionType.DEPOSIT;
      transaction.userId = account.userId;
      const transactionDb = await this.transactionRepoMaster.save(transaction);
      await this.sendTransactions(transactionDb, producer);
      await producer.disconnect();
    }
  }

  private async addRewardBalanceToAccount(userId: number, amount: string, asset: string) {
    const account = await this.accountRepoReport.findOne({
      where: {
        userId,
        asset,
      },
    });

    if (!account) {
      this.logger.log(`Account not found: asset: ${asset}, userId: ${userId}`);
      return;
    }

    try {
      // Use direct SQL UPDATE with addition to avoid race conditions
      const updateResult = await this.accountRepoMaster
        .createQueryBuilder()
        .update(AccountEntity)
        .set({
          rewardBalance: () => `rewardBalance + ${amount}`,
        })
        .where("id = :accountId", { accountId: account.id })
        .execute();
      
      this.logger.log(`Update user account ${account.id} reward balance result: ${JSON.stringify(updateResult)}`);

      // Create transaction record
      const transaction = new TransactionEntity();
      transaction.accountId = account.id;
      transaction.asset = asset;
      transaction.amount = amount;
      transaction.status = TransactionStatus.PENDING;
      transaction.type = TransactionType.EVENT_REWARD;
      transaction.userId = userId;
      transaction.contractType = ContractType.USD_M;

      const transactionDb = await this.transactionRepoMaster.save(transaction);
      return transactionDb;
    } catch (error) {
      this.logger.error(`Failed to add reward balance: ${error.message}`);
      throw new Error(`Failed to add reward balance: ${error}`);
    }
  }

  public async saveFutureEventReward() {
    await this.kafkaClient.consume<FutureEventReward>(
      KafkaTopics.future_event_reward,
      KafkaGroups.future_save_reward_from_event,
      async (rewardData) => {
        try {
          await this.processFutureReward(rewardData);
        } catch (error) {
          this.logger.error(`[accountService][saveFutureEventReward] - error: ${error}`);
          throw error;
        }
      },
      { fromBeginning: true }
    );
    return new Promise(() => {});
  }

  private async processFutureReward(rewardData: FutureEventReward) {
    const queryRunner = this.connectionMaster.createQueryRunner();
    await queryRunner.connect();
    await queryRunner.startTransaction();

    try {
      const { userId, amount, asset, expiredDate } = rewardData;

      // add reward balance to account
      const transaction = await this.addRewardBalanceToAccount(userId, amount, asset);
      if (transaction) {
        // save reward to db
        const userRewardEntity = new UserRewardFutureEventEntity();
        userRewardEntity.status = RewardStatus.IN_USE;
        userRewardEntity.remaining = amount;
        Object.assign(userRewardEntity, rewardData);
        const savedReward = await queryRunner.manager.save(UserRewardFutureEventEntity, userRewardEntity);

        // Send to Kafka for processing
        await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
          code: CommandCode.DEPOSIT,
          data: transaction,
        });

        // send msg to process trading volume session
        await this.kafkaClient.send(FutureEventKafkaTopic.rewards_to_process_trading_volume_session, savedReward);

        // add job queue to revoke reward
        const delayRevokeMs = moment(expiredDate).diff(moment());
        await this.revokeRewardBalanceQueue.add(
          JOB_NAMES.REVOKE_REWARD_BALANCE,
          {
            accountId: transaction.accountId,
            userId: transaction.userId,
          },
          { delay: delayRevokeMs + 5000 }
        );

        await queryRunner.commitTransaction();

        this.logger.log(`Successfully process reward: ${JSON.stringify(rewardData)}`);
      }
    } catch (error) {
      await queryRunner.rollbackTransaction();
      this.logger.error(`[processFutureReward] - error: ${error.message}`);
      throw new Error(`[processFutureReward] - error: ${error}`);
    } finally {
      await queryRunner.release();
    }
  }

  public async getBotAccounts(): Promise<AccountEntity[]> {
    const bots = await this.userRepoReport.find({ where: { isBot: true } });
    const botAccounts = await this.accountRepoReport.find({
      where: { userId: In(bots.map((bot) => bot.id)), asset: AssetType.USDT },
    });
    return botAccounts;
  }
}

import { HttpException, HttpStatus, Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { UserRewardFutureEventEntity } from "src/models/entities/user-reward-future-event.entity";
import { OrderRepository } from "src/models/repositories/order.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { UserRewardFutureEventRepository } from "src/models/repositories/user-reward-future-event.repository";
import { In } from "typeorm";
import { RewardStatus } from "./constants/reward-status.enum";
import { CommandCode, CommandOutput } from "../matching-engine/matching-engine.const";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { convertDateFields } from "../matching-engine/helper";
import { TransactionStatus, TransactionType } from "src/shares/enums/transaction.enum";
import { AccountRepository } from "src/models/repositories/account.repository";
import { httpErrors } from "src/shares/exceptions";
import { AdminRevokeRewardError } from "./constants/admin-revoke-reward-error.enum";
import BigNumber from "bignumber.js";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { ContractType } from "src/shares/enums/order.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { Queue } from "bull";
import { QUEUE_NAMES } from "../bull-mq/constants/queue-name.enum";
import { InjectQueue } from "@nestjs/bull";
import { JOB_NAMES } from "../bull-mq/constants/job-name.enum";
import { BotInMemoryService } from "../bot/bot.in-memory.service";

@Injectable()
export class FutureEventRevokeRewardService {
  private readonly logger = new Logger(FutureEventRevokeRewardService.name);

  constructor(
    @InjectRepository(PositionRepository, "report")
    private positionRepository: PositionRepository,
    @InjectRepository(OrderRepository, "report")
    private orderRepository: OrderRepository,
    @InjectRepository(UserRewardFutureEventRepository, "master")
    private userRewardFutureEventRepoMaster: UserRewardFutureEventRepository,
    @InjectRepository(AccountRepository, "master")
    private accountRepoMaster: AccountRepository,
    @InjectRepository(TransactionRepository, "master")
    private transactionRepoMaster: TransactionRepository,
    private readonly kafkaClient: KafkaClient,
    @InjectQueue(QUEUE_NAMES.REVOKE_REWARD_BALANCE) private revokeRewardBalanceQueue: Queue,
    private readonly botInMemoryService: BotInMemoryService,
  ) {}

  public async userHasOpenPosition(accountId: number) {
    const existOpenPosition = await this.positionRepository
      .createQueryBuilder("p")
      .select("1")
      .where(`p.accountId = :accountId`, { accountId })
      .andWhere(`p.currentQty != 0`)
      .limit(1)
      .getRawOne();

    return !!existOpenPosition;
  }

  public async userHasOpenOrder(userId: number) {
    const existOpenOrder = await this.orderRepository
      .createQueryBuilder("o")
      .select("1")
      .where("o.status = 'ACTIVE' and o.userId = :userId", { userId })
      .getRawOne();

    return !!existOpenOrder;
  }

  public async updateRewardStatus(ids: number[], status: RewardStatus) {
    await this.userRewardFutureEventRepoMaster
      .createQueryBuilder()
      .update(UserRewardFutureEventEntity)
      .set({ status })
      .where({ id: In(ids) })
      .execute();
  }

  public async updateRevokingRewardBalance(commands: CommandOutput[]) {
    const entities: TransactionEntity[] = [];
    for (const command of commands) {
      if (command.transactions?.length > 0) {
        entities.push(...command.transactions.map((item) => convertDateFields(new TransactionEntity(), item)));
      }
    }

    if (!entities.length) return;

    const acceptRevokeEntities = entities.filter(
      (entity) => entity.status === TransactionStatus.APPROVED && entity.type === TransactionType.REVOKE_EVENT_REWARD
    );
    if (acceptRevokeEntities.length) {
      this.logger.log(`Start update revoking reward: ${JSON.stringify(acceptRevokeEntities)}`);
      await Promise.all(
        acceptRevokeEntities.map(async (entity) => {
          try {
            await Promise.all([
              this.accountRepoMaster
                .createQueryBuilder()
                .update()
                .set({
                  rewardBalance: () => `GREATEST(rewardBalance - ${entity.amount}, 0)`,
                })
                .where("id = :accountId", { accountId: entity.accountId })
                .andWhere("rewardBalance > 0")
                .execute(),
              this.userRewardFutureEventRepoMaster
                .createQueryBuilder("rw")
                .update()
                .set({
                  status: () => `'${RewardStatus.REVOKED}'`,
                })
                .where("userId = :userId", { userId: entity.userId })
                .andWhere(`status = :status`, { status: RewardStatus.REVOKING })
                .andWhere(`expiredDate <= :currentDate`, { currentDate: new Date().toISOString() })
                .execute(),
            ]);
          } catch (error) {
            console.log(error);
          }
        })
      );
    }

    const rejectedRevokeEntities = entities.filter(
      (entity) => entity.status === TransactionStatus.REJECTED && entity.type === TransactionType.REVOKE_EVENT_REWARD
    );
    if (rejectedRevokeEntities.length) {
      this.logger.log(`Start update revoking reward: ${JSON.stringify(rejectedRevokeEntities)}`);
      await Promise.all(
        rejectedRevokeEntities.map(async (entity) => {
          try {
            await Promise.all([
              this.userRewardFutureEventRepoMaster
                .createQueryBuilder("rw")
                .update()
                .set({
                  status: () => `'${RewardStatus.IN_USE}'`,
                })
                .where("userId = :userId", { userId: entity.userId })
                .andWhere(`status = :status`, { status: RewardStatus.REVOKING })
                .andWhere(`expiredDate <= :currentDate`, { currentDate: new Date().toISOString() }) 
                .execute(),
            ]);
          } catch (error) {
            console.log(error);
          }
        })
      );
    }
  }

  async adminRevokeRewardBalance(userId: number, amount: string) {
    const account = await this.accountRepoMaster.findOne({
      where: {
        userId,
        asset: "USDT",
      },
    });
    if (!account) {
      throw new HttpException(httpErrors.ACCOUNT_NOT_FOUND, HttpStatus.BAD_REQUEST);
    }

    const [hasOpenPosition, hasOpenOrder] = await Promise.all([this.userHasOpenPosition(account.id), await this.userHasOpenOrder(userId)]);

    if (hasOpenPosition || hasOpenOrder) {
      throw new HttpException(
        { code: AdminRevokeRewardError.USER_HAS_OPEN_POSITION_OR_ORDER, message: "User has opened position or open order" },
        HttpStatus.BAD_REQUEST
      );
    }

    const maxRevokeAmount = BigNumber.minimum(account.balance, account.rewardBalance);

    if (new BigNumber(amount).gt(maxRevokeAmount)) {
      throw new HttpException(
        { maxRevokeAmount, code: AdminRevokeRewardError.EXCEED_REWARD_BALANCE, message: "Request amount exceed reward balance or balance" },
        HttpStatus.BAD_REQUEST
      );
    }

    // send to ME to revoke balance
    const transaction = new TransactionEntity();
    transaction.accountId = account.id;
    transaction.amount = amount;
    transaction.status = TransactionStatus.PENDING;
    transaction.type = TransactionType.REVOKE_EVENT_REWARD;
    transaction.asset = "USDT";
    transaction.userId = userId;
    transaction.contractType = ContractType.USD_M;
    const savedTransaction = await this.transactionRepoMaster.save(transaction);

    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.WITHDRAW,
      data: savedTransaction,
    });
    return savedTransaction;
  }

  public async revokeRewardWhenUserClosePositionOrder(commands: CommandOutput[]) {
    const accMap = new Map<number, number>();
    for (const command of commands) {
      if (command.accHasNoOpenOrdersAndPositionsList?.length) {
        for (const acc of command.accHasNoOpenOrdersAndPositionsList) {
          if (!(await this.botInMemoryService.checkIsBotAccountId(acc.accountId))) {
            accMap.set(acc.accountId, acc.userId)
          }
        }
      }
    }

    if (!accMap.size) return;

    // create queue to revoke reward balance
    for (const [accountId, userId] of accMap) {
      await this.revokeRewardBalanceQueue.add(
        JOB_NAMES.REVOKE_REWARD_BALANCE,
        {
          accountId: accountId,
          userId: userId,
          isNoOpenPositionOrder: true
        },
        { delay: 0 }
      );
    }
  }
}

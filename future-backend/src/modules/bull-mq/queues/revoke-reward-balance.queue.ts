import { Process, Processor } from "@nestjs/bull";
import { Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { Job } from "bull";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { UserRewardFutureEventRepository } from "src/models/repositories/user-reward-future-event.repository";
import { RewardStatus } from "src/modules/future-event/constants/reward-status.enum";
import { FutureEventRevokeRewardService } from "src/modules/future-event/future-event-revoke-reward.service";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { ContractType } from "src/shares/enums/order.enum";
import { TransactionStatus, TransactionType } from "src/shares/enums/transaction.enum";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { JOB_NAMES } from "../constants/job-name.enum";
import { QUEUE_NAMES } from "../constants/queue-name.enum";

@Processor(QUEUE_NAMES.REVOKE_REWARD_BALANCE)
export class RevokeRewardBalanceQueue {
  private readonly logger = new Logger(RevokeRewardBalanceQueue.name);
  constructor(
    @InjectRepository(AccountRepository, "report")
    private accountRepoReport: AccountRepository,
    @InjectRepository(UserRewardFutureEventRepository, "report")
    private userRewardFutureEventRepoReport: UserRewardFutureEventRepository,
    @InjectRepository(TransactionRepository, "master")
    private transactionRepoMaster: TransactionRepository,
    private readonly kafkaClient: KafkaClient,
    private readonly futureEventRevokeRewardService: FutureEventRevokeRewardService
  ) {}

  @Process(JOB_NAMES.REVOKE_REWARD_BALANCE)
  async revokeRewardBalanceJob(job: Job<{ accountId: number; userId: number, isNoOpenPositionOrder: boolean }>) {
    const { accountId, userId, isNoOpenPositionOrder } = job.data;
    this.logger.log(`Start processing revoke reward balance - job data: ${JSON.stringify(job.data)}`);

    // check if user has open position and user has open order
    if (!isNoOpenPositionOrder) {
      const [userHasOpenPosition, userHasOpenOrder] = await Promise.all([
        this.futureEventRevokeRewardService.userHasOpenPosition(accountId),
        this.futureEventRevokeRewardService.userHasOpenOrder(accountId),
      ]);
      if (userHasOpenPosition || userHasOpenOrder) {
        this.logger.log(`User has opened a position or opened order`);
        return;
      }
    }

    // get user's account
    const [account, userRewardCouldRevokes] = await Promise.all([
      this.accountRepoReport.findOne({ where: { id: accountId } }),
      this.userRewardFutureEventRepoReport
        .createQueryBuilder("rw")
        .where("rw.userId = :userId", { userId })
        .andWhere(`rw.status = :status`, { status: RewardStatus.IN_USE })
        .andWhere(`rw.expiredDate <= :currentDate`, { currentDate: new Date().toISOString() })
        .getMany(),
    ]);

    if (!userRewardCouldRevokes?.length) {
      this.logger.log(`User has no remaining reward need to revoke!`);
      return;
    }

    // check if remaining reward or balance is 0
    let totalReward = new BigNumber("0");
    const rewardIds = [];
    for (const reward of userRewardCouldRevokes) {
      totalReward = totalReward.plus(reward.amount);
      rewardIds.push(reward.id);
    }
    this.logger.log(
      `totalReward: ${totalReward.toString()}, account.balance: ${account.balance}, account.rewardBalance: ${account.rewardBalance}`
    );

    const amountWillBeRevoked = BigNumber.minimum(totalReward.toString(), account.balance, account.rewardBalance).toString();
    if (new BigNumber(amountWillBeRevoked).eq("0")) {
      this.logger.log(`User has no remaining reward balance or balance currently is 0!`);
      if (!totalReward.eq("0")) {
        // update user reward entity status to REVOKED if totalReward != 0
        await this.futureEventRevokeRewardService.updateRewardStatus(rewardIds, RewardStatus.REVOKED);
      }
      return;
    }

    // update user reward balance status to REVOKING
    await this.futureEventRevokeRewardService.updateRewardStatus(rewardIds, RewardStatus.REVOKING);

    // send to ME to revoke balance
    const transaction = new TransactionEntity();
    transaction.accountId = accountId;
    transaction.amount = amountWillBeRevoked;
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
}

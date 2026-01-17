import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Command, Console } from "nestjs-console";
import { UserEntity } from "src/models/entities/user.entity";
import { UserRepository } from "src/models/repositories/user.repository";
import { AssetType } from "../enums/transaction.enum";
import { OrderStatus } from "../enums/order.enum";
import { REDIS_COMMON_PREFIX } from "../redis-client/common-prefix";
import { RedisClient } from "../redis-client/redis-client";
import { AccountRepository } from "src/models/repositories/account.repository";
import { OrderRepository } from "src/models/repositories/order.repository";

@Console()
@Injectable()
export class OrderToolConsole {
  constructor(
    @InjectRepository(UserRepository, "report")
    private readonly userRepoReport: UserRepository,
    @InjectRepository(AccountRepository, "report")
    private readonly accountRepoReport: AccountRepository,
    @InjectRepository(OrderRepository, "master")
    private readonly orderRepoMaster: OrderRepository,
    private readonly redisClient: RedisClient
  ) {}
  private readonly logger = new Logger(OrderToolConsole.name);

  @Command({
    command: "order-tool:sync-order-from-db-to-cache",
  })
  async syncOrderFromDbToCache(): Promise<void> {
    this.logger.log("Runing ...");
    const allUsers: UserEntity[] = await this.userRepoReport
      .createQueryBuilder("user")
      .select(["user.id"])
      .getMany();

    for (const user of allUsers) {
      this.logger.log(`Processing user ${user.id}`);
      const account = await this.accountRepoReport
        .createQueryBuilder("account")
        .where("account.userId = :userId", { userId: user.id })
        .andWhere("account.asset = :asset", { asset: AssetType.USDT })
        .select(["account.id", "account.userId", "account.asset"])
        .getOne();

      if (!account) {
        this.logger.log(`No USDT account found for user ${user.id}`);
        continue;
      }

      // Get all active/untrigged orders for this user
      const orders = await this.orderRepoMaster
        .createQueryBuilder("order")
        .where("order.accountId = :accountId", { accountId: account.id })
        .andWhere("order.status IN (:...status)", {
          status: [OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED],
        })
        .getMany();

      for (const order of orders) {
        // Check if order exists in Redis
        const keyWithOrderId = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${order.userId}:orderId_${order.id}`;
        const keyWithTmpId = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${order.userId}:tmpId_${order.tmpId}`;
        const orderOnRedis = await this.redisClient
          .getInstance()
          .zrevrange(keyWithOrderId, 0, 0, "WITHSCORES");

        if (orderOnRedis.length != 0) continue;

        // Save order to Redis if not exists
        const orderOperationId = order?.operationId
          ? Number(
              (
                BigInt(order.operationId.toString()) % BigInt(1000000000000000)
              ).toString()
            )
          : null;
        this.redisClient
          .getInstance()
          .zadd(keyWithOrderId, orderOperationId, JSON.stringify(order));
        this.redisClient.getInstance().expire(keyWithOrderId, 3 * 24 * 60 * 60);

        if (order.tmpId)
          this.redisClient
            .getInstance()
            .set(keyWithTmpId, JSON.stringify(order), "EX", 3 * 24 * 60 * 60);
        this.logger.log(`Saved order ${order.id} to Redis`);
      }
    }
  }

  @Command({
    command: "order-tool:sync-user-order-from-db-to-order-ids-set-cache",
  })
  async syncOrderFromDbToOrderIdsSetCache(): Promise<void> {
    this.logger.log("Runing ...");
    const allUsers: UserEntity[] = await this.userRepoReport
      .createQueryBuilder("user")
      .where("user.isBot = :isBot", { isBot: false })
      .select(["user.id"])
      .getMany();

    for (const user of allUsers) {
      this.logger.log(`Processing user ${user.id}`);
      const account = await this.accountRepoReport
        .createQueryBuilder("account")
        .where("account.userId = :userId", { userId: user.id })
        .andWhere("account.asset = :asset", { asset: AssetType.USDT })
        .select(["account.id", "account.userId", "account.asset"])
        .getOne();

      if (!account) {
        this.logger.log(`No USDT account found for user ${user.id}`);
        continue;
      }

      // Get all active/untrigged orders for this user
      const orders = await this.orderRepoMaster
        .createQueryBuilder("order")
        .where("order.accountId = :accountId", { accountId: account.id })
        .andWhere("order.status IN (:...status)", {
          status: [OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED],
        })
        .getMany();

      for (const order of orders) {
        const keyWithActiveOrderIds = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${order.userId}:activeOrderIds`;
        this.redisClient
          .getInstance()
          .sadd(keyWithActiveOrderIds, Number(order.id));
        this.logger.log(`Saved order ${order.id} to Redis`);
      }
    }
  }
}

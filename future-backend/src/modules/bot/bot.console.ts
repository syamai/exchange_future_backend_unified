import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Command, Console } from "nestjs-console";
import { OrderEntity } from "src/models/entities/order.entity";
import { OrderRepository } from "src/models/repositories/order.repository";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { OrderSide, OrderStatus } from "src/shares/enums/order.enum";
import { Between, In, LessThanOrEqual, MoreThan, MoreThanOrEqual } from "typeorm";
import { BotService } from "./bot.service";
import { BotInMemoryService } from "./bot.in-memory.service";
import { UserTradeToRemoveBotOrderRepository } from "src/models/repositories/user-trade-to-remove-bot-order.repository";
import BigNumber from "bignumber.js";
import { TradeEntity } from "src/models/entities/trade.entity";
import * as moment from "moment";

@Console()
@Injectable()
export class BotConsole {
  constructor(
    private readonly botService: BotService,
    private readonly botInMemoryService: BotInMemoryService,
    @InjectRepository(OrderRepository, "master")
    private orderRepoMaster: OrderRepository,
    @InjectRepository(OrderRepository, "report")
    private orderRepoReport: OrderRepository,
    @InjectRepository(TradeRepository, "master")
    private tradeRepository: TradeRepository,
    @InjectRepository(UserTradeToRemoveBotOrderRepository, "report")
    private userTradeToRemoveBotOrderRepoReport: UserTradeToRemoveBotOrderRepository,
  ) {}

  private readonly logger = new Logger(BotConsole.name);

  @Command({
    command: "bot:remove-bot-orders <batch> <sleepInMs>",
    description: "remove bot order from db",
  })
  async removeBotOrders(batch: number, sleepInMs: number): Promise<void> {
    batch = Number(batch);
    const botAccountIds = (await this.botInMemoryService.getBotAccountIds()).sort((a, b) => a - b);
    while (true) {
      let lastId = 0;
      let totalDeletedOrders = 0;

      let totalOrdersCount = 0;
      let orders: OrderEntity[] = [];

      do {
        orders = await this.orderRepoReport.find({
          select: ["id", "side", "executedPrice"],
          where: { 
            accountId: Between(botAccountIds[0], botAccountIds[botAccountIds.length - 1]), 
            status: In([OrderStatus.FILLED, OrderStatus.CANCELED]), 
            id: MoreThan(lastId),
            createdAt: LessThanOrEqual('2025-06-11 09:00:00.000')
          },
          take: batch,
          // order: { id: "ASC" },
        });

        if (orders.length) {
          const orderIdsWillBeDeleted: number[] = [];
          totalOrdersCount += orders.length;
          console.log(`Total query orders: ${totalOrdersCount}`);

          await Promise.all(
            orders.map(async (order) => {
              if (order.status === OrderStatus.CANCELED && !order.executedPrice) {
                orderIdsWillBeDeleted.push(order.id);
              } else {
                const existUserTrade = await this.tradeRepository
                  .createQueryBuilder("t")
                  .select("1")
                  .where(`t.${order.side.toLowerCase()}OrderId = :orderId`, { orderId: order.id })
                  .andWhere(`t.${order.side === OrderSide.BUY ? "sell" : "buy"}AccountId NOT BETWEEN :botAccountIdStart AND :botAccountIdEnd`, {
                    botAccountIdStart: botAccountIds[0],
                    botAccountIdEnd: botAccountIds[botAccountIds.length - 1],
                  })
                  .limit(1)
                  .getRawOne();

                if (!existUserTrade) {
                  orderIdsWillBeDeleted.push(order.id);
                }
              }
            })
          );

          totalDeletedOrders += orderIdsWillBeDeleted.length;
          console.log(`TotalOrderDelete: ${totalDeletedOrders}`);
          // delete orders with array ids
          if (orderIdsWillBeDeleted.length) {
            await this.orderRepoMaster.delete(orderIdsWillBeDeleted);
            await new Promise((resolve) => setTimeout(resolve, sleepInMs));
          }

          lastId = orders[orders.length - 1].id;
        }
      } while (orders.length === batch);

      console.log(`No more orders to delete, total deleted orders: ${totalDeletedOrders}`);
      console.log(`Waiting for 5 seconds before next iteration...`);
      console.log("------------------------------------------------------------------------");
      await new Promise((resolve) => setTimeout(resolve, 5000));
    }
  }

  @Command({
    command: "bot:remove-bot-orders-using-user-trade-table <batch> <sleepInMs>",
    description: "remove bot order from db",
  })
  async removeBotOrdersUsingUserTradeTable(batch: number, sleepInMs: number): Promise<void> {
    batch = Number(batch);
    const botAccountIds = (await this.botInMemoryService.getBotAccountIds()).sort((a, b) => a - b);
    while (true) {
      let lastId = 0;
      let totalDeletedOrders = 0;
      let totalOrdersCount = 0;
      let orders: OrderEntity[] = [];

      do {
        // Get list of orders can be deleted
        orders = await this.orderRepoReport.find({
          select: ["id", "side", "remaining", "quantity"],
          where: { 
            accountId: Between(botAccountIds[0], botAccountIds[botAccountIds.length - 1]),
            status: In([OrderStatus.FILLED, OrderStatus.CANCELED]), 
            id: MoreThan(lastId),
            createdAt: MoreThanOrEqual('2025-06-11 09:00:00.000'),
            updatedAt: LessThanOrEqual(
              new Date(Date.now() - 60 * 1000)
                .toISOString()
                .replace('T', ' ')
                .replace('Z', '')
                .slice(0, 19)
            ),
          },
          take: batch,
          // order: { id: "ASC" },
        });

        if (orders.length === 0) break;

        const orderIdsWillBeDeleted: number[] = [];
        totalOrdersCount += orders.length;
        this.logger.log(`Total query orders: ${totalOrdersCount}`);

        await Promise.all(
          orders.map(async (order) => {
            if (order.status === OrderStatus.CANCELED && order.remaining && order.quantity && new BigNumber(order.remaining).eq(new BigNumber(order.quantity))) {
              orderIdsWillBeDeleted.push(order.id);
            } else {
              const existUserTrade = await this.userTradeToRemoveBotOrderRepoReport
                .createQueryBuilder("ut")
                .select("1")
                .where(`ut.${order.side.toLowerCase()}OrderId = :orderId`, { orderId: order.id })
                .limit(1)
                .getRawOne();

              if (!existUserTrade) {
                orderIdsWillBeDeleted.push(order.id);
              }
            }
          })
        );

        totalDeletedOrders += orderIdsWillBeDeleted.length;
        this.logger.log(`TotalOrderDelete: ${totalDeletedOrders}`);
        // delete orders with array ids
        if (orderIdsWillBeDeleted.length) {
          await this.orderRepoMaster.delete(orderIdsWillBeDeleted);
          await new Promise((resolve) => setTimeout(resolve, sleepInMs));
        }

        lastId = orders[orders.length - 1].id;
      } while (orders.length === batch);

      this.logger.log(`No more orders to delete, total deleted orders: ${totalDeletedOrders}`);
      this.logger.log(`Waiting for 5 seconds before next iteration...`);
      this.logger.log("------------------------------------------------------------------------");
      await new Promise((resolve) => setTimeout(resolve, 5000));
    }
  }

  @Command({
    command: "bot:remove-bot-trades <batch> <sleepInMs>",
    description: "remove bot trades from db",
  })
  async removeBotTrades(batch: number, sleepInMs: number): Promise<void> {
    batch = Number(batch);
    const botAccountIds = await this.botInMemoryService.getBotAccountIds();

    while (true) {
      let lastId = 0;
      let totalDeletedTrades = 0;

      let trades: TradeEntity[] = [];

      do {
        const toTime = moment().subtract(1, 'days').toISOString();
        console.log(`Delete to time: ${toTime}`);

        trades = await this.tradeRepository
          .createQueryBuilder("t")
          .where(`t.buyAccountId in (:...botAccountIds)`, { botAccountIds })
          .andWhere(`t.sellAccountId in (:...botAccountIds)`, { botAccountIds })
          .andWhere(`t.createdAt <= :toTime`, { toTime })
          .andWhere(`t.id > :lastId`, { lastId })
          .orderBy("t.id", "ASC")
          .limit(batch)
          .getMany();

        if (trades.length) {
          const tradeIdsWillBeDeleted: number[] = trades.map((t) => t.id);

          // delete trades with array ids
          if (tradeIdsWillBeDeleted.length) {
            await this.tradeRepository.delete(tradeIdsWillBeDeleted);
            console.log(
              `Delete trade id from: ${tradeIdsWillBeDeleted[0]}, to: ${tradeIdsWillBeDeleted[tradeIdsWillBeDeleted.length - 1]}`
            );

            await new Promise((resolve) => setTimeout(resolve, sleepInMs));
          }

          totalDeletedTrades += trades.length;
          console.log(`TotalDeletedTrades: ${totalDeletedTrades}`);
          console.log("---------------------------------------------------------------");
          lastId = trades[trades.length - 1].id;
        }
      } while (trades.length === batch);

      console.log(`No more trades to delete, total deleted trades: ${totalDeletedTrades}`);
      console.log(`Waiting for 5 seconds before next iteration...`);
      console.log("------------------------------------------------------------------------");
      await new Promise((resolve) => setTimeout(resolve, 5000));
    }
  }
}

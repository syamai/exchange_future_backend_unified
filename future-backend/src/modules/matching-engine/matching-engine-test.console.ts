import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { serialize } from "class-transformer";
import { Producer } from "kafkajs";
import { Command, Console } from "nestjs-console";
import { kafka } from "src/configs/kafka";
import { AccountEntity } from "src/models/entities/account.entity";
import { OrderEntity } from "src/models/entities/order.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { AccountService } from "src/modules/account/account.service";
import { IndexService } from "src/modules/index/index.service";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { MatchingEngineService } from "src/modules/matching-engine/matching-engine.service";
import { OrderService } from "src/modules/order/order.service";
import { PositionService } from "src/modules/position/position.service";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import {
  OrderSide,
  OrderStatus,
  OrderTimeInForce,
  OrderType,
} from "src/shares/enums/order.enum";
import { SocketEmitter } from "src/shares/helpers/socket-emitter";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { FirebaseAdminService } from "../firebase-noti-module/firebase-admin.service";

@Console()
@Injectable()
export class MatchingEngineTestConsole {
  private readonly logger = new Logger(MatchingEngineTestConsole.name);

  constructor(
    private readonly accountService: AccountService,
    private readonly positionService: PositionService,
    private readonly orderService: OrderService,
    private readonly matchingEngineService: MatchingEngineService,
    private readonly instrumentService: InstrumentService,
    private readonly indexService: IndexService,
    public readonly kafkaClient: KafkaClient,
    @InjectRepository(AccountRepository, "report")
    private accountRepository: AccountRepository,
    private readonly firebaseAdminService: FirebaseAdminService,
  ) {}

  @Command({
    command: "matching-engine:test-oracle-price <symbol> <price>",
    description: "Test liquidate",
  })
  async testOraclePrice(symbol: string, oraclePrice: string): Promise<void> {
    await this.indexService.saveOraclePrice(symbol, oraclePrice);
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.LIQUIDATE,
      data: { symbol, oraclePrice },
    });
  }

  @Command({
    command: "matching-engine:test-index-price <symbol> <price>",
    description: "Test liquidate",
  })
  async testIndexPrice(symbol: string, indexPrice: string): Promise<void> {
    await this.indexService.saveIndexPrice(symbol, indexPrice);
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.LIQUIDATE,
      data: { symbol, indexPrice },
    });
  }

  @Command({
    command: "matching-engine:test-performance",
    description: "Test performance",
  })
  async testPerformance(): Promise<void> {
    // const userCount = 100000;
    // const orderCount = 1000000;
    const userCount = 1000;
    const orderCount = 10000;

    const producer = kafka.producer();
    await producer.connect();
    const totalAccount = await this.createAccounts(producer, userCount);

    let accountsOfUsers = [];
    const NUMBER_ACCOUNT_FOR_ONE_USER = 5;
    for (let i = 0; i < totalAccount.length; i += NUMBER_ACCOUNT_FOR_ONE_USER) {
      for (let j = 0; j < NUMBER_ACCOUNT_FOR_ONE_USER; j++) {
        accountsOfUsers.push({
          userId: i + 1,
          accountId: totalAccount[i + j].id,
        });
      }
    }
    // console.log(accountsOfUsers);
    await this.createOrder(producer, userCount, orderCount, accountsOfUsers);
  }

  private async createAccounts(
    producer: Producer,
    userCount: number
  ): Promise<any[]> {
    const batchSize = 1000;
    let totalAccount = [];
    // const batchSize = 1;
    for (let batch = 0; batch < userCount / batchSize; batch++) {
      const accounts = [];
      for (let i = 0; i < batchSize; i++) {
        // console.log(123);
        const account = new AccountEntity();
        account.id = batch * batchSize + i;
        account.balance = "1000000000";
        account.createdAt = new Date();
        account.updatedAt = account.createdAt;
        accounts.push(account);
      }
      const messages = accounts.map((entity) => ({
        value: serialize({ code: CommandCode.CREATE_ACCOUNT, data: entity }),
      }));
      await producer.send({
        topic: "matching_engine_input",
        messages,
      });

      totalAccount = totalAccount.concat(accounts);
    }
    return totalAccount;
  }

  private async createOrder(
    producer: Producer,
    userCount: number,
    orderCount: number,
    accountsOfUsers: any
  ): Promise<void> {
    const batchSize = 2000;
    // const batchSize = 1;
    for (let batch = 0; batch < orderCount / batchSize; batch++) {
      const orders = [];
      for (let i = 0; i < batchSize; i++) {
        const randomId = Math.floor(Math.random() * userCount);
        const order = new OrderEntity();
        order.id = batch * batchSize + i;
        order.side = Math.random() > 0.5 ? OrderSide.BUY : OrderSide.SELL;
        order.price = (
          Math.floor(Math.random() * 10000) +
          50000 +
          (order.side === OrderSide.BUY ? 1000 : -1000)
        ).toString();
        order.quantity = (Math.floor(Math.random() * 10000) / 10000).toString();
        order.remaining = order.quantity;
        order.userId = accountsOfUsers[randomId].userId;
        Math.floor(Math.random() * userCount);
        order.type = OrderType.LIMIT;
        order.timeInForce = OrderTimeInForce.GTC;
        order.symbol = "BTCUSDT";
        order.status = OrderStatus.PENDING;
        order.createdAt = new Date();
        order.updatedAt = order.createdAt;
        order.accountId = accountsOfUsers[randomId].accountId;
        accountsOfUsers;
        order.leverage = "10.000";
        order.asset = "USDT";
        orders.push(order);
      }
      const messages = orders.map((entity) => ({
        value: serialize({ code: CommandCode.PLACE_ORDER, data: entity }),
      }));
      //   console.log(orders);
      await producer.send({
        topic: "matching_engine_input",
        messages,
      });
    }
  }

  @Command({
    command: 'matching-engine:test-admin-firebase-noti <token> <title> <body> <data>',
    description: 'Test admin firebase send noti with custom data',
  })
  async testAdminFirebaseSendNoti(
    token: string,
    title: string,
    body?: string,
    data?: string,
  ): Promise<void> {
    const response = await this.firebaseAdminService.sendMessageToToken(token, title, body, JSON.parse(data));
  
    console.log('✅ Send message successfully:', response);
  }  
}

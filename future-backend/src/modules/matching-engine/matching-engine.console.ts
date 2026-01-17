import {
  CACHE_MANAGER,
  forwardRef,
  Inject,
  Injectable,
  Logger,
} from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Cache } from "cache-manager";
import { Command, Console } from "nestjs-console";
import { kafka } from "src/configs/kafka";
import { AccountEntity } from "src/models/entities/account.entity";
import { InstrumentEntity } from "src/models/entities/instrument.entity";
import { OrderEntity } from "src/models/entities/order.entity";
import { PositionEntity } from "src/models/entities/position.entity";
import { TradeEntity } from "src/models/entities/trade.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { IndexService } from "src/modules/index/index.service";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { convertDateFields } from "src/modules/matching-engine/helper";
import {
  CommandCode,
  CommandOutput,
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  handleTimeout,
  Notification,
  PREFIX_ASSET,
} from "src/modules/matching-engine/matching-engine.const";
import { MatchingEngineService } from "src/modules/matching-engine/matching-engine.service";
import { NotificationService } from "src/modules/matching-engine/notifications.service";
import { OrderService } from "src/modules/order/order.service";
import { PositionService } from "src/modules/position/position.service";
import { InstrumentTypes } from "src/shares/enums/instrument.enum";
import { KafkaGroups, KafkaTopics } from "src/shares/enums/kafka.enum";
import { SocketEmitter } from "src/shares/helpers/socket-emitter";
import { sleep } from "src/shares/helpers/utils";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { BalanceService } from "../balance/balance.service";
import * as fs from "fs";
import BigNumber from "bignumber.js";
import { FirebaseAdminService } from "../firebase-noti-module/firebase-admin.service";
import { UserService } from "../user/users.service";
import { OrderTypeSendEmail } from "../order/order.const";
import { BotService } from "../bot/bot.service";
import { SaveOrderToCacheUseCase } from "./usecase/save-order-to-cache.usecase";
import { SaveOrderToDbUseCase } from "./usecase/save-order-to-db.usecase";
import { SavePositionUseCase } from "./usecase/save-position.usecase";
import { SaveAccountToCacheUseCase } from "./usecase/save-account-to-cache.usecase";
import { SaveAccountToDbUseCase } from "./usecase/save-account-to-db.usecase";
import { SaveMarginHistoriesUseCase } from "./usecase/save-margin-histories.usecase";
import { SavePositionHistoriesUseCase } from "./usecase/save-position-histories.usecase";
import { SavePositionHistoryBySessionFromMarginHistoryUseCase } from "./usecase/save-position-history-by-session-from-margin-history.usecase";
import { SaveUserPositionToCacheUseCase } from "./usecase/save-user-position-to-cache.usecase";
import { CheckToSeedLiquidationOrderIdsUseCase } from "./usecase/check-to-seed-liq-order-ids.usecase";

interface Map<T> {
  [key: string]: T;
}

@Console()
@Injectable()
export class MatchingEngineConsole {
  private readonly logger = new Logger(MatchingEngineConsole.name);

  constructor(
    private readonly balanceService: BalanceService,
    private readonly positionService: PositionService,
    private readonly orderService: OrderService,
    private readonly matchingEngineService: MatchingEngineService,
    private readonly instrumentService: InstrumentService,
    private readonly indexService: IndexService,
    private readonly notificationService: NotificationService,
    public readonly kafkaClient: KafkaClient,
    @InjectRepository(AccountRepository, "report")
    private accountRepository: AccountRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly firebaseAdminService: FirebaseAdminService,
    private readonly userService: UserService,
    @Inject(forwardRef(() => BotService))
    private readonly botService: BotService,
    private readonly saveOrderToCacheUseCase: SaveOrderToCacheUseCase,
    private readonly saveOrderToDbUseCase: SaveOrderToDbUseCase,
    private readonly savePositionUseCase: SavePositionUseCase,
    private readonly saveAccountToCacheUseCase: SaveAccountToCacheUseCase,
    private readonly saveAccountToDbUseCase: SaveAccountToDbUseCase,
    private readonly saveMarginHistoriesUseCase: SaveMarginHistoriesUseCase,
    private readonly savePositionHistoriesUseCase: SavePositionHistoriesUseCase,
    private readonly savePositionHistoryBySessionFromMarginHistoryUseCase: SavePositionHistoryBySessionFromMarginHistoryUseCase,
    private readonly saveUserPositionToCacheUseCase: SaveUserPositionToCacheUseCase,
    private readonly checkToSeedLiquidationOrderIdsUseCase: CheckToSeedLiquidationOrderIdsUseCase
  ) {}

  @Command({
    command: "matching-engine:test-send-trades",
    description: "Load data into matching engine",
  })
  async testSendTrades(): Promise<void> {
    console.log("RUNNNNN");
    try {
      let testTicker: any = await fs.promises.readFile(
        `./test_trades.json`,
        "utf-8"
      );
      testTicker = JSON.parse(testTicker);
      console.log(testTicker);
      SocketEmitter.getInstance().emitTickers(testTicker);
    } catch (e) {
      console.log(e);
    }
  }

  @Command({
    command: "matching-engine:load",
    description: "Load data into matching engine",
  })
  async load(): Promise<void> {
    // await this.kafkaClient.delete([KafkaTopics.matching_engine_preload]);

    // let laggedGroup = await this.getLaggedGroup();
    // while (laggedGroup) {
    //   this.logger.log(
    //     `Waiting for topic ${KafkaTopics.matching_engine_output}, group ${laggedGroup.group} to be consumed. Current lag: ${laggedGroup.combinedLag}`,
    //   );
    //   await sleep(1000);
    //   laggedGroup = await this.getLaggedGroup();
    // }

    const producer = kafka.producer();
    await producer.connect();

    console.log("initializeEngine");
    await this.matchingEngineService.initializeEngine(producer);
    console.log("loadInstruments");
    await this.matchingEngineService.loadInstruments(producer);
    console.log("loadInstrumentExtras");
    await this.matchingEngineService.loadInstrumentExtras(producer);
    console.log("loadLeverageMargin");
    await this.matchingEngineService.loadLeverageMargin(producer);
    console.log("loadAccounts");
    await this.matchingEngineService.loadAccounts(producer);
    console.log("loadBotAccounts");
    await this.matchingEngineService.loadBotAccounts(producer);
    console.log("loadPositions");
    await this.matchingEngineService.loadPositions(producer);
    console.log("loadPositionHistories");
    await this.matchingEngineService.loadPositionHistories(producer);
    console.log("loadFundingHistories");
    await this.matchingEngineService.loadFundingHistories(producer);
    console.log("loadDeposits");
    await this.matchingEngineService.loadDeposits(producer);
    console.log("loadWithdrawals");
    await this.matchingEngineService.loadWithdrawals(producer);
    console.log("loadOrders");
    await this.matchingEngineService.loadOrders(producer);
    console.log("loadTradingRules");
    await this.matchingEngineService.loadTradingRules(producer);
    console.log("startEngine");
    await this.matchingEngineService.startEngine(producer);
    await producer.disconnect();
  }

  @Command({
    command: "test:matching-engine:load",
    description: "Load data into matching engine (test)",
  })
  async testLoad(): Promise<void> {
    const producer = kafka.producer();
    await producer.connect();

    await this.matchingEngineService.initializeEngine(producer, true);
    await this.matchingEngineService.loadInstruments(producer, true);
    await this.matchingEngineService.loadInstrumentExtras(producer, true);
    await this.matchingEngineService.loadLeverageMargin(producer, true);
    await this.matchingEngineService.loadAccounts(producer, true);
    await this.matchingEngineService.loadPositions(producer, true);
    await this.matchingEngineService.loadPositionHistories(producer, true);
    await this.matchingEngineService.loadFundingHistories(producer, true);
    await this.matchingEngineService.loadDeposits(producer, true);
    await this.matchingEngineService.loadWithdrawals(producer, true);
    await this.matchingEngineService.loadOrders(producer, true);
    await this.matchingEngineService.loadTradingRules(producer, true);
    await this.matchingEngineService.startEngine(producer, true);
    await producer.disconnect();
  }

  async getLaggedGroup(): Promise<{ group: string; combinedLag: number }> {
    const groups = [
      "matching_engine_saver_accounts",
      "matching_engine_saver_positions",
      "matching_engine_saver_orders",
      "matching_engine_saver_trades",
      "matching_engine_saver_transactions",
      "matching_engine_saver_position_histories",
      "matching_engine_saver_funding",
      "matching_engine_saver_margin_histories",
    ];

    for (const group of groups) {
      const combinedLag = await this.kafkaClient.getCombinedLag(
        KafkaTopics.matching_engine_output,
        group
      );
      if (combinedLag > 0) {
        return { group, combinedLag };
      }
    }
  }

  @Command({
    command: "matching-engine:save-accounts-to-cache",
  })
  async saveAccountsToCache(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.matching_engine_output,
      KafkaGroups.save_account_to_cache,
      async (commands: CommandOutput[]) => {
        // await this.matchingEngineService.saveAccountsToCache(commands);
        await this.saveAccountToCacheUseCase.execute(commands);
      }
    );

    return new Promise(() => {});
  }

  // @Command({
  //   command: "matching-engine:save-accounts",
  //   description: "Save accounts",
  // })
  // async saveAccounts(): Promise<void> {
  //   await this.saveEntities(KafkaGroups.matching_engine_saver_accounts, (commands) => this.matchingEngineService.saveAccountsV2(commands));
  // }

  @Command({
    command: "matching-engine:save-accounts-to-db",
    description: "Save accounts to db",
  })
  async saveAccountsToDb(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.matching_engine_output,
      KafkaGroups.save_account_to_db,
      async (commands: CommandOutput[]) => {
        await this.saveAccountToDbUseCase.execute(commands);
      }
    );
    return new Promise(() => {});
  }

  @Command({
    command: "matching-engine:save-positions",
    description: "Save positions",
  })
  async savePositions(): Promise<void> {
    // await this.saveEntities(KafkaGroups.matching_engine_saver_positions, (commands) => this.matchingEngineService.savePositionsV2(commands));
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_positions,
      (commands) => this.savePositionUseCase.execute(commands)
    );
  }

  @Command({
    command: "matching-engine:save-user-position-to-cache",
  })
  async saveUserPositionToCache(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.save_user_position_to_cache,
      KafkaGroups.save_user_position_to_cache,
      async (positionMessage: string) => {
        await this.saveUserPositionToCacheUseCase.execute(positionMessage);
      }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "matching-engine:save-orders",
    description: "Save orders",
  })
  async saveOrders(): Promise<void> {
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_orders,
      (commands) => this.matchingEngineService.saveOrdersV2(commands)
    );
  }

  @Command({
    command: "matching-engine:save-orders-to-cache",
  })
  async saveOrdersToCache(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.matching_engine_output,
      KafkaGroups.save_orders_to_cache,
      async (commands: CommandOutput[]) => {
        await this.saveOrderToCacheUseCase.execute(commands);
      }
    );

    return new Promise(() => {});
  }

  @Command({
    command: "matching-engine:save-orders-to-db",
  })
  async saveOrdersToDb(): Promise<void> {
    await this.kafkaClient.consume(
      KafkaTopics.matching_engine_output,
      KafkaGroups.save_order_to_db,
      async (commands: CommandOutput[]) => {
        await this.saveOrderToDbUseCase.execute(commands);
      }
    );

    return new Promise(() => {});
  }

  // @Command({
  //   command: "matching-engine:save-orders-to-db",
  // })
  // async saveOrdersToDb(): Promise<void> {
  //   await this.kafkaClient.consume(
  //     KafkaTopics.save_order_to_db,
  //     KafkaGroups.save_order_to_db,
  //     async (orderMsg: any) => {
  //       await this.matchingEngineService.saveOrdersToDb(orderMsg);
  //     }
  //   );

  //   return new Promise(() => {});
  // }

  @Command({
    command: "matching-engine:save-trades",
    description: "Save trades",
  })
  async saveTrades(): Promise<void> {
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_trades,
      (commands) => this.matchingEngineService.saveTrades(commands)
    );
  }

  @Command({
    command: "matching-engine:save-transactions",
    description: "Save transactions",
  })
  async saveTransactions(): Promise<void> {
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_transactions,
      (commands) => this.matchingEngineService.saveTransactions(commands)
    );
  }

  @Command({
    command: "matching-engine:save-position-histories",
    description: "Save position histories",
  })
  async savePositionHistories(): Promise<void> {
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_position_histories,
      // (commands) => this.matchingEngineService.savePositionHistories(commands)
      (commands) => this.savePositionHistoriesUseCase.execute(commands)
    );
  }

  @Command({
    command: "matching-engine:save-funding",
    description: "Save funding",
  })
  async saveFunding(): Promise<void> {
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_funding,
      (commands) => this.matchingEngineService.saveFunding(commands)
    );
  }

  @Command({
    command: "matching-engine:save-margin-histories",
    description: "Save margin histories",
  })
  async saveMarginHistories(): Promise<void> {
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_margin_histories,
      (commands) => this.saveMarginHistoriesUseCase.execute(commands)
    );
  }

  @Command({
    command: "matching-engine:save-position-history-by-session-from-margin-history",
    description: "Save position history by session from margin histories",
  })
  async savePositionHistoryBySessions(): Promise<void> {
    await this.kafkaClient.consume<CommandOutput[]>(
      KafkaTopics.matching_engine_output,
      KafkaGroups.save_position_history_by_session_from_margin_history,
      async (commands) => {
        await this.savePositionHistoryBySessionFromMarginHistoryUseCase.execute(commands);
      }, 
    );

    return new Promise(() => {});
  }

  @Command({
    command: "matching-engine:save-margin-leverage",
    description: "Save margin mode and leverage",
  })
  async saveMarginLeverage(): Promise<void> {
    await this.saveEntities(
      KafkaGroups.matching_engine_saver_margin_leverage,
      (commands) => this.matchingEngineService.saveMarginLeverage(commands)
    );
  }

  @Command({
    command: "matching-engine:get-offset [offset] [topic]",
    description: "Save margin mode and leverage",
  })
  async getOffset(offset: string, topic: string): Promise<void> {
    await this.kafkaClient
      .getMessageAtOffset(offset, topic)
      .then(console.error);
  }

  async saveEntities(
    groupId: string,
    callback: (commands: CommandOutput[]) => Promise<void>
  ): Promise<void> {
    await this.kafkaClient.consume<CommandOutput[]>(
      KafkaTopics.matching_engine_output,
      groupId,
      async (commands) => {
        await callback(commands);
      },
      { fromBeginning: true }
    );

    return new Promise(() => {});
  }

  private static botUserIds: number[] = [];
  @Command({
    command: "matching-engine:notify",
    description: "Notify output from matching engine via socket",
  })
  async notify(): Promise<void> {
    MatchingEngineConsole.botUserIds = await this.botService.getBotIdsFromDB();
    const instrumentMap = await this.getInstrumentMap();
    let accounts = [];
    let positions = [];
    let orders = [];
    let notifications = [];
    let adjustLeverage = [];
    let adjustMarginPosition = [];
    let countStep = 0;
    await this.kafkaClient.consume<CommandOutput[]>(
      KafkaTopics.matching_engine_output,
      KafkaGroups.matching_engine_notifier,
      async (commands) => {
        try {
          for (const command of commands) {
            if (command.adjustLeverage) {
              if (
                command.adjustLeverage.userId &&
                !MatchingEngineConsole.botUserIds.includes(
                  +command.adjustLeverage.userId
                )
              ) {
                adjustLeverage.push(command.adjustLeverage);
              }
            }

            if (command.orders.length > 0) {
              command.orders.forEach((order) => {
                if (
                  order.userId &&
                  !MatchingEngineConsole.botUserIds.includes(+order.userId)
                ) {
                  orders.push(
                    convertDateFields(new OrderEntity(), {
                      ...order,
                      isShowToast: command.adjustLeverage ? false : true,
                    })
                  );
                }
              });
            }

            if (command.code === CommandCode.ADJUST_MARGIN_POSITION) {
              if (
                command.data.userId &&
                !MatchingEngineConsole.botUserIds.includes(+command.data.userId)
              ) {
                adjustMarginPosition.push({ ...command.data });
              }
            }

            if (command.accounts.length > 0) {
              command.accounts.forEach((account) => {
                if (
                  account.userId &&
                  !MatchingEngineConsole.botUserIds.includes(+account.userId)
                ) {
                  accounts.push(
                    convertDateFields(new AccountEntity(), account)
                  );
                }
              });
            }

            if (command.positions.length > 0) {
              command.positions.forEach((position) => {
                if (
                  position.userId &&
                  !MatchingEngineConsole.botUserIds.includes(+position.userId)
                ) {
                  positions.push(
                    convertDateFields(new PositionEntity(), position)
                  );
                }
              });
            }

            const commandNotifications = await this.notificationService.createNotifications(
              command,
              instrumentMap
            );
            notifications.push(...commandNotifications);
            // this.logger.log(`Notifications of command ${command.code}: ${JSON.stringify(commandNotifications)}`);
          }

          console.log("NOTIFY MATCHING ENGINE countStep=", countStep++);
          await this.notifyAccounts(accounts);
          await this.notifyPositions(positions);
          await this.notifyOrders(orders);

          await this.notifyNotifications(notifications);
          await this.notifyAdjustLeverage(adjustLeverage);
          await this.notifyAdjustMarginPosition(adjustMarginPosition);
          accounts = [];
          positions = [];
          orders = [];
          notifications = [];
          adjustLeverage = [];
          adjustMarginPosition = [];
        } catch (e) {
          this.logger.error(e);
          this.logger.error(`Message: ${JSON.stringify(commands)}`);
        }
      },
      { fromBeginning: true }
    );

    // setInterval(async () => {
    //   await this.notifyAccounts(accounts);
    //   await this.notifyPositions(positions);
    //   await this.notifyOrders(orders);
    //   this.notifyTrades(trades);
    //   await this.notifyNotifications(notifications);
    //   await this.notifyAdjustLeverage(adjustLeverage);
    //   await this.notifyAdjustMarginPosition(adjustMarginPosition);
    //   accounts = [];
    //   positions = [];
    //   orders = [];
    //   trades = [];
    //   notifications = [];
    //   adjustLeverage = [];
    //   adjustMarginPosition = [];
    // }, 200);

    return new Promise(() => {});
  }

  private async getInstrumentMap(): Promise<Map<InstrumentEntity>> {
    const instruments = await this.instrumentService.getAllInstruments();
    return instruments.reduce(
      (
        map: Map<InstrumentEntity>,
        instrument: InstrumentEntity
      ): Map<InstrumentEntity> => {
        map[instrument.symbol] = instrument;
        return map;
      },
      {}
    );
  }

  private async notifyAccounts(accounts: AccountEntity[]): Promise<void> {
    await Promise.all(
      accounts.map(async (account: AccountEntity) => {
        // const formattedAccount = await this.balanceService.formatAccountBeforeResponse(account);
        SocketEmitter.getInstance().emitAccount(account.userId, account);
      })
    );
  }

  private async notifyPositions(positions: PositionEntity[]): Promise<void> {
    const map = positions.reduce(
      (
        map: Map<PositionEntity>,
        position: PositionEntity
      ): Map<PositionEntity> => {
        map[position.id] = position;
        return map;
      },
      {}
    );

    for (const id in map) {
      const position = map[id];
      position.positionMargin = new BigNumber(position.positionMargin)
        .plus(position.tmpTotalFee)
        .toString();
      SocketEmitter.getInstance().emitPosition(position, position.userId);
    }
  }

  private async notifyAdjustLeverage(adjustLeverages): Promise<void> {
    for (const adjustLeverage of adjustLeverages) {
      SocketEmitter.getInstance().emitAdjustLeverage(
        adjustLeverage,
        +adjustLeverage.userId
      );
    }
  }

  private async notifyAdjustMarginPosition(marginPositions): Promise<void> {
    for (const marginPosition of marginPositions) {
      SocketEmitter.getInstance().emitAdjustMarginPosition(
        marginPosition,
        marginPosition.userId
      );
    }
  }

  private async notifyOrders(orders: OrderEntity[]): Promise<void> {
    // only get latest order
    const map = orders.reduce(
      (map: Map<OrderEntity>, order: OrderEntity): Map<OrderEntity> => {
        map[order.id] = order;
        return map;
      },
      {}
    );

    // group by accountId
    const orderMap = Object.values(map).reduce(
      (map: Map<OrderEntity[]>, order: OrderEntity): Map<OrderEntity[]> => {
        const list = map[order.userId] || [];
        list.push(order);
        map[order.userId] = list;
        return map;
      },
      {}
    );

    for (const id in orderMap) {
      const orders = orderMap[id];
      SocketEmitter.getInstance().emitOrders(orders, +id);
    }
  }

  private async loadMissingAccounts(
    currentIds: string[],
    accountMap: Map<number>
  ): Promise<Map<number>> {
    const missingAccountIds = currentIds.filter(
      (accountId) => !accountMap[accountId]
    );
    if (missingAccountIds.length > 0) {
      const missingAccounts = await this.accountRepository.getAccountsByIds(
        missingAccountIds
      );
      accountMap = missingAccounts.reduce(
        (map: Map<number>, account: AccountEntity): Map<number> => {
          map[account.userId] = account.userId;
          return map;
        },
        accountMap
      );
    }
    return accountMap;
  }

  // private notifyTrades(trades: TradeEntity[]): void {
  //   const map = trades.reduce((map: Map<TradeEntity[]>, trade: TradeEntity): Map<TradeEntity[]> => {
  //     const list = map[trade.symbol] || [];
  //     list.push(trade);
  //     map[trade.symbol] = list;
  //     return map;
  //   }, {});

  //   for (const symbol in map) {
  //     SocketEmitter.getInstance().emitTrades(map[symbol], symbol);
  //   }
  // }

  private async notifyNotifications(
    notifications: Notification[]
  ): Promise<void> {
    const map = notifications.reduce(
      (
        map: Map<Notification[]>,
        notification: Notification
      ): Map<Notification[]> => {
        if (notification) {
          const list = map[notification.userId] || [];
          list.push(notification);
          map[notification.userId] = list;
          return map;
        }
        this.logger.warn("Notification null");
        return map;
      },
      {}
    );

    for (const userId in map) {
      SocketEmitter.getInstance().emitNotifications(map[userId], +userId);
    }
  }

  @Command({
    command: "matching-engine:save-prefix",
    description: "Save margin mode and leverage",
  })
  async savePrefix(): Promise<void> {
    const instruments = await this.instrumentService.find();
    const task = [];
    for (const instrument of instruments) {
      task.push(
        this.cacheManager.set(
          `${PREFIX_ASSET}${instrument.symbol}`,
          instrument.contractType === InstrumentTypes.USD_M
            ? instrument.quoteCurrency
            : instrument.rootSymbol,
          { ttl: 0 }
        )
      );
    }
  }

  @Command({
    command: "matching-engine:check-to-seed-liquidation-order-ids",
    description: "Check to seed liquidation order ids",
  })
  private async checkToSeedLiquidationOrderIds(): Promise<void> {
    const producer = kafka.producer();
    await producer.connect();

    await this.kafkaClient.consume<CommandOutput[]>(
      KafkaTopics.matching_engine_output,
      KafkaGroups.check_to_seed_liq_order_ids,
      async (commands) => {
        await this.checkToSeedLiquidationOrderIdsUseCase.execute(producer, commands);
      }, 
    );

    return new Promise(() => {});
  }
}

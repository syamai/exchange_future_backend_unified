/* eslint-disable @typescript-eslint/no-unused-vars */
/* eslint-disable @typescript-eslint/no-explicit-any */
import { CACHE_MANAGER, Inject, Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Cache } from "cache-manager";
import * as config from "config";
import { Producer } from "kafkajs";
import { MatchingEngineConfig } from "src/configs/matching.config";
import { AccountEntity } from "src/models/entities/account.entity";
import { FundingHistoryEntity } from "src/models/entities/funding-history.entity";
import { MarginHistoryEntity } from "src/models/entities/margin-history";
import { MIN_ORDER_ID, OrderEntity } from "src/models/entities/order.entity";
import { PositionHistoryEntity } from "src/models/entities/position-history.entity";
import { PositionEntity } from "src/models/entities/position.entity";
import { TradeEntity } from "src/models/entities/trade.entity";
import { TransactionEntity } from "src/models/entities/transaction.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { FundingHistoryRepository } from "src/models/repositories/funding-history.repository";
import { FundingRepository } from "src/models/repositories/funding.repository";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { OrderRepository } from "src/models/repositories/order.repository";
import { PositionHistoryRepository } from "src/models/repositories/position-history.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import { AccountService } from "src/modules/account/account.service";
import { FundingService } from "src/modules/funding/funding.service";
import { IndexService } from "src/modules/index/index.service";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { BaseEngineService } from "src/modules/matching-engine/base-engine.service";
import { convertDateFields, convertDateFieldsForOrders, convertFundingHistoriesDateFields } from "src/modules/matching-engine/helper";

import {
  BATCH_SIZE,
  CommandCode,
  CommandOutput,
  FUNDING_HISTORY_TIMESTAMP_KEY,
  FUNDING_HISTORY_TIMESTAMP_TTL,
  POSITION_HISTORY_TIMESTAMP_KEY,
  POSITION_HISTORY_TIMESTAMP_TTL,
  PREFIX_ASSET,
} from "src/modules/matching-engine/matching-engine.const";
import { OrderService } from "src/modules/order/order.service";
import { PositionService } from "src/modules/position/position.service";
import { TradeService } from "src/modules/trade/trade.service";
import { TransactionService } from "src/modules/transaction/transaction.service";
import { FutureEventKafkaTopic, KafkaTopics } from "src/shares/enums/kafka.enum";
import { ContractType, OrderNote, OrderStatus } from "src/shares/enums/order.enum";
import { TransactionStatus, TransactionType } from "src/shares/enums/transaction.enum";
import { UserMarginModeRepository } from "src/models/repositories/user-margin-mode.repository";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { LeverageMarginService } from "../leverage-margin/leverage-margin.service";
import { TradingRulesRepository } from "src/models/repositories/trading-rules.repository";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import BigNumber from "bignumber.js";
import { TICKERS_LAST_PRICE_KEY } from "../ticker/ticker.const";
import { COINM } from "../instrument/instrument.const";
import { InstrumentTypes } from "src/shares/enums/instrument.enum";
import { ADMIN_ID } from "src/models/entities/user.entity";
import axios from "axios";
import { BotService } from "../bot/bot.service";
import { LinkedQueue } from "src/utils/linked-queue";
import { OrderAverageByTradeRepository } from "src/models/repositories/order-average-by-trade.repository";
import { OrderAverageByTradeEntity } from "src/models/entities/order-average-by-trade.entity";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { v4 as uuidv4 } from "uuid";
import { BotInMemoryService } from "../bot/bot.in-memory.service";
import { UserTradeToRemoveBotOrderRepository } from "src/models/repositories/user-trade-to-remove-bot-order.repository";
import { UserTradeToRemoveBotOrderEntity } from "src/models/entities/user-trade-to-remove-bot-order.entity";
import { OPERATION_ID_DIVISOR } from "src/shares/number-formatter";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import { OrderRouterService } from "src/shares/order-router/order-router.service";

@Injectable()
export class MatchingEngineService extends BaseEngineService {
  private readonly MAX_ACCOUNT_QUEUE_SIZE = 100;
  private readonly MAX_ORDER_QUEUE_SIZE = 200;
  private readonly MAX_POSITION_QUEUE_SIZE = 100;
  private readonly MAX_POSITION_HISTORIES_QUEUE_SIZE = 100;
  constructor(
    private readonly accountService: AccountService,
    private readonly fundingService: FundingService,
    private readonly indexService: IndexService,
    private readonly instrumentService: InstrumentService,
    private readonly orderService: OrderService,
    private readonly positionService: PositionService,
    private readonly tradeService: TradeService,
    private readonly transactionService: TransactionService,
    private readonly leverageMarginService: LeverageMarginService,
    private readonly kafkaClient: KafkaClient,
    @InjectRepository(AccountRepository, "master")
    private accountRepository: AccountRepository,
    @InjectRepository(AccountRepository, "report")
    private accountRepoReport: AccountRepository,
    @InjectRepository(InstrumentRepository, "report")
    private instrumentRepoReport: InstrumentRepository,
    @InjectRepository(PositionRepository, "master")
    private positionRepository: PositionRepository,
    @InjectRepository(OrderRepository, "master")
    private orderRepository: OrderRepository,
    @InjectRepository(TradeRepository, "master")
    private tradeRepository: TradeRepository,
    @InjectRepository(TransactionRepository, "master")
    private transactionRepository: TransactionRepository,
    @InjectRepository(FundingRepository, "master")
    private fundingRepoMaster: FundingRepository,
    @InjectRepository(FundingHistoryRepository, "master")
    private fundingHistoryRepoMaster: FundingHistoryRepository,
    @InjectRepository(PositionHistoryRepository, "master")
    private positionHistoryRepoMaster: PositionHistoryRepository,
    @InjectRepository(MarginHistoryRepository, "master")
    private marginHistoryRepoMaster: MarginHistoryRepository,
    @InjectRepository(UserMarginModeRepository, "master")
    private userMarginModeRepoMaster: UserMarginModeRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    @InjectRepository(TradingRulesRepository, "report")
    private tradingRuleRepoReport: TradingRulesRepository,
    @InjectRepository(MarginHistoryRepository, "report")
    private marginHistoryRepoReport: MarginHistoryRepository,
    @InjectRepository(OrderAverageByTradeRepository, "report")
    private readonly orderAverageByTradeRepoReport: OrderAverageByTradeRepository,
    @InjectRepository(OrderAverageByTradeRepository, "master")
    private readonly orderAverageByTradeRepoMaster: OrderAverageByTradeRepository,
    private readonly botService: BotService,
    private readonly botInMemoryService: BotInMemoryService,
    // private readonly redisService: RedisService,
    private readonly redisClient: RedisClient,
    @InjectRepository(UserTradeToRemoveBotOrderRepository, "master")
    private readonly userTradeToRemoveBotOrderRepoMaster: UserTradeToRemoveBotOrderRepository,
    private readonly orderRouterService: OrderRouterService
  ) {
    super();
  }
  private readonly logger = new Logger(MatchingEngineService.name);

  public async initializeEngine(producer: Producer, isTest: boolean = false): Promise<void> {
    const lastOrderId = await this.orderService.getLastOrderId();
    const liquidationOrderIds = await this.orderService.getLiquidationOrderIds();
    const lastPositionId = await this.positionService.getLastPositionId();
    const lastTradeId = await this.tradeService.getLastTradeId();
    const lastMarginHistoryId = await this.marginHistoryRepoMaster.getLastId();
    const lastPositionHistoryId = await this.positionHistoryRepoMaster.getLastId();
    const lastFundingHistoryId = await this.fundingHistoryRepoMaster.getLastId();
    const command = {
      code: CommandCode.INITIALIZE_ENGINE,
      data: {
        lastOrderId,
        liquidationOrderIds,
        lastPositionId,
        lastTradeId,
        lastMarginHistoryId,
        lastPositionHistoryId,
        lastFundingHistoryId,
      },
    };

    // Broadcast to all shards when sharding is enabled
    if (!isTest && this.orderRouterService.isShardingEnabled()) {
      await this.orderRouterService.broadcastToAllShards(command, true);
    } else {
      await producer.send({
        topic: isTest
          ? KafkaTopics.test_matching_engine_preload
          : KafkaTopics.matching_engine_preload,
        messages: [{ value: JSON.stringify(command) }],
      });
    }
  }

  public async loadInstruments(producer: Producer, isTest: boolean = false): Promise<void> {
    const instruments = await this.instrumentService.find();
    const task = [];
    for (const instrument of instruments) {
      task.push(
        this.cacheManager.set(
          `${PREFIX_ASSET}${instrument.symbol}`,
          instrument.contractType === InstrumentTypes.USD_M ? instrument.quoteCurrency : instrument.rootSymbol,
          { ttl: 0 }
        )
      );
    }
    await Promise.all([
      task,
      this.sendDataToPreloadTopics(
        producer,
        CommandCode.UPDATE_INSTRUMENT,
        instruments,
        isTest,
        this.orderRouterService
      )
    ]);
  }

  public async loadInstrumentExtras(producer: Producer, isTest: boolean = false): Promise<void> {
    const instruments = await this.instrumentService.find();
    const symbols = instruments.map((instrument) => instrument.symbol);
    const tickers: [] = await this.cacheManager.get(TICKERS_LAST_PRICE_KEY);

    const tickerObject =
      tickers !== null
        ? tickers.reduce((acc, { symbol, lastPrice }) => {
            acc[symbol] = lastPrice;
            return acc;
          }, {})
        : [];
    const [indexPrices, oraclePrices, fundingRates] = await Promise.all([
      this.indexService.getIndexPrices(symbols),
      this.indexService.getOraclePrices(symbols),
      this.fundingService.getFundingRates(symbols),
    ]);
    const instrumentExtras = [];
    for (const i in symbols) {
      instrumentExtras.push({
        symbol: symbols[i],
        oraclePrice: oraclePrices[i] ?? 300,
        indexPrice: indexPrices[i],
        fundingRate: fundingRates[i],
        lastPrice: tickerObject[`${symbols[i]}`] ? tickerObject[`${symbols[i]}`] : null,
      });
    }
    await this.sendDataToPreloadTopics(
      producer,
      CommandCode.UPDATE_INSTRUMENT_EXTRA,
      instrumentExtras,
      isTest,
      this.orderRouterService
    );
  }

  public async loadAccounts(producer: Producer, isTest: boolean = false): Promise<void> {
    const loader = async (fromId: number, size: number): Promise<AccountEntity[]> => {
      return await this.accountService.findBatch(fromId, size);
    };
    await this.loadDataSharded(
      producer,
      loader,
      CommandCode.CREATE_ACCOUNT,
      isTest,
      this.orderRouterService
    );
  }

  public async loadBotAccounts(producer: Producer, isTest: boolean = false): Promise<void> {
    const botAccounts = await this.accountService.getBotAccounts();
    if (botAccounts.length === 0) return;
    await this.sendDataToPreloadTopics(
      producer,
      CommandCode.LOAD_BOT_ACCOUNT,
      botAccounts,
      isTest,
      this.orderRouterService
    );
  }

  public async loadPositions(producer: Producer, isTest: boolean = false): Promise<void> {
    const loader = async (fromId: number, size: number): Promise<PositionEntity[]> => {
      return await this.positionService.findBatch(fromId, size);
    };
    await this.loadDataSharded(
      producer,
      loader,
      CommandCode.LOAD_POSITION,
      isTest,
      this.orderRouterService
    );
  }

  public async loadPositionHistories(producer: Producer, isTest: boolean = false): Promise<void> {
    const date = new Date(Date.now() - MatchingEngineConfig.positionHistoryTime);
    const positionHistory = await this.positionService.findHistoryBefore(date);
    const firstId = positionHistory?.id || 0;
    const loader = async (fromId: number, size: number): Promise<PositionHistoryEntity[]> => {
      return await this.positionService.findHistoryBatch(Math.max(firstId, fromId), size);
    };
    await this.loadDataSharded(
      producer,
      loader,
      CommandCode.LOAD_POSITION_HISTORY,
      isTest,
      this.orderRouterService
    );

    await this.savePositionHistoryTimestamp(date.getTime());
  }

  public async loadFundingHistories(producer: Producer, isTest: boolean = false): Promise<void> {
    const date = new Date(Date.now() - MatchingEngineConfig.fundingHistoryTime);
    const fundingHistory = await this.fundingService.findHistoryBefore(date);
    const firstId = fundingHistory?.id || 0;
    const loader = async (fromId: number, size: number): Promise<FundingHistoryEntity[]> => {
      return await this.fundingService.findHistoryBatch(Math.max(firstId, fromId), size);
    };
    await this.loadDataSharded(
      producer,
      loader,
      CommandCode.LOAD_FUNDING_HISTORY,
      isTest,
      this.orderRouterService
    );

    await this.saveFundingHistoryTimestamp(date.getTime());
  }

  public async loadOrders(producer: Producer, isTest: boolean = false): Promise<void> {
    // await this.loadOrderByStatus(OrderStatus.ACTIVE, producer, isTest);
    // await this.loadOrderByStatus(OrderStatus.UNTRIGGERED, producer, isTest);
    // await this.loadOrderByStatus(OrderStatus.PENDING, producer, isTest);

    await this.loadActiveOrder(producer, isTest);
    await this.loadUnTriggedOrder(producer, isTest);
    // await this.loadPendingOrder(producer, isTest);
  }

  private async loadPendingOrder(producer: Producer, isTest: boolean = false) {
    // get all order PENDING
    const orders = await this.orderRepository.find({ where: { status: OrderStatus.PENDING } });
    if (orders && orders.length > 0) {
      await this.sendDataToPreloadTopics(
        producer,
        CommandCode.LOAD_ORDER,
        orders,
        isTest,
        this.orderRouterService
      );
    }
  }

  private async loadActiveOrder(producer: Producer, isTest: boolean = false) {
    // get all order ACTIVE
    const orders = await this.orderRepository.find({ where: { status: OrderStatus.ACTIVE } });
    if (orders && orders.length > 0) {
      let ordersToSend = [];
      let i = 0;
      while (true) {
        ordersToSend.push(orders[i]);
        if (
          ordersToSend.length === BATCH_SIZE ||
          i === orders.length - 1
        ) {
          await this.sendDataToPreloadTopics(
            producer,
            CommandCode.LOAD_ORDER,
            ordersToSend,
            isTest,
            this.orderRouterService
          );
          ordersToSend = [];
        }

        if (i === orders.length - 1) break;
        i++;
      };
    }
  }

  private async loadUnTriggedOrder(producer: Producer, isTest: boolean = false) {
    // get all order UNTRIGGERED
    const orders = await this.orderRepository.find({ where: { status: OrderStatus.UNTRIGGERED } });
    if (orders && orders.length > 0) {
      await this.sendDataToPreloadTopics(
        producer,
        CommandCode.LOAD_ORDER,
        orders,
        isTest,
        this.orderRouterService
      );
    }
  }

  async loadOrderByStatus(status: OrderStatus, producer: Producer, isTest: boolean = false): Promise<void> {
    const loader = async (fromId: number, size: number): Promise<OrderEntity[]> => {
      return await this.orderService.findOrderBatch(status, fromId, size);
    };
    await this.loadDataSharded(
      producer,
      loader,
      CommandCode.LOAD_ORDER,
      isTest,
      this.orderRouterService
    );
  }

  public async loadDeposits(producer: Producer, isTest: boolean = false): Promise<void> {
    const yesterday = new Date(Date.now() - 86400000);
    const loader = async (fromId: number, size: number): Promise<TransactionEntity[]> => {
      return await this.transactionService.findRecentDeposits(yesterday, fromId, size);
    };
    await this.loadDataSharded(
      producer,
      loader,
      CommandCode.DEPOSIT,
      isTest,
      this.orderRouterService
    );
  }

  public async loadWithdrawals(producer: Producer, isTest: boolean = false): Promise<void> {
    const loader = async (fromId: number, size: number): Promise<TransactionEntity[]> => {
      return await this.transactionService.findPendingWithdrawals(fromId, size);
    };
    await this.loadDataSharded(
      producer,
      loader,
      CommandCode.WITHDRAW,
      isTest,
      this.orderRouterService
    );
  }

  public async loadLeverageMargin(producer: Producer, isTest: boolean = false): Promise<void> {
    const leverageMargins = await this.leverageMarginService.findAll();
    await this.sendDataToPreloadTopics(
      producer,
      CommandCode.LOAD_LEVERAGE_MARGIN,
      leverageMargins,
      isTest,
      this.orderRouterService
    );
  }

  public async loadTradingRules(producer: Producer, isTest: boolean = false): Promise<void> {
    const tradingRules = await this.tradingRuleRepoReport.find();
    await this.sendDataToPreloadTopics(
      producer,
      CommandCode.LOAD_TRADING_RULE,
      tradingRules,
      isTest,
      this.orderRouterService
    );
  }

  public async startEngine(producer: Producer, isTest: boolean = false): Promise<void> {
    const command = { code: CommandCode.START_ENGINE };

    // Broadcast to all shards when sharding is enabled
    if (!isTest && this.orderRouterService.isShardingEnabled()) {
      await this.orderRouterService.broadcastToAllShards(command, true);
    } else {
      await producer.send({
        topic:
          isTest
            ? KafkaTopics.test_matching_engine_preload
            : KafkaTopics.matching_engine_preload,
        messages: [{ value: JSON.stringify(command) }],
      });
    }
  }

  private static saveAccountQueue = new LinkedQueue<any>();
  private static saveAccountInterval = null;
  public async saveAccounts(commands: CommandOutput[]): Promise<void> {
    if (!MatchingEngineService.saveAccountInterval) {
      MatchingEngineService.saveAccountInterval = setInterval(async () => {
        // console.log(`Interval is running...`);
        const batch = 50;
        const saveAccountToProcess = [];
        while (saveAccountToProcess.length < batch && !MatchingEngineService.saveAccountQueue.isEmpty()) {
          saveAccountToProcess.push(MatchingEngineService.saveAccountQueue.dequeue());
        }

        const entities: AccountEntity[] = [];
        for (const accounts of saveAccountToProcess) {
          for (const account of accounts) {
            const newAccount = convertDateFields(new AccountEntity(), account);
            const oldAccountIdx = entities.findIndex(e => e.id == newAccount.id);
            if (oldAccountIdx > -1) {
              entities.splice(oldAccountIdx, 1);
            } 
            
            if (newAccount.rewardBalance == null) newAccount.rewardBalance = '0';
            entities.push(newAccount);
            console.log(`newAccount=${newAccount.id}`);
          }
        }

        this.accountRepository.insertOrUpdate(entities).catch(e => {
          this.logger.error(e);
        }).finally(() => {
          if (entities?.length) {
            console.log(`Processed: ${entities?.length}`);
          }
        });
      }, 50);   
    }

    for(const c of commands) {
      if (!c.accounts || c.accounts?.length == 0) continue;
      
      //check max size of account queue
      if(MatchingEngineService.saveAccountQueue.size() >= this.MAX_ACCOUNT_QUEUE_SIZE) {
        this.logger.warn(`saveAccountQueue size=${MatchingEngineService.saveAccountQueue.size()} is greater than MAX_ACCOUNT_QUEUE_SIZE, wait 100ms`)
        await new Promise(resolve => setTimeout(resolve, 100));
      }

      MatchingEngineService.saveAccountQueue.enqueue(c.accounts)
    }
  }

  private static cleanupAccountInterval: NodeJS.Timeout = null;
  public async saveAccountsToCache(commands: CommandOutput[]): Promise<void> {
    const accountsToProcess: AccountEntity[] = [];

    for (const command of commands) {
      if (!command.accounts || command.accounts.length === 0) continue;

      for (const account of command.accounts) {
        const newAccount = convertDateFields(new AccountEntity(), account);
        const newAccountOperationId = newAccount?.operationId
          ? Number(
              (
                BigInt(newAccount.operationId.toString()) %
                OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

        const existingAccount = accountsToProcess.find(a => Number(a.id) === Number(newAccount.id));
        const existingAccountOperationId = existingAccount?.operationId
          ? Number(
              (
                BigInt(existingAccount.operationId.toString()) %
                OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;
        
        if (!existingAccount || existingAccountOperationId == null || newAccountOperationId == null || 
            newAccountOperationId >= existingAccountOperationId) {
          if (existingAccount) {
            accountsToProcess.splice(accountsToProcess.indexOf(existingAccount), 1);
          }
          accountsToProcess.push(newAccount);
        }
      }
    }

    if (accountsToProcess.length === 0) return;

    // Cache accounts to Redis with operationId as score
    for (const account of accountsToProcess) {
      const key = `accounts:userId_${account.userId}:accountId_${account.id}`;
      const accountOperationId = Number(
        (
          BigInt(account.operationId.toString()) %
          OPERATION_ID_DIVISOR
        ).toString()
      );
      this.redisClient.getInstance().zadd(key, accountOperationId, JSON.stringify(account));
      const redisKeyWithAsset = `accounts:userId_${account.userId}:asset_${account.asset}`; 
      this.redisClient.getInstance().set(redisKeyWithAsset, JSON.stringify(account), 'EX', 24 * 60 * 60); // 24 hours
    }

    // Set up interval to clean up old account versions
    if (!MatchingEngineService.cleanupAccountInterval) {
      MatchingEngineService.cleanupAccountInterval = setInterval(async () => {
        try {
          let cursor = '0';
          do {
            const [nextCursor, keys] = await this.redisClient.getInstance().scan(cursor, 'MATCH', 'accounts:userId_*', 'COUNT', 1000);
            cursor = nextCursor;

            for (const key of keys) {
              if (key.includes('asset_')) continue;
              const members = await this.redisClient.getInstance().zrevrange(key, 0, 0, "WITHSCORES");
              if (members.length < 2) continue;
              
              // Keep only the member with highest score
              const highestScoreMember = members[members.length - 2];
              const highestScore = members[members.length - 1];
              
              // Remove all members except the one with highest score
              this.redisClient.getInstance().zremrangebyscore(key, 0, String(BigInt(highestScore) - BigInt(1)));
              this.redisClient.getInstance().expire(key, 3 * 24 * 60 * 60); // 3 days in seconds
            }

            // Slight delay to avoid overloading Redis
            await new Promise(resolve => setTimeout(resolve, 20));
          } while (cursor !== '0');
        } catch (error) {
          this.logger.error('Error cleaning up account versions:', error);
        }
      }, 5000); // Run every 5 seconds
    }
  }

  private static accountsWillBeUpdatedOnDb = new Map<number, AccountEntity>();
  private static updatedAccountIds = new Set<number>();
  private static saveAccountIntervalV2 = null;

  public async saveAccountsV2(commands: CommandOutput[]): Promise<void> {
    const accountsToProcess: AccountEntity[] = [];

    for (const command of commands) {
      if (!command.accounts || command.accounts.length === 0) continue;

      for (const account of command.accounts) {
        const newAccount = convertDateFields(new AccountEntity(), account);
        const existingAccount = accountsToProcess.find(a => Number(a.id) === Number(newAccount.id));
        
        if (!existingAccount || !existingAccount.operationId || !newAccount.operationId || 
            Number(newAccount.operationId) >= Number(existingAccount.operationId)) {
          if (existingAccount) {
            accountsToProcess.splice(accountsToProcess.indexOf(existingAccount), 1);
          }
          accountsToProcess.push(newAccount);
        }
      }
    }

    if (accountsToProcess.length === 0) return;

    for (const accountToProcess of accountsToProcess) {
      MatchingEngineService.updatedAccountIds.add(accountToProcess.id);
      MatchingEngineService.accountsWillBeUpdatedOnDb.set(accountToProcess.id, accountToProcess);
    }

    if (!MatchingEngineService.saveAccountIntervalV2) {
      MatchingEngineService.saveAccountIntervalV2 = setInterval(async () => {
        if (MatchingEngineService.updatedAccountIds.size === 0) return;

        const accountIds = Array.from(MatchingEngineService.updatedAccountIds);
        MatchingEngineService.updatedAccountIds.clear();

        const accountsToSaveDb = accountIds.map(id => 
          MatchingEngineService.accountsWillBeUpdatedOnDb.get(id)
        ).filter(Boolean);

        await this.accountRepository.insertOrUpdate(accountsToSaveDb).catch(async e1 => {
          this.logger.error(e1);
          if (e1.toString().includes('ER_LOCK_DEADLOCK')) {
            this.logger.error(`DEADLOCK accountIds: ${accountsToSaveDb.map(a => a.id)} - Resave ...`);
            let shouldBeOutDeadlock = false;
            while (!shouldBeOutDeadlock) {
              try {
                await this.accountRepository.insertOrUpdate(accountsToSaveDb);
                shouldBeOutDeadlock = true;
              } catch(e2) {
                this.logger.error(`Retry: DEADLOCK accountIds: ${accountsToSaveDb.map(p => p.id)}`);
                shouldBeOutDeadlock = false;
              }
            }
          }
        });
        this.logger.log(`Save new account ids=${accountIds}`);
      }, 500);
    }
  }

  private static savePositionQueue = new LinkedQueue<any>();
  private static savePositionInterval = null;
  public async savePositions(commands: CommandOutput[]): Promise<void> {
    if (!MatchingEngineService.savePositionInterval) {
      MatchingEngineService.savePositionInterval = setInterval(async () => {
        // console.log(`Interval is running...`);
        const batch = 50;
        const savePositionToProcess = [];
        while (savePositionToProcess.length < batch && !MatchingEngineService.savePositionQueue.isEmpty()) {
          savePositionToProcess.push(MatchingEngineService.savePositionQueue.dequeue());
        }

        const entities: PositionEntity[] = [];
        for (const positions of savePositionToProcess) {
          for (const position of positions) {
            const newPosition = convertDateFields(new PositionEntity(), position);
            const newPositionOperationId = newPosition?.operationId
              ? Number(
                  (
                    BigInt(newPosition.operationId.toString()) % OPERATION_ID_DIVISOR
                  ).toString()
                )
              : null;

            const oldPositionIdx = entities.findIndex(e => e.id == newPosition.id);
            const oldPosition = entities[oldPositionIdx];
            const oldPositionOperationId = oldPosition?.operationId
              ? Number(
                  (
                    BigInt(oldPosition.operationId.toString()) % OPERATION_ID_DIVISOR
                  ).toString()
                )
              : null;

            if (oldPositionIdx === -1 || newPositionOperationId == null || oldPositionOperationId == null) {
              entities.push(newPosition);
              console.log(`Add newPosition=${newPosition.id} operationId=${newPosition.operationId}`);
              continue;
            }
            
            if (newPositionOperationId >= oldPositionOperationId) {
              entities.splice(oldPositionIdx, 1);
              entities.push(newPosition);
              console.log(`Remove oldPosition{id=${oldPosition.id}, operationId=${oldPosition.operationId}} - Add newPosition{${newPosition.id}, operationId=${newPosition.operationId}}`);
            }
          }
        }

        await this.positionRepository.insertOrUpdate(entities).catch(e => {
          this.logger.error(e);
        }).finally(() => {
          if (entities?.length) {
            console.log(`Processed: ${entities?.length}`);
          }
        });
      }, 50);   
    }

    for (const c of commands) {
      if (!c.positions || c.positions?.length == 0) continue;
      if (MatchingEngineService.savePositionQueue.size() >= this.MAX_POSITION_QUEUE_SIZE) {
        this.logger.warn(`savePositionQueue size=${MatchingEngineService.savePositionQueue.size()} is greater than MAX_POSITION_QUEUE_SIZE, wait 100ms`)
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      MatchingEngineService.savePositionQueue.enqueue(c.positions);
    };
  }

  private static positionsWillBeUpdatedOnDb = new Map<number, PositionEntity>();
  private static updatedPositionIds = new Set<number>();
  private static savePositionIntervalV2 = null;

  public async savePositionsV2(commands: CommandOutput[]): Promise<void> {
    const positionsToProcess: PositionEntity[] = [];

    for (const command of commands) {
      if (!command.positions || command.positions.length === 0) continue;

      for (const position of command.positions) {
        const newPosition = convertDateFields(new PositionEntity(), position);
        const newPositionOperationId = newPosition?.operationId
          ? Number(
              (
                BigInt(newPosition.operationId.toString()) % OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

        const existingPosition = positionsToProcess.find(p => p.id === newPosition.id);
        const existingPositionOperationId = existingPosition?.operationId
          ? Number(
              (
                BigInt(existingPosition.operationId.toString()) % OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;
        
        if (!existingPosition || existingPositionOperationId == null || newPositionOperationId == null || 
            newPositionOperationId >= existingPositionOperationId) {
          if (existingPosition) {
            positionsToProcess.splice(positionsToProcess.indexOf(existingPosition), 1);
          }
          positionsToProcess.push(newPosition);
        }
      }
    }

    if (positionsToProcess.length === 0) return;

    for (const positionToProcess of positionsToProcess) {
      const redisKey = `${REDIS_COMMON_PREFIX.POSITIONS}:userId_${positionToProcess.userId}:accountId_${positionToProcess.accountId}:positionId_${positionToProcess.id}`;
      this.redisClient.getInstance().set(redisKey, JSON.stringify(positionToProcess), 'EX', 86400); // 1 day TTL

      MatchingEngineService.updatedPositionIds.add(positionToProcess.id);
      MatchingEngineService.positionsWillBeUpdatedOnDb.set(positionToProcess.id, positionToProcess);
    }

    if (!MatchingEngineService.savePositionIntervalV2) {
      MatchingEngineService.savePositionIntervalV2 = setInterval(async () => {
        if (MatchingEngineService.updatedPositionIds.size === 0) return;

        const positionIds = Array.from(MatchingEngineService.updatedPositionIds);
        MatchingEngineService.updatedPositionIds.clear();

        const positionsToSaveDb = positionIds.map(id => 
          MatchingEngineService.positionsWillBeUpdatedOnDb.get(id)
        ).filter(Boolean);

        await this.positionRepository.insertOrUpdate(positionsToSaveDb).catch(async e1 => {
          this.logger.error(e1);
          if (e1.toString().includes('ER_LOCK_DEADLOCK')) {
            this.logger.error(`DEADLOCK positionIds: ${positionsToSaveDb.map(p => p.id)} - Resave ...`);
            let shouldOutDeadlock = false;
            while (!shouldOutDeadlock) {
              try {
                await this.positionRepository.insertOrUpdate(positionsToSaveDb);
                shouldOutDeadlock = true;
              } catch(e2) {
                this.logger.error(`Retry: DEADLOCK positionIds: ${positionsToSaveDb.map(p => p.id)}`);
                shouldOutDeadlock = false;
              }
            }
          }
        });
        this.logger.log(`Save new position ids=${positionIds}`);
      }, 500);
    }
  }

  private static saveOrderQueue = new LinkedQueue<any>();
  private static saveOrderInterval = null;
  public async saveOrders(commands: CommandOutput[]): Promise<void> {
    if (!MatchingEngineService.saveOrderInterval) {
      MatchingEngineService.saveOrderInterval = setInterval(async () => {
        // console.log(`Interval is running...`);
        const batch = 100;
        const saveOrdersToProcess = [];
        while (saveOrdersToProcess.length < batch && !MatchingEngineService.saveOrderQueue.isEmpty()) {
          saveOrdersToProcess.push(MatchingEngineService.saveOrderQueue.dequeue());
        }

        const entities: OrderEntity[] = [];
        for (const orders of saveOrdersToProcess) {
          if (!orders) continue;
          const canceledOrderIdsWillBeDeleted: number[] = [];
          for (const order of orders) {
            const newOrder = convertDateFields(new OrderEntity(), order);
            const oldOrderIdx = entities.findIndex(e => e.id == newOrder.id);
            if (oldOrderIdx > -1) {
              entities.splice(oldOrderIdx, 1);
            } 
            // if (order.status === OrderStatus.CANCELED && !order.executedPrice) {
            //   canceledOrderIdsWillBeDeleted.push(newOrder.id);
            // } else {
              entities.push(newOrder);
            // }
            console.log(`newOrder=${newOrder.id}`);
          }

          // delete canceled orders
          // if (canceledOrderIdsWillBeDeleted.length) {
          //   console.log(`Length of deleted canceled orders: ${canceledOrderIdsWillBeDeleted.length}`);
          //   this.orderRepository.delete(canceledOrderIdsWillBeDeleted);
          // }
        }

        let numOfActiveOrderSavedToDb = 0;
        let numOfCancelledOrderSavedToDb = 0;
        let numOfFilledOrderSavedToDb = 0;
        const tasks = entities.map(entity => {
          if (entity.status == OrderStatus.ACTIVE) {
            numOfActiveOrderSavedToDb++;
          } else if (entity.status == OrderStatus.CANCELED) {
            numOfCancelledOrderSavedToDb++;
          } else if (entity.status == OrderStatus.FILLED) {
            numOfFilledOrderSavedToDb++;
          }

          return this.orderRepository.insertOrUpdate([entity]).catch(async e1 => {
            this.logger.error(e1);
            if (e1.toString().includes('ER_LOCK_DEADLOCK')) {
              this.logger.error(`DEADLOCK orderId: ${entity.id}`);
              let shouldOutDeadlock = false;
              while (!shouldOutDeadlock) {
                try {
                  await this.orderRepository.insertOrUpdate([entity]);
                  shouldOutDeadlock = true;
                } catch(e2) {
                  this.logger.error(`Retry: DEADLOCK orderId: ${entity.id}`);
                  shouldOutDeadlock = false;
                }
              }
            }
          })
        });

        const redisClient = (this.cacheManager.store as any).getClient();
        tasks.push(
          redisClient.incrby("numOfActiveOrderSavedToDb", numOfActiveOrderSavedToDb), 
          redisClient.expire("numOfActiveOrderSavedToDb", 3600000000000),

          redisClient.incrby("numOfCancelledOrderSavedToDb", numOfCancelledOrderSavedToDb), 
          redisClient.expire("numOfCancelledOrderSavedToDb", 3600000000000),

          redisClient.incrby("numOfFilledOrderSavedToDb", numOfFilledOrderSavedToDb), 
          redisClient.expire("numOfFilledOrderSavedToDb", 3600000000000),
        );

        Promise.all(tasks);
      }, 50);   
    }

    for (const c of commands) {
      if (!c?.orders || c?.orders?.length == 0) continue;

      if (MatchingEngineService.saveOrderQueue.size() >= this.MAX_ORDER_QUEUE_SIZE) {
        this.logger.warn(`saveOrderCommands size=${MatchingEngineService.saveOrderQueue.size()} is greater than MAX_ORDER_QUEUE_SIZE, wait 100ms`)
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      MatchingEngineService.saveOrderQueue.enqueue(c.orders);
    }
  }

  public async saveOrdersV2(commands: CommandOutput[]): Promise<void> {
    const orders = [];
    for (const c of commands) {
      if (c.orders && c.orders.length !== 0) orders.push(...c.orders);
    }
    if (orders.length === 0) return;

    for (const order of orders) {
      const keyWithOrderId = `orders:userId_${order.userId}:orderId_${order.id}`;
      const keyWithTmpId = `orders:userId_${order.userId}:tmpId_${order.tmpId}`;
      if (order.status.toString() === OrderStatus.ACTIVE.toString() || 
          order.status.toString() === OrderStatus.UNTRIGGERED.toString()) {
        this.redisClient.getInstance().set(keyWithOrderId, JSON.stringify(order), 'EX', 3 * 24 * 60 * 60); // 3 days in seconds
        if (order.tmpId) this.redisClient.getInstance().set(keyWithTmpId, JSON.stringify(order), 'EX', 3 * 24 * 60 * 60); // 3 days in seconds
      } else {
        this.redisClient.getInstance().del(keyWithOrderId);
        if (order.tmpId) this.redisClient.getInstance().del(keyWithTmpId);
      }
      // this.kafkaClient.send(KafkaTopics.save_order_to_db, order);
    }
  }

  private static saveOrdersToDbTimeout: NodeJS.Timeout = null;
  private static orderMessagesToSaveDb = [];

  public async saveOrdersToDb(orderMsg: any) {
    const BATCH_SIZE = 500;
    const MAX_PROCESS_TIME = 2000;

    const processOrders = async () => {
      if (MatchingEngineService.orderMessagesToSaveDb.length === 0) return;
      
      const ordersToProcess = MatchingEngineService.orderMessagesToSaveDb.splice(0, MatchingEngineService.orderMessagesToSaveDb.length);
      const entities: OrderEntity[] = [];
      
      // Remove duplicate order
      for (const order of ordersToProcess) {
        const oldOrderIdx = entities.findIndex(e => e.id == order.id);
        const oldOrder = entities[oldOrderIdx];

        if (!oldOrder) {
          entities.push(order);
          continue;
        }

        if (oldOrder.operationId <= order.operationId) {
          entities.splice(oldOrderIdx, 1);
          entities.push(order);
        } 
      }

      // Filter bot's orders with status CANCELLED 
      const orderIdsToDelete = [];
      const ordersToSave = [];
      entities.forEach(entity => {
        if (entity.status === OrderStatus.CANCELED && entity.remaining && entity.quantity && new BigNumber(entity.remaining).eq(new BigNumber(entity.quantity))) {
          orderIdsToDelete.push(entity.id);
        } else {
          ordersToSave.push(entity);
        }
      });

      // Save user's orders and delete bot's orders
      this.logger.log(`Save orderIds=${JSON.stringify(entities.map(e => e.id))}`);
      await this.orderRepository.insertOrUpdate(ordersToSave);
      if (orderIdsToDelete.length) {
        this.logger.log(`Delete orderIds: ${JSON.stringify(orderIdsToDelete)}`);
        this.orderRepository.delete(orderIdsToDelete);
      }
    };

    const order = convertDateFields(new OrderEntity(), orderMsg);
    
    clearTimeout(MatchingEngineService.saveOrdersToDbTimeout);
    MatchingEngineService.orderMessagesToSaveDb.push(order);

    if (MatchingEngineService.orderMessagesToSaveDb.length < BATCH_SIZE) {
      MatchingEngineService.saveOrdersToDbTimeout = setTimeout(async () => {
        if (MatchingEngineService.orderMessagesToSaveDb.length > 0 && MatchingEngineService.orderMessagesToSaveDb.length < BATCH_SIZE) {
          await processOrders();
        }
      }, MAX_PROCESS_TIME);
      return;
    }

    if (MatchingEngineService.orderMessagesToSaveDb.length >= BATCH_SIZE) {
      await processOrders();
      return;
    }
  }

  public async saveTrades(commands: CommandOutput[]): Promise<void> {
    // 1. Extract and convert all trade entities
    const entities = [];
    const userTrades: UserTradeToRemoveBotOrderEntity[] = [];
    const prepareOrderToSendMailPromises = [];
    const tradesForProcessFeeVoucherPromises = [];
    for (const command of commands) {
      if (!command.trades || command.trades.length === 0) continue;
      entities.push(
        ...(await Promise.all(
          command.trades.map(async (trade) => {

            if (trade.buyOrder == null && trade.sellOrder == null) return null;
            const [buyerIsBot, sellerIsBot] = await Promise.all([
              this.botInMemoryService.checkIsBotAccountId(trade.buyAccountId as number),
              this.botInMemoryService.checkIsBotAccountId(trade.sellAccountId as number),
            ]);

            if (trade.buyOrder != null && !buyerIsBot) {
              prepareOrderToSendMailPromises.push(
                this.kafkaClient.send(KafkaTopics.prepare_order_to_send_mail_and_notify, {
                  ...(trade.buyOrder as any),
                  trade: {
                    id: trade.id,
                    createdAt: trade.createdAt,
                  },
                })
              );
            }

            if (trade.sellOrder != null && !sellerIsBot) {
              prepareOrderToSendMailPromises.push(
                this.kafkaClient.send(KafkaTopics.prepare_order_to_send_mail_and_notify, {
                  ...(trade.sellOrder as any),
                  trade: {
                    id: trade.id,
                    createdAt: trade.createdAt,
                  },
                })
              );
            }

            // User-User || Bot-User || User-Bot
            if ((!buyerIsBot && !sellerIsBot) || (buyerIsBot && !sellerIsBot) || (!buyerIsBot && sellerIsBot)) {
              userTrades.push({
                id: trade.id as number,
                sellOrderId: trade.sellOrderId as number,
                buyOrderId: trade.buyOrderId as number 
              });
            }

            if (!buyerIsBot) {
              tradesForProcessFeeVoucherPromises.push(
                this.kafkaClient.send(FutureEventKafkaTopic.trades_for_process_fee_voucher, {
                  userId: trade.buyUserId,
                  quantity: trade.quantity,
                  price: trade.price,
                })
              );
            }

            if (!sellerIsBot) {
              tradesForProcessFeeVoucherPromises.push(
                this.kafkaClient.send(FutureEventKafkaTopic.trades_for_process_fee_voucher, {
                  userId: trade.sellUserId,
                  quantity: trade.quantity,
                  price: trade.price,
                })
              );
            }

            return convertDateFields(new TradeEntity(), trade);
          })
        ))
      );
    }
    
    // 2. Insert/update all trade entities at once
    this.tradeRepository.insertOrUpdate(entities);
    this.userTradeToRemoveBotOrderRepoMaster.save(userTrades);

    // Save order leverage by trade
    // await this.saveOrderAverageByTrade(entities);

    // Count number of trades sent to client
    // const redisClient = (this.cacheManager.store as any).getClient();
    // await redisClient.incrby("numOfTradesSavedToDb", entities.length);
    // await redisClient.expire("numOfTradesSavedToDb", 3600000000000);
    // const numOfTradesSavedToDb = await this.cacheManager.get("numOfTradesSavedToDb");
    // console.log(`numOfTradesSavedToDb: ${numOfTradesSavedToDb}`);
    
    // 3. Prepare transaction records for batch insertion
    const tradingFeeTransactions = [];
    const realizedPnlTransactions = [];
    const referralPromises = [];
    const rewardCenterPromises = [];

    // 4. Process each entity in parallel
    await Promise.all(entities.map(async (entity) => {
      try {
        // Add fee transactions for buyer and seller
        const buyerAccIsBot = await this.botInMemoryService.checkIsBotAccountId(entity.buyAccountId);
        const sellerAccIsBot = await this.botInMemoryService.checkIsBotAccountId(entity.sellAccountId);

        if (!buyerAccIsBot) {
          const transaction = {
            symbol: entity.symbol,
            status: TransactionStatus.APPROVED,
            accountId: entity.buyAccountId,
            asset: entity.buyOrder.asset,
            type: TransactionType.TRADING_FEE,
            amount: entity.buyFee,
            userId: entity.buyUserId,
            contractType: entity.contractType,
            uuid: uuidv4()
          }
          tradingFeeTransactions.push(transaction);
          this.kafkaClient.send(FutureEventKafkaTopic.transactions_to_process_used_event_rewards, transaction);
        }
        
        if(!sellerAccIsBot) {
          const transaction = {
            symbol: entity.symbol,
            status: TransactionStatus.APPROVED,
            accountId: entity.sellAccountId,
            asset: entity.sellOrder.asset,
            type: TransactionType.TRADING_FEE,
            amount: entity.sellFee,
            userId: entity.sellUserId,
            contractType: entity.contractType,
            uuid: uuidv4()
          };
          tradingFeeTransactions.push(transaction);
          this.kafkaClient.send(FutureEventKafkaTopic.transactions_to_process_used_event_rewards, transaction);
        }
        
        // Add realized PNL transactions if applicable
        if (entity.realizedPnlOrderBuy && !buyerAccIsBot) {
          const transaction = {
            symbol: entity.symbol,
            status: TransactionStatus.APPROVED,
            accountId: entity.buyAccountId,
            asset: entity.sellOrder.asset,
            type: TransactionType.REALIZED_PNL,
            amount: entity.realizedPnlOrderBuy,
            userId: entity.buyUserId,
            contractType: entity.contractType,
            uuid: uuidv4()
          }
          realizedPnlTransactions.push(transaction);
          this.kafkaClient.send(FutureEventKafkaTopic.transactions_to_process_used_event_rewards, transaction);
        }
        
        if (entity.realizedPnlOrderSell && !sellerAccIsBot) {
          const transaction = {
            symbol: entity.symbol,
            status: TransactionStatus.APPROVED,
            accountId: entity.sellAccountId,
            asset: entity.sellOrder.asset,
            type: TransactionType.REALIZED_PNL,
            amount: entity.realizedPnlOrderSell,
            userId: entity.sellUserId,
            contractType: entity.contractType,
            uuid: uuidv4()
          };
          realizedPnlTransactions.push(transaction);
          this.kafkaClient.send(FutureEventKafkaTopic.transactions_to_process_used_event_rewards, transaction);
        }

        // Determine asset
        let asset;
        if (entity.symbol.includes("USDM")) {
          asset = entity.symbol.split("USDM")[0];
        } else if (entity.symbol.includes("USDT")) {
          asset = "USDT";
        } else {
          asset = "USD";
        }

        let sellUserId = entity.sellUserId;
        let buyUserId = entity.buyUserId
        // if 1 of 2 userId is null, we need to get account from db
        if(!buyUserId || !sellUserId) {
          // Fetch accounts in parallel
          const [accountSell, accountBuy] = await Promise.all([
            this.accountRepoReport.findOne(entity.sellAccountId),
            this.accountRepoReport.findOne(entity.buyAccountId),
          ]);
          sellUserId = accountSell.userId;
          buyUserId = accountBuy.userId;
        }
        
        if (!buyerAccIsBot || !sellerAccIsBot) {
          const rateWithUsdt = await this.exchangeRate(asset);
          const volume = new BigNumber(entity.price).times(entity.quantity).toString();
          
          // Handle referrals
          referralPromises.push(
            this.kafkaClient.send(KafkaTopics.future_referral, {
              data: {
                buyerId: buyUserId,
                sellerId: sellUserId,
                buyerFee: entity.buyFee,
                sellerFee: entity.sellFee,
                asset: asset?.toString().toLowerCase(),
                volume,
                symbol: entity.symbol,
                rateWithUsdt,
                buyOrderId: entity.buyOrderId,
                sellOrderId: entity.sellOrderId,
              },
            })
          );

          // Handle liquidation referrals
          if (entity.note === OrderNote.LIQUIDATION || entity.note === OrderNote.INSURANCE_LIQUIDATION) {
            // Buyer is liquidated if buyerIsTaker = true
            if (entity.buyerIsTaker && entity.realizedPnlOrderBuy && !buyerAccIsBot) {
              referralPromises.push(
                this.kafkaClient.send(KafkaTopics.future_referral_liquidation, {
                  liquidationAmount: Math.abs(entity.realizedPnlOrderBuy),
                  userId: buyUserId,
                  asset: asset?.toString().toLowerCase(),
                  symbol: entity.symbol,
                  rateWithUsdt,
                  timestamp: new Date().getTime()
                })
              );
            }
            
            // Seller is liquidated if buyerIsTaker = false
            if (!entity.buyerIsTaker && entity.realizedPnlOrderSell && !sellerAccIsBot) {
              referralPromises.push(
                this.kafkaClient.send(KafkaTopics.future_referral_liquidation, {
                  liquidationAmount: Math.abs(entity.realizedPnlOrderSell),
                  userId: sellUserId,
                  asset: asset?.toString().toLowerCase(),
                  symbol: entity.symbol,
                  rateWithUsdt,
                  timestamp: new Date().getTime()
                })
              );
            }
          }
        }

        // Handle reward center
        if (!entity.note && entity.contractType === InstrumentTypes.USD_M) {
          const volume = new BigNumber(entity.price).times(entity.quantity).toString();
          if (!buyerAccIsBot) {
            rewardCenterPromises.push(
              this.kafkaClient.send(KafkaTopics.future_reward_center, {
                data: [
                  {
                    userId: buyUserId,
                    volume,
                    symbol: entity.symbol,
                  },
                ]
              })
            );
          }
          if(!sellerAccIsBot) {
            rewardCenterPromises.push(
              this.kafkaClient.send(KafkaTopics.future_reward_center, {
                data: [
                  {
                    userId: sellUserId,
                    volume,
                    symbol: entity.symbol,
                  },
                ]
              })
            );
          }
        }
      } catch (error) {
        console.error(`Error processing trade entity (ID: ${entity.id}):`, error);
        // Log but continue processing other entities
      }
    }));

    // 5. Execute batched operations in parallel
    Promise.all([
      // Insert all fee transactions in one batch
      tradingFeeTransactions.length > 0 ? this.transactionRepository.insert(tradingFeeTransactions) : Promise.resolve(),
      
      // Insert all PNL transactions in one batch
      realizedPnlTransactions.length > 0 ? this.transactionRepository.insert(realizedPnlTransactions) : Promise.resolve(),
      
      // Process all Kafka referral events
      ...referralPromises,
      
      // Process all reward center events
      ...rewardCenterPromises, 

      // Process all prepare order to send mail events
      ...prepareOrderToSendMailPromises,

      ...tradesForProcessFeeVoucherPromises
    ]);
  }

  private async saveOrderAverageByTrade(entities: any[]) {
    const orderIdsWithMetadata: {
      orderId: number;
		  type: "SELL" | "BUY";
			symbol: string;
      isCoinM: boolean;
      tradeQuantity: string;
      tradePrice: string;
    }[] = [];

    for (const entity of entities) {
      const trade = entity as TradeEntity;
      let isBot = await this.botService.checkIsBotByAccountId(trade.sellAccountId);
      if (!isBot) {
        orderIdsWithMetadata.push({
          orderId: trade.sellOrderId,
          type: "SELL",
          symbol: trade.symbol,
          isCoinM: trade.contractType === ContractType.COIN_M.toString(),
          tradeQuantity: trade.quantity,
          tradePrice: trade.price,
        });
      }

      isBot = await this.botService.checkIsBotByAccountId(trade.buyAccountId);
      if (!isBot) {
        orderIdsWithMetadata.push({
          orderId: trade.buyOrderId,
          type: "BUY",
          symbol: trade.symbol,
          isCoinM: trade.contractType === ContractType.COIN_M.toString(),
          tradeQuantity: trade.quantity,
          tradePrice: trade.price,
        });
      }
    }

    const orderIds = orderIdsWithMetadata.map(o => o.orderId);
    let orderAverageByTrades: OrderAverageByTradeEntity[] = [];
    if (orderIds && orderIds.length !== 0) {
      orderAverageByTrades = await this.orderAverageByTradeRepoReport
        .createQueryBuilder("orderAverageByTrade")
        .where("orderAverageByTrade.orderId IN (:...orderIds)", { orderIds })
        .getMany();
    }

    for (const orderIdWithMetadata of orderIdsWithMetadata) {
      let orderAverageByTrade: OrderAverageByTradeEntity = orderAverageByTrades.find(o => Number(o.orderId) === Number(orderIdWithMetadata.orderId));
      
      // If orderAverageByTrade !== null
      if (orderAverageByTrade) {
        if (orderIdWithMetadata.isCoinM) {
          orderAverageByTrade.totalQuantityMulOrDivPrice = 
            new BigNumber(orderAverageByTrade.totalQuantityMulOrDivPrice)
            .plus(new BigNumber(orderIdWithMetadata.tradeQuantity).dividedBy(orderIdWithMetadata.tradePrice))
            .toString();
          
          orderAverageByTrade.totalQuantity = 
            new BigNumber(orderAverageByTrade.totalQuantity)
            .plus(new BigNumber(orderIdWithMetadata.tradeQuantity))
            .toString();
          
          orderAverageByTrade.average = 
            new BigNumber(orderAverageByTrade.totalQuantity).dividedBy(new BigNumber(orderAverageByTrade.totalQuantityMulOrDivPrice))
            .toString();

        } else {
          orderAverageByTrade.totalQuantityMulOrDivPrice = 
            new BigNumber(orderAverageByTrade.totalQuantityMulOrDivPrice)
            .plus(new BigNumber(orderIdWithMetadata.tradeQuantity).multipliedBy(orderIdWithMetadata.tradePrice))
            .toString();
          
          orderAverageByTrade.totalQuantity = 
            new BigNumber(orderAverageByTrade.totalQuantity)
            .plus(new BigNumber(orderIdWithMetadata.tradeQuantity))
            .toString();
          
          orderAverageByTrade.average = 
            new BigNumber(orderAverageByTrade.totalQuantityMulOrDivPrice).dividedBy(new BigNumber(orderAverageByTrade.totalQuantity))
            .toString();
        }
      } 
      
      // If orderAverageByTrade === null
      else {
        if (orderIdWithMetadata.isCoinM) {
          const totalQuantityMulOrDivPrice = new BigNumber(orderIdWithMetadata.tradeQuantity).dividedBy(new BigNumber(orderIdWithMetadata.tradePrice));
          orderAverageByTrade = {
            id: null,
            orderId: orderIdWithMetadata.orderId,
            type: orderIdWithMetadata.type,
            symbol: orderIdWithMetadata.symbol,
            isCoinM: orderIdWithMetadata.isCoinM,
            totalQuantityMulOrDivPrice: totalQuantityMulOrDivPrice.toString(),
            totalQuantity: orderIdWithMetadata.tradeQuantity,
            average: new BigNumber(orderIdWithMetadata.tradeQuantity).dividedBy(totalQuantityMulOrDivPrice).toString()
          }
        } else {
          const totalQuantityMulOrDivPrice = new BigNumber(orderIdWithMetadata.tradeQuantity).multipliedBy(new BigNumber(orderIdWithMetadata.tradePrice));
          orderAverageByTrade = {
            id: null,
            orderId: orderIdWithMetadata.orderId,
            type: orderIdWithMetadata.type,
            symbol: orderIdWithMetadata.symbol,
            isCoinM: orderIdWithMetadata.isCoinM,
            totalQuantityMulOrDivPrice: totalQuantityMulOrDivPrice.toString(),
            totalQuantity: orderIdWithMetadata.tradeQuantity,
            average: totalQuantityMulOrDivPrice.dividedBy(new BigNumber(orderIdWithMetadata.tradeQuantity)).toString()
          }
        }
      }

      orderAverageByTrade = await this.orderAverageByTradeRepoMaster.save(orderAverageByTrade);
      orderAverageByTrades.push(orderAverageByTrade);
    }
  }

  public async saveTransactions(commands: CommandOutput[]): Promise<void> {
    const entities: TransactionEntity[] = [];
    for (const command of commands) {
      if (command.transactions?.length > 0) {
        entities.push(...command.transactions.map((item) => convertDateFields(new TransactionEntity(), item)));
      }
    }

    if (entities.length) {
      const acceptWithdrawEntities = entities.filter(
        (entity) => entity.status === TransactionStatus.APPROVED && entity.type === TransactionType.WITHDRAWAL
      );
        
      // filter bot entities
      const checkIsBotArr: boolean[] = await Promise.all(entities.map(async (entity) => {
        return await this.botService.checkIsBotByAccountId(entity.accountId);
      }));

      const filteredBotEntities: TransactionEntity[] = entities.filter((_, index) => !checkIsBotArr[index]);

      for (const entity of filteredBotEntities) {
        const transTypes = ['FUNDING_FEE', 'LIQUIDATION_CLEARANCE', "MARGIN_INSURANCE_FEE"];
        if (transTypes.includes(entity.type)) {
          entity.uuid = entity.uuid ?? uuidv4();
          this.kafkaClient.send(FutureEventKafkaTopic.transactions_to_process_used_event_rewards, entity);
        }
      }
      
      await this.transactionRepository.insertOrUpdate(filteredBotEntities);
  
      if (acceptWithdrawEntities.length) {
        await Promise.all(
          acceptWithdrawEntities.map(async (entity) => {
            try {
              const { userId } = await this.accountRepoReport.findOne({
                where: {
                  id: entity.accountId,
                },
              });
              await this.kafkaClient.send(KafkaTopics.spot_transfer, {
                userId: +userId,
                from: "future",
                to: "main",
                amount: +entity.amount,
                asset: `${entity.asset}`.toLowerCase(), // temple fixed
              });
            } catch (error) {
              console.log(error);
            }
          })
        );
      }
    }
  }

  private static savePositionHistoriesQueue = new LinkedQueue<any>();
  private static savePositionHistoriesInterval = null;
  public async savePositionHistories(commands: CommandOutput[]): Promise<void> {
    if (!MatchingEngineService.savePositionHistoriesInterval) {
      MatchingEngineService.savePositionHistoriesInterval = setInterval(async () => {
        const batch = 50;
        const savePositionHistoriesToProcess = [];
        while (savePositionHistoriesToProcess.length < batch && !MatchingEngineService.savePositionHistoriesQueue.isEmpty()) {
          savePositionHistoriesToProcess.push(MatchingEngineService.savePositionHistoriesQueue.dequeue());
        }

        const entities: PositionHistoryEntity[] = [];
        for (const positionHistories of savePositionHistoriesToProcess) {
          for (const pH of positionHistories) {
            const newPositionHistory = convertDateFields(new PositionHistoryEntity(), pH);
            entities.push(newPositionHistory);
            console.log(`newPositionHistory=${newPositionHistory.id}`);
          }
        }

        await this.positionHistoryRepoMaster
          .insertOrUpdate(entities)
          .catch((e) => {
            this.logger.error(e);
          })
          .finally(() => {
            if (entities?.length) {
              console.log(`Processed: ${entities?.length}`);
            }
          });
      }, 50);
    }

    for (const c of commands) {
      if (!c.positionHistories || c.positionHistories?.length == 0) continue;

      //check max size of position histories queue
      if (MatchingEngineService.savePositionHistoriesQueue.size() >= this.MAX_POSITION_HISTORIES_QUEUE_SIZE) {
        this.logger.warn(
          `savePositionHistoriesQueue size=${MatchingEngineService.savePositionHistoriesQueue.size()} is greater than MAX_POSITION_HISTORIES_QUEUE_SIZE, wait 100ms`
        );
        await new Promise((resolve) => setTimeout(resolve, 100));
      }

      MatchingEngineService.savePositionHistoriesQueue.enqueue(c.positionHistories);
    }
  }

  public async saveFunding(commands: CommandOutput[]): Promise<void> {
    const entities = [];
    for (const command of commands) {
      if (command.fundingHistories?.length > 0) {
        entities.push(...command.fundingHistories.map((item) => convertFundingHistoriesDateFields(new FundingHistoryEntity(), item)));
      }

      if (command.code === CommandCode.PAY_FUNDING && command.fundingHistories?.length === 0) {
        const symbol = command.data["symbol"] as string;
        const time = new Date(command.data["time"] as string);
        await this.fundingRepoMaster.update({ symbol, time }, { paid: true });
      }
    }
    await this.fundingHistoryRepoMaster.insertOrUpdate(entities);

    entities.forEach(async (entity) => {
      if (!(await this.botService.checkIsBotByAccountId(entity.accountId))) {
        await this.transactionRepository.insert({
          symbol: entity.symbol,
          status: TransactionStatus.APPROVED,
          accountId: entity.accountId,
          asset: entity.asset,
          type: TransactionType.FUNDING_FEE,
          amount: entity.amount,
          contractType: entity.contractType,
          userId: entity.userId,
        });
      }
    });
  }

  public async saveMarginLeverage(commands: CommandOutput[]): Promise<void> {
    try {
      const entities = [];
      for (const command of commands) {
        if (command.adjustLeverage?.status === "SUCCESS") {
          const { accountId, symbol, leverage, marginMode, id } = command.adjustLeverage;
          console.log(`command.adjustLeverage: ${JSON.stringify(command.adjustLeverage)}`);
          const [account, instrument] = await Promise.all([
            this.accountRepoReport.findOne(accountId),
            this.instrumentRepoReport.findOne({
              where: {
                symbol,
              },
            }),
          ]);
          console.log(`account: ${JSON.stringify(account)}`);
          
          if (account) {
            entities.push({
              userId: account.userId,
              instrumentId: instrument.id,
              marginMode,
              leverage,
              id: +id,
            });
          }
        }
        await Promise.all(
          entities.map((entity) => {
            if (entity.id) {
              const id = entity.id;
              delete entity.id;
              this.userMarginModeRepoMaster.update(id, entity);
            } else {
              entity.id = null;
              this.userMarginModeRepoMaster.insert(entity);
            }
          })
        );
      }
    } catch (error) {
      console.error(`Error processing margin leverage command:`, error);
    }
  }

  private async savePositionHistoryTimestamp(timestamp: number): Promise<void> {
    await this.cacheManager.set(POSITION_HISTORY_TIMESTAMP_KEY, timestamp, {
      ttl: POSITION_HISTORY_TIMESTAMP_TTL,
    });
  }

  private async saveFundingHistoryTimestamp(timestamp: number): Promise<void> {
    await this.cacheManager.set(FUNDING_HISTORY_TIMESTAMP_KEY, timestamp, {
      ttl: FUNDING_HISTORY_TIMESTAMP_TTL,
    });
  }

  private async exchangeRate(asset: string) {
    asset = asset.toLowerCase();
    try {
      if (asset === "usdt" || asset === "usd") return "1";
      const { data } = await axios.get(`https://api.coinbase.com/v2/exchange-rates?currency=${asset}`);
      return data?.data?.rates?.USDT;
    } catch (error) {
      console.log(error);
      return "1";
    }
  }
}
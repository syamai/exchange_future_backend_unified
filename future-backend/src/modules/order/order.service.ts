import { AdminOrderDto } from "./dto/admin-order.dto";
import { CACHE_MANAGER, HttpException, HttpStatus, Inject, Injectable, Logger, forwardRef } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { plainToClass } from "class-transformer";
import { Producer } from "kafkajs";
import { kafka } from "src/configs/kafka";
import { OrderEntity } from "src/models/entities/order.entity";
import { OrderRepository } from "src/models/repositories/order.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { AccountService } from "src/modules/account/account.service";
import { InstrumentService } from "src/modules/instrument/instrument.service";
import { BaseEngineService } from "src/modules/matching-engine/base-engine.service";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { CreateOrderDto } from "src/modules/order/dto/create-order.dto";
import { MAX_RESULT_COUNT } from "src/modules/trade/trade.const";
import { UserService } from "src/modules/user/users.service";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import {
  CANCEL_ORDER_TYPE,
  ContractType,
  EOrderBy,
  OrderSide,
  OrderStatus,
  OrderTimeInForce,
  OrderType,
  ORDER_TPSL,
  TpSlType,
  OrderNote,
} from "src/shares/enums/order.enum";
import { httpErrors } from "src/shares/exceptions";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { getQueryLimit } from "src/shares/pagination-util";
import { TakeProfitStopLossOrder } from "./tp-sl.type";
import { In, LessThan, MoreThan, Like, getConnection, IsNull, Equal, Brackets, MoreThanOrEqual } from "typeorm";
import { OrderHistoryDto } from "./dto/order-history.dto";
import * as moment from "moment";
import { OpenOrderDto } from "./dto/open-order.dto";
import { UserMarginModeRepository } from "src/models/repositories/user-margin-mode.repository";
import { IUserAccount } from "./interface/account-user.interface";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { CANCEL_LIMIT_TYPES, CANCEL_STOP_TYPES, ENABLE_CREATE_ORDER, TMP_ORDER_CACHE, TMP_ORDER_ID_PREFIX, TMP_ORDER_TTL } from "./order.const";
import { UpdateTpSlOrderDto } from "./dto/update-tpsl-order.dto";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { DEFAULT_LEVERAGE, DEFAULT_MARGIN_MODE } from "../user-margin-mode/user-marging-mode.const";
import { Cache } from "cache-manager";
import { AccountRepository } from "src/models/repositories/account.repository";
import { AccountEntity } from "src/models/entities/account.entity";
import { GET_NUMBER_RECORD, START_CRAWL } from "../transaction/transaction.const";
import { RedisService } from "nestjs-redis";
import { ORACLE_PRICE_PREFIX } from "../index/index.const";
import { TradingRulesService } from "../trading-rules/trading-rule.service";
import { TradingRulesRepository } from "src/models/repositories/trading-rules.repository";
import { MAX_PRICE, MIN_PRICE, PRICE_CACHE_TTL } from "../trading-rules/trading-rules.constants";
import { Ticker, TICKERS_KEY } from "../ticker/ticker.const";
import { UserRepository } from "src/models/repositories/user.repository";
import { Orderbook, OrderbookMEBinance } from "../orderbook/orderbook.const";
import { OrderbookService } from "../orderbook/orderbook.service";
import * as ExcelJS from "exceljs";
import { GetOrderHistoryForPartner } from "./dto/get-order-history-for-partner.dto";
import { Response } from "express";
import { ExcelService } from "../export-excel/services/excel.service";
import { OrderTypeExcel } from "../export-excel/enums/order-type-excel.enum";
import { OrderStatusExcel } from "../export-excel/enums/order-status-excel.enum";
import { UserEntity } from "src/models/entities/user.entity";
import { removeEmptyField } from "src/utils/remove-empty-field";
import { UserMarginModeService } from "../user-margin-mode/user-margin-mode.service";
import { v4 as uuidv4 } from "uuid";
import { USDT } from "../balance/balance.const";
import { BalanceService } from "../balance/balance.service";
import { PositionEntity } from "src/models/entities/position.entity";
import { InstrumentEntity } from "src/models/entities/instrument.entity";
import { PositionService } from "../position/position.service";
import { OrderAverageByTradeEntity } from "src/models/entities/order-average-by-trade.entity";
import { OrderAverageByTradeRepository } from "src/models/repositories/order-average-by-trade.repository";
import { BotInMemoryService } from "../bot/bot.in-memory.service";
import { TradingRulesEntity } from "src/models/entities/trading_rules.entity";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import Long from "long";

@Injectable()
export class OrderService extends BaseEngineService {
  constructor(
    public readonly logger: Logger,
    @InjectRepository(OrderRepository, "report")
    public readonly orderRepoReport: OrderRepository,
    @InjectRepository(TradeRepository, "report")
    public readonly tradeRepoReport: TradeRepository,
    @InjectRepository(OrderRepository, "master")
    public readonly orderRepoMaster: OrderRepository,
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepoReport: PositionRepository,
    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepoReport: InstrumentRepository,
    @InjectRepository(UserMarginModeRepository, "report")
    public readonly userMarginModeRepoReport: UserMarginModeRepository,
    public readonly kafkaClient: KafkaClient,
    public readonly instrumentService: InstrumentService,
    public readonly accountService: AccountService,
    public readonly userService: UserService,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(AccountRepository, "master")
    public readonly accountRepoMaster: AccountRepository,
    private readonly redisService: RedisService,
    private readonly tradingRulesService: TradingRulesService,
    @InjectRepository(TradingRulesRepository, "report")
    public readonly tradingRulesRepoReport: TradingRulesRepository,
    @Inject(CACHE_MANAGER) private cacheService: Cache,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    @Inject(forwardRef(() => OrderbookService))
    private readonly orderbookService: OrderbookService,
    private readonly excelService: ExcelService,
    @Inject(forwardRef(() => UserMarginModeService))
    private readonly userMarginModeService: UserMarginModeService,
    @Inject(forwardRef(() => BalanceService))
    private readonly balanceService: BalanceService,
    @Inject(forwardRef(() => PositionService))
    private readonly positionService: PositionService,
    @Inject(forwardRef(() => BotInMemoryService))
    private readonly botInMemoryService: BotInMemoryService,
    @InjectRepository(OrderAverageByTradeRepository, "report")
    private readonly orderAverageByTradeRepoReport: OrderAverageByTradeRepository,
    private readonly redisClient: RedisClient
  ) {
    super();
  }

  async getOpenOrderByAccountId(paging: PaginationDto, userId: number, openOrderDto: OpenOrderDto): Promise<ResponseDto<OrderEntity[]>> {
    const commonAndConditions = {
      userId: userId,
      status: In([OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED]),
      isHidden: false,
      contractType: In([ContractType.COIN_M, ContractType.USD_M]),
    };
    const where = [];
    if (openOrderDto.contractType && openOrderDto.contractType !== ContractType.ALL) {
      commonAndConditions["contractType"] = In([openOrderDto.contractType]);
    }
    if (openOrderDto.symbol) {
      commonAndConditions["symbol"] = openOrderDto.symbol;
    }
    if (openOrderDto.side) {
      commonAndConditions["side"] = openOrderDto.side;
    }
    where.push(commonAndConditions);
    switch (openOrderDto.type) {
      case ORDER_TPSL.STOP_LOSS:
        where[0]["tpSLType"] = OrderType.STOP_MARKET;
        where[0]["isTpSlOrder"] = true;
        break;
      case ORDER_TPSL.TAKE_PROFIT:
        where[0]["tpSLType"] = OrderType.TAKE_PROFIT_MARKET;
        break;
      case OrderType.STOP_MARKET:
        where[0]["tpSLType"] = OrderType.STOP_MARKET;
        where[0]["isTpSlOrder"] = false;
        break;
      case OrderType.STOP_LIMIT:
        where[0]["tpSLType"] = OrderType.STOP_LIMIT;
        where[0]["status"] = OrderStatus.UNTRIGGERED;
        break;
      case OrderType.LIMIT:
      case OrderType.MARKET:
        where[0]["tpSLType"] = null;
        where[0]["type"] = openOrderDto.type;
        break;
      case undefined:
        break;
      case null:
        break;
      default:
        where.push({
          tpSLType: openOrderDto.type,
          ...commonAndConditions,
        });
        where[0]["type"] = openOrderDto.type;
        break;
    }
    const paginationOption = {};
    if (!openOrderDto.getAll) {
      paginationOption["skip"] = (paging.page - 1) * paging.size;
      paginationOption["take"] = paging.size;
    }
    const [orders, count] = await this.orderRepoReport.findAndCount({
      select: [
        "id",
        "createdAt",
        "type",
        "side",
        "quantity",
        "price",
        "trigger",
        "tpSLType",
        "tpSLPrice",
        "remaining",
        "activationPrice",
        "symbol",
        "timeInForce",
        "status",
        "takeProfitOrderId",
        "stopLossOrderId",
        "isHidden",
        "stopCondition",
        "isPostOnly",
        "cost",
        "parentOrderId",
        "isClosePositionOrder",
        "isTriggered",
        "isReduceOnly",
        "callbackRate",
        "isTpSlOrder",
        "contractType",
        "isTpSlTriggered",
        "updatedAt",
        "userEmail",
        "orderMargin",
        "originalCost",
        "originalOrderMargin",
      ],
      where: where,
      ...paginationOption,
      order: {
        createdAt: "DESC",
        id: "DESC",
      },
    });
    return {
      data: orders,
      metadata: {
        totalPage: Math.ceil(count / paging.size),
        totalItem: count,
      },
    };
  }

  async getOpenOrderByAccountIdV2(paging: PaginationDto, userId: number, openOrderDto: OpenOrderDto): Promise<ResponseDto<OrderEntity[]>> {
    const redisClient = this.redisClient.getInstance();
    const keys = await redisClient.keys(`orders:userId_${userId}:orderId_*`);
    
    if (!keys.length) {
      this.checkMissCachedOrders({
        userId,
        redisOrders: []
      });
      return {
        data: [],
        metadata: {
          totalPage: 0,
          totalItem: 0,
        },
      };
    }
    const ordersFromRedis = await redisClient.mget(keys);
    const redisOrdersToCheckCache = [...ordersFromRedis.map(o => JSON.parse(o) as OrderEntity)];
    this.checkMissCachedOrders({
      userId,
      redisOrders: redisOrdersToCheckCache as unknown as OrderEntity[]
    });

    let orders = [];
    for (const orderFromRedis of ordersFromRedis) {
      if (!orderFromRedis) continue;
      const order = JSON.parse(orderFromRedis) as OrderEntity;
        
      // Apply filters
      if (openOrderDto.contractType && openOrderDto.contractType !== ContractType.ALL && 
          order.contractType !== openOrderDto.contractType) {
        continue;
      }

      if (openOrderDto.symbol && String(order.symbol) !== String(openOrderDto.symbol)) {
        continue;
      }

      if (openOrderDto.side && order.side.toString() !== openOrderDto.side.toString()) {
        continue;
      }

      if (openOrderDto.type) {
        if (openOrderDto.type === ORDER_TPSL.STOP_LOSS.toString() && 
            (order.tpSLType.toString() !== OrderType.STOP_MARKET.toString() || !order.isTpSlOrder)) {
          continue;
        }
        if (openOrderDto.type === ORDER_TPSL.TAKE_PROFIT.toString() && 
            order.tpSLType.toString() !== OrderType.TAKE_PROFIT_MARKET.toString()) {
          continue;
        }
        if (openOrderDto.type === OrderType.STOP_MARKET.toString() && 
            (order.tpSLType.toString() !== OrderType.STOP_MARKET.toString() || order.isTpSlOrder)) {
          continue;
        }
        if (openOrderDto.type === OrderType.STOP_LIMIT && 
            (order.tpSLType.toString() !== OrderType.STOP_LIMIT.toString() || order.status !== OrderStatus.UNTRIGGERED)) {
          continue;
        }
        if ((openOrderDto.type === OrderType.LIMIT || openOrderDto.type === OrderType.MARKET) && 
            (order.tpSLType != null || order.type.toString() !== openOrderDto.type.toString())) {
          continue;
        }
      }

      orders.push(order);
    }

    // Sort orders by createdAt and id descending
    orders.sort((a, b) => {
      if (new Date(b.createdAt).getTime() === new Date(a.createdAt).getTime()) {
        return b.id - a.id;
      }
      return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
    });
    const totalItem = orders.length;

    // Apply pagination
    const start = (paging.page - 1) * paging.size;
    const end = start + paging.size;
    if (!openOrderDto.getAll) {
      orders = orders.slice(start, end);
    }

    return {
      data: orders,
      metadata: {
        totalPage: Math.ceil(totalItem / paging.size),
        totalItem,
      },
    };
  }

  async checkMissCachedOrders(data: {
    userId: number,
    redisOrders: OrderEntity[]
  }): Promise<void> {
    try {
      // Get all active/untriggered orders from DB
      const dbOrders = await this.orderRepoReport
        .createQueryBuilder('order')
        .where('order.userId = :userId', { userId: data.userId })
        .andWhere('order.status IN (:...statuses)', { statuses: [OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED] })
        .getMany();
      dbOrders.forEach(o => {
        o.id = Number(o.id);
      });

      if (dbOrders.length === 0 && data.redisOrders.length === 0) return;

      // Check if miss cached
      if (dbOrders.length === data.redisOrders.length) return;

      // Cache db orders
      for (const dbOrder of dbOrders) {
        if (data.redisOrders.find(rO => Number(rO.id) === dbOrder.id)) continue;
        const redisKey = `orders:userId_${data.userId}:orderId_${dbOrder.id}`;
        this.redisClient.getInstance().set(redisKey, JSON.stringify(dbOrder), 'EX', 3 * 24 * 60 * 60); // 3 day TTL
      } 

      // Check if redisOrder is cancelled/filled on db
      if (data.redisOrders.length) {
        const dbOrdersByRedisOrderIds = await this.orderRepoReport
          .createQueryBuilder('order')
          .andWhere('order.id IN (:...redisOrderIds)', { redisOrderIds: data.redisOrders.map(rO => rO.id) })
          .getMany();

        dbOrdersByRedisOrderIds.forEach(o => {
          o.id = Number(o.id);
          const redisKey = `orders:userId_${data.userId}:orderId_${o.id}`;
          if (o.status === OrderStatus.CANCELED || o.status ===  OrderStatus.FILLED) {
            this.redisClient.getInstance().del(redisKey);
          }
        });
      }
    } catch (e) {
      console.log(e);
    }
  }

  async getOrderByAdmin(paging: PaginationDto, queries: AdminOrderDto): Promise<ResponseDto<OrderEntity[]>> {
    const startTime = moment(parseInt(queries.from)).format("YYYY-MM-DD HH:mm:ss");
    const endTime = moment(parseInt(queries.to)).format("YYYY-MM-DD HH:mm:ss");    

    const commonAndConditions = {};
    const where = [];

    if (queries.isActive) {
      commonAndConditions["status"] = In([OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED]);
    } else {
      const openStatuses = [OrderStatus.FILLED, OrderStatus.CANCELED];
      commonAndConditions["status"] = In(openStatuses);
    }

    if (queries.status && queries.status !== OrderStatus.PARTIALLY_FILLED) {
      commonAndConditions["status"] = Equal(queries.status);
    }
    if (queries.status === OrderStatus.PARTIALLY_FILLED) {
      commonAndConditions["status"] = Equal(OrderStatus.ACTIVE);
    }

    if (queries.side) {
      commonAndConditions["side"] = queries.side;
    }

    if (queries.symbol) {
      commonAndConditions["symbol"] = Like(`%${queries.symbol}%`);
    }

    if (queries.contractType) {
      commonAndConditions["contractType"] = Like(`%${queries.contractType}%`);
    }

    if (queries.userId) {
      commonAndConditions["userId"] = queries.userId;
    }

    where.push(commonAndConditions);
    if (queries.type) {
      switch (queries.type) {
        case ORDER_TPSL.STOP_LOSS:
          where[0]["tpSLType"] = OrderType.STOP_MARKET;
          where[0]["isTpSlOrder"] = true;
          break;
        case ORDER_TPSL.TAKE_PROFIT:
          where[0]["tpSLType"] = OrderType.TAKE_PROFIT_MARKET;
          break;
        case OrderType.STOP_MARKET:
          where[0]["tpSLType"] = OrderType.STOP_MARKET;
          where[0]["isTpSlOrder"] = false;
          break;
        case OrderType.LIMIT:
        case OrderType.MARKET:
          where[0]["tpSLType"] = null;
          where[0]["type"] = queries.type;
          break;
        case "liquidation":
          where[0]["note"] = "LIQUIDATION";
        case undefined:
          break;
        case null:
          break;
        default:
          where.push({
            tpSLType: queries.type,
            ...commonAndConditions,
          });
          where[0]["type"] = queries.type;
          break;
      }
    }

    if (queries.getOpenOrder) {
      commonAndConditions["status"] = In([OrderStatus.ACTIVE, OrderStatus.PENDING]);
    } else if (queries.getOrderHistory) {
      commonAndConditions["status"] = In([OrderStatus.FILLED, OrderStatus.CANCELED]);
    }

    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    const qb = this.orderRepoReport.createQueryBuilder("od");
    // qb.leftJoin(UserEntity, 'user', 'user.id = od.userId')
    qb.select("od.*")
      .addSelect("IF(od.status = 'ACTIVE' AND od.remaining != od.quantity, 'Partial_Filled', od.status)", "customStatus")
      .addSelect(
        `CASE
        WHEN od.note = 'LIQUIDATION' THEN 'LIQUIDATION'
        WHEN od.type = 'MARKET 'AND od.tpSLType = 'TRAILING_STOP' THEN 'TRAILING_STOP'
        WHEN od.type = 'LIMIT' AND od.isPostOnly = '1' THEN 'POST_ONLY'
        WHEN od.type = 'LIMIT' AND od.tpSLType = 'STOP_LIMIT' THEN 'STOP_LIMIT'
        WHEN od.isTpSlOrder = '0' AND od.tpSLType = 'STOP_MARKET' THEN 'STOP_MARKET'
        WHEN od.type = 'LIMIT' THEN 'LIMIT'
        WHEN od.type = 'MARKET' THEN 'MARKET'
        END
      `,
        "customType"
      )
      .addSelect(`od.quantity - od.remaining`, "customFilled")
      .useIndex('`IDX-orders-accountId_createdAt`')
      // .addSelect(`user.uid`, "userUid")
      .where("od.createdAt > :startTime AND od.createdAt < :endTime", { startTime, endTime })
      .andWhere("od.accountId NOT IN (1489,1490,1491,22,23,24)")
      .andWhere(where);

    if (queries.status === OrderStatus.PARTIALLY_FILLED) {
      qb.andWhere("od.remaining != od.quantity");
    }

    if (queries.search_key) {
      qb.andWhere(
        new Brackets((qb) => {
          qb.where("od.id LIKE :search_key", { search_key: `%${queries.search_key}%` }).orWhere("od.accountId LIKE :search_key", {
            search_key: `%${queries.search_key}%`,
          });
        })
      );
    }

    if (queries.orderBy) {
      switch (queries.orderBy) {
        case EOrderBy.COST:
          qb.orderBy("od.cost", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.EMAIL:
          qb.orderBy("od.userEmail", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.FILLED:
          qb.orderBy("customFilled", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.LEVERAGE:
          qb.orderBy("od.leverage", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.PRICE:
          qb.orderBy("od.price", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.QUANTITY:
          qb.orderBy("od.quantity", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.SIDE:
          qb.orderBy("od.side", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.STATUS:
          qb.orderBy("customStatus", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.STOP_PRICE:
          qb.orderBy("od.tpSLPrice", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.SYMBOL:
          qb.orderBy("od.symbol", queries.direction ? queries.direction : "DESC");
          break;
        case EOrderBy.TIME:
          qb.orderBy("od.createdAt", queries.direction ? queries.direction : "DESC");
          break;
        default:
          break;
      }
    } else {
      qb.orderBy("od.createdAt", "DESC");
    }
    qb.limit(limit).offset(offset);

    const [orders, count] = await Promise.all([qb.getRawMany(), qb.getCount()]);

    // add user uid
    if (orders.length) {
      const userIds = orders.map((order) => {
        return order.userId;
      });
      const users = await this.userRepoReport.find({ select: ["id", "uid"], where: { id: In(userIds) } });
      for (const order of orders) {
        order.userUid =
          users.find((user) => {
            return user.id === order.userId;
          })?.uid || null;
      }
    }

    return {
      data: orders,
      metadata: {
        totalPage: Math.ceil(count / paging.size),
        total: count,
      },
    };
  }

  async exportOrderAdminExcelFile(paging: PaginationDto, queries: AdminOrderDto) {
    const { data } = await this.getOrderByAdmin(paging, queries);

    if (!data.length) {
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }

    const preprocessData = (
      data: any
    ): {
      orderId: string;
      accountId: string;
      pair: string;
      side: string;
      type: string;
      filledPerQty: string;
      trigger: string;
      price: string;
      creationTime: string;
      status: string;
    }[] => {
      return data.map((d: any) => {
        return {
          orderId: d.id,
          accountId: d.accountId,
          pair: d.symbol,
          side: d.side,
          type: OrderTypeExcel[d.type],
          filledPerQty: `${d.orderValue}/${d.quantity}`,
          trigger: d.trigger,
          price: d.price,
          creationTime: moment(d.createdAt).format(`YYYY-MM-DD HH:mm:ss`),
          status: OrderStatusExcel[d.customStatus],
        };
      });
    };
    const processedData = preprocessData(data);

    const COLUMN_NAMES = [
      "Order ID",
      "Account ID",
      "Pair",
      "Side",
      "Type",
      "Filled/Quantity",
      "Trigger",
      "Price",
      "Creation Time",
      "Status",
    ];

    const columnDataKeys = ["orderId", "accountId", "pair", "side", "type", "filledPerQty", "trigger", "price", "creationTime", "status"];

    // Generate the Excel buffer
    const buffer = await this.excelService.generateExcelBuffer(COLUMN_NAMES, columnDataKeys, processedData);

    const fileName = "open-order";
    // Set response headers for file download
    const exportTime = moment().format("YYYY-MM-DD_HH-mm-ss");
    return {
      fileName: `${fileName}-${exportTime}.xlsx`,
      base64Data: Buffer.from(buffer).toString("base64"),
    };
  }

  async getOneOrderV2(orderId: string, userId: number): Promise<OrderEntity> {
    let order: OrderEntity | null = null;
    if (orderId.startsWith("uuid-")) {
      const redisKey = `${REDIS_COMMON_PREFIX.ORDERS}:userId_${userId}:tmpId_${orderId}`;
      const redisOrder = await this.redisClient.getInstance().get(redisKey);
      if (redisOrder) {
        order = JSON.parse(redisOrder);
      }
    } else {
      const keyWithOrderId = `${REDIS_COMMON_PREFIX.ORDERS_BY_SCORE}:userId_${userId}:orderId_${orderId}`;
      const members = await this.redisClient.getInstance().zrevrange(keyWithOrderId, 0, 0, "WITHSCORES");
      if (members && members.length >= 2) {
        order = JSON.parse(members[members.length - 2]) as OrderEntity;
      }
    }

    // In case this order is not on redis
    if (!order) {
      if (orderId.startsWith("uuid-")) {
        // Find by tmpId (uuid)
        order = await this.orderRepoReport.findOne({
          where: {
            tmpId: String(orderId),
            userId: Number(userId),
            status: In([
              OrderStatus.ACTIVE,
              OrderStatus.PENDING,
              OrderStatus.UNTRIGGERED,
            ]),
          },
        });
      } else {
        // Find by id
        order = await this.orderRepoReport.findOne({
          where: {
            id: Number(orderId),
            userId: Number(userId),
            status: In([
              OrderStatus.ACTIVE,
              OrderStatus.PENDING,
              OrderStatus.UNTRIGGERED,
            ]),
          },
        });
      }
    }

    if (!order) {
      this.logger.error(`ORDER_NOT_FOUND orderId=${orderId}`)
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }

    return order;
  }

  async getOneOrder(orderId: string, userId: number): Promise<OrderEntity> {
    let order = null;
    const retryFindOrderByTmpId = async (
      maxRetries: number,
      delayMs: number
    ): Promise<OrderEntity | null> => {
      for (let attempt = 0; attempt < maxRetries; attempt++) {
        order = await this.orderRepoReport.findOne({
          where: {
            tmpId: orderId,
            userId: userId,
          },
        });
        if (order) return order;
        console.log(`[getOneOrder|orderId=${orderId}] attempt = `, attempt);
        if (attempt < maxRetries - 1) await new Promise((res) => setTimeout(res, delayMs * 2 ** attempt));
      }
      return null;
    };
  
    if (orderId.startsWith('uuid-')) {
      order = await retryFindOrderByTmpId(2, 4000); 
    } else {
      order = await this.orderRepoReport.findOne({
        where: {
          id: orderId,
          userId: userId,
        },
      });
    }

    if (!order) {
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
    return order;
  }

  async setCacheEnableOrDisableCreateOrder(status: boolean): Promise<void> {
    await this.cacheManager.set<boolean>(ENABLE_CREATE_ORDER, status, {
      ttl: 0,
    });
  }

  async createOrder(createOrderDto: CreateOrderDto, { accountId, userId, email }: IUserAccount): Promise<OrderEntity> {
    const checkStatusEnableCreateOrder = await this.cacheManager.get<boolean>(ENABLE_CREATE_ORDER);
    if (checkStatusEnableCreateOrder) {
      return;
    }
    const defaultLeverage = `${DEFAULT_LEVERAGE}`;
    const defaultMarginMode = DEFAULT_MARGIN_MODE;

    const instrument = await this.instrumentRepoReport.findOne({
      where: {
        symbol: createOrderDto.symbol,
      },
    });
    const marginMode = await this.userMarginModeRepoReport.findOne({
      where: {
        instrumentId: instrument.id,
        userId,
      },
    });
    const order = {
      ...createOrderDto,
      accountId,
      leverage: marginMode ? marginMode.leverage : `${defaultLeverage}`,
      marginMode: marginMode ? marginMode.marginMode : defaultMarginMode,
      orderValue: "0",
      userId,
      contractType: instrument.contractType,
      isTpSlTriggered: false,
      userEmail: email ? email : null,
      originalCost: "0",
      originalOrderMargin: "0",
    };

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { side, trigger, orderValue, ...body } = createOrderDto;

    const tpSlOrder: TakeProfitStopLossOrder = {
      stopLossOrderId: null,
      takeProfitOrderId: null,
    };
    let stopLossOrder: OrderEntity;
    let takeProfitOrder: OrderEntity;
    if (body.stopLoss) {
      stopLossOrder = await this.orderRepoMaster.save({
        ...body,
        accountId,
        userId,
        side: order.side === OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY,
        tpSLPrice: body.stopLoss,
        trigger: order.stopLossTrigger,
        orderValue: "0",
        tpSLType: TpSlType.STOP_MARKET,
        stopLoss: null,
        takeProfit: null,
        price: null,
        type: OrderType.MARKET,
        asset: order.asset,
        leverage: marginMode ? marginMode.leverage : `${defaultLeverage}`,
        marginMode: marginMode ? marginMode.marginMode : defaultMarginMode,
        timeInForce: OrderTimeInForce.IOC,
        isHidden: true,
        stopCondition: order.stopLossCondition,
        isReduceOnly: true,
        isTpSlOrder: true,
        contractType: instrument.contractType,
        isPostOnly: false,
        userEmail: email ? email : null,
        originalCost: "0",
        originalOrderMargin: "0",
      });
      tpSlOrder.stopLossOrderId = stopLossOrder.id;
    }
    if (body.takeProfit) {
      takeProfitOrder = await this.orderRepoMaster.save({
        ...body,
        accountId: accountId,
        userId,
        side: side === OrderSide.BUY ? OrderSide.SELL : OrderSide.BUY,
        tpSLPrice: body.takeProfit,
        trigger: order.takeProfitTrigger,
        orderValue: "0",
        tpSLType: TpSlType.TAKE_PROFIT_MARKET,
        stopLoss: null,
        takeProfit: null,
        price: null,
        type: OrderType.MARKET,
        asset: order.asset,
        leverage: marginMode ? marginMode.leverage : `${defaultLeverage}`,
        marginMode: marginMode ? marginMode.marginMode : defaultMarginMode,
        timeInForce: OrderTimeInForce.IOC,
        isHidden: true,
        stopCondition: order.takeProfitCondition,
        isReduceOnly: true,
        isTpSlOder: true,
        contractType: instrument.contractType,
        isPostOnly: false,
        userEmail: email ? email : null,
        originalCost: "0",
        originalOrderMargin: "0",
      });
      tpSlOrder.takeProfitOrderId = takeProfitOrder.id;
    }

    // if (order.type === OrderType.MARKET) {
    //   // Check to pre-creating orders
    //   this.logger.log(`[createOrder] - Start checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
    //   const orderNeedCreate: CreateOrderDto[] = await this.checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder({
    //     symbol: instrument.symbol,
    //     asset: order.asset,
    //     quantityOfMarketOrder: order.quantity,
    //     marketOrderSide: order.side,
    //   });
    //   this.logger.log(`[createOrder] - End checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
    //   // Create order
    //   // Push order to kafka
    //   // => orderbookME on cache will be updated
    //   this.logger.log(`[createOrder] - Start createOrderForDefaultCreateOrderUser.....`)
    //   await this.createOrderForDefaultCreateOrderUser({
    //     createOrderDtos: orderNeedCreate,
    //     symbol: instrument.symbol,
    //   });
    //   this.logger.log(`[createOrder] - End createOrderForDefaultCreateOrderUser.....`)
    // }

    const newOrder = await this.orderRepoMaster.save({
      ...order,
      ...tpSlOrder,
    });
    this.removeEmptyValues(newOrder);

    // Send created orders to kafka
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.PLACE_ORDER,
      data: plainToClass(OrderEntity, newOrder),
    });
    if (body.stopLoss) {
      this.removeEmptyValues(stopLossOrder);
      await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.PLACE_ORDER,
        data: plainToClass(OrderEntity, {
          ...stopLossOrder,
          linkedOrderId: tpSlOrder.takeProfitOrderId ? tpSlOrder.takeProfitOrderId : null,
          parentOrderId: newOrder.id,
        }),
      });
    }
    if (body.takeProfit) {
      this.removeEmptyValues(takeProfitOrder);
      await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.PLACE_ORDER,
        data: plainToClass(OrderEntity, {
          ...takeProfitOrder,
          linkedOrderId: tpSlOrder.stopLossOrderId ? tpSlOrder.stopLossOrderId : null,
          parentOrderId: newOrder.id,
        }),
      });
    }

    return newOrder;
  }

  async createOrderOptimized(data: {
    createOrderDto: CreateOrderDto, accountData: IUserAccount
  }): Promise<OrderEntity> {
    const startTime = Date.now();
    const { createOrderDto, accountData } = data;
    const checkStatusEnableCreateOrder = await this.cacheManager.get<boolean>(ENABLE_CREATE_ORDER);
    const isBot = await this.botInMemoryService.checkIsBotAccountId(accountData.accountId);
    if (checkStatusEnableCreateOrder && isBot) {
      return;
    }

    const instrument = await this.instrumentService.getCachedInstrument(createOrderDto.symbol);
    const marginMode = await this.userMarginModeService.getCachedMarginMode(accountData.userId, instrument.id);

    const { side, trigger, orderValue, ...body } = createOrderDto;
    const orderId = `uuid-${uuidv4()}`;
    const stopLossOrderId = body.stopLoss ? `uuid-${uuidv4()}` : null;
    const takeProfitOrderId = body.takeProfit ? `uuid-${uuidv4()}` : null;
    const order = {
      id: orderId,
      ...createOrderDto,
      accountId: accountData.accountId,
      leverage: marginMode ? marginMode.leverage : `${DEFAULT_LEVERAGE}`,
      marginMode: marginMode ? marginMode.marginMode : DEFAULT_MARGIN_MODE,
      orderValue: "0",
      userId: accountData.userId,
      contractType: instrument.contractType,
      isTpSlTriggered: false,
      userEmail: accountData.email,
      originalCost: "0",
      originalOrderMargin: "0",
      stopLossOrderId,
      takeProfitOrderId,
    };

    // Check available balance of user if this user is not bot
    if (!isBot) {
      const account: AccountEntity = await this.accountRepoReport.findOne({
        where: { id: accountData.accountId },
        select: ["id", "balance"],
      });
      const accountAvailableBalance = await this.balanceService.calAvailableBalance(account.balance, account.id, USDT);
      const availBalance = new BigNumber(accountAvailableBalance.availableBalance);
      const userLeverage = marginMode ? Number(marginMode.leverage) : Number(DEFAULT_LEVERAGE);
      // console.log(`accountAvailableBalance time: ${Date.now() - startTime}`);
      
      const position = await this.positionRepoReport.findOne({ where: { accountId: account.id, symbol: instrument.symbol }, select: ["id", "currentQty", "marBuy", "marSel"] });
      // console.log(`position time: ${Date.now() - startTime}`);
      
      const orderCost = await this.calcOrderCost({
        order: order as unknown as OrderEntity,
        position,
        leverage: userLeverage,
        instrument,
        isCoinM: createOrderDto.contractType === ContractType.COIN_M
      }); 
      
      // console.log(`calcOrderCost time: ${Date.now() - startTime}`);
      if (availBalance.isLessThanOrEqualTo(orderCost)){
        throw new HttpException(httpErrors.NOT_ENOUGH_BALANCE, HttpStatus.BAD_REQUEST);
      }
      if (!isNaN(orderCost as any)) {
        order.originalCost = orderCost?.toString() ?? "0";
      }
    }

    // Send order to kafka
    this.kafkaClient.send(KafkaTopics.save_order_from_client, {
      createOrderDto,
      accountData, 
      tmpOrder: order
    }); 
    return order as unknown as OrderEntity;
  }

  async createOrderOptimizedV2(data: {
    createOrderDto: CreateOrderDto, userId: number, isTesting?: boolean
  }): Promise<OrderEntity> {
    const { createOrderDto, userId } = data;
    // Parallel fetch for performance optimization
    const [checkStatusEnableCreateOrder, isBot] = await Promise.all([
      this.cacheManager.get<boolean>(ENABLE_CREATE_ORDER),
      this.botInMemoryService.checkIsBotUserId(userId)
    ]);
    if (checkStatusEnableCreateOrder && isBot) {
      return;
    }

    const tmpOrderId = `uuid-${uuidv4()}`;
    // Send to kafka
    if (
      // data.isTesting && String(createOrderDto.symbol) === "ONDOUSDT" && 
      createOrderDto.type === OrderType.MARKET && !createOrderDto.tpSLType) { // Market order
      this.kafkaClient.send(KafkaTopics.save_order_from_client_v2_for_user_market, {
        createOrderDto,
        userId, 
        tmpOrderId
      }); 
    } else {
      this.kafkaClient.send(KafkaTopics.save_order_from_client_v2, {
        createOrderDto,
        userId, 
        tmpOrderId
      }); 
    }

    return { id: tmpOrderId } as unknown as OrderEntity;
  }

  async calcOrderCost(data: {
    order: OrderEntity;
    position: PositionEntity;
    leverage: number;
    instrument: InstrumentEntity;
    isCoinM: boolean;
  }): Promise<BigNumber> {
    const { order, position, leverage, instrument, isCoinM } = data;
    const accountId = order.accountId;
    const inputPrice = new BigNumber(order.price);
    // the system always create new position when place a first order
    // so check position has quantity != 0 for sure
    const positionCurrentQty = new BigNumber(position?.currentQty ?? 0);
    const isHasPosition = position && !new BigNumber(position?.currentQty).isEqualTo(0);
    // check position is long or short
    // long means current quantity > 0 and short means current quantity < 0
    const isLongPosition = positionCurrentQty.isGreaterThan(0);

    // get marBuy/marSell from position
    const marBuy = new BigNumber(position?.marBuy ?? 0);
    const marSel = new BigNumber(position?.marSel ?? 0);

    // Compute mulBuy 
    let mulBuy = null;
    const markPrice = new BigNumber(await this.redisClient.getInstance().get(`${ORACLE_PRICE_PREFIX}${instrument.symbol}`)) ?? new BigNumber(0);
    if (isCoinM) {
      // MulBuy = 1/(Input price * Leverage) + (1/ Mark price - 1/ Input price) * (1 + 1/ Leverage)
      // transform to avoid division
      // MulBuy = (Input price * Leverage + Input price - Mark price * Leverage)
      // % (Input price * Mark price * Leverage)
      const numerator =
          inputPrice.multipliedBy(leverage).plus(inputPrice).minus(markPrice.multipliedBy(leverage));
      const denominator = inputPrice.multipliedBy(markPrice).multipliedBy(leverage);
      mulBuy = numerator.dividedBy(denominator);
    } else {
      // MulBuy = Input price/ Leverage + Input price - Mark price
      mulBuy = inputPrice.dividedBy(leverage).plus(inputPrice).minus(markPrice);
    }

    // Compute mulSell
    let mulSell = null;
    if (isCoinM) {
      // MulSel = 1/(Input price * Leverage) + (1/ Input price - 1/ Mark price)
      // = (Mark price + Leverage * Mark price - Leverage * Input price) / (Input price * Mark price
      // * Leverage)
      const numerator =
          markPrice.plus(markPrice.multipliedBy(leverage)).minus(inputPrice.multipliedBy(leverage));
      const denominator = inputPrice.multipliedBy(markPrice).multipliedBy(leverage);
      mulSell = numerator.dividedBy(denominator);
    } else {
      // MulSel = Input price/ Leverage + (Mark price - Input price) * (1 + 1/ Leverage)
      // = ((mark price - input price) * (leverage + 1) + input price) / leverage ->this get better
      // precision
      mulSell = markPrice
          .minus(inputPrice)
          .multipliedBy(leverage + 1)
          .plus(inputPrice)
          .dividedBy(leverage);
    }


    let orderCost = null;
    if (isHasPosition) {
      const positionMargin = new BigNumber((await this.positionService.calPositionMarginForAcc(accountId, USDT)).positionMargin);
      const positionSize = positionCurrentQty.abs();
      orderCost = this.calculateOrderCostWithPosition({
        isLongPosition,
        positionMargin,
        positionSize,
        inputPrice,
        marBuy,
        marSel,
        mulBuy,
        mulSell,
        order, 
        instrument, 
        isCoinM
      });
    } else {
      orderCost = this.calculateOrderCostWithoutPosition({
        inputPrice,
        marBuy,
        marSel,
        mulBuy,
        mulSell,
        order, 
        instrument, 
        isCoinM
      });
    }

    return orderCost;
  }

  private async calculateOrderCostWithPosition(data:{
    isLongPosition: boolean,
    positionMargin: BigNumber,
    positionSize: BigNumber,
    inputPrice: BigNumber,
    marBuy: BigNumber,
    marSel: BigNumber,
    mulBuy: BigNumber,
    mulSell: BigNumber,
    order: OrderEntity, 
    instrument: InstrumentEntity,
    isCoinM: boolean
  }): Promise<BigNumber> {
    const { isLongPosition, positionMargin, positionSize, inputPrice, marBuy, marSel, mulBuy, mulSell, order, instrument, isCoinM } = data; 
    const multiplier = isCoinM ? instrument.multiplier : new BigNumber(1);
    const markPrice = new BigNumber(await this.redisClient.getInstance()
        .get(`${ORACLE_PRICE_PREFIX}${instrument.symbol}`)) ?? new BigNumber(0);
    const size = new BigNumber(order.remaining);
    const leverage = Number(order.leverage);

    let orderCost = new BigNumber(0);
    const comparePrice = inputPrice.isGreaterThan(markPrice);

    if (isLongPosition) {
      // user hold long position
      if (comparePrice) {
        // if inputPrice > markPrice
        if (order.side == OrderSide.SELL) {
          if (isCoinM) {
            // Sell order cost = max (0; Size * Contract Multiplier / (Leverage * Input price)
            // - 2 * Position Margin + MarSel - MarBuy)
            const tempVal = size
              .multipliedBy(multiplier)
              .dividedBy(inputPrice.multipliedBy(leverage))              
              .minus(new BigNumber(2).multipliedBy(positionMargin))
              .plus(marSel)
              .minus(marBuy);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          } else {
            // Sell order cost = max (0; Size * Input price/ Leverage - 2 * Position Margin + MarSel
            // -
            // MarBuy)
            const tempVal = inputPrice
              .multipliedBy(size)
              .dividedBy(leverage)
              .minus(new BigNumber(2).multipliedBy(positionMargin))
              .plus(marSel)
              .minus(marBuy);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          }
        } else {
          if (isCoinM) {
            // Buy order cost = Size * Contract Multiplier * MulBuy
            orderCost = size.multipliedBy(multiplier).multipliedBy(mulBuy);
          } else {
            // Buy order cost = Size * MulBuy
            orderCost = size.multipliedBy(mulBuy);
          }
        }
      } else {
        // if inputPrice < markPrice
        if (order.side == OrderSide.SELL) {
          if (isCoinM) {
            // Sell order cost = max (0; (Order size - Position size) * Contract Multiplier
            // * MulSel - Position Margin + MarSel - MarBuy)
            const tempVal = mulSell
              .multipliedBy(size.minus(positionSize))
              .multipliedBy(multiplier)
              .minus(positionMargin)
              .plus(marSel)
              .minus(marBuy);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          } else {
            // Sell order cost = max (0; (Order size - Position size) * MulSel - Position Margin +
            // MarSel - MarBuy)
            const tempVal = mulSell
              .multipliedBy(size.minus(positionSize))
              .minus(positionMargin)
              .plus(marSel)
              .minus(marBuy);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          }
        } else {
          if (isCoinM) {
            // Buy order cost = Size *Contract Multiplier / (Leverage * Input price)
            orderCost = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
          } else {
            // Buy order cost = Input price * Size/ Leverage
            orderCost = inputPrice.multipliedBy(size).dividedBy(leverage);
          }
        }
      }
    } else {
      // user hold short position
      if (comparePrice) {
        // if inputPrice > markPrice
        if (order.side == OrderSide.SELL) {
          if (isCoinM) {
            // Sell order cost = Size *Contract Multiplier / (Leverage * Input price)
            orderCost = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
          } else {
            // Sell order cost = Size * Input price/ Leverage
            orderCost = inputPrice.multipliedBy(size).dividedBy(leverage);
          }
        } else {
          if (isCoinM) {
            // Buy order cost = max (0; (Order size - Position size)
            // * Contract Multiplier * MulBuy - Position Margin + MarBuy - MarSel)
            const tempVal = mulBuy
              .multipliedBy(size.minus(positionSize))
              .multipliedBy(multiplier)
              .minus(positionMargin)
              .plus(marBuy)
              .minus(marSel);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          } else {
            // Buy order cost = max (0; (Order size - Position size) * MulBuy - Position Margin +
            // MarBuy - MarSel)
            const tempVal = mulBuy
              .multipliedBy(size.minus(positionSize))
              .minus(positionMargin)
              .plus(marBuy)
              .minus(marSel);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          }
        }
      } else {
        // if inputPrice < markPrice
        if (order.side == OrderSide.SELL) {
          if (isCoinM) {
            // Sell order cost = Size * Contract Multiplier * MulSel
            orderCost = size.multipliedBy(multiplier).multipliedBy(mulSell);
          } else {
            // Sell order cost = Size * MulSel
            orderCost = size.multipliedBy(mulSell);
          }
        } else {
          if (isCoinM) {
            // Buy order cost = max (0; Size *Contract Multiplier / (Leverage * Input price)
            // - 2 * Position Margin + MarBuy - MarSel)
            const tempVal = size
              .multipliedBy(multiplier)
              .dividedBy(inputPrice.multipliedBy(leverage))
              .minus(new BigNumber(2).multipliedBy(positionMargin))
              .plus(marBuy)
              .minus(marSel);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          } else {
            // Buy order cost = max (0; Size * Input price/ Leverage - 2 * Position Margin + MarBuy
            // -
            // MarSel)
            const tempVal = inputPrice
              .multipliedBy(size)
              .dividedBy(leverage)
              .minus(new BigNumber(2).multipliedBy(positionMargin))
              .plus(marBuy)
              .minus(marSel);
            orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
          }
        }
      }
    }
    return orderCost;
  }

  private async calculateOrderCostWithoutPosition(data: {
    inputPrice: BigNumber;
    marBuy: BigNumber;
    marSel: BigNumber;
    mulBuy: BigNumber;
    mulSell: BigNumber;
    order: OrderEntity;
    instrument: InstrumentEntity;
    isCoinM: boolean;
  }): Promise<BigNumber> {
    const { inputPrice, marBuy, marSel, mulBuy, mulSell, order, instrument, isCoinM } = data;
    const multiplier = isCoinM ? instrument.multiplier : new BigNumber(1);
    const markPrice = new BigNumber(await this.redisClient.getInstance()
        .get(`${ORACLE_PRICE_PREFIX}${instrument.symbol}`)) ?? new BigNumber(0);
    const size = new BigNumber(order.remaining);
    const leverage = new BigNumber(order.leverage);

    let orderCost = new BigNumber(0);
    const comparePrice = inputPrice.isGreaterThan(markPrice);
    const compareMarBuySell = marBuy.minus(marSel);
    switch (compareMarBuySell) {
      case new BigNumber(1):
        // if marBuy > marSel
        if (comparePrice) {
          // if inputPrice > markPrice
          if (order.side == OrderSide.SELL) {
            if (isCoinM) {
              // Sell order cost = max (0,
              // Size * Contract Multiplier / (Leverage * Input price) - MarBuy + MarSel)
              const tempVal = size
                .multipliedBy(multiplier)
                .dividedBy(inputPrice.multipliedBy(leverage))
                .minus(marBuy)
                .plus(marSel);
              orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
            } else {
              // Sell order cost = max (0; Input price * Size/ Leverage - MarBuy + MarSel)
              const tempVal = inputPrice
                .multipliedBy(size)
                .dividedBy(leverage)
                .minus(marBuy)
                .plus(marSel);
              orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size * Contract Multiplier * MulBuy
              orderCost = size.multipliedBy(multiplier).multipliedBy(mulBuy);
            } else {
              // Buy order cost = Size * MulBuy
              orderCost = size.multipliedBy(mulBuy);
            }
          }
        } else {
          // if inputPrice < markPrice
          if (order.side == OrderSide.SELL) {
            if (isCoinM) {
              // "Sell order cost = Size * Contract Multiplier * MulSel -
              // min(Size *Contract Multiplier / (Leverage * Input price), MarBuy - MarSel)"
              let minTempVal = marBuy.minus(marSel);
              const tempVal = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
              minTempVal = minTempVal.isLessThan(tempVal)? minTempVal: tempVal;
              orderCost = size.multipliedBy(multiplier).multipliedBy(mulSell).minus(minTempVal);
            } else {
              // Sell order cost = Size * MulSel - min (Size * Input price/ Leverage; MarBuy)
              const tempVal = inputPrice.multipliedBy(size).dividedBy(leverage);
              const minTempVal = marBuy.isLessThan(tempVal)? marBuy: tempVal;
              orderCost = size.multipliedBy(mulSell).minus(minTempVal);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size *Contract Multiplier / (Leverage * Input price)
              orderCost = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
            } else {
              // Buy order cost = Input price * Size/ Leverage
              orderCost = inputPrice.multipliedBy(size).dividedBy(leverage);
            }
          }
        }
        break;
      case new BigNumber(-1):
        // if marBuy < marSel
        if (comparePrice) {
          // if inputPrice > markPrice
          if (order.side == OrderSide.SELL) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier / (Leverage * Input price)
              orderCost = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
            } else {
              // Sell order cost = Input price * Size / Leverage
              orderCost = inputPrice.multipliedBy(size).dividedBy(leverage);
            }
          } else {
            if (isCoinM) {
              // "Buy order cost = Size * Contract Multiplier * MulBuy -
              // min (Size * Contract Multiplier / (Leverage * Input price), MarSel - MarBuy)"
              const tempVal = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
              let minTempVal = marSel.minus(marBuy);
              minTempVal = minTempVal.isLessThan(tempVal)? minTempVal: tempVal;
              orderCost = size.multipliedBy(multiplier).multipliedBy(mulBuy).minus(minTempVal);
            } else {
              // Buy order cost = Size * MulBuy - min(Size * Input price/ Leverage; MarSel)
              const tempVal = inputPrice.multipliedBy(size).dividedBy(leverage);
              const minTempVal = marSel.isLessThan(tempVal)? marSel: tempVal;
              orderCost = size.multipliedBy(mulBuy).minus(minTempVal);
            }
          }
        } else {
          // if inputPrice < markPrice
          if (order.side == OrderSide.SELL) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier * MulSel
              orderCost = size.multipliedBy(multiplier).multipliedBy(mulSell);
            } else {
              // Sell order cost = Size * MulSel
              orderCost = size.multipliedBy(mulSell);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = max (0, Size * Contract Multiplier / (Leverage * Input price)
              // - MarSel + MarBuy)
              const tempVal =
                  size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage))
                    .minus(marSel)
                    .plus(marBuy);
              orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
            } else {
              // Buy order cost = max (0; Input price * Size/ Leverage - MarSel + MarBuy)
              const tempVal = inputPrice.multipliedBy(size).dividedBy(leverage).minus(marSel).plus(marBuy);
              orderCost = orderCost.isGreaterThan(tempVal)? orderCost: tempVal;
            }
          }
        }
        break;
      case new BigNumber(0):
        // if marBuy = marSel
        if (comparePrice) {
          // if inputPrice > markPrice
          if (order.side == OrderSide.SELL) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier / (Leverage * Input price)
              orderCost = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
            } else {
              // Sell order cost = Input price * Size / Leverage
              orderCost = inputPrice.multipliedBy(size).dividedBy(leverage);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size * Contract Multiplier * MulBuy
              orderCost = size.multipliedBy(multiplier).multipliedBy(mulBuy);
            } else {
              // Buy order cost = Size * MulBuy
              orderCost = size.multipliedBy(mulBuy);
            }
          }
        } else {
          // if inputPrice < markPrice
          if (order.side == OrderSide.SELL) {
            if (isCoinM) {
              // Sell order cost = Size * Contract Multiplier * MulSel
              orderCost = size.multipliedBy(multiplier).multipliedBy(mulSell);
            } else {
              // Sell order cost = Size * MulSel
              orderCost = size.multipliedBy(mulSell);
            }
          } else {
            if (isCoinM) {
              // Buy order cost = Size *Contract Multiplier / (Leverage * Input price)
              orderCost = size.multipliedBy(multiplier).dividedBy(inputPrice.multipliedBy(leverage));
            } else {
              // Buy order cost = Input price * Size/ Leverage
              orderCost = inputPrice.multipliedBy(size).dividedBy(leverage);
            }
          }
        }
        break;
    }
    return orderCost;
  }


  async createOrderForDefaultCreateOrderUser(data: { createOrderDtos: CreateOrderDto[]; symbol: string }) {
    if (!data.createOrderDtos || data.createOrderDtos.length == 0) return;
    const defaultCreateOrderAccounts: AccountEntity[] = await this.accountService.getAllAccount(2);

    let asset = "";
    if (data.symbol.includes("USDM")) {
      asset = data.symbol.split("USDM")[0];
    } else if (data.symbol.includes("USDT")) {
      asset = "USDT";
    } else {
      asset = "USD";
    }

    for (const orderNeedCreate of data.createOrderDtos) {
      const defaultCreateOrderAccount: AccountEntity = defaultCreateOrderAccounts.find((a) => a.asset === asset);
      if (!defaultCreateOrderAccount) continue;
      await this.createOrder(orderNeedCreate, {
        accountId: defaultCreateOrderAccount.id,
        userId: defaultCreateOrderAccount.userId,
        email: defaultCreateOrderAccount.userEmail,
      });
    }
  }

  async getRootOrder(accountId: number, orderId: number, type: ORDER_TPSL): Promise<OrderEntity> {
    const where = {
      accountId: accountId,
    };
    if (type == ORDER_TPSL.STOP_LOSS) {
      where["stopLossOrderId"] = orderId;
    }
    if (type == ORDER_TPSL.TAKE_PROFIT) {
      where["takeProfitOrderId"] = orderId;
    }
    const order = await this.orderRepoReport.findOne({
      select: ["price", "quantity", "quantity", "id", "side", "tpSLPrice", "type", "isReduceOnly", "trigger", "remaining"],
      where: where,
    });
    return order;
  }
  async findOrderBatch(status: OrderStatus, fromId: number, count: number): Promise<OrderEntity[]> {
    return await this.orderRepoMaster.findOrderBatch(status, fromId, count);
  }

  async findAccountOrderBatch(
    userId: number,
    status: OrderStatus,
    fromId: number,
    count: number,
    types: string[],
    cancelOrderType: CANCEL_ORDER_TYPE,
    contractType: ContractType
  ): Promise<OrderEntity[]> {
    return await this.orderRepoReport.findAccountOrderBatch(userId, status, fromId, count, types, cancelOrderType, contractType);
  }

  async cancelOrder(orderId: string, userId: number): Promise<OrderEntity> {
    const checkStatusEnableCreateOrder = await this.cacheManager.get<boolean>(ENABLE_CREATE_ORDER);
    if (checkStatusEnableCreateOrder && await this.botInMemoryService.checkIsBotUserId(userId)) {
      return;
    }

    let canceledOrder: OrderEntity | null = null;
  
    const retryFindOrderByTmpId = async (
      maxRetries: number,
      delayMs: number
    ): Promise<OrderEntity | null> => {
      for (let attempt = 0; attempt < maxRetries; attempt++) {
        const order = await this.orderRepoReport.findOne({
          where: {
            tmpId: orderId,
            userId: userId,
            status: In([OrderStatus.ACTIVE, OrderStatus.PENDING, OrderStatus.UNTRIGGERED]),
          },
        });
        if (order) return order;
        console.log(`[cancelOrder|orderId=${orderId}] attempt = `, attempt);
        if (attempt < maxRetries - 1) await new Promise((res) => setTimeout(res, delayMs * 2 ** attempt));
      }
      return null;
    };
  
    if (orderId.startsWith('uuid-')) {
      canceledOrder = await retryFindOrderByTmpId(2, 4000); 
    } else {
      canceledOrder = await this.orderRepoReport.findOne({
        where: {
          id: Number(orderId),
          userId: userId,
          status: In([OrderStatus.ACTIVE, OrderStatus.PENDING, OrderStatus.UNTRIGGERED]),
        },
      });
    }
  
    if (!canceledOrder) {
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
  
    this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.CANCEL_ORDER,
      data: canceledOrder,
    });
    return canceledOrder;
  }

  async cancelOrderV2(orderId: string, userId: number): Promise<OrderEntity> {
    const checkStatusEnableCreateOrder = await this.cacheManager.get<boolean>(ENABLE_CREATE_ORDER);
    if (checkStatusEnableCreateOrder && await this.botInMemoryService.checkIsBotUserId(userId)) {
      return;
    }

    const newCancelFlowFlag = await this.redisClient.getInstance().get('NEW_CANCEL_ORDER_FLOW');
    if (!newCancelFlowFlag) {
      this.kafkaClient.send(KafkaTopics.cancel_order_from_client, {
        userId: String(userId),
        orderId: String(orderId),
      });
    } else {
      this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.CANCEL_ORDER,
        data: { id: orderId },
      });
    }
    
    return { id: orderId as any } as OrderEntity;
  }

  async cancelAllOrder(userId: number, cancelOrderType: CANCEL_ORDER_TYPE, contractType: ContractType): Promise<OrderEntity[]> {
    const statuses = [OrderStatus.ACTIVE, OrderStatus.PENDING, OrderStatus.UNTRIGGERED];
    let types: any = [];
    switch (cancelOrderType) {
      case CANCEL_ORDER_TYPE.LIMIT:
        types = CANCEL_LIMIT_TYPES;
        break;
      case CANCEL_ORDER_TYPE.STOP:
        types = CANCEL_STOP_TYPES;
        break;
      case CANCEL_ORDER_TYPE.ALL:
        types = [...CANCEL_STOP_TYPES, OrderType.LIMIT];
        break;
      default:
        break;
    }
    return await this.sendDataCancelToKafka(statuses, userId, types, cancelOrderType, contractType);
  }

  async sendDataCancelToKafka(
    statuses: OrderStatus[],
    userId: number,
    types: string[],
    cancelOrderType: CANCEL_ORDER_TYPE,
    contractType: ContractType
  ) {
    const producer = kafka.producer();
    await producer.connect();
    for (const status of statuses) {
      await this.cancelOrderByStatus(userId, status, producer, types, cancelOrderType, contractType);
    }
    await producer.disconnect();
    return [];
  }

  async cancelOrderByStatus(
    userId: number,
    status: OrderStatus,
    producer: Producer,
    types: string[],
    cancelOrderType: CANCEL_ORDER_TYPE,
    contractType: ContractType
  ): Promise<void> {
    const loader = async (fromId: number, size: number): Promise<OrderEntity[]> => {
      return await this.findAccountOrderBatch(userId, status, fromId, size, types, cancelOrderType, contractType);
    };
    await this.loadData(producer, loader, CommandCode.CANCEL_ORDER, KafkaTopics.matching_engine_input);
  }

  async getLastOrderId(): Promise<number> {
    return await this.orderRepoMaster.getLastId();
  }

  async getHistoryOrders(userId: number, paging: PaginationDto, orderHistoryDto: OrderHistoryDto) {
    const startTime = moment(orderHistoryDto.startTime).format("YYYY-MM-DD HH:mm:ss");
    const endTime = moment(orderHistoryDto.endTime).format("YYYY-MM-DD HH:mm:ss");
    const { orders, count } = await this.orderRepoReport.getOrderHistory(orderHistoryDto, startTime, endTime, userId, paging);
    if (!orders || orders.length === 0) {
      return {
        data: [],
        metadata: {
          totalPage: 0,
        },
      };
    }

    const orderAverageByTrades = await this.getOrderAverageByTradeForGetOrderHistory(orders.map(o => o.id));
    for (const order of orders) {
      const orderAverageByTrade = orderAverageByTrades.find(oabt => Number(oabt.orderId) === Number(order.id));
      order['average'] = orderAverageByTrade? orderAverageByTrade.average: 0;
    }

    return {
      data: orders,
      metadata: {
        totalPage: Math.ceil(count / paging.size),
      },
    };
  }

  private async getOrderAverageByTradeForGetOrderHistory(orderIds: number[]): Promise<OrderAverageByTradeEntity[]> {
    const startProcessTime = Date.now();
    return await this.orderAverageByTradeRepoReport
      .createQueryBuilder("orderAverageByTrade")
      .where("orderAverageByTrade.orderId IN (:...orderIds)", { orderIds })
      .select(["orderAverageByTrade.id", "orderAverageByTrade.orderId", "orderAverageByTrade.average"])
      .getMany();
    console.log(`get orderAverageByTrade: ${Date.now() - startProcessTime}`);
  }

  async validateOrder(createOrder: CreateOrderDto, accountData: IUserAccount): Promise<CreateOrderDto> {
    removeEmptyField(createOrder);
    const order = { ...createOrder };
    if (order.quantity == null || (typeof order.quantity === 'string' && order.quantity === "")) {
      throw new HttpException(httpErrors.ORDER_QUANTITY_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
    }
    if (typeof order.quantity === 'string' && order.quantity.includes('e')) {
      order.quantity = new BigNumber(parseFloat(order.quantity)).toFixed();
    }
    if (typeof order.remaining === 'string' && order.remaining.includes('e')) {
      order.remaining = new BigNumber(parseFloat(order.remaining)).toFixed();
    }

    const { maxFiguresForPrice, maxFiguresForSize } = await this.instrumentService.getCachedInstrument(order.symbol);
    const isBot = await this.botInMemoryService.checkIsBotAccountId(accountData.accountId);
    if (!isBot) {
      if (typeof order.quantity === 'string' && order.type === OrderType.MARKET && String(order?.quantity)?.includes('%')) {
        const accountAvailableBalance = await this.balanceService.calAvailableBalance(accountData.balance.toString(), accountData.accountId, USDT);
        const availBalance = new BigNumber(accountAvailableBalance.availableBalance);
        const instrument = await this.instrumentService.getCachedInstrument(order.symbol);
        const marginMode = await this.userMarginModeService.getCachedMarginMode(accountData.userId, instrument.id);
        const userLeverage = marginMode ? Number(marginMode.leverage) : Number(DEFAULT_LEVERAGE);
  
        let orderQtyInPercent = new BigNumber(String(order?.quantity).replace('%', ''));
        orderQtyInPercent = orderQtyInPercent.isEqualTo(100)? orderQtyInPercent.minus(0.5): orderQtyInPercent;
        const balanceFromPercent = availBalance.dividedBy(100).multipliedBy(orderQtyInPercent);
        const balanceFromPercentMulLeverage = balanceFromPercent.multipliedBy(userLeverage);
        const markPrice = new BigNumber(await this.redisClient.getInstance().get(`${ORACLE_PRICE_PREFIX}${instrument.symbol}`)) ?? new BigNumber(0);
        const convertedQuantity = balanceFromPercentMulLeverage.dividedBy(markPrice);
        order.quantity = convertedQuantity.toFixed(+maxFiguresForSize);
      }
    } 

    order.remaining = order.quantity;
    order.status = OrderStatus.PENDING;
    order.timeInForce = order.timeInForce || OrderTimeInForce.GTC;

    const tradingRule = await this.tradingRulesService.getTradingRuleByInstrumentId(order.symbol) as TradingRulesEntity;
    const num = parseInt("1" + "0".repeat(+maxFiguresForSize));

    const minimumQty = new BigNumber(`${(1 / num).toFixed(+maxFiguresForSize)}`);
    const maximumQty = new BigNumber(tradingRule.maxOrderAmount);

    // FOR ALL ORDER TYPE VALIDATION
    // validate minimum quantity
    if (minimumQty.gt(order.quantity)) {
      throw new HttpException(httpErrors.ORDER_MINIMUM_QUANTITY_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
    }
    // validate maximum quantity
    if (maximumQty.lt(order.quantity)) {
      throw new HttpException(httpErrors.ORDER_MAXIMUM_QUANTITY_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
    }

    // validate precision
    if (this.validatePrecision(order.quantity, maxFiguresForSize)) {
      throw new HttpException(httpErrors.ORDER_QUANTITY_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
    }
    if (order.price) {
      if (this.validatePrecision(order.price, maxFiguresForPrice)) {
        throw new HttpException(httpErrors.ORDER_PRICE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }
    }
    await this.validateMinMaxPrice(createOrder);
    // TPSL
    let checkPrice;
    if (order.type === OrderType.MARKET && !order.tpSLType) {
      const tickers = await this.cacheService.get<Ticker[]>(TICKERS_KEY);
      const ticker = tickers.find((ticker) => ticker.symbol === order.symbol);
      checkPrice = ticker?.lastPrice ?? null;
    } else if (order.type === OrderType.MARKET && order.tpSLType === TpSlType.STOP_MARKET) {
      checkPrice = order.tpSLPrice;
    }
    if (order.takeProfit || order.takeProfitTrigger) {
      if (order.takeProfit && order.takeProfitTrigger) {
        if (order.side == OrderSide.BUY && Number(order.takeProfit) <= Number(checkPrice)) {
          throw new HttpException(httpErrors.TAKE_PROFIT_TRIGGER_OR_PRICE_NOT_VALID, HttpStatus.BAD_REQUEST);
        }
        if (order.side == OrderSide.SELL && Number(order.takeProfit) >= Number(checkPrice)) {
          throw new HttpException(httpErrors.TAKE_PROFIT_TRIGGER_OR_PRICE_NOT_VALID, HttpStatus.BAD_REQUEST);
        }
      } else {
        throw new HttpException(httpErrors.TAKE_PROFIT_TRIGGER_OR_PRICE_NOT_VALID, HttpStatus.BAD_REQUEST);
      }
      if (!order.takeProfitCondition) {
        throw new HttpException(httpErrors.TAKE_PROFIT_CONDITION_UNDEFINED, HttpStatus.BAD_REQUEST);
      }
    }
    if (order.stopLoss || order.stopLossTrigger) {
      if (order.stopLoss && order.stopLossTrigger) {
        if (order.side == OrderSide.BUY && Number(order.stopLoss) >= Number(checkPrice)) {
          throw new HttpException(httpErrors.STOP_LOSS_TRIGGER_OR_PRICE_NOT_VALID, HttpStatus.BAD_REQUEST);
        }
        if (order.side == OrderSide.SELL && Number(order.stopLoss) <= Number(checkPrice)) {
          throw new HttpException(httpErrors.STOP_LOSS_TRIGGER_OR_PRICE_NOT_VALID, HttpStatus.BAD_REQUEST);
        }
      } else {
        throw new HttpException(httpErrors.STOP_LOSS_TRIGGER_OR_PRICE_NOT_VALID, HttpStatus.BAD_REQUEST);
      }
      if (!order.stopLossCondition) {
        throw new HttpException(httpErrors.STOP_LOSS_CONDITION_UNDEFINED, HttpStatus.BAD_REQUEST);
      }
    }
    // TRAILING_STOP
    if (order.tpSLType == TpSlType.TRAILING_STOP) {
      order.type = OrderType.MARKET;
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      delete order.takeProfit;
      delete order.takeProfitTrigger;
      delete order.stopLoss;
      delete order.stopLossTrigger;
      if (!order.trigger) throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.activationPrice || new BigNumber(order.activationPrice).lte(0))
        throw new HttpException(httpErrors.ORDER_ACTIVATION_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.callbackRate) {
        throw new HttpException(httpErrors.CALLBACK_RATE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }
      if (this.validatePrecision(order.activationPrice, maxFiguresForPrice))
        throw new HttpException(httpErrors.ORDER_TRAIL_VALUE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (order.type !== OrderType.MARKET) {
        throw new HttpException(httpErrors.TRAILING_STOP_ORDER_TYPE_NOT_VALID, HttpStatus.BAD_REQUEST);
      }
      
      return order;
    }

    // POST_ONLY
    if (order.type === OrderType.LIMIT && order.isPostOnly) {
      order.timeInForce = OrderTimeInForce.GTC;
      delete order.isHidden;
      if ((order.stopLoss || order.takeProfit) && !order.trigger)
        throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      return order;
    }

    // STOP_LIMIT
    if (order.type == OrderType.LIMIT && order.tpSLType == TpSlType.STOP_LIMIT) {
      if (!order.price) throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.trigger) throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.tpSLPrice || new BigNumber(order.tpSLPrice).eq(0))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.stopCondition) {
        throw new HttpException(httpErrors.NOT_HAVE_STOP_CONDITION, HttpStatus.BAD_REQUEST);
      }
      if (this.validatePrecision(order.tpSLPrice, maxFiguresForPrice))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      delete order.activationPrice;
      return order;
    }

    // STOP_MARKET
    if (order.type == OrderType.MARKET && order.tpSLType == TpSlType.STOP_MARKET) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      if (!order.trigger) throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.tpSLPrice || new BigNumber(order.tpSLPrice).eq(0))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (this.validatePrecision(order.tpSLPrice, maxFiguresForPrice))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.stopCondition) {
        throw new HttpException(httpErrors.NOT_HAVE_STOP_CONDITION, HttpStatus.BAD_REQUEST);
      }
      delete order.activationPrice;
      return order;
    }

    // TAKE_PROFIT_LIMIT
    if (order.type == OrderType.LIMIT && order.takeProfit && new BigNumber(order.takeProfit).gt(0)) {
      if (!order.price || new BigNumber(order.price).eq(0))
        throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.trigger) throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.takeProfit || new BigNumber(order.takeProfit).eq(0))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (this.validatePrecision(order.takeProfit, maxFiguresForPrice))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      delete order.activationPrice;
      return order;
    }
    //STOP_LOSS_LIMIT
    if (order.type == OrderType.LIMIT && order.stopLoss && new BigNumber(order.stopLoss).gt(0)) {
      if (!order.price || new BigNumber(order.price).eq(0))
        throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.trigger) throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.stopLoss || new BigNumber(order.stopLoss).eq(0))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (this.validatePrecision(order.stopLoss, maxFiguresForPrice))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      delete order.activationPrice;
      return order;
    }
    // TAKE_PROFIT_MARKET
    if (order.type == OrderType.MARKET && order.takeProfit && new BigNumber(order.takeProfit).gt(0)) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      if (!order.trigger) throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.takeProfit || new BigNumber(order.takeProfit).eq(0))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (this.validatePrecision(order.takeProfit, maxFiguresForPrice))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      delete order.activationPrice;
      return order;
    }
    // STOP_LOSS_MARKET
    if (order.type == OrderType.MARKET && order.stopLoss && new BigNumber(order.stopLoss).gt(0)) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      if (!order.trigger) throw new HttpException(httpErrors.ORDER_TRIGGER_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (!order.stopLoss || new BigNumber(order.stopLoss).eq(0))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (this.validatePrecision(order.stopLoss, maxFiguresForPrice))
        throw new HttpException(httpErrors.ORDER_STOP_PRICE_PRECISION_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      delete order.activationPrice;
      return order;
    }
    // LIMIT
    if (order.type == OrderType.LIMIT) {
      if (!order.price) throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      delete order.trigger;
      delete order.activationPrice;
      return order;
    }

    // MARKET
    if (order.type == OrderType.MARKET) {
      delete order.price;
      order.timeInForce = OrderTimeInForce.IOC;
      delete order.isPostOnly;
      delete order.isHidden;
      delete order.trigger;
      delete order.activationPrice;
      return order;
    }

    this.logger.debug("ORDER_UNKNOWN_VALIDATION_FAIL");
    this.logger.debug(createOrder);
    this.logger.debug(order);
    throw new HttpException(httpErrors.ORDER_UNKNOWN_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
  }

  validatePrecision(value: string | BigNumber, precision: string | BigNumber): boolean {
    const numberOfDecimalFigures = value.toString().split(".")[1];
    if (!numberOfDecimalFigures) {
      return false;
    }
    return numberOfDecimalFigures.length > +precision.toString();
    // return new BigNumber(value).dividedToIntegerBy(precision).multipliedBy(precision).lt(new BigNumber(value));
  }

  async validateMinMaxPrice(createOrderDto: CreateOrderDto) {
    const order = { ...createOrderDto };
    const [tradingRules, instrument, markPrice] = await Promise.all([
      this.tradingRulesService.getTradingRuleByInstrumentId(order.symbol) as any,
      this.instrumentService.getCachedInstrument(order.symbol),
      this.redisClient.getInstance().get(`${ORACLE_PRICE_PREFIX}${order.symbol}`),
    ]);
    let price: BigNumber;
    let minPrice: BigNumber;
    let maxPrice: BigNumber;
    switch (order.side) {
      case OrderSide.SELL: {
        // validate minPrice
        maxPrice = new BigNumber(instrument?.maxPrice);
        minPrice = new BigNumber(tradingRules?.minPrice);
        if ((order.type == OrderType.LIMIT && !order.tpSLType) || order.isPostOnly == true) {
          price = new BigNumber(markPrice).times(new BigNumber(1).minus(new BigNumber(tradingRules?.floorRatio).dividedBy(100)));
          minPrice = BigNumber.maximum(new BigNumber(tradingRules?.minPrice), price);
        }

        if (order.tpSLType == TpSlType.STOP_LIMIT) {
          price = new BigNumber(order.tpSLPrice).times(new BigNumber(1).minus(new BigNumber(tradingRules?.floorRatio).dividedBy(100)));
          minPrice = BigNumber.maximum(new BigNumber(tradingRules?.minPrice), price);
        }
        if (new BigNumber(order.price).isLessThan(minPrice)) {
          console.log("minPrice", minPrice);
          console.log("order.price", order.price);
          throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
        }
        // validate max Price:
        if (new BigNumber(order.price).isGreaterThan(instrument.maxPrice)) {
          throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
        }
        break;
      }
      //limitOrderPrice = cap ratio
      case OrderSide.BUY: {
        maxPrice = new BigNumber(instrument?.maxPrice);
        minPrice = new BigNumber(tradingRules?.minPrice);
        if ((order.type == OrderType.LIMIT && !order.tpSLType) || order.isPostOnly == true) {
          price = new BigNumber(markPrice).times(new BigNumber(1).plus(new BigNumber(tradingRules?.limitOrderPrice).dividedBy(100)));
          maxPrice = BigNumber.minimum((new BigNumber(instrument?.maxPrice), price));
        }
        if (order.tpSLType == TpSlType.STOP_LIMIT) {
          price = new BigNumber(order.tpSLPrice).times(new BigNumber(1).plus(new BigNumber(tradingRules?.limitOrderPrice).dividedBy(100)));
          maxPrice = BigNumber.minimum((new BigNumber(instrument?.maxPrice), price));
        }
        if (new BigNumber(order.price).isLessThan(minPrice))
          throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
        if (new BigNumber(order.price).isGreaterThan(maxPrice))
          throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
        break;
      }
      default:
        break;
    }
  }

  async getTpSlOrder(rootOrderId: string) {
    let rootOrder: OrderEntity | null = null;
  
    const retryFindOrderByTmpId = async (
      maxRetries: number,
      delayMs: number
    ): Promise<OrderEntity | null> => {
      for (let attempt = 0; attempt < maxRetries; attempt++) {
        const rootOrder = await this.orderRepoReport.findOne({
          where: {
            tmpId: rootOrderId,
          },
        });

        if (rootOrder) return rootOrder;
        console.log(`[getTpSlOrder|orderId=${rootOrderId}] attempt = `, attempt);
        if (attempt < maxRetries - 1) await new Promise((res) => setTimeout(res, delayMs * 2 ** attempt));
      }
      return null;
    };
  
    if (rootOrderId.startsWith('uuid-')) {
      rootOrder = await retryFindOrderByTmpId(2, 4000);
    } else {
      rootOrder = await this.orderRepoReport.findOne({
        where: {
          id: Number(rootOrderId),
        },
      });
    }
    
    if (!rootOrder || (!rootOrder.takeProfitOrderId && !rootOrder.stopLossOrderId)) {
      return new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
    const [tpOrder, slOrder] = await Promise.all([
      this.orderRepoReport.findOne({
        where: {
          id: rootOrder.takeProfitOrderId,
        },
      }),
      this.orderRepoReport.findOne({
        where: {
          id: rootOrder.stopLossOrderId,
        },
      }),
    ]);
    return {
      rootOrder,
      tpOrder,
      slOrder,
    };
  }

  async updateTpSlOrder(userId: number, updateTpSlOrder: UpdateTpSlOrderDto[], rootOrderId: string): Promise<void> {
    if (updateTpSlOrder.length === 0) {
      return;
    }

    const retryFindOrderByTmpId = async (
      orderId: string,
      maxRetries: number,
      delayMs: number
    ): Promise<OrderEntity | null> => {
      for (let attempt = 0; attempt < maxRetries; attempt++) {
        const rootOrder = await this.orderRepoReport.findOne({
          where: {
            tmpId: orderId,
          },
        });

        if (rootOrder) return rootOrder;
        console.log(`[updateTpSlOrder|orderId=${orderId}] attempt = `, attempt);
        if (attempt < maxRetries - 1) await new Promise((res) => setTimeout(res, delayMs * 2 ** attempt));
      }
      return null;
    };

    let rootOrder = null; 
    if (rootOrderId.startsWith('uuid-')) {
      rootOrder = await retryFindOrderByTmpId(rootOrderId, 2, 4000);
    } else {
      rootOrder = await this.orderRepoReport.findOne(Number(rootOrderId));
    }

    if (!rootOrder) {
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
    let maxPrice = await this.cacheManager.get(`${MAX_PRICE}_${rootOrder.symbol}`);
    let minPrice = await this.cacheManager.get(`${MIN_PRICE}_${rootOrder.symbol}`);
    if (!maxPrice) {
      const instrument = await this.instrumentRepoReport.findOne({
        symbol: rootOrder.symbol,
      }),
      maxPrice = instrument.maxPrice;
      await this.cacheManager.set(`${MAX_PRICE}_${rootOrder.symbol}`, maxPrice, { ttl: PRICE_CACHE_TTL });
    }
    if (!minPrice) {
      const tradingRule = await this.tradingRulesRepoReport.findOne({
        symbol: rootOrder.symbol,
      });
      minPrice = tradingRule.minPrice;
      await this.cacheManager.set(`${MIN_PRICE}_${rootOrder.symbol}`, minPrice, { ttl: PRICE_CACHE_TTL });
    }

    const tpSlOrder = {
      tpOrderId: null,
      slOrderId: null,
      tpOrderChangePrice: null,
      slOrderChangePrice: null,
      tpOrderTrigger: null,
      slOrderTrigger: null,
    };
    const checkPrice = rootOrder.price ? +rootOrder.price : +rootOrder.tpSLPrice;
    for (const element of updateTpSlOrder) {
      const { orderId, ...dataUpdate } = element;
      let isExistOrder = null;
      if (orderId.startsWith('uuid-')) {
        isExistOrder = await retryFindOrderByTmpId(orderId, 2, 4000);
      } else {
        isExistOrder = await this.orderRepoReport.findOne({
          id: Number(orderId),
          userId,
        });
      }

      if (!isExistOrder) {
        throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
      }

      if (+element.tpSLPrice < Number(minPrice) || +element.tpSLPrice > Number(maxPrice)) {
        throw new HttpException(httpErrors.ORDER_UNKNOWN_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }
      if (rootOrder.side == OrderSide.BUY && isExistOrder.tpSLType === TpSlType.TAKE_PROFIT_MARKET && +dataUpdate.tpSLPrice <= checkPrice) {
        throw new HttpException(httpErrors.ORDER_UNKNOWN_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }
      if (rootOrder.side == OrderSide.BUY && isExistOrder.tpSLType === TpSlType.STOP_MARKET && +dataUpdate.tpSLPrice >= checkPrice) {
        throw new HttpException(httpErrors.ORDER_UNKNOWN_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }

      if (
        rootOrder.side == OrderSide.SELL &&
        isExistOrder.tpSLType === TpSlType.TAKE_PROFIT_MARKET &&
        +dataUpdate.tpSLPrice >= checkPrice
      ) {
        throw new HttpException(httpErrors.ORDER_UNKNOWN_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }

      if (rootOrder.side == OrderSide.SELL && isExistOrder.tpSLType === TpSlType.STOP_MARKET && +dataUpdate.tpSLPrice <= checkPrice) {
        throw new HttpException(httpErrors.ORDER_UNKNOWN_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }
      if (isExistOrder.tpSLType === TpSlType.TAKE_PROFIT_MARKET) {
        tpSlOrder.tpOrderId = isExistOrder.id;
        tpSlOrder.tpOrderChangePrice = dataUpdate.tpSLPrice;
        tpSlOrder.tpOrderTrigger = dataUpdate.trigger === isExistOrder.trigger ? null : dataUpdate.trigger;
      }
      if (isExistOrder.tpSLType === TpSlType.STOP_MARKET) {
        tpSlOrder.slOrderId = isExistOrder.id;
        tpSlOrder.slOrderChangePrice = dataUpdate.tpSLPrice;
        tpSlOrder.slOrderTrigger = dataUpdate.trigger === isExistOrder.trigger ? null : dataUpdate.trigger;
      }
    }
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.ADJUST_TP_SL_PRICE,
      data: {
        ...tpSlOrder,
      },
    });
  }

  public removeEmptyValues(object: OrderEntity | any): void {
    for (const key in object) {
      if (key !== "userEmail" && object.hasOwnProperty(key)) {
        const value = object[key];
        if (value === null || value === undefined || value === "") {
          delete object[key];
        }
      }
    }
  }

  async calOrderMargin(accountId: number, asset: string) {
    try {
      const result = await this.orderRepoReport
        .createQueryBuilder("o")
        .where("o.asset = :asset", { asset })
        .andWhere("o.accountId = :accountId", { accountId })
        .andWhere("o.status IN (:status)", {
          status: [OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED],
        })
        .select("SUM(o.orderMargin) as totalCost")
        .getRawOne();

      return result.totalCost ? result.totalCost : 0;
    } catch (error) {
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
  }

  async updateUserIdInOrder(): Promise<void> {
    let skip = START_CRAWL;
    const take = GET_NUMBER_RECORD;
    do {
      const orders = await this.orderRepoReport.find({
        where: {
          userId: IsNull(),
        },
        skip,
        take,
      });

      skip += take;

      if (orders.length) {
        for (const o of orders) {
          const user = await getConnection("report").getRepository(AccountEntity).findOne({
            id: o.accountId,
          });

          if (!user) continue;
          await this.orderRepoMaster.update({ accountId: user.id }, { userId: user.userId });

          const newAccount = await this.accountRepoReport.findOne({
            userId: user.userId,
            asset: o.asset.toLowerCase(),
          });
          await this.orderRepoMaster.update({ userId: newAccount.userId }, { accountId: newAccount.id });
        }
      } else {
        break;
      }
    } while (true);
  }

  async updateUserEmailInOrder(): Promise<void> {
    let skip = 0;
    const take = GET_NUMBER_RECORD;
    do {
      const ordersUpdate = await this.orderRepoReport.find({
        where: { userEmail: IsNull() },
        skip,
        take,
      });
      skip += take;
      if (ordersUpdate.length > 0) {
        const task = [];
        for (const order of ordersUpdate) {
          const user = await this.userRepoReport.findOne({
            where: { id: order.userId },
          });
          if (!user) {
            continue;
          }
          task.push(
            this.orderRepoMaster.update(
              { id: order.id },
              {
                userEmail: user.email ? user.email : null,
                createdAt: () => `orders.createdAt`,
                updatedAt: () => `orders.updatedAt`,
              }
            )
          );
        }
        await Promise.all([...task]);
      } else {
        break;
      }
    } while (true);
  }

  public async checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder(data: {
    symbol: string;
    quantityOfMarketOrder: string;
    marketOrderSide: OrderSide;
    asset: string;
  }): Promise<CreateOrderDto[]> {
    console.log(`[DEBUG] Starting checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder`, {
      symbol: data.symbol,
      quantityOfMarketOrder: data.quantityOfMarketOrder,
      marketOrderSide: data.marketOrderSide,
      asset: data.asset
    });

    // Check to pre-creating orders
    let orderbookMEBinance: OrderbookMEBinance = await this.cacheManager.get<Orderbook>(
      OrderbookService.getOrderbookMEBinanceKey(data.symbol)
    );

    console.log(`[DEBUG] Retrieved orderbook from cache:`, {
      symbol: data.symbol,
      asksCount: orderbookMEBinance?.asks?.length || 0,
      bidsCount: orderbookMEBinance?.bids?.length || 0,
      firstAsk: orderbookMEBinance?.asks?.[0],
      firstBid: orderbookMEBinance?.bids?.[0]
    });

    let marketOrderSizeRemain: string = data.quantityOfMarketOrder;
    const orderNeedCreate: CreateOrderDto[] = [];
    let iterationCount = 0;
    
    console.log(`[DEBUG] Initial marketOrderSizeRemain: ${marketOrderSizeRemain}`);
    
    while (true) {
      iterationCount++;
      console.log(`[DEBUG] Iteration ${iterationCount} - marketOrderSizeRemain: ${marketOrderSizeRemain}`);
      
      const bestAskOrBid: string[] = data.marketOrderSide == OrderSide.BUY ? orderbookMEBinance?.asks[0] : orderbookMEBinance?.bids[0];
      
      if (!bestAskOrBid) {
        console.log(`[DEBUG] No bestAskOrBid found, breaking loop`);
        break;
      }
      
      const [bestAskOrBidPriceStr, bestAskOrBidSizeStr, bestAskOrBidMESizeStr, bestAskOrBidBSizeStr] = bestAskOrBid;
      const bestAskOrBidPrice = Number(bestAskOrBidPriceStr);
      const bestAskOrBidSize = Number(bestAskOrBidSizeStr);
      const bestAskOrBidMESize = Number(bestAskOrBidMESizeStr ?? 0) ?? 0;
      const bestAskOrBidBSize = Number(bestAskOrBidBSizeStr ?? 0) ?? 0;

      if (bestAskOrBidMESize === 0 && bestAskOrBidBSize === 0) {
        console.log(`[DEBUG] No bestAskOrBidMESize and bestAskOrBidBSize found, breaking loop`);
        break;
      }

      console.log(`[DEBUG] Current bestAskOrBid:`, {
        price: bestAskOrBidPrice,
        size: bestAskOrBidSize,
        meSize: bestAskOrBidMESize,
        bSize: bestAskOrBidBSize,
        marketOrderSide: data.marketOrderSide
      });

      if (bestAskOrBidMESize >= Number(marketOrderSizeRemain)) {
        console.log(`[DEBUG] bestAskOrBidMESize (${bestAskOrBidMESize}) >= marketOrderSizeRemain (${marketOrderSizeRemain}), breaking loop`);
        break;
      }
      
      if (bestAskOrBidMESize < Number(marketOrderSizeRemain) && Number(marketOrderSizeRemain) <= bestAskOrBidSize) {
        const neededCreateSize = Number(marketOrderSizeRemain) - bestAskOrBidMESize;
        console.log(`[DEBUG] Creating order for partial fill - neededCreateSize: ${neededCreateSize}`);
        
        const createdOrder: CreateOrderDto =
          data.marketOrderSide == OrderSide.BUY
            ? await this.orderbookService.createSellLimitOrderDtoForDefaultUser({
                symbol: data.symbol,
                quantity: neededCreateSize,
                price: bestAskOrBidPrice,
                asset: data.asset,
              })
            : await this.orderbookService.createBuyLimitOrderDtoForDefaultUser({
                symbol: data.symbol,
                quantity: neededCreateSize,
                price: bestAskOrBidPrice,
                asset: data.asset,
              });
        
        console.log(`[DEBUG] Created order for partial fill:`, createdOrder);
        orderNeedCreate.push(createdOrder);
        break;
      }
      
      if (bestAskOrBidSize < Number(marketOrderSizeRemain)) {
        const neededCreateSize = bestAskOrBidBSize;
        console.log(`[DEBUG] Creating order for full level - neededCreateSize: ${neededCreateSize}`);
        
        const createdOrder: CreateOrderDto =
          data.marketOrderSide == OrderSide.BUY
            ? await this.orderbookService.createSellLimitOrderDtoForDefaultUser({
                symbol: data.symbol,
                quantity: neededCreateSize,
                price: bestAskOrBidPrice,
                asset: data.asset,
              })
            : await this.orderbookService.createBuyLimitOrderDtoForDefaultUser({
                symbol: data.symbol,
                quantity: neededCreateSize,
                price: bestAskOrBidPrice,
                asset: data.asset,
              });
        
        console.log(`[DEBUG] Created order for full level:`, createdOrder);
        orderNeedCreate.push(createdOrder);
        
        const oldMarketOrderSizeRemain = marketOrderSizeRemain;
        marketOrderSizeRemain = String(Number(marketOrderSizeRemain) - bestAskOrBidSize);
        console.log(`[DEBUG] Updated marketOrderSizeRemain: ${oldMarketOrderSizeRemain} -> ${marketOrderSizeRemain}`);
        
        if (bestAskOrBidSize == 0) {
          console.log(`[DEBUG] bestAskOrBidSize is 0, breaking loop`);
          break;
        }

        if (data.marketOrderSide == OrderSide.BUY) {
          console.log(`[DEBUG] Removing first ask from orderbook`);
          orderbookMEBinance.asks.splice(0, 1);
        } else {
          console.log(`[DEBUG] Removing first bid from orderbook`);
          orderbookMEBinance.bids.splice(0, 1);
        }
        
        console.log(`[DEBUG] Orderbook after removal:`, {
          asksCount: orderbookMEBinance.asks.length,
          bidsCount: orderbookMEBinance.bids.length
        });
      }
    }

    console.log(`[DEBUG] Function completed. Total orders created: ${orderNeedCreate.length}`, {
      orders: orderNeedCreate.map(order => ({
        symbol: order.symbol,
        quantity: order.quantity,
        price: order.price,
        side: order.side
      }))
    });

    return orderNeedCreate;
  }

  public async countPendingOrder(): Promise<number> {
    return await this.orderRepoReport.count({ status: OrderStatus.PENDING });
  }

  public async getLiquidationOrderIds(startOrderId?: number): Promise<string[]> {
    const BATCH_SIZE = 1000;
    let startId = startOrderId != null? startOrderId: 0;
    let endId = BATCH_SIZE;
    let resultIds: string[] = [];

    // Get the max order id to know the range
    const maxOrder = await this.orderRepoReport
      .createQueryBuilder("order")
      .select("MAX(order.id)", "max")
      .getRawOne();
    const maxOrderId = Number(maxOrder.max);

    while (startId < maxOrderId) {
      // 1. Get all order ids in the current batch
      const ordersInBatch = await this.orderRepoReport
        .createQueryBuilder("order")
        .select(["order.id"])
        .where("order.id >= :startId AND order.id < :endId", { startId, endId })
        .getMany();
      const orderIdsInBatch = ordersInBatch.map((o) => o.id.toString());

      // 2. Get all order ids in the whole table in this range (simulate missing ids)
      // We'll assume order ids are sequential integers, so we can generate the full range
      const allIdsInRange: string[] = [];
      for (let i = startId; i < endId && i <= maxOrderId; i++) {
        allIdsInRange.push(i.toString());
      }
      // 3. Find not existing order ids in this batch
      const notExistOrderIds = allIdsInRange.filter((id) => !orderIdsInBatch.includes(id));

      if (notExistOrderIds.length === 0) {
        startId = endId;
        endId += BATCH_SIZE;
        continue;
      }

      // 4. Find all trades where buyOrderId or sellOrderId IN (notExistOrderIds)
      const tradesWithMissingOrders = await this.tradeRepoReport
        .createQueryBuilder("trade")
        .select(["trade.id", "trade.buyOrderId", "trade.sellOrderId"])
        .where("trade.buyOrderId IN (:...ids) OR trade.sellOrderId IN (:...ids)", { ids: notExistOrderIds })
        .getMany();

      const idsInTrades = new Set<string>();
      for (const trade of tradesWithMissingOrders) {
        if (trade.buyOrderId) idsInTrades.add(trade.buyOrderId.toString());
        if (trade.sellOrderId) idsInTrades.add(trade.sellOrderId.toString());
      }

      // 5. Find ids in notExistOrderIds that are not in idsInTrades
      const idsCanBeGot = notExistOrderIds.filter((id) => !idsInTrades.has(id));
      resultIds.push(...idsCanBeGot);

      if (resultIds.length >= BATCH_SIZE) {
        break;
      }

      startId = endId;
      endId += BATCH_SIZE;
    }

    // INSERT_YOUR_CODE
    resultIds.splice(0, 200);
    return resultIds;
  }
}

import { MAX_RESULT_COUNT } from "src/modules/trade/trade.const";
import { getQueryLimit } from "src/shares/pagination-util";
import { AdminPositionDto } from "./dto/admin-position.dto";
import { PRICE_CACHE_TTL } from "./position.const";
import { CACHE_MANAGER, forwardRef, HttpException, HttpStatus, Inject, Injectable } from "@nestjs/common";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { Cache } from "cache-manager";
import { PositionHistoryEntity } from "src/models/entities/position-history.entity";
import { PositionEntity } from "src/models/entities/position.entity";
import { PositionHistoryRepository } from "src/models/repositories/position-history.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { httpErrors } from "src/shares/exceptions";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { Not, Like, In, getManager, Connection } from "typeorm";
import { AccountService } from "../account/account.service";
import { UpdateMarginDto } from "./dto/update-margin.dto";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { ActionAdjustTpSl, CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { plainToClass } from "class-transformer";
import { ClosePositionDto } from "./dto/close-position.dto";
import { ClosePositionType } from "src/shares/enums/position.enum";
import { OrderRepository } from "src/models/repositories/order.repository";
import {
  OrderSide,
  OrderTimeInForce,
  OrderType,
  OrderStatus,
  MarginMode,
  TpSlType,
  OrderStopCondition,
  ContractType,
} from "src/shares/enums/order.enum";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { UserMarginModeRepository } from "src/models/repositories/user-margin-mode.repository";
import { BaseEngineService } from "../matching-engine/base-engine.service";
import { MIN_ORDER_ID, OrderEntity } from "src/models/entities/order.entity";
import { UpdatePositionDto } from "./dto/update-position.dto";
import { TakeProfitStopLossOrder } from "../order/tp-sl.type";
import { RemoveTpSlDto } from "./dto/RemoveTpSlDto";
import * as moment from "moment";
import { INDEX_PRICE_PREFIX, ORACLE_PRICE_PREFIX } from "../index/index.const";
import { InstrumentService } from "../instrument/instrument.service";
import { AccountRepository } from "src/models/repositories/account.repository";
import { TradingRulesRepository } from "src/models/repositories/trading-rules.repository";
import { MAX_PRICE, MIN_PRICE } from "../trading-rules/trading-rules.constants";

import { RedisService } from "nestjs-redis";
import { PREVIOUS_TIME } from "./position.const";

import { UserRepository } from "src/models/repositories/user.repository";
import { MarketDataRepository } from "src/models/repositories/market-data.repository";
import { FundingHistoryRepository } from "src/models/repositories/funding-history.repository";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { TradingRulesService } from "../trading-rules/trading-rule.service";
import { IndexService } from "../index/index.service";
import { LeverageMarginRepository } from "src/models/repositories/leverage-margin.repository";
import { LeverageMarginEntity } from "src/models/entities/leverage-margin.entity";
import { InstrumentEntity } from "src/models/entities/instrument.entity";
import { LIST_SYMBOL_COINM, LIST_SYMBOL_USDM } from "../transaction/transaction.const";
import { AccountEntity } from "src/models/entities/account.entity";
import { OrderbookService } from "../orderbook/orderbook.service";
import { Orderbook, OrderbookMEBinance } from "../orderbook/orderbook.const";
import { CreateOrderDto } from "../order/dto/create-order.dto";
import { OrderService } from "../order/order.service";
import { LeverageMarginService } from "../leverage-margin/leverage-margin.service";
import { TickerService } from "../ticker/ticker.service";
import { MarginHistoryEntity } from "src/models/entities/margin-history";
import { ExcelService } from "../export-excel/services/excel.service";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import { OPERATION_ID_DIVISOR } from "src/shares/number-formatter";
import { SaveUserMarketOrderUseCase } from "../order/usecase/save-user-market-order.usecase";
import { BOT_STOP_CREATE_ORDER } from "../order/order.const";
import { BotInMemoryService } from "../bot/bot.in-memory.service";

@Injectable()
export class PositionService extends BaseEngineService {
  constructor(
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepoReport: PositionRepository,
    @InjectRepository(PositionRepository, "master")
    public readonly positionRepoMaster: PositionRepository,
    @InjectRepository(PositionHistoryRepository, "master")
    public readonly positionHistoryRepository: PositionHistoryRepository,
    @InjectRepository(FundingHistoryRepository, "master")
    public readonly fundingHistoryRepository: FundingHistoryRepository,
    @InjectRepository(MarginHistoryRepository, "master")
    public readonly marginHistoryRepository: MarginHistoryRepository,
    private readonly accountService: AccountService,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    public readonly kafkaClient: KafkaClient,
    @InjectRepository(OrderRepository, "report")
    public readonly orderRepoReport: OrderRepository,
    @InjectRepository(OrderRepository, "master")
    public readonly orderRepoMaster: OrderRepository,
    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepoReport: InstrumentRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(TradingRulesRepository, "report")
    public readonly tradingRulesRepoReport: TradingRulesRepository,
    @InjectRepository(UserMarginModeRepository, "report")
    public readonly userMarginModeRepoReport: UserMarginModeRepository,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    @InjectRepository(LeverageMarginRepository, "report")
    public readonly leverageMarginRepoReport: LeverageMarginRepository,
    public readonly instrumentService: InstrumentService,
    private readonly redisService: RedisService,
    private readonly tradingRulesService: TradingRulesService,
    public readonly indexService: IndexService,

    @InjectRepository(MarketDataRepository, "report")
    private marketDataRepositoryReport: MarketDataRepository,

    private readonly leverageMarginService: LeverageMarginService,
    private readonly tickerService: TickerService,

    @InjectConnection("master") private connection: Connection,
    private readonly excelService: ExcelService,
    private readonly redisClient: RedisClient,
    private readonly saveUserMarketOrderUseCase: SaveUserMarketOrderUseCase,
    protected readonly botInMemoryService: BotInMemoryService,
    @Inject(forwardRef(() => OrderService))
    private readonly orderService: OrderService,
  ) {
    super();
  }

  async getAllPositionByUserId(userId: number, paging: PaginationDto, contractType: ContractType, symbol?: string) {
    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    // const limitInner = Math.min(paging.size * paging.page, MAX_RESULT_COUNT);

    const getPositionByTakeProfit = `
        SELECT pTp.*
      FROM positions as pTp
      LEFT JOIN orders as oTp on pTp.takeProfitOrderId = oTp.id
      WHERE pTp.userId = ${userId} and pTp.currentQty <> '0'  ${
      contractType == ContractType.ALL ? `` : `and pTp.contractType = '${contractType}'`
    }
      ${symbol ? `and pTp.symbol = '${symbol}'` : ``}
      ORDER BY pTp.updatedAt DESC
    `;

    const getPositionByStopLoss = `
SELECT pSl.*
        FROM positions as pSl
        LEFT JOIN orders as oSl on pSl.stopLossOrderId = oSl.id
        WHERE pSl.userId = ${userId} and pSl.currentQty <> '0' ${
      contractType == ContractType.ALL ? `` : `and pSl.contractType = '${contractType}'`
    }
        ${symbol ? `and pSl.symbol = '${symbol}'` : ``}
        ORDER BY pSl.updatedAt DESC
    `;

    const getAllQuery = `
      SELECT * FROM (
        (
          ${getPositionByTakeProfit} 
        )
      UNION 
        (
          ${getPositionByStopLoss}
      )) AS P
      ORDER BY P.lastOpenTime DESC
      LIMIT ${limit}
      OFFSET ${offset}
    `;

    const countQueryTakeProfit = `
    SELECT countTP.*
    FROM positions as countTP
    LEFT JOIN orders on  countTP.takeProfitOrderId = orders.id 
    WHERE countTP.userId = ${userId} and countTP.currentQty <> '0' ${
      contractType == ContractType.ALL ? `` : `and countTP.contractType = '${contractType}'`
    }
    ${symbol ? `and countTP.symbol = '${symbol}'` : ``}`;
    const countQueryStopLoss = `
    SELECT countSL.*
    FROM positions as countSL
    LEFT JOIN orders on  countSL.stopLossOrderId = orders.id
    WHERE countSL.userId = ${userId} and countSL.currentQty <> '0' ${
      contractType == ContractType.ALL ? `` : `and countSL.contractType = '${contractType}'`
    }
    ${symbol ? `and countSL.symbol = '${symbol}'` : ``}`;

    const queryCount = `
  SELECT COUNT(*) as count FROM (
    (
      ${countQueryTakeProfit} 
    )
  UNION 
    (
      ${countQueryStopLoss}
  )) AS totalItem
  `;
    const fills = await this.positionRepoReport.query(getAllQuery);

    const countResult = Number((await this.positionRepoReport.query(queryCount))[0].count);
    const totalItem = countResult;
    for (const position of fills) {
      position.positionMargin = new BigNumber(position.positionMargin).plus(position.tmpTotalFee || 0).toString();
    }
    return {
      data: fills,
      metadata: {
        totalPage: Math.ceil(totalItem / paging.size),
        totalItem: totalItem,
      },
    };
  }

  async getAllPositionByUserIdV2(
    userId: number,
    paging: PaginationDto,
    contractType: ContractType,
    symbol?: string
  ): Promise<ResponseDto<PositionEntity[]>> {
    // const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);

    // Get cached positions
    const keys: string[] = [];
    let cursor = '0';
    do {
      const result = await this.redisClient.getInstance().scan(cursor, 'MATCH', `${REDIS_COMMON_PREFIX.POSITIONS}:userId_${userId}:accountId_*`, 'COUNT', 1000);
      cursor = result[0];
      keys.push(...result[1]);
    } while (cursor !== '0');

    if (keys.length === 0) {
      this.checkMissCachedPositions({
        userId,
        redisPositions: []
      });
      return {
        data: [],
        metadata: {
          totalPage: 0,
          totalItem: 0,
        },
      };
    }
    
    const positions = await this.redisClient.getInstance().mget(keys);
    let redisPositions: PositionEntity[] = positions
      .filter(p => p && JSON.parse(p))
      .map(p => {
        const parsedP = JSON.parse(p) as PositionEntity;
        parsedP.id = Number(parsedP.id);
        return parsedP;
      });

    // Check cache sync 
    const redisPositionsToCheckCache = [...redisPositions];
    this.checkMissCachedPositions({
      userId,
      redisPositions: redisPositionsToCheckCache
    });

    redisPositions = redisPositions
      .filter(p => {
        if (new BigNumber(p.currentQty).eq(0) || new BigNumber(p.currentQty).abs().isLessThanOrEqualTo(0.00000001)) return false;
        if (contractType && contractType !== ContractType.ALL && p.contractType !== contractType) return false;
        if (symbol && p.symbol !== symbol) return false;
        p.id = Number(p.id);
        p.positionMargin = new BigNumber(p.positionMargin).plus(p.tmpTotalFee || 0).toString()
        return true;
      });
    
    const totalItems = redisPositions.length;
    redisPositions = redisPositions
      .sort((a, b) => new Date(b.lastOpenTime).getTime() - new Date(a.lastOpenTime).getTime());
      // .slice(offset, offset + limit);

    return {
      data: redisPositions,
      metadata: {
        totalPage: Math.ceil(totalItems / paging.size),
        totalItem: totalItems,
      },
    };
  }

  async checkMissCachedPositions(data: {
    userId: number,
    redisPositions: PositionEntity[]
  }): Promise<void> {
    try {
      // Get all positions from DB
      const dbPositions = await this.positionRepoReport
        .createQueryBuilder('position')
        .where('position.userId = :userId', { userId: data.userId })
        .getMany();
      dbPositions.forEach(p => {
        p.id = Number(p.id);
      });

      if (dbPositions.length === 0 && data.redisPositions.length === 0) return;

      // Check if miss cached
      if (dbPositions.length === data.redisPositions.length) return;

      // Cache db positions
      for (const dbPosition of dbPositions) {
        if (data.redisPositions.find(rP => rP.id === dbPosition.id)) continue;
        const redisKey = `${REDIS_COMMON_PREFIX.POSITIONS}:userId_${data.userId}:accountId_${dbPosition.accountId}:positionId_${dbPosition.id}`;
        this.redisClient.getInstance().set(redisKey, JSON.stringify(dbPosition), 'EX', 86400); // 1 day TTL
      }
    } catch (e) {
      console.error(e);
    }
  }

  async getAllPositionWithQuantity(userId: number, contractType: ContractType, symbol?: string) {
    // const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    // const limitInner = Math.min(paging.size * paging.page, MAX_RESULT_COUNT);

    const getPositionByTakeProfit = `
        SELECT pTp.*
      FROM positions as pTp
      LEFT JOIN orders as oTp on pTp.takeProfitOrderId = oTp.id
      WHERE pTp.userId = ${userId} ${contractType == ContractType.ALL ? `` : `and pTp.contractType = '${contractType}'`}
      ${symbol ? `and pTp.symbol = '${symbol}'` : ``}
      ORDER BY pTp.updatedAt DESC
    `;

    const getPositionByStopLoss = `
SELECT pSl.*
        FROM positions as pSl
        LEFT JOIN orders as oSl on pSl.stopLossOrderId = oSl.id
        WHERE pSl.userId = ${userId} ${contractType == ContractType.ALL ? `` : `and pSl.contractType = '${contractType}'`}
        ${symbol ? `and pSl.symbol = '${symbol}'` : ``}
        ORDER BY pSl.updatedAt DESC`;

    const getAllQuery = `
      SELECT * FROM (
        (
          ${getPositionByTakeProfit} 
        )
      UNION 
        (
          ${getPositionByStopLoss}
      )) AS P
      ORDER BY P.updatedAt DESC
    `;

    const fills = await this.positionRepoReport.query(getAllQuery);

    return {
      data: fills,
    };
  }

  async getAllPositionByAdmin(paging: PaginationDto, queries?: AdminPositionDto): Promise<ResponseDto<PositionEntity[]>> {
    const where: string[] = [];
    const whereParam: Record<string, any> = {};

    where.push("p.currentQty <> :currentQty");
    whereParam.currentQty = 0;

    if (queries.from) {
      const startTime = moment(parseInt(queries.from)).format("YYYY-MM-DD HH:mm:ss");
      where.push("p.updatedAt >= :startTime");
      whereParam.startTime = startTime;
    }
    if (queries.to) {
      const endTime = moment(parseInt(queries.to)).format("YYYY-MM-DD HH:mm:ss");
      where.push("p.updatedAt <= :endTime");
      whereParam.endTime = endTime;
    }

    if (queries.symbol) {
      where.push("p.symbol LIKE :symbol");
      whereParam.symbol = `%${queries.symbol}%`;
    }

    if (queries.contractType && queries.contractType !== ContractType.ALL) {
      where.push("p.contractType LIKE :contractType");
      whereParam.contractType = `%${queries.contractType}%`;
    }

    if (queries.search_key) {
      where.push("p.id LIKE :search_key");
      whereParam.search_key = `%${queries.search_key}%`;
    }

    if (queries.side) {
      if (queries.side === OrderSide.BUY) {
        where.push("p.currentQty > 0");
      }
      if (queries.side === OrderSide.SELL) {
        where.push("p.currentQty < 0");
      }
    }

    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    const query = this.positionRepoReport
      .createQueryBuilder("p")
      .select("p.*, u.email")
      // .innerJoin('accounts', 'ac', 'p.userId = ac.userId')
      .innerJoin("users", "u", "p.userId = u.id")
      .where(where.join(" AND "), whereParam)
      .orderBy("p.updatedAt", "DESC")
      .limit(limit)
      .offset(offset);

    const [positions, count] = await Promise.all([query.getRawMany(), query.getCount()]);

    const tickers = await this.tickerService.getTickers();

    // get multiplier
    const instrument = await this.instrumentRepoReport.find();
    const hashMapInstruments = instrument.reduce((acc, item) => {
      acc[item.symbol] = item;
      return acc;
    }, {});

    for (const position of positions) {
      const currentTicker = tickers?.find((ticker) => ticker.symbol === position.symbol);
      const size = Number(position?.currentQty);
      const markPrice = Number(currentTicker?.oraclePrice) ?? 0;
      const entryPrice = Number(position?.entryPrice);
      const side = size > 0 ? 1 : -1;
      const isCoinM = position?.contractType === ContractType.COIN_M;
      let unrealizedPNL = "0";

      if (isCoinM) {
        // Unrealized PNL = Size * Contract Multiplier  *(1/ Entry price - 1/ Mark Price) * Side
        const inverseEntry = new BigNumber(1).dividedBy(entryPrice);
        const inverseMark = new BigNumber(1).dividedBy(markPrice);
        const multiplier = hashMapInstruments[position.symbol].multiplier || 100;
        unrealizedPNL = new BigNumber(size)
          .abs()
          .times(multiplier)
          .times(new BigNumber(inverseEntry).minus(inverseMark))
          .times(side)
          .toString();
      } else {
        // Unrealized PNL = Size * (Mark price - Entry Price) * Side
        unrealizedPNL = new BigNumber(size).abs().times(new BigNumber(markPrice).minus(entryPrice)).times(side).toString();
      }
      position.pnl = unrealizedPNL;
      position.unrealizedPNL = unrealizedPNL;

      const allocatedMargin = this.getAllocatedMargin(position, markPrice, hashMapInstruments[position.symbol].multiplier, isCoinM);
      position.margin = allocatedMargin;
      const ROE = new BigNumber(unrealizedPNL).dividedBy(allocatedMargin).times(100).toNumber();
      position.percentPNL = ROE;

      position.side = +position.currentQty > 0 ? OrderSide.BUY : OrderSide.SELL;
      position.estimateUSDT = Math.abs(position.currentQty) * position.entryPrice;
      position.rootSymbol = position.symbol.replace(position.asset, "");
      position.avgPrice = position.entryPrice;
    }

    return {
      data: positions,
      metadata: {
        total: count,
        totalPage: Math.ceil(count / paging.size),
      },
    };
  }

  private getAllocatedMargin(position: any, markPrice, multiplier, isCoinM): string {
    const size = Number(position?.currentQty);
    const leverage = position?.leverage ? Number(position?.leverage).toFixed() : 0;
    let allocatedMargin = "0";

    if (isCoinM) {
      //  Allocated Margin for Cross position
      // = Size * Contract Multiplier / (Leverage * Mark price)

      // Allocated Margin for Isolated position
      // = Size * Contract Multiplier / (Leverage * Entry price) + Added Margin
      if (position?.isCross) {
        allocatedMargin = new BigNumber(size).abs().times(multiplier).dividedBy(new BigNumber(leverage).times(markPrice)).toString();
      } else {
        allocatedMargin = new BigNumber(position?.positionMargin || 0)
          .plus(position?.adjustMargin || 0)
          .plus(position?.tmpTotalFee || 0)
          .toString();
      }
    } else {
      // Allocated Margin for Cross position
      // = Size * Mark price / Leverage

      // Allocated Margin for Isolated position
      // = Size * Entry price / Leverage  + Added Margin
      if (position?.isCross) {
        allocatedMargin = new BigNumber(size).abs().times(markPrice).dividedBy(leverage).toString();
      } else {
        // allocatedMargin = new BigNumber(size)
        //   .abs()
        //   .times(entryPrice)
        //   .dividedBy(leverage)
        //   .plus(position?.adjustMargin || 0)
        //   .toString();

        allocatedMargin = new BigNumber(position?.positionMargin || 0)
          .plus(position?.adjustMargin || 0)
          .plus(position?.tmpTotalFee || 0)
          .toString();
      }
    }

    return allocatedMargin;
  }

  async getAllPositionHistoryByAdmin(paging: PaginationDto, queries?: AdminPositionDto): Promise<ResponseDto<PositionEntity[]>> {
    const where: string[] = [];
    const whereParam: Record<string, any> = {};

    if (queries.from) {
      const startTime = moment(parseInt(queries.from)).format("YYYY-MM-DD HH:mm:ss");      
      where.push("open_and_close.openPositionTime >= :startTime");
      where.push("open_and_close.closePositionTime >= :startTime");
      whereParam.startTime = startTime;
    }
    if (queries.to) {
      const endTime = moment(parseInt(queries.to)).format("YYYY-MM-DD HH:mm:ss");
      where.push("open_and_close.closePositionTime <= :endTime");
      where.push("open_and_close.openPositionTime <= :endTime");
      whereParam.endTime = endTime;
    }

    if (queries.symbol) {
      where.push("o.symbol LIKE :symbol");
      whereParam.symbol = `%${queries.symbol}%`;
    }

    if (queries.contractType && queries.contractType !== ContractType.ALL) {
      where.push("o.contractType LIKE :contractType");
      whereParam.contractType = `%${queries.contractType}%`;
    }

    if (queries.search_key) {
      where.push("open_and_close.closePositionId LIKE :search_key");
      whereParam.search_key = `%${queries.search_key}%`;
    }

    if (queries.side) {
      where.push(`o.side = :side`);
      whereParam.side = queries.side;
    }

    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);

    // GET CLOSE POSITION
    const closePositionHistoryQuery = this.positionHistoryRepository
      .createQueryBuilder("close")
      .select([
        "close.id AS closeId",
        "close.positionId AS closePositionId",
        "close.operationId AS closeOperationId",
        "close.createdAt AS closePositionTime",
      ])
      .where("close.currentQtyAfter = 0")
      .getQuery();

    // OPEN AND CLOSE POSITION
    const openAndCloseHistoryQuery = this.positionHistoryRepository
      .createQueryBuilder("open")
      .select(["open.id as openId", "open.createdAt AS openPositionTime"])
      .addSelect("close.*")
      .innerJoin(
        `(${closePositionHistoryQuery})`,
        "close",
        "open.currentQty = 0 AND open.positionId = close.closePositionId AND open.operationId < close.closeOperationId"
      )
      .groupBy("open.id")
      .getQuery();

    const positionHistoriesQuery = this.connection
      .createQueryBuilder()
      .select([
        "o.side AS side",
        "o.executedPrice AS exitPrice",
        "o.symbol AS symbol",
        "o.leverage AS leverage",
        "o.marginMode AS marginMode",
        "o.asset AS asset",
        "o.quantity AS quantity",

        "mh.entryPrice AS entryPrice",
        "mh.orderId AS orderId",

        "SUM(mh.balanceAfter - mh.balance) AS realizedPnl",
        "(mh.contractMargin - mh.balance) / mh.leverage AS margin",
      ])
      .addSelect("open_and_close.*")
      .from(`(${openAndCloseHistoryQuery})`, "open_and_close")
      .innerJoin(
        "margin_histories",
        "mh",
        "open_and_close.closeOperationId = mh.operationId AND mh.positionId = open_and_close.closePositionId"
      )
      .innerJoin("orders", "o", "mh.orderId = o.id")
      .where(where.join(" AND "), whereParam)
      .orderBy("mh.createdAt", "DESC")
      .groupBy("mh.orderId");

    const totalCountQuery = this.connection
      .createQueryBuilder()
      .select("COUNT(*)", "totalCount")
      .from(`(${positionHistoriesQuery.getQuery()})`, "subquery")
      .setParameters(whereParam);

    positionHistoriesQuery.limit(limit).offset(offset);

    const [positions, { totalCount }] = await Promise.all([positionHistoriesQuery.getRawMany(), totalCountQuery.getRawOne()]);

    const result = positions.map((position) => ({
      positionId: position.closePositionId,
      side: position.side,
      symbol: position.symbol,
      leverage: position.leverage,
      marginMode: position.marginMode,
      realizedPnl: position.realizedPnl,
      asset: position.asset,
      quantity: position.quantity,
      entryPrice: position.entryPrice,
      exitPrice: position.exitPrice,
      margin: new BigNumber(position.margin).abs().toString(),
      openPositionTime: position.openPositionTime,
      closePositionTime: position.closePositionTime,
    }));

    return {
      data: result as any,
      metadata: {
        total: Number(totalCount),
        totalPage: Math.ceil(Number(totalCount) / paging.size),
      },
    };
  }

  async calcGetAllPositionByAdmin(symbol: string) {}

  async getPositionById(positionId: number): Promise<PositionEntity> {
    const position = await this.positionRepoReport.findOne({ id: positionId });
    if (!position) {
      throw new HttpException("Position not found", HttpStatus.NOT_FOUND);
    }
    return position;
  }

  async findBatch(fromId: number, count: number): Promise<PositionEntity[]> {
    return await this.positionRepoMaster.findBatch(fromId, count);
  }

  async findHistoryBefore(date: Date): Promise<PositionHistoryEntity | undefined> {
    return await this.positionHistoryRepository.findHistoryBefore(date);
  }

  async findHistoryBatch(fromId: number, count: number): Promise<PositionHistoryEntity[]> {
    return await this.positionHistoryRepository.findBatch(fromId, count);
  }

  async getLastPositionId(): Promise<number> {
    return await this.positionRepoMaster.getLastId();
  }

  async getPositionByUserIdBySymbol(userId: number, symbol: string): Promise<PositionEntity> {
    const position = await this.positionRepoReport.find({
      where: {
        userId,
        symbol: symbol,
      },
    });
    if (position[0]) return position[0];
    throw new HttpException(httpErrors.POSITION_NOT_FOUND, HttpStatus.NOT_FOUND);
  }

  public async updateMargin(userId: number, updateMarginDto: UpdateMarginDto) {
    const position = await this.positionRepoReport.findOne({
      where: {
        userId,
        id: updateMarginDto.positionId,
        isCross: false,
        currentQty: Not("0"),
      },
    });

    if (!position) {
      throw new HttpException(httpErrors.POSITION_NOT_FOUND, HttpStatus.NOT_FOUND);
    }

    const account = await this.accountRepoReport.findOne({
      userId,
      asset: position.asset,
    });

    const usdtAvailableBalance = new BigNumber(account.balance);
    if (usdtAvailableBalance.lt(+updateMarginDto.assignedMarginValue)) {
      throw new HttpException(httpErrors.NOT_ENOUGH_BALANCE, HttpStatus.BAD_REQUEST);
    }

    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.ADJUST_MARGIN_POSITION,
      data: {
        userId,
        accountId: account.id,
        symbol: position.symbol,
        assignedMarginValue: updateMarginDto.assignedMarginValue,
      },
    });
    return true;
  }

  async closePosition(userId: number, body: ClosePositionDto, isTesting?: boolean): Promise<OrderEntity> {
    const { positionId, quantity, type, limitPrice } = body;
    const defaultMarginMode = MarginMode.CROSS;
    const defaultLeverage = "20";
    let position = await this.positionRepoReport.findOne({
      where: {
        id: positionId,
      },
    });

    if (!position) {
      throw new HttpException({ ...httpErrors.POSITION_NOT_FOUND, symbol: null }, HttpStatus.NOT_FOUND);
    }

    // Get position from cache
    const redisKey = `${REDIS_COMMON_PREFIX.POSITIONS}:userId_${userId}:accountId_${position.accountId}:positionId_${positionId}`;
    let cachedPosition: PositionEntity | null = null;
    const cachedPositionStr = await this.redisClient.getInstance().get(redisKey);
    if (cachedPositionStr) {
      try {
        cachedPosition = JSON.parse(cachedPositionStr);
      } catch (e) {
        cachedPosition = null;
      }
    }

    if (cachedPosition) {
      const dividedCachedPositionOperationId = cachedPosition.operationId
          ? Number(
              (
                BigInt(cachedPosition.operationId.toString()) %
                OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

      const dividedPositionOperationId = position.operationId
          ? Number(
              (
                BigInt(position.operationId.toString()) %
                OPERATION_ID_DIVISOR
              ).toString()
            )
          : null;

      if (dividedCachedPositionOperationId > dividedPositionOperationId) position = cachedPosition;  
    }

    if (+position.currentQty == 0)  {
      throw new HttpException({ ...httpErrors.POSITION_NOT_FOUND, symbol: position.symbol }, HttpStatus.NOT_FOUND);
    }

    if (new BigNumber(quantity).isLessThanOrEqualTo(0)) {
      throw new HttpException({ ...httpErrors.POSITION_INVALID_QUANTITY, symbol: position.symbol }, HttpStatus.BAD_REQUEST);
    }

    if (new BigNumber(quantity).isGreaterThan(new BigNumber(position.currentQty).abs())) {
      throw new HttpException({ ...httpErrors.POSITION_QUANTITY_NOT_ENOUGH, symbol: position.symbol }, HttpStatus.BAD_REQUEST);
    }

    if (type === ClosePositionType.LIMIT) {
      await this.validateMinMaxPrice(position, limitPrice);
    }

    const instrument = await this.instrumentRepoReport.findOne({
      where: {
        symbol: position.symbol,
      },
    });
    const [marginMode, account] = await Promise.all([
      this.userMarginModeRepoReport.findOne({
        where: {
          instrumentId: instrument.id,
          userId,
        },
      }),
      this.accountRepoReport.findOne({
        where: {
          userId,
          asset: position.asset,
        },
      }),
    ]);
    let closeOrder: OrderEntity;
    const botUserId = this.botInMemoryService.getBotUserIdFromSymbol(position.symbol);
    switch (type) {
      case ClosePositionType.MARKET:
        // Check to pre-creating orders
        // const orderNeedCreate: CreateOrderDto[] = await this.orderService.checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder(
        //   {
        //     symbol: position.symbol,
        //     asset: position.asset,
        //     quantityOfMarketOrder: quantity,
        //     marketOrderSide: Number(position.currentQty) > 0 ? OrderSide.SELL : OrderSide.BUY,
        //   }
        // );

        // // Create order
        // // Push order to kafka
        // // => orderbookME on cache will be updated
        // await this.orderService.createOrderForDefaultCreateOrderUser({
        //   createOrderDtos: orderNeedCreate,
        //   symbol: instrument.symbol,
        // });

        // Stop bot to creating new order
        await this.redisClient
          .getInstance()
          .set(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`, "true");

        // Create bot orders and push to kafka
        const botOrders = await this.saveUserMarketOrderUseCase.checkAndCreateBotOrdersForBinancePrice(
          {
            symbol: position.symbol,
            side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
            asset: position.asset,
            quantity: `${new BigNumber(quantity).toFixed()}`,
          } as CreateOrderDto,
          botUserId,
          position.userId
        );
        if (botOrders && botOrders.length > 0) {
          const savedBotOrders = await Promise.all(
            botOrders.map((o) => this.orderRepoMaster.save(o))
          );
          for (const savedBotOrder of savedBotOrders) {
            await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
              code: CommandCode.PLACE_ORDER,
              data: plainToClass(OrderEntity, savedBotOrder),
            });
          }
        }

        closeOrder = await this.orderRepoMaster.save({
          userId: position.userId,
          accountId: account.id,
          side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
          quantity: `${new BigNumber(quantity).toFixed()}`,
          type: OrderType.MARKET,
          symbol: position.symbol,
          timeInForce: OrderTimeInForce.IOC,
          status: OrderStatus.PENDING,
          asset: position.asset,
          marginMode: marginMode ? marginMode.marginMode : defaultMarginMode,
          leverage: marginMode ? marginMode.leverage : `${defaultLeverage}`,
          remaining: `${new BigNumber(quantity).toFixed()}`,
          isClosePositionOrder: true,
          isReduceOnly: true,
          contractType: instrument.contractType,
          userEmail: account.userEmail,
          originalCost: "0",
          originalOrderMargin: "0",
        });

        // // Check to pre-creating orders
        // console.log(`[createOrder] - Start checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
        // const orderNeedCreate: CreateOrderDto[] = await this.orderService.checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder({
        //   symbol: instrument.symbol,
        //   asset: closeOrder.asset,
        //   quantityOfMarketOrder: closeOrder.quantity,
        //   marketOrderSide: closeOrder.side,
        // });
        // console.log(`[createOrder] - End checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
        // // Create order
        // // Push order to kafka
        // // => orderbookME on cache will be updated
        // console.log(`[createOrder] - Start createOrderForDefaultCreateOrderUser.....`)
        // await this.orderService.createOrderForDefaultCreateOrderUser({
        //   createOrderDtos: orderNeedCreate,
        //   symbol: instrument.symbol,
        // });
        // console.log(`[createOrder] - End createOrderForDefaultCreateOrderUser.....`)
        break;
      case ClosePositionType.LIMIT:
        closeOrder = await this.orderRepoMaster.save({
          userId: position.userId,
          accountId: account.id,
          side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
          quantity: `${new BigNumber(quantity).toFixed()}`,
          price: limitPrice,
          type: OrderType.LIMIT,
          symbol: position.symbol,
          timeInForce: OrderTimeInForce.GTC,
          status: OrderStatus.PENDING,
          asset: position.asset,
          marginMode: marginMode ? marginMode.marginMode : defaultMarginMode,
          leverage: marginMode ? marginMode.leverage : `${defaultLeverage}`,
          remaining: `${new BigNumber(quantity).toFixed()}`,
          isClosePositionOrder: true,
          isReduceOnly: true,
          contractType: instrument.contractType,
          userEmail: account.userEmail,
          originalCost: "0",
          originalOrderMargin: "0",
        });
        break;
      default:
        break;
    }
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.PLACE_ORDER,
      data: plainToClass(OrderEntity, closeOrder),
    });

    // Allow bot to create order
    await this.redisClient
    .getInstance()
    .del(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`);

    return closeOrder;
  }

  async closeAllPosition(userId: number, contractType: ContractType): Promise<boolean> {
    const defaultMarginMode = MarginMode.CROSS;
    const defaultLeverage = "20";
    const [positions, orders] = await Promise.all([
      this.positionRepoReport.find({
        where: {
          userId,
          currentQty: Not("0"),
          contractType: contractType,
        },
      }),
      this.orderRepoReport.find({
        userId,
        contractType: contractType,
        status: In([OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED, OrderStatus.PENDING]),
      }),
    ]);
    if (positions.length === 0) {
      throw new HttpException(httpErrors.ACCOUNT_HAS_NO_POSITION, HttpStatus.NOT_FOUND);
    }
    if (orders.length > 0) {
      await Promise.all(
        orders.map((order) => {
          this.kafkaClient.send(KafkaTopics.matching_engine_input, {
            code: CommandCode.CANCEL_ORDER,
            data: order,
          });
        })
      );
    }
    await Promise.all(
      positions.map(async (position) => {
        const instrument = await this.instrumentRepoReport.findOne({
          where: {
            symbol: position.symbol,
          },
        });
        const marginMode = await this.userMarginModeRepoReport.findOne({
          where: {
            instrumentId: instrument.id,
            userId,
          },
        });
        const account = await this.accountRepoReport.findOne({
          where: {
            userId,
            asset: position.asset,
          },
        });

        // Stop bot to creating new order
        const botUserId = this.botInMemoryService.getBotUserIdFromSymbol(position.symbol);
        await this.redisClient
          .getInstance()
          .set(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`, "true");

        // Create bot orders and push to kafka
        const botOrders = await this.saveUserMarketOrderUseCase.checkAndCreateBotOrdersForBinancePrice(
          {
            symbol: position.symbol,
            side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
            asset: position.asset,
            quantity: `${new BigNumber(`${Math.abs(+position.currentQty)}`).toFixed()}`,
          } as CreateOrderDto,
          botUserId,
          position.userId
        );
        if (botOrders && botOrders.length > 0) {
          const savedBotOrders = await Promise.all(
            botOrders.map((o) => this.orderRepoMaster.save(o))
          );
          for (const savedBotOrder of savedBotOrders) {
            await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
              code: CommandCode.PLACE_ORDER,
              data: plainToClass(OrderEntity, savedBotOrder),
            });
          }
        }

        const cancelOrder = await this.orderRepoMaster.save({
          userId,
          accountId: account.id,
          side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
          quantity: `${Math.abs(+position.currentQty)}`,
          type: OrderType.MARKET,
          symbol: position.symbol,
          timeInForce: OrderTimeInForce.IOC,
          status: OrderStatus.PENDING,
          asset: position.asset,
          marginMode: marginMode ? marginMode.marginMode : defaultMarginMode,
          leverage: marginMode ? marginMode.leverage : `${defaultLeverage}`,
          remaining: `${Math.abs(+position.currentQty)}`,
          isClosePositionOrder: true,
          contractType: instrument.contractType,
          userEmail: account.userEmail,
          originalCost: "0",
          originalOrderMargin: "0",
        });

        // // Check to pre-creating orders
        // console.log(`[createOrder] - Start checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
        // const orderNeedCreate: CreateOrderDto[] = await this.orderService.checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder({
        //   symbol: instrument.symbol,
        //   asset: cancelOrder.asset,
        //   quantityOfMarketOrder: cancelOrder.quantity,
        //   marketOrderSide: cancelOrder.side,
        // });
        // console.log(`[createOrder] - End checkAndCreateOrderForDefaultCreateOrderUserBeforeMakeMarketOrder.....`)
        // // Create order
        // // Push order to kafka
        // // => orderbookME on cache will be updated
        // console.log(`[createOrder] - Start createOrderForDefaultCreateOrderUser.....`)
        // await this.orderService.createOrderForDefaultCreateOrderUser({
        //   createOrderDtos: orderNeedCreate,
        //   symbol: instrument.symbol,
        // });
        // console.log(`[createOrder] - End createOrderForDefaultCreateOrderUser.....`)

        await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
          code: CommandCode.PLACE_ORDER,
          data: plainToClass(OrderEntity, cancelOrder),
        });

        // Allow bot to create order
        await this.redisClient
          .getInstance()
          .del(`${BOT_STOP_CREATE_ORDER}:botUserId_${botUserId}`);
      })
    );
    return true;
  }

  private async validateUpdatePosition(updatePositionDto: UpdatePositionDto, position: PositionEntity): Promise<void> {
    const { takeProfit, stopLoss } = updatePositionDto;
    const checkPrice = await this.cacheManager.get<string>(`${INDEX_PRICE_PREFIX}${position.symbol}`);
    // const checkPrice = position.entryPrice;

    let maxPrice = await this.cacheManager.get(`${MAX_PRICE}_${position.symbol}`);
    let minPrice = await this.cacheManager.get(`${MIN_PRICE}_${position.symbol}`);
    if (!maxPrice) {
      const instrument = await this.instrumentRepoReport.findOne({
        symbol: position.symbol,
      });
      maxPrice = instrument.maxPrice;
      await this.cacheManager.set(`${MAX_PRICE}_${position.symbol}`, maxPrice, {
        ttl: PRICE_CACHE_TTL,
      });
    }
    if (!minPrice) {
      const tradingRule = await this.tradingRulesRepoReport.findOne({
        symbol: position.symbol,
      });
      minPrice = tradingRule.minPrice;
      await this.cacheManager.set(`${MIN_PRICE}_${position.symbol}`, minPrice, {
        ttl: PRICE_CACHE_TTL,
      });
    }

    if (takeProfit && (+takeProfit < Number(minPrice) || +takeProfit > Number(maxPrice))) {
      throw new HttpException(httpErrors.PARAMS_UPDATE_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    }
    if (stopLoss && (+stopLoss < Number(minPrice) || +stopLoss > Number(maxPrice))) {
      throw new HttpException(httpErrors.PARAMS_UPDATE_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    }
    // if (+position.currentQty > 0) {
    //   if (takeProfit && +takeProfit <= +checkPrice) {
    //     throw new HttpException(httpErrors.PARAMS_UPDATE_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    //   }
    //   if (stopLoss && +stopLoss >= +checkPrice) {
    //     throw new HttpException(httpErrors.PARAMS_UPDATE_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    //   }
    // } else {
    //   if (takeProfit && +takeProfit >= +checkPrice) {
    //     throw new HttpException(httpErrors.PARAMS_UPDATE_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    //   }
    //   if (stopLoss && +stopLoss <= +checkPrice) {
    //     throw new HttpException(httpErrors.PARAMS_UPDATE_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    //   }
    // }
  }

  async updatePosition(userId: number, updatePositionDto: UpdatePositionDto): Promise<void> {
    const { positionId, takeProfit, stopLoss, takeProfitTrigger, stopLossTrigger } = updatePositionDto;
    const whereCondition = {
      id: positionId,
      userId,
      currentQty: Not("0"),
    };

    const position = await this.positionRepoReport.findOne({
      where: {
        ...whereCondition,
      },
    });
    if (!position) {
      throw new HttpException(httpErrors.POSITION_NOT_FOUND, HttpStatus.BAD_REQUEST);
    }

    if (
      (!takeProfit && !stopLoss) ||
      (!takeProfitTrigger && !stopLossTrigger) ||
      (!takeProfit && takeProfitTrigger) ||
      (takeProfit && !takeProfitTrigger) ||
      (!stopLoss && stopLossTrigger) ||
      (stopLoss && !stopLossTrigger)
    ) {
      throw new HttpException(httpErrors.PARAMS_UPDATE_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    }
    await this.validateUpdatePosition(updatePositionDto, position);

    let stopLossOrder: Partial<OrderEntity>;
    let takeProfitOrder: Partial<OrderEntity>;
    const tpSlOrder: TakeProfitStopLossOrder = {
      stopLossOrderId: null,
      takeProfitOrderId: null,
    };
    const objectSend = {};
    const account = await this.accountRepoReport.findOne({
      where: {
        userId,
        asset: position.asset,
      },
    });

    if (position.stopLossOrderId !== null) {
      const stopLossOrderOld = await this.orderRepoReport.findOne(position.stopLossOrderId);
      if (stopLossOrderOld && (stopLossOrderOld.status === OrderStatus.CANCELED || stopLossOrderOld.status === OrderStatus.FILLED)) {
        position.stopLossOrderId = null;
      }
    }

    if (position.takeProfitOrderId !== null) {
      const takeProfitOrderOld = await this.orderRepoReport.findOne(position.takeProfitOrderId);
      if (takeProfitOrderOld && (takeProfitOrderOld.status === OrderStatus.CANCELED || takeProfitOrderOld.status === OrderStatus.FILLED)) {
        position.takeProfitOrderId = null;
      }
    }

    if (stopLoss && position.stopLossOrderId === null) {
      stopLossOrder = await this.orderRepoMaster.save({
        symbol: position.symbol,
        type: OrderType.MARKET,
        quantity: `${Math.abs(+position.currentQty)}`,
        remaining: `${Math.abs(+position.currentQty)}`,
        isReduceOnly: true,
        tpSLType: TpSlType.STOP_MARKET,
        tpSLPrice: stopLoss,
        status: OrderStatus.PENDING,
        timeInForce: OrderTimeInForce.IOC,
        userId: userId,
        accountId: account.id,
        side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
        asset: position.asset.toUpperCase(),
        leverage: position.leverage,
        marginMode: position.isCross ? MarginMode.CROSS : MarginMode.ISOLATE,
        price: null,
        trigger: stopLossTrigger,
        orderValue: "0",
        stopLoss: null,
        takeProfit: null,
        stopCondition: +position.currentQty > 0 ? OrderStopCondition.LT : OrderStopCondition.GT,
        isClosePositionOrder: true,
        isTpSlOrder: true,
        contractType: position.contractType,
        userEmail: account.userEmail,
        originalCost: "0",
        originalOrderMargin: "0",
      });
      tpSlOrder.stopLossOrderId = stopLossOrder.id;
      objectSend["slOrder"] = {
        ...stopLossOrder,
        createdAt: new Date(stopLossOrder.createdAt).getTime(),
        updatedAt: new Date(stopLossOrder.updatedAt).getTime(),
        action: ActionAdjustTpSl.PLACE,
      };
    }
    if (takeProfit && position.takeProfitOrderId === null) {
      takeProfitOrder = await this.orderRepoMaster.save({
        symbol: position.symbol,
        type: OrderType.MARKET,
        quantity: `${Math.abs(+position.currentQty)}`,
        remaining: `${Math.abs(+position.currentQty)}`,
        isReduceOnly: true,
        tpSLType: TpSlType.TAKE_PROFIT_MARKET,
        tpSLPrice: takeProfit,
        status: OrderStatus.PENDING,
        timeInForce: OrderTimeInForce.IOC,
        userId: userId,
        accountId: account.id,
        side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
        asset: position.asset.toUpperCase(),
        leverage: position.leverage,
        marginMode: position.isCross ? MarginMode.CROSS : MarginMode.ISOLATE,
        price: null,
        trigger: takeProfitTrigger,
        orderValue: "0",
        stopLoss: null,
        takeProfit: null,
        stopCondition: +position.currentQty > 0 ? OrderStopCondition.GT : OrderStopCondition.LT,
        isClosePositionOrder: true,
        contractType: position.contractType,
        userEmail: account.userEmail,
        originalCost: "0",
        originalOrderMargin: "0",
      });
      tpSlOrder.takeProfitOrderId = takeProfitOrder.id;
      objectSend["tpOrder"] = {
        ...takeProfitOrder,
        createdAt: new Date(takeProfitOrder.createdAt).getTime(),
        updatedAt: new Date(takeProfitOrder.updatedAt).getTime(),
        action: ActionAdjustTpSl.PLACE,
      };
    }
    if (stopLossOrder) {
      objectSend["slOrder"].linkedOrderId = tpSlOrder.takeProfitOrderId ? tpSlOrder.takeProfitOrderId : null;
    }
    if (takeProfitOrder) {
      objectSend["tpOrder"].linkedOrderId = tpSlOrder.stopLossOrderId ? tpSlOrder.stopLossOrderId : null;
    }
    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.ADJUST_TP_SL,
      data: {
        ...objectSend,
        userId,
        symbol: position.symbol,
        accountId: account.id,
      },
    });
  }

  async removeTpSlPosition(userId: number, removeTpSlDto: RemoveTpSlDto): Promise<void> {
    const { positionId, takeProfitOrderId, stopLossOrderId } = removeTpSlDto;
    if ((!takeProfitOrderId && !stopLossOrderId) || (takeProfitOrderId && stopLossOrderId)) {
      throw new HttpException(httpErrors.PARAMS_REMOVE_TP_SL_POSITION_NOT_VALID, HttpStatus.BAD_REQUEST);
    }
    const position = await this.positionRepoReport.findOne({
      where: {
        id: +positionId,
        userId,
        currentQty: Not("0"),
      },
    });
    if (!position) {
      throw new HttpException(httpErrors.POSITION_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
    let order: OrderEntity;
    const objectSend = {};

    if (takeProfitOrderId) {
      order = await this.orderRepoReport.findOne(+takeProfitOrderId);
      objectSend["tpOrder"] = {
        ...order,
        createdAt: new Date(order.createdAt).getTime(),
        updatedAt: new Date(order.updatedAt).getTime(),
        action: ActionAdjustTpSl.CANCEL,
      };
    } else {
      order = await this.orderRepoReport.findOne(+stopLossOrderId);
      objectSend["slOrder"] = {
        ...order,
        createdAt: new Date(order.createdAt).getTime(),
        updatedAt: new Date(order.updatedAt).getTime(),
        action: ActionAdjustTpSl.CANCEL,
      };
    }

    if (!order) {
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }

    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.ADJUST_TP_SL,
      data: {
        ...objectSend,
        userId,
        symbol: position.symbol,
        accountId: position.accountId,
      },
    });
  }

  async getTpSlOrderPosition(userId: number, positionId: number): Promise<OrderEntity[]> {
    const position = await this.positionRepoReport.findOne(positionId);
    if (!position) {
      throw new HttpException(httpErrors.POSITION_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
    const orders = await this.orderRepoReport.find({
      where: [
        {
          userId,
          id: position.takeProfitOrderId,
        },
        {
          userId,
          id: position.stopLossOrderId,
        },
      ],
    });
    return orders;
  }

  async calPositionMarginForAcc(
    accountId: number,
    asset: string
  ): Promise<{
    positionMargin: string;
    unrealizedPNL: string;
    positionMarginCross: string;
    positionMarginIsIsolate: string;
  }> {
    const instruments = await this.instrumentService.getAllSymbolInstrument();
    if (!instruments.length) {
      return {
        positionMargin: "0",
        unrealizedPNL: "0",
        positionMarginCross: "0",
        positionMarginIsIsolate: "0",
      };
    }

    const positionCross = await this.CalPositionMarginIsCross(accountId, asset);
    const positionMarginCross = positionCross.margin;
    const unrealizedPNL = positionCross.pnl;

    const positionMarginIsIsolate = await this.calPositionMarginIsIsolate(instruments, accountId, asset);

    return {
      positionMargin: new BigNumber(positionMarginIsIsolate).plus(positionMarginCross).toString(),
      unrealizedPNL,
      positionMarginCross,
      positionMarginIsIsolate,
    };
  }

  /**
   * Cached version of calPositionMarginForAcc
   * TTL: 3 seconds (short TTL due to frequently changing position data)
   */
  async calPositionMarginForAccCached(
    accountId: number,
    asset: string
  ): Promise<{
    positionMargin: string;
    unrealizedPNL: string;
    positionMarginCross: string;
    positionMarginIsIsolate: string;
  }> {
    const cacheKey = `positionMargin:${accountId}:${asset}`;

    const cached = await this.cacheManager.get<{
      positionMargin: string;
      unrealizedPNL: string;
      positionMarginCross: string;
      positionMarginIsIsolate: string;
    }>(cacheKey);

    if (cached) {
      return cached;
    }

    const result = await this.calPositionMarginForAcc(accountId, asset);
    await this.cacheManager.set(cacheKey, result, 3); // 3 seconds TTL
    return result;
  }

  async calPositionMarginIsIsolate(symbols: any, accountId: number, asset: string) {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore
    const { margin } = await this.positionRepoMaster
      .createQueryBuilder("positions")
      .select("SUM(abs(positions.currentQty) * positions.entryPrice / positions.leverage + positions.tmpTotalFee) as margin")
      .where({
        isCross: false,
      })
      .andWhere("positions.symbol IN (:symbols)", { symbols })
      .andWhere({ accountId, asset })
      .getRawOne();
    return margin ? margin : 0;
  }

  async CalPositionMarginIsCross(accountId: number, asset: string) {
    const listPositions = await this.positionRepoReport
      .createQueryBuilder("p")
      .select(["p.currentQty as currentQty", "p.leverage as leverage", "p.entryPrice as entryPrice, p.symbol"])
      .where({
        isCross: true,
        accountId,
        asset,
      })
      .getRawMany();

    const markPrices = await Promise.all(listPositions.map((item) => this.cacheManager.get<string>(`${INDEX_PRICE_PREFIX}${item.symbol}`)));

    let margin = "0";
    let pnl = "0";

    if (!listPositions.length) {
      return { margin, pnl };
    }

    for (let i = 0; i < listPositions.length; i++) {
      const item = listPositions[i];
      const markPrice = markPrices[i];
      if (!markPrice) continue;

      const margin1 = new BigNumber(item.currentQty).abs().times(markPrice).div(item.leverage).toString();
      const curPnl = new BigNumber(item.currentQty).abs().times(new BigNumber(markPrice).minus(item.entryPrice));
      let pnl1 = curPnl.toString();

      if (new BigNumber(item.currentQty).lt(0)) {
        pnl1 = curPnl.negated().toString();
      }

      margin = new BigNumber(margin).plus(margin1).toString();
      pnl = new BigNumber(pnl).plus(pnl1).toString();
    }

    return { margin, pnl };
  }

  async updatePositions(): Promise<void> {
    const data = await this.positionRepoReport.find();
    if (data) {
      for (const item of data) {
        item.userId = item.accountId;
        const account = await this.accountRepoReport.findOne({
          where: {
            asset: item.asset.toUpperCase(),
            userId: item.userId,
          },
        });
        if (account) {
          item.accountId = account.id;
        } else {
          item.accountId = null;
        }
        await this.positionRepoMaster.save(item);
      }
    }
  }

  public async calculateIndexPriceAverage(symbol: string) {
    const instrument = await this.instrumentService.findBySymbol(symbol);
    const newSymbol = symbol.replace("USDM", "USDT");
    const now = new Date().getTime();
    const previousTime = now - PREVIOUS_TIME;
    const startTime = moment(previousTime).format("YYYY-MM-DD HH:mm:ss");
    const endTime = moment(now).format("YYYY-MM-DD HH:mm:ss");
    const history = await this.marketDataRepositoryReport
      .createQueryBuilder("marketData")
      .select("marketData.index")
      .where("createdAt BETWEEN :startTime and :endTime ", {
        startTime,
        endTime,
      })
      .andWhere(`symbol = :newSymbol`, { newSymbol })
      .getMany();
    const sumIndexPrice = history.reduce((acc, curr) => acc + parseFloat(curr.index), 0);
    const averageIndexPrice = (sumIndexPrice / history.length).toFixed(Number(instrument.maxFiguresForPrice));
    return { averageIndexPrice, history };
  }

  private async validateMinMaxPrice(position, limitPrice) {
    const [tradingRules, instrument, markPrice] = await Promise.all([
      this.tradingRulesService.getTradingRuleByInstrumentId(position.symbol) as any,
      this.instrumentRepoReport.findOne({ where: { symbol: position.symbol } }),
      this.redisClient.getInstance().get(`${ORACLE_PRICE_PREFIX}${position.symbol}`),
    ]);
    let price: BigNumber;
    let minPrice = new BigNumber(tradingRules?.minPrice);
    let maxPrice = new BigNumber(instrument?.maxPrice);
    if (+position.currentQty > 0) {
      price = new BigNumber(markPrice).times(new BigNumber(1).minus(new BigNumber(tradingRules?.floorRatio).dividedBy(100)));
      minPrice = BigNumber.maximum(new BigNumber(tradingRules?.minPrice), price);
      if (new BigNumber(limitPrice).isLessThan(minPrice))
        throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      // validate max Price:
      if (new BigNumber(limitPrice).isGreaterThan(instrument.maxPrice)) {
        throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      }
    } else {
      price = new BigNumber(markPrice).times(new BigNumber(1).plus(new BigNumber(tradingRules?.limitOrderPrice).dividedBy(100)));
      maxPrice = BigNumber.minimum((new BigNumber(instrument?.maxPrice), price));
      if (new BigNumber(limitPrice).isLessThan(minPrice))
        throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
      if (new BigNumber(limitPrice).isGreaterThan(maxPrice))
        throw new HttpException(httpErrors.ORDER_PRICE_VALIDATION_FAIL, HttpStatus.BAD_REQUEST);
    }
  }

  async closeAllPositionCommand(symbol?: string): Promise<void> {
    const defaultMarginMode = MarginMode.CROSS;
    const defaultLeverage = "20";
    let wherePosition = {};
    let whereOrder = {};
    if (symbol) {
      wherePosition = {
        symbol: symbol.toUpperCase(),
      };
      whereOrder = {
        symbol: symbol.toUpperCase(),
      };
    }
    const [positions, orders] = await Promise.all([
      this.positionRepoReport.find({
        where: {
          currentQty: Not("0"),
          ...wherePosition,
        },
      }),
      this.orderRepoReport.find({
        status: In([OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED, OrderStatus.PENDING]),
        ...whereOrder,
      }),
    ]);

    if (orders.length > 0) {
      await Promise.all(
        orders.map((order) => {
          this.kafkaClient.send(KafkaTopics.matching_engine_input, {
            code: CommandCode.CANCEL_ORDER,
            data: order,
          });
        })
      );
    }

    if (positions.length === 0) {
      console.log("no postions");
      return;
    }

    const chunkSize = 100;
    let offset = 0;

    const positionSymbols = positions.map((position) => position.symbol);
    const symbolMap = {};
    for (const symbol of positionSymbols) {
      symbolMap[symbol] = await this.calculateIndexPriceAverage(symbol);
    }

    while (offset < positions.length) {
      const chunk = positions.slice(offset, offset + chunkSize);

      await Promise.all(
        chunk.map(async (position) => {
          const instrument = await this.instrumentRepoReport.findOne({
            where: {
              symbol: position.symbol,
            },
          });
          const marginMode = await this.userMarginModeRepoReport.findOne({
            where: {
              instrumentId: instrument.id,
              userId: position.userId,
            },
          });
          const account = await this.accountRepoReport.findOne({
            where: {
              userId: position.userId,
              asset: position.asset,
            },
          });
          if (!account) {
            return;
          }
          const { averageIndexPrice } = symbolMap[position.symbol];
          const cancelOrder = await this.orderRepoMaster.save({
            userId: position.userId,
            accountId: account.id,
            side: +position.currentQty > 0 ? OrderSide.SELL : OrderSide.BUY,
            quantity: `${Math.abs(+position.currentQty)}`,
            type: OrderType.LIMIT,

            symbol: position.symbol,
            timeInForce: OrderTimeInForce.GTC,
            status: OrderStatus.PENDING,
            asset: position.asset,
            marginMode: marginMode ? marginMode.marginMode : defaultMarginMode,
            leverage: marginMode ? marginMode.leverage : `${defaultLeverage}`,
            remaining: `${Math.abs(+position.currentQty)}`,
            isClosePositionOrder: true,
            contractType: instrument.contractType,
            userEmail: account.userEmail,
            price: averageIndexPrice,
          });
          await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
            code: CommandCode.PLACE_ORDER,
            data: plainToClass(OrderEntity, cancelOrder),
          });
        })
      );

      offset += chunk.length;
    }
  }

  async updateIdPositionCommand(): Promise<void> {
    const startPositionId = MIN_ORDER_ID;
    const positions = await this.positionRepoReport.find();
    let offset = 0;
    for (const position of positions) {
      const newPositionId = +startPositionId + +offset;
      await this.positionRepoMaster.update({ id: position.id }, { id: newPositionId, updatedAt: position.updatedAt });
      await this.positionHistoryRepository.update(
        { positionId: `${position.id}` },
        {
          positionId: `${newPositionId}`,
          updatedAt: () => "position_histories.updatedAt",
        }
      );
      await this.marginHistoryRepository.update(
        { positionId: `${position.id}` },
        {
          positionId: `${newPositionId}`,
          updatedAt: () => "margin_histories.updatedAt",
        }
      );
      await this.fundingHistoryRepository.update(
        { positionId: `${position.id}` },
        {
          positionId: `${newPositionId}`,
          updatedAt: () => "funding_histories.updatedAt",
        }
      );
      offset++;
    }
  }

  async getInforPositions(userId: number, symbol?: string) {
    const listSymbol = [...LIST_SYMBOL_COINM, ...LIST_SYMBOL_USDM];

    const response = [];
    if (symbol) {
      if (!listSymbol.includes(symbol)) {
        throw new HttpException(httpErrors.SYMBOL_DOES_NOT_EXIST, HttpStatus.NOT_FOUND);
      }
      const result = await this.getInforAPosition(symbol, userId);
      if (Object.keys(result).length === 0) {
        return response;
      }
      response.push(result);
    } else {
      for (const symbol of listSymbol) {
        const result = await this.getInforAPosition(symbol, userId);
        if (Object.keys(result).length !== 0) {
          response.push(result);
        }
      }
    }
    return response;
  }

  private getLeverageMargin(leverageMargin: LeverageMarginEntity[], checkValue: string): LeverageMarginEntity {
    let selected = null;
    for (const item of leverageMargin) {
      const bigNumberCheckValue = new BigNumber(checkValue);
      if (new BigNumber(item.min).isLessThanOrEqualTo(checkValue) && bigNumberCheckValue.isLessThanOrEqualTo(new BigNumber(item.max))) {
        selected = item;
      }
    }
    if (selected === null) {
      selected = leverageMargin[leverageMargin.length - 1];
    }
    return selected;
  }

  private calMaintenanceMargin(
    leverageMargin: LeverageMarginEntity[],
    checkTier: string,
    position: PositionEntity,
    oraclePrice: string,
    contractType: string,
    instrument?: InstrumentEntity
  ): string {
    if (contractType === ContractType.USD_M) {
      const selectedLeverageMargin = this.getLeverageMargin(leverageMargin, checkTier);
      const maintenanceMargin = new BigNumber(position.currentQty)
        .abs()
        .times(new BigNumber(oraclePrice))
        .times(new BigNumber(selectedLeverageMargin.maintenanceMarginRate / 100))
        .minus(selectedLeverageMargin.maintenanceAmount)
        .toString();
      return maintenanceMargin;
    } else {
      const selectedLeverageMargin = this.getLeverageMargin(leverageMargin, checkTier);

      const maintenanceMargin = new BigNumber(position.currentQty)
        .abs()
        .times(new BigNumber(instrument.multiplier).div(new BigNumber(oraclePrice)))
        .times(selectedLeverageMargin.maintenanceMarginRate / 100)
        .minus(selectedLeverageMargin.maintenanceAmount)
        .toString();
      return maintenanceMargin;
    }
  }

  private async calMarginBalanceForCrossUSDM(userId: number, asset: string, oraclePrice: string, account: AccountEntity): Promise<string> {
    const positions = await this.positionRepoReport.find({
      where: { userId, asset, currentQty: Not("0") },
    });
    let totalAllocatedMargin = "0";
    let totalUnrealizedPnl = "0";
    for (const position of positions) {
      const sideValue = +position.currentQty > 0 ? 1 : -1;
      const itemOraclePrice = await this.indexService.getOraclePrices([position.symbol]);

      if (position.isCross) {
        const unrealizedPNL = new BigNumber(
          Math.abs(+position.currentQty) * (+itemOraclePrice[0] - +position.entryPrice) * sideValue
        ).toString();
        totalUnrealizedPnl = new BigNumber(totalUnrealizedPnl).plus(new BigNumber(unrealizedPNL)).toString();
      } else {
        const allocatedMargin = +position.positionMargin + +position.adjustMargin;
        totalAllocatedMargin = new BigNumber(totalAllocatedMargin).plus(new BigNumber(allocatedMargin)).toString();
      }
    }
    const marginBalance = new BigNumber(account.balance).plus(new BigNumber(totalUnrealizedPnl)).minus(new BigNumber(totalAllocatedMargin));
    return marginBalance.toString();
  }

  private async calMarginBalanceForCrossCOINM(
    userId: number,
    asset: string,
    oraclePrice: string,
    instrument: InstrumentEntity,
    account: AccountEntity
  ): Promise<string> {
    const positions = await this.positionRepoReport.find({
      where: { userId, asset, currentQty: Not("0") },
    });
    let totalAllocatedMargin = "0";
    let totalUnrealizedPnl = "0";
    for (const position of positions) {
      const sideValue = +position.currentQty > 0 ? 1 : -1;
      if (position.isCross) {
        const unrealizedPNL = new BigNumber(
          Math.abs(+position.currentQty) * +instrument.multiplier * (1 / +position.entryPrice - 1 / +oraclePrice) * sideValue
        ).toString();
        totalUnrealizedPnl = new BigNumber(totalUnrealizedPnl).plus(new BigNumber(unrealizedPNL)).toString();
      } else {
        const allocatedMargin = +position.positionMargin + +position.adjustMargin;
        totalAllocatedMargin = new BigNumber(totalAllocatedMargin).plus(new BigNumber(allocatedMargin)).toString();
      }
    }
    const marginBalance = new BigNumber(account.balance).plus(new BigNumber(totalUnrealizedPnl)).minus(new BigNumber(totalAllocatedMargin));
    return marginBalance.toString();
  }
  private calMarginBalanceForIso(allocatedMargin: string, unrealizedPNL: string): string {
    return new BigNumber(allocatedMargin).plus(new BigNumber(unrealizedPNL)).toString();
  }

  private async getInforAPosition(symbol: string, userId: number) {
    const result = {};
    if (symbol) {
      const [position, oraclePrice, indexPrice, instrument, leverageMargin] = await Promise.all([
        this.positionRepoReport.findOne({
          where: { symbol, userId, currentQty: Not("0") },
        }),
        this.indexService.getOraclePrices([symbol]),
        this.indexService.getIndexPrices([symbol]),
        this.instrumentRepoReport.findOne({ where: { symbol } }),
        this.leverageMarginRepoReport.find({
          where: {
            symbol,
          },
        }),
      ]);

      if (position) {
        result[`${symbol}`] = { ...position };
        result[`${symbol}`][`averageOpeningPrice`] = position.entryPrice;
        // result[`${symbol}`][`averageClosingPrice`] = position.;
        result[`${symbol}`][`marginType`] = position.isCross ? "CROSS" : "ISOLATED";
        result[`${symbol}`][`indexPrice`] = indexPrice[0];
        result[`${symbol}`][`markPrice`] = oraclePrice[0];
        result[`${symbol}`]["liquidationPrice"] = position.liquidationPrice;
        result[`${symbol}`]["totalPosition"] = position.currentQty;
        result[`${symbol}`]["averageClosingPrice"] = position.avgClosePrice;
        result[`${symbol}`]["closingPosition"] = position.closeSize;

        // const maintenanceMargin =
        const checkTier =
          position.contractType === ContractType.COIN_M
            ? oraclePrice[0]
              ? new BigNumber(position.currentQty).abs().times(instrument.multiplier).div(oraclePrice[0]).toString()
              : "0"
            : new BigNumber(position.currentQty).abs().times(oraclePrice[0]).toString();
        let allocatedMargin = "0";
        const sideValue = +position.currentQty > 0 ? 1 : -1;
        const account = await this.accountRepoReport.findOne({
          where: { asset: position.asset, userId },
        });
        switch (position.contractType) {
          case ContractType.COIN_M:
            // calculate position margin
            if (position.isCross) {
              // cal liquidation price
              const selectedLeverageMargin = this.getLeverageMargin(leverageMargin, checkTier);
              const numerator = new BigNumber(
                new BigNumber(position.currentQty)
                  .abs()
                  .times(selectedLeverageMargin.maintenanceMarginRate / 100)
                  .plus(new BigNumber(new BigNumber(sideValue)).times(new BigNumber(position.currentQty).abs()))
              ).toString();
              const denominator = new BigNumber(
                new BigNumber(+account.balance + +selectedLeverageMargin.maintenanceAmount).div(new BigNumber(instrument.multiplier))
              )
                .plus(new BigNumber((sideValue * Math.abs(+position.currentQty)) / +position.entryPrice))
                .toString();
              result[`${symbol}`]["liquidationPrice"] = new BigNumber(numerator).div(new BigNumber(denominator)).toString();
              console.log("check liquidtion coin m: ", {
                position,
                numerator,
                denominator,
                selectedLeverageMargin,
                instrument,
              });
              // End cal Liquidation
              const allocatedMargin = new BigNumber(position.currentQty)
                .abs()
                .times(instrument.multiplier)
                .div(new BigNumber(position.leverage).times(new BigNumber(oraclePrice[0])))
                .toString();
              result[`${symbol}`]["positionMargin"] = new BigNumber(allocatedMargin).toString();
            } else {
              allocatedMargin = new BigNumber(position.positionMargin).plus(new BigNumber(position.adjustMargin)).toString();
              result[`${symbol}`]["positionMargin"] = new BigNumber(allocatedMargin).toString();
            }
            // console.log({ allocatedMargin, position, indexPrice, oraclePrice, result });

            result[`${symbol}`]["unrealizedPNL"] = new BigNumber(
              Math.abs(+position.currentQty) * +instrument.multiplier * (1 / +position.entryPrice - 1 / +oraclePrice[0]) * sideValue
            ).toString();
            // result[`${symbol}`]['unrealizedROE'] = new BigNumber(
            //   (result[`${symbol}`]['unrealizedPNL'] / allocatedMargin) * 100,
            // ).toString();
            const maintenanceMarginCOINM = this.calMaintenanceMargin(
              leverageMargin,
              checkTier,
              position,
              oraclePrice[0],
              ContractType.COIN_M,
              instrument
            );
            const marginBalanceCOINM = position.isCross
              ? await this.calMarginBalanceForCrossCOINM(userId, position.asset, oraclePrice[0], instrument, account)
              : this.calMarginBalanceForIso(allocatedMargin, result[`${symbol}`]["unrealizedPNL"]);
            // cal margin rate
            result[`${symbol}`]["marginRate"] = new BigNumber(maintenanceMarginCOINM)
              .div(new BigNumber(marginBalanceCOINM))
              .times(100)
              .toString();
            break;
          case ContractType.USD_M:
            //calculate position margin
            if (position.isCross) {
              allocatedMargin = new BigNumber(position.currentQty)
                .abs()
                .times(new BigNumber(oraclePrice[0]))
                .div(new BigNumber(position.leverage))
                .toString();
              result[`${symbol}`]["positionMargin"] = new BigNumber(allocatedMargin).toString();
              const assetPosition = await this.positionRepoReport.find({
                where: {
                  asset: position.asset,
                  userId: userId,
                  currentQty: Not("0"),
                },
              });
              let Ipm = "0";
              let Tmm = "0";
              let Upnl = "0";
              const selectedLeverageMargin = this.getLeverageMargin(leverageMargin, checkTier);

              for (const itemPosition of assetPosition) {
                if (!itemPosition.isCross) {
                  Ipm = new BigNumber(Ipm)
                    .plus(new BigNumber(itemPosition.positionMargin))
                    .plus(new BigNumber(itemPosition.adjustMargin))
                    .toString();
                }
                if (itemPosition.isCross && itemPosition.symbol !== position.symbol) {
                  const [oraclePriceItem, itemLeverageMarginArr] = await Promise.all([
                    this.indexService.getOraclePrices([itemPosition.symbol]),
                    this.leverageMarginRepoReport.find({
                      where: { symbol: itemPosition.symbol },
                    }),
                  ]);
                  const itemSideValue = +itemPosition.currentQty > 0 ? 1 : -1;
                  const notionalValue = new BigNumber(itemPosition.currentQty).abs().times(oraclePriceItem[0]).toString();
                  const itemLeverageMargin = this.getLeverageMargin(itemLeverageMarginArr, notionalValue);
                  Tmm = new BigNumber(Tmm)
                    .plus(
                      new BigNumber(
                        Math.abs(+itemPosition.currentQty) * +oraclePriceItem[0] * (itemLeverageMargin.maintenanceMarginRate / 100)
                      ).minus(new BigNumber(itemLeverageMargin.maintenanceAmount))
                    )
                    .toString();
                  Upnl = new BigNumber(Upnl)
                    .plus(
                      new BigNumber(itemPosition.currentQty)
                        .abs()
                        .times(new BigNumber(oraclePriceItem[0]).minus(new BigNumber(itemPosition.entryPrice)))
                        .times(itemSideValue)
                    )
                    .toString();
                }
              }
              const numerator = new BigNumber(account.balance)
                .minus(new BigNumber(Ipm))
                .minus(new BigNumber(Tmm))
                .plus(new BigNumber(Upnl))
                .plus(new BigNumber(selectedLeverageMargin.maintenanceAmount))
                .minus(new BigNumber(sideValue).times(new BigNumber(position.currentQty).abs()).times(position.entryPrice));
              const denominator = new BigNumber(
                (Math.abs(+position.currentQty) * selectedLeverageMargin.maintenanceMarginRate) / 100
              ).minus(new BigNumber(sideValue * Math.abs(+position.currentQty)));
              result[`${symbol}`]["liquidationPrice"] = new BigNumber(numerator).div(new BigNumber(denominator)).toString();
              console.log("check liquidation price usdm: ", {
                selectedLeverageMargin,
                Tmm,
                Upnl,
                Ipm,
                oraclePrice: oraclePrice[0],
                position,
                denominator: denominator.toString(),
                numerator: numerator.toString(),
              });
            } else {
              allocatedMargin = new BigNumber(position.positionMargin).plus(position.adjustMargin).toString();
              result[`${symbol}`]["positionMargin"] = new BigNumber(allocatedMargin).toString();
              // check again funding fee
            }
            console.log({
              allocatedMargin,
              position,
              indexPrice,
              oraclePrice,
              result,
            });

            //calculate pnl
            result[`${symbol}`]["unrealizedPNL"] = new BigNumber(
              Math.abs(+position.currentQty) * (+oraclePrice[0] - +position.entryPrice) * sideValue
            ).toString();
            // result[`${symbol}`]['unrealizedROE'] = new BigNumber(
            //   (result[`${symbol}`]['unrealizedPNL'] / allocatedMargin) * 100,
            // ).toString();
            const maintenanceMarginUSDM = this.calMaintenanceMargin(
              leverageMargin,
              checkTier,
              position,
              oraclePrice[0],
              ContractType.USD_M
            );
            const marginBalanceUSDM = position.isCross
              ? await this.calMarginBalanceForCrossUSDM(userId, position.asset, oraclePrice[0], account)
              : this.calMarginBalanceForIso(allocatedMargin, result[`${symbol}`]["unrealizedPNL"]);
            result[`${symbol}`]["marginRate"] = new BigNumber(maintenanceMarginUSDM)
              .div(new BigNumber(marginBalanceUSDM))
              .times(100)
              .toString();
            console.log({
              maintenanceMarginUSDM,
              marginBalanceUSDM,
              leverageMargin,
              checkTier,
              position,
              oraclePrice: oraclePrice[0],
            });
            break;
          default:
            break;
        }
      }
    }
    return result;
  }

  async exportPositionHistoryAdminExcelFile(paging: PaginationDto, queries: AdminPositionDto) {
    const { data } = await this.getAllPositionHistoryByAdmin(paging, queries);

    if (!data.length) {
      throw new HttpException(httpErrors.POSITION_NOT_FOUND, HttpStatus.NOT_FOUND);
    }

    const preprocessData = (
      data: any
    ): {
      positionId: string;
      symbol: string;
      mode: string;
      leverage: string;
      side: string;
      quantity: string;
      estimateUSDT: string;
      entryPrice: string;
      exitPrice: string;
      margin: string;
      pnl: string;
      openTime: string;
      closeTime: string;
    }[] => {
      return data.map((d: any) => {
        return {
          positionId: d.positionId,
          symbol: d.symbol,
          mode: d.marginMode,
          leverage: d.leverage,
          side: d.side === "SELL" ? "Short" : "Long",
          quantity: d.quantity,
          estimateUSDT: d.quantity * d.entryPrice,
          entryPrice: d.entryPrice,
          exitPrice: d.exitPrice,
          margin: d.margin,
          pnl: d.realizedPnl,
          openTime: moment(d.openPositionTime).format(`YYYY-MM-DD HH:mm:ss`),
          closeTime: moment(d.closePositionTime).format(`YYYY-MM-DD HH:mm:ss`),
        };
      });
    };
    const processedData = preprocessData(data);

    const COLUMN_NAMES = [
      "Position ID",
      "Symbol",
      "Mode",
      "Leverage",
      "Side",
      "Quantity",
      "Estimate USDT",
      "Entry Price",
      "Exit Price",
      "Margin",
      "Pnl",
      "OpenTime",
      "CloseTime",
    ];

    const columnDataKeys = [
      "positionId",
      "symbol",
      "mode",
      "leverage",
      "side",
      "quantity",
      "estimateUSDT",
      "entryPrice",
      "exitPrice",
      "margin",
      "pnl",
      "openTime",
      "closeTime",
    ];

    // Generate the Excel buffer
    const buffer = await this.excelService.generateExcelBuffer(COLUMN_NAMES, columnDataKeys, processedData);

    const fileName = "position-history";
    // Set response headers for file download
    const exportTime = moment().format("YYYY-MM-DD_HH-mm-ss");
    return {
      fileName: `${fileName}-${exportTime}.xlsx`,
      base64Data: Buffer.from(buffer).toString("base64"),
    };
  }

  async exportPositionAdminExcel(paging: PaginationDto, queries?: AdminPositionDto) {
    const { data } = await this.getAllPositionByAdmin(paging, queries);

    if (!data.length) {
      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }

    const preprocessData = (
      data: any
    ): {
      positionId: string;
      symbol: string;
      mode: string;
      leverage: string;
      side: string;
      quantity: string;
      estimateUSDT: string;
      avgPrice: string;
      margin: string;
      pnl: string;
      liqPrice: string;
      tierMMR: string;
      time: string;
    }[] => {
      return data.map((d: any) => {
        return {
          positionId: d.id,
          symbol: d.symbol,
          mode: d.isCross ? MarginMode.CROSS : MarginMode.ISOLATE,
          leverage: d.leverage,
          side: d.side === "SELL" ? "Short" : "Long",
          quantity: d.currentQty,
          estimateUSDT: Math.abs(d.currentQty) * d.entryPrice,
          avgPrice: d.entryPrice,
          margin: d.bankruptPrice,
          pnl: d.pnl,
          liqPrice: d.liquidationPrice,
          tierMMR: d.pnlRanking,
          time: moment(d.updatedAt).format(`YYYY-MM-DD HH:mm:ss`),
        };
      });
    };
    const processedData = preprocessData(data);

    const COLUMN_NAMES = [
      "Position ID",
      "Symbol",
      "Mode",
      "Leverage",
      "Side",
      "Quantity",
      "Estimate USDT",
      "Average Price",
      "Margin",
      "Pnl",
      "Liquidation Price",
      "Tier MMR",
      "Time",
    ];

    const columnDataKeys = [
      "positionId",
      "symbol",
      "mode",
      "leverage",
      "side",
      "quantity",
      "estimateUSDT",
      "avgPrice",
      "margin",
      "pnl",
      "liqPrice",
      "tierMMR",
      "time",
    ];

    // Generate the Excel buffer
    const buffer = await this.excelService.generateExcelBuffer(COLUMN_NAMES, columnDataKeys, processedData);

    const fileName = "open-position";
    // Set response headers for file download
    const exportTime = moment().format("YYYY-MM-DD_HH-mm-ss");
    return {
      fileName: `${fileName}-${exportTime}.xlsx`,
      base64Data: Buffer.from(buffer).toString("base64"),
    };
  }
}

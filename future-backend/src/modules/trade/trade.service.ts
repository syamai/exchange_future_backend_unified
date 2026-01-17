import { MAX_RESULT_COUNT } from "./trade.const";
import { getQueryLimit } from "./../../shares/pagination-util";
import { AdminTradeDto } from "./dto/admin-trade.dto";
import { HttpException, HttpStatus, Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { TradeEntity } from "src/models/entities/trade.entity";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { FillDto } from "src/modules/trade/dto/get-fills.dto";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { TradeHistoryDto } from "./dto/trade-history.dto";
import * as moment from "moment";
import { LessThan, MoreThan, Like, getConnection, IsNull, In } from "typeorm";
import { AccountRepository } from "src/models/repositories/account.repository";
import { AccountEntity } from "src/models/entities/account.entity";
import { GET_NUMBER_RECORD, START_CRAWL } from "../transaction/transaction.const";
import { UserRepository } from "src/models/repositories/user.repository";
import axios from "axios";
import { OrderSide } from "src/shares/enums/order.enum";
import { GetTradesPartnerDto } from "./dto/get-trades-partner.dto";
import { httpErrors } from "src/shares/exceptions";
import { ExcelService } from "../export-excel/services/excel.service";
import { UserEntity } from "src/models/entities/user.entity";
import { RedisClient } from "src/shares/redis-client/redis-client";

@Injectable()
export class TradeService {
  constructor(
    @InjectRepository(TradeRepository, "master")
    private tradeRepoMaster: TradeRepository,
    @InjectRepository(TradeRepository, "report")
    private tradeRepoReport: TradeRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(AccountRepository, "master")
    public readonly accountRepoMaster: AccountRepository,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    private readonly excelService: ExcelService,
    private readonly redisClient: RedisClient
  ) {
    setTimeout(() => {
      this.updateTrade();
    }, 3000);
  }

  async getFillTrade(userId: number, paging: PaginationDto, tradeHistoryDto: TradeHistoryDto): Promise<ResponseDto<FillDto[]>> {
    if (Number(userId) === 12057) {
      const [fills, totalPage] = await this.tradeRepoReport.getFillsOfUser5Company(userId, paging, tradeHistoryDto);
      return {
        data: fills,
        metadata: {
          totalPage: totalPage,
        },
      };  
    }

    const [fills, totalPage] = await this.tradeRepoReport.getFills(userId, paging, tradeHistoryDto);
    return {
      data: fills,
      metadata: {
        totalPage: totalPage,
      },
    };
  }

  async getRecentTrades(symbol: string, paging: PaginationDto): Promise<TradeEntity[]> {
    const trades = await this.tradeRepoReport.find({
      select: ["symbol", "price", "quantity", "buyerIsTaker", "createdAt", "id"],
      where: {
        symbol,
      },
      take: paging.size,
      order: {
        id: "DESC",
      },
    });
    return trades;
  }

  async getRecentTradesFromBinance(symbol: string, paging: PaginationDto): Promise<TradeEntity[]> {
    // Get trades data from Binance
    const tradesFromBinance = [];
    let response = null;
    try {
      // Send a GET request to Kakao API's user info endpoint with the provided access token.
      response = await axios.get(
        symbol.includes("USDM")
          ? `https://dapi.binance.com/dapi/v1/trades?symbol=${symbol.replace("USDM", "USD_PERP")}&limit=50`
          : `https://fapi.binance.com/fapi/v1/trades?symbol=${symbol}&limit=50`
      );
      tradesFromBinance.push(...response.data);
    } catch (e) {
      // If an error occurs during the user info retrieval process, log the error and throw an exception.
      Logger.error(`Cannot get trades from Binance: ${e}`);
      return [];
    }

    const trades: {
      buyerIsTaker: boolean;
      createdAt: number;
      id: string;
      price: string;
      quantity: string;
      symbol: string;
    }[] = [];
    for (const tradeFromBinance of tradesFromBinance) {
      trades.unshift({
        buyerIsTaker: !tradeFromBinance.isBuyerMaker,
        createdAt: tradeFromBinance.time,
        id: tradeFromBinance.id,
        price: tradeFromBinance.price,
        quantity: String(Number(tradeFromBinance.qty)),
        symbol: symbol,
      });
    }
    return (trades as any) as TradeEntity[];
  }

  async findYesterdayTrade(date: Date, symbol: string | undefined): Promise<TradeEntity | undefined> {
    return this.tradeRepoReport.findYesterdayTrade(date, symbol);
  }

  async findTodayTrades(date: Date, from: number, count: number): Promise<TradeEntity[]> {
    return this.tradeRepoReport.findTodayTrades(date, from, count);
  }

  async getLastTrade(symbol: string): Promise<TradeEntity[]> {
    return this.tradeRepoMaster.getLastTrade(symbol);
  }

  async getLastTradeId(): Promise<number> {
    return await this.tradeRepoMaster.getLastId();
  }

  async getTrades(paging: PaginationDto, queries: AdminTradeDto): Promise<any> {
    const where: string[] = [];
    const whereParam: Record<string, any> = {};

    if (queries.from) {
      const startTime = moment(parseInt(queries.from)).format("YYYY-MM-DD HH:mm:ss");
      where.push("tr.createdAt >= :startTime");
      whereParam.startTime = startTime;
    }
    if (queries.to) {
      const endTime = moment(parseInt(queries.to)).format("YYYY-MM-DD HH:mm:ss");
      where.push("tr.createdAt <= :endTime");
      whereParam.endTime = endTime;
    }

    if (queries.symbol) {
      where.push("tr.symbol LIKE :symbol");
      whereParam.symbol = `%${queries.symbol}%`;
    }

    if (queries.userId) {
      where.push(`(tr.sellUserId = :userId OR tr.buyUserId = :userId)`);
      whereParam.userId = queries.userId
    }

    if (queries.search_key) {
      const orWhere: string[] = [];

      orWhere.push("tr.id = :search_key");
      orWhere.push("tr.buyUserId = :search_key");
      orWhere.push("tr.sellUserId = :search_key");
      orWhere.push("(tr.buyAccountId = :search_key AND tr.buyerIsTaker = false)");
      orWhere.push("(tr.sellAccountId = :search_key AND tr.buyerIsTaker = true)");

      where.push(`(${orWhere.join(" OR ")})`);
      whereParam.search_key = queries.search_key;
    }

    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    const query = this.tradeRepoReport
      .createQueryBuilder("tr")
      // .leftJoin(UserEntity, 'buyer', 'buyer.id = tr.buyUserId') 
      // .leftJoin(UserEntity, 'seller', 'seller.id = tr.sellUserId') 
      .select("tr.*")
      // .addSelect(`buyer.uid`, "buyUserUid")
      // .addSelect(`seller.uid`, "sellUserUid")
      .where(where.join(" AND "), whereParam)
      // .andWhere("tr.buyUserId <> 530 || tr.sellUserId <> 530")
      .orderBy("tr.createdAt", "DESC")
      .limit(limit)
      .offset(offset);

    const [trades, count] = await Promise.all([query.getRawMany(), query.getCount()]);

    // trades.map((item) => {
    //   item.side = item.buyerIsTaker ? OrderSide.SELL : OrderSide.BUY;
    //   item.accountId = item.buyerIsTaker ? item.sellAccountId : item.buyAccountId;
    // });

    // add user uid
    let userUids = {}
    if (trades.length) {
      const userIds = []
      trades.forEach((trade) => {
        userIds.push(trade.sellUserId, trade.buyUserId)
      });

      const users = await this.userRepoReport.find({ select: ["id", "uid"], where: { id: In(userIds) } });

      for (const user of users) {
        userUids[user.id] = user.uid
      }
    }

    for (const trade of trades) {
      if (trade.buyerIsTaker) {
        trade.side = OrderSide.SELL
        trade.accountId = trade.sellAccountId
      } else {
        trade.side = OrderSide.BUY
        trade.accountId = trade.buyAccountId
      }
      //assign userUids
      trade.buyUserUid = userUids[trade.buyUserId]
      trade.sellUserUid = userUids[trade.sellUserId]
    }

    return {
      data: trades,
      metadata: {
        total: count,
        totalPage: Math.ceil(count / paging.size),
      },
    };
  }

  async getTradesHistoryForPartner(queries: GetTradesPartnerDto) {
    const where: string[] = [];
    const whereParam: Record<string, any> = {};
    
    if (queries.startDate) {
      const startTime = moment(queries.startDate).format("YYYY-MM-DD HH:mm:ss");
      where.push(`trades.createdAt >= '${startTime}'`);
      //   whereParam.startTime = startTime;
    }
    if (queries.endDate) {
      const endTime = moment(queries.endDate).format("YYYY-MM-DD HH:mm:ss");
      where.push(`trades.updatedAt <= '${endTime}'`);
      //   whereParam.endTime = endTime;
    }

    if (queries.currency) {
      where.push(`trades.symbol LIKE '%${queries.currency}%'`);
    }

    const { offset, limit } = getQueryLimit(
      {
        page: queries.page,
        size: queries.pageSize,
      } as PaginationDto,
      MAX_RESULT_COUNT
    );

    const getBuyerQuery = `
      SELECT 
        trades.createdAt as createdAt,
        trades.id as id,
        trades.buyOrderId as orderId,
        trades.buyAccountId as accountId,
        trades.buyUserId as userId,
        trades.buyFee as fee,
        trades.quantity as filledQuantity,
        trades.price as avgPrice,
        trades.symbol as symbol,
        trades.realizedPnlOrderBuy as realizedPnlOrder,
        IF(trades.buyerIsTaker, 'Taker', 'Maker') as liquidity,
        trades.contractType as contractType,
        'LONG' as tradeSide,
        o.leverage AS leverage,
        o.quantity AS orderQuantity,
        o.marginMode AS marginMode,
        o.executedPrice AS price
      FROM trades 
      INNER JOIN orders o ON o.id = trades.buyAccountId
      WHERE 
        trades.buyUserId = ${Number(queries.userId)}
        ${queries.orderId ? " AND " + `trades.buyOrderId = ${queries.orderId}` : ""}
      ${where.length ? "AND " + where.join(" AND ") : ""}
      ORDER BY id DESC
    `;

    const getSellerQuery = `
        SELECT 
        trades.createdAt as createdAt,
        trades.id as id,
        trades.sellOrderId as orderId,
        trades.sellAccountId as accountId,
        trades.sellUserId as userId,
        trades.sellFee AS fee,
        trades.quantity AS filledQuantity,
        trades.price AS avgPrice,
        trades.symbol AS symbol,
        trades.realizedPnlOrderSell AS realizedPnlOrder,
        IF(trades.buyerIsTaker, 'Maker', 'Taker') AS liquidity,
        trades.contractType AS contractType,
        'SHORT' AS tradeSide,
        o.leverage AS leverage,
        o.quantity AS orderQuantity,
        o.marginMode AS marginMode,
        o.executedPrice AS price
      FROM trades 
      INNER JOIN orders o ON o.id = trades.sellOrderId
      WHERE trades.sellUserId = ${Number(queries.userId)}
        ${queries.orderId ? " AND " + `trades.sellOrderId = ${queries.orderId}` : ""}
      ${where.length ? "AND " + where.join(" AND ") : ""}
      ORDER BY id DESC 
    `;

    const getAllQuery = `
      SELECT * FROM (
        (
          ${getBuyerQuery} 
        )
      UNION ALL 
        (
          ${getSellerQuery}
      )) AS T
      ORDER BY T.id DESC
      LIMIT ${limit}
      OFFSET ${offset}
    `;

    const countBuyerQuery = `
        SELECT COUNT(*) AS totalCount
        FROM ((${getBuyerQuery}) UNION ALL (${getSellerQuery})) AS C
    `;

    const [items, totalCount] = await Promise.all([this.tradeRepoMaster.query(getAllQuery), this.tradeRepoMaster.query(countBuyerQuery)]);

    return {
      data: {
        page: queries.page,
        pageSize: queries.pageSize,
        items,
        pageCount: Math.ceil(Number(totalCount[0].totalCount) / queries.pageSize),
      },
    };
  }

  async updateTrade() {
    let skip = START_CRAWL;
    const take = GET_NUMBER_RECORD;
    do {
      const listTrade = await this.tradeRepoMaster.find({
        where: [
          {
            buyUserId: IsNull(),
          },
          {
            sellUserId: IsNull(),
          },
        ],
        skip,
        take,
      });

      skip += take;
      if (listTrade.length) {
        for (const item of listTrade) {
          const getBuyUserId = await getConnection("report")
            .getRepository(AccountEntity)
            .findOne({
              where: {
                id: item.buyAccountId,
              },
            });

          const getSellUserId = await getConnection("report")
            .getRepository(AccountEntity)
            .findOne({
              where: {
                id: item.sellAccountId,
              },
            });
          await this.tradeRepoMaster.update({ buyAccountId: getBuyUserId?.id }, { buyUserId: getBuyUserId?.userId });
          await this.tradeRepoMaster.update({ sellAccountId: getSellUserId?.id }, { sellUserId: getSellUserId?.userId });
          const asset = item.symbol.includes("USDT") ? "USDT" : "USD";
          const buyAccountId = await this.accountRepoReport.findOne({
            where: {
              asset: asset.toLowerCase(),
              userId: getBuyUserId?.userId,
            },
          });
          const sellAccountId = await this.accountRepoReport.findOne({
            where: {
              asset: asset.toLowerCase(),
              userId: getSellUserId?.userId,
            },
          });
          await this.tradeRepoMaster.update({ buyUserId: getBuyUserId?.id }, { buyAccountId: buyAccountId?.id });
          await this.tradeRepoMaster.update({ sellUserId: getSellUserId?.id }, { sellAccountId: sellAccountId?.id });
        }
      } else {
        break;
      }
    } while (true);
  }

  async updateTradeEmail() {
    let skip = 0;
    const take = 1000;
    do {
      const tradesUpdate = await this.tradeRepoReport.find({
        where: {
          buyEmail: IsNull(),
          sellEmail: IsNull(),
        },
        skip,
        take,
      });
      skip += take;
      if (tradesUpdate.length > 0) {
        for (const trade of tradesUpdate) {
          const [buyUser, sellUser] = await Promise.all([
            this.userRepoReport.findOne({ where: { id: trade.buyUserId } }),
            this.userRepoReport.findOne({ where: { id: trade.sellUserId } }),
          ]);
          await this.tradeRepoMaster.update(
            { id: trade.id },
            {
              buyEmail: buyUser?.email ? buyUser.email : null,
              sellEmail: sellUser?.email ? sellUser.email : null,
              updatedAt: () => "trades.updatedAt",
              createdAt: () => "trades.createdAt",
            }
          );
        }
      } else {
        break;
      }
    } while (true);
  }

  async testUpdateTradeEmail(tradeId: string) {
    const tradesUpdate = await this.tradeRepoReport.findOne({
      where: {
        buyEmail: IsNull(),
        sellEmail: IsNull(),
        id: +tradeId,
      },
    });
    const [buyUser, sellUser] = await Promise.all([
      this.userRepoReport.findOne({ where: { id: tradesUpdate.buyUserId } }),
      this.userRepoReport.findOne({ where: { id: tradesUpdate.sellUserId } }),
    ]);
    await this.tradeRepoMaster.update(
      { id: +tradeId },
      {
        buyEmail: buyUser?.email ? buyUser.email : null,
        sellEmail: sellUser?.email ? sellUser.email : null,
        updatedAt: () => "trades.updatedAt",
        createdAt: () => "trades.createdAt",
      }
    );
  }

  async exportTradeAdminExcelFile(paging: PaginationDto, queries: AdminTradeDto) {
    const { data } = await this.getTrades(paging, queries);

    if (!data.length) {
      throw new HttpException(httpErrors.TRADE_NOT_FOUND, HttpStatus.NOT_FOUND);
    }

    const preprocessData = (
      data: any
    ): {
      tradeId: string;
      accountId: string;
      buyId: string;
      sellId: string;
      symbol: string;
      side: string;
      quantity: string;
      price: string;
      time: string;
    }[] => {
      return data.map((d: any) => {
        return {
          tradeId: d.id,
          accountId: d.accountId,
          buyId: d.buyOrderId,
          sellId: d.sellOrderId,
          symbol: d.symbol,
          side: d.side,
          quantity: d.quantity,
          price: d.price,
          time: moment(d.createdAt).format(`YYYY-MM-DD HH:mm:ss`),
        };
      });
    };
    const processedData = preprocessData(data);

    const COLUMN_NAMES = ["Trade ID", "Account ID", "Buy ID", "Sell ID", "Symbol", "side", "quantity", "Price", "Time"];

    const columnDataKeys = ["tradeId", "accountId", "buyId", "sellId", "symbol", "side", "quantity", "price", "time"];

    // Generate the Excel buffer
    const buffer = await this.excelService.generateExcelBuffer(COLUMN_NAMES, columnDataKeys, processedData);

    const fileName = "trade-history";
    // Set response headers for file download
    const exportTime = moment().format("YYYY-MM-DD_HH-mm-ss");
    return {
      fileName: `${fileName}-${exportTime}.xlsx`,
      base64Data: Buffer.from(buffer).toString("base64"),
    };
  }

  async getBinanceTradeDataFromRedis(symbol: string, size: number = 30) {
    const tradesStr = await this.redisClient.getInstance().lrange(`binance_trades:${symbol}`, 0, size - 1);
    return tradesStr.map((t) => JSON.parse(t));
  }
}

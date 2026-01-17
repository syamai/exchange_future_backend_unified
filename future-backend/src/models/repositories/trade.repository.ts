import { TradeEntity } from "src/models/entities/trade.entity";
import { BaseRepository } from "src/models/repositories/base.repository";
import { FillDto } from "src/modules/trade/dto/get-fills.dto";
import { TradeHistoryDto } from "src/modules/trade/dto/trade-history.dto";
import { MAX_RESULT_COUNT } from "src/modules/trade/trade.const";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { getQueryLimit } from "src/shares/pagination-util";
import { EntityRepository, LessThanOrEqual } from "typeorm";
import * as moment from "moment";
import { TradeType } from "src/shares/enums/trade.enum";
// import { take } from 'rxjs';

@EntityRepository(TradeEntity)
export class TradeRepository extends BaseRepository<TradeEntity> {
  public async updateBatch(entities: TradeEntity[]): Promise<void> {
    const queryBuilder = this.createQueryBuilder();
    const overwriteColumns = queryBuilder.connection
      .getMetadata(TradeEntity)
      .columns.map((column) => column.propertyName);
    await queryBuilder
      .insert()
      .values(entities)
      .orUpdate(overwriteColumns, "id")
      .execute();
  }

  async findYesterdayTrade(
    date: Date,
    symbol: string | undefined
  ): Promise<TradeEntity | undefined> {
    const where = {};
    if (symbol) {
      where["symbol"] = symbol;
    }
    where["createdAt"] = LessThanOrEqual(date);
    return this.findOne({
      where,
      order: {
        createdAt: "ASC",
      },
    });
  }

  async findTodayTrades(
    date: Date,
    from: number,
    count: number
  ): Promise<TradeEntity[]> {
    return this.createQueryBuilder()
      .where("createdAt >= :createdAt", { createdAt: date })
      .orderBy("id", "ASC")
      .offset(from)
      .limit(count)
      .getMany();
  }

  async getFills(
    userId: number,
    paging: PaginationDto,
    tradeHistoryDto: TradeHistoryDto
  ): Promise<[FillDto[], number]> {
    const startTime = moment(tradeHistoryDto.startTime).format(
      "YYYY-MM-DD HH:mm:ss"
    );
    const endTime = moment(tradeHistoryDto.endTime).format(
      "YYYY-MM-DD HH:mm:ss"
    );

    const where = `trades.createdAt > '${startTime}' and trades.updatedAt < '${endTime}'`;
    const whereContractType = `trades.contractType = '${tradeHistoryDto.contractType}'`;
    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    const limitInner = paging.size;
    const getBuyerQuery = `
      SELECT 
        trades.createdAt as createdAt,
        trades.id as id,
        trades.buyOrderId as orderId,
        trades.buyAccountId as accountId,
        trades.buyUserId as userId,
        trades.buyFee as fee,
        trades.quantity as quantity,
        trades.price as price,
        trades.symbol as symbol,
        trades.realizedPnlOrderBuy as realizedPnlOrderBuy,
        trades.realizedPnlOrderSell as realizedPnlOrderSell,
        IF(trades.buyerIsTaker, 'Taker', 'Maker') as liquidity,
        trades.contractType as contractType,
        'BUY' as tradeSide
      FROM trades 
      ${
        tradeHistoryDto.symbol
          ? `WHERE trades.symbol = '${tradeHistoryDto.symbol}'  AND trades.buyUserId = ${userId}`
          : `WHERE trades.buyUserId = ${userId}`
      }
      and ${where}
      and ${whereContractType}
      ORDER BY id DESC
    `;

    const getSellerQuery = `
      SELECT 
        trades.createdAt as createdAt,
        trades.id as id,
        trades.sellOrderId as orderId,
        trades.sellAccountId as accountId,
        trades.buyUserId as userId,
        trades.sellFee as fee,
        trades.quantity as quantity,
        trades.price as price,
        trades.symbol as symbol,
        trades.realizedPnlOrderBuy as realizedPnlOrderBuy,
        trades.realizedPnlOrderSell as realizedPnlOrderSell,
        IF(trades.buyerIsTaker, 'Maker', 'Taker') as liquidity,
        trades.contractType as contractType,
        'SELL' as tradeSide
      FROM trades 
      ${
        tradeHistoryDto.symbol
          ? `WHERE trades.symbol =  '${tradeHistoryDto.symbol}' AND trades.sellUserId = ${userId}`
          : `WHERE trades.sellUserId = ${userId}`
      }
      and ${where}
      and ${whereContractType}
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
      SELECT count(id) as totalItem
      FROM trades 
      WHERE ${
        tradeHistoryDto.symbol
          ? `trades.symbol = '${tradeHistoryDto.symbol}' AND trades.buyUserId = ${userId}`
          : `trades.buyUserId = ${userId}`
      }
      and ${where}
      and ${whereContractType}
      limit ${limit}`;

    const countSellerQuery = `
      SELECT count(id) as totalItem
      FROM trades 
      WHERE ${
        tradeHistoryDto.symbol
          ? `trades.symbol = '${tradeHistoryDto.symbol}' AND trades.sellUserId = ${userId}`
          : `trades.sellUserId = ${userId}`
      }
      and ${where}
      and ${whereContractType}
      limit ${limit}
    `;
    let fills: any;
    let countResult: any;
    switch (tradeHistoryDto.side) {
      case TradeType.BUY:
        fills = await this.manager.query(
          getBuyerQuery + `LIMIT ${limitInner} OFFSET ${offset}`
        );
        countResult = Number(
          (await this.manager.query(countBuyerQuery))[0].totalItem
        );
        break;

      case TradeType.SELL:
        fills = await this.manager.query(
          getSellerQuery + `LIMIT ${limitInner} OFFSET ${offset}`
        );
        countResult = Number(
          (await this.manager.query(countSellerQuery))[0].totalItem
        );
        break;

      case null:
      case TradeType.ALL:
        fills = await this.manager.query(getAllQuery);
        const [totalSeller, totalBuyer] = await Promise.all([
          this.manager.query(countSellerQuery),
          this.manager.query(countBuyerQuery),
        ]);
        countResult =
          Number(totalSeller[0].totalItem) + Number(totalBuyer[0].totalItem);
        break;

      default:
        break;
    }
    const totalItem = countResult;

    return [fills, Math.ceil(totalItem / paging.size)];
  }

  async getFillsOfUser5Company(
    userId: number,
    paging: PaginationDto,
    tradeHistoryDto: TradeHistoryDto
  ): Promise<[FillDto[], number]> {
    const startTime = moment(tradeHistoryDto.startTime).format(
      "YYYY-MM-DD HH:mm:ss"
    );
    const endTime = moment(tradeHistoryDto.endTime).format(
      "YYYY-MM-DD HH:mm:ss"
    );
    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);

    const fills = [
      {
        "createdAt": "2025-09-11T17:58:00.000Z",
        "id": 9461307,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1100.000",
        "price": "4518.09",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "8692.67286778",
        "realizedPnlOrderBuy": "18244.21",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T17:50:00.000Z",
        "id": 9032002,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1418.000",
        "price": "4427.36",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5025.24264108",
        "realizedPnlOrderBuy": "-7111.71",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T17:29:00.000Z",
        "id": 9451946,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "600.000",
        "price": "4427.04",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "2126.51990468",
        "realizedPnlOrderBuy": "-3845.88",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T17:21:00.000Z",
        "id": 9416597,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "600.000",
        "price": "4427.42",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "2126.72813840",
        "realizedPnlOrderBuy": "-3916.31",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T17:08:00.000Z",
        "id": 9989764,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1600.000",
        "price": "4423.91",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5657.11068890",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-13744.38",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T01:00:00.000Z",
        "id": 9843993,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "200.000",
        "price": "4402",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "4246.83",
        "realizedPnlOrderBuy": "2647.06",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T18:36:00.000Z",
        "id": 9945959,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "300.000",
        "price": "4349",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1047.30474196",
        "realizedPnlOrderBuy": "-8704.34",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T16:25:00.000Z",
        "id": 9520180,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4377",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1754.90219044",
        "realizedPnlOrderBuy": "-9752.82",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T03:47:00.000Z",
        "id": 9174188,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4399",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1761.53856728",
        "realizedPnlOrderBuy": "-4465.99",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-11T03:31:00.000Z",
        "id": 9313719,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1100.000",
        "price": "4402",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "3879.85987775",
        "realizedPnlOrderBuy": "-14020.77",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T14:36:00.000Z",
        "id": 9104228,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1000.000",
        "price": "4419",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "3542.09462223",
        "realizedPnlOrderBuy": "-16124.71",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T02:08:00.000Z",
        "id": 9596653,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "100.000",
        "price": "4446",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "355.88112968",
        "realizedPnlOrderBuy": "-326.91",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T02:08:00.000Z",
        "id": 9970730,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1300.000",
        "price": "4430",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5663.57586594",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-18604.15",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T01:48:00.000Z",
        "id": 9798936,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4430",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1771.27787383",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-2294.68",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T01:43:00.000Z",
        "id": 9960235,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "2500.000",
        "price": "4376",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "8752.58762672",
        "realizedPnlOrderBuy": "-746.66",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T21:17:00.000Z",
        "id": 9291240,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1600.000",
        "price": "4314",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5519.82059405",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-5369.10",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T17:55:00.000Z",
        "id": 9797770,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "800.000",
        "price": "4297",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "2756.92654192",
        "realizedPnlOrderBuy": "-17038.10",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T17:05:00.000Z",
        "id": 9677404,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1000.000",
        "price": "4308",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "2609.39788662",
        "realizedPnlOrderBuy": "-9696.06",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T02:09:00.000Z",
        "id": 9627624,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "59.332",
        "price": "4311",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "104.87820980",
        "realizedPnlOrderBuy": "-222.49",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T01:25:00.000Z",
        "id": 9284988,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4300",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "881.59991724",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-3837.29",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T00:26:00.000Z",
        "id": 9167042,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "100.000",
        "price": "4294",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "344.36477984",
        "realizedPnlOrderBuy": "-2018.05",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T18:08:00.000Z",
        "id": 9956744,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "400.000",
        "price": "4286",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "703.02201580",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-2755.03",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T17:01:00.000Z",
        "id": 9109544,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "400.000",
        "price": "4291",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "703.81636408",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-4740.91",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T16:14:00.000Z",
        "id": 9293985,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "300.000",
        "price": "4291",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "527.82673310",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-3466.83",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T03:55:00.000Z",
        "id": 9995361,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "300.000",
        "price": "4292",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1028.60354652",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-3880.04",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-10T03:39:00.000Z",
        "id": 9020929,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "920.000",
        "price": "4283",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "4026.12370312",
        "realizedPnlOrderBuy": "-9909.89",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T02:54:00.000Z",
        "id": 9682119,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "849.559",
        "price": "4300",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "3953.33488970",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-4018.30",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T02:41:00.000Z",
        "id": 9291460,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1614.441",
        "price": "4320",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5067.36433242",
        "realizedPnlOrderBuy": "-61837.73",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-08T16:05:00.000Z",
        "id": 9195875,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "600.000",
        "price": "4303",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "2243.47603492",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-10854.81",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-09T00:38:00.000Z",
        "id": 9566074,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1250.000",
        "price": "4305",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5028.24407234",
        "realizedPnlOrderBuy": "-43155.44",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-08T23:58:00.000Z",
        "id": 9949864,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "50.0000",
        "price": "111718",
        "symbol": "BTCUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5779.90379100",
        "realizedPnlOrderBuy": "21351.53",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T20:06:00.000Z",
        "id": 9565213,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "414.686",
        "price": "4290",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1398.67487276",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-319.91",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T19:33:00.000Z",
        "id": 9900478,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "800.000",
        "price": "4308",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1580.41212016",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-12994.40",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T16:54:00.000Z",
        "id": 9865605,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "700.000",
        "price": "4296",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1570.09645754",
        "realizedPnlOrderBuy": "-15859.23",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-08T02:07:00.000Z",
        "id": 9003315,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1400.000",
        "price": "4299",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "5364.44432687",
        "realizedPnlOrderBuy": "-5071.32",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T21:38:00.000Z",
        "id": 9943196,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4294",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1714.78894487",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-7560.50",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T02:16:00.000Z",
        "id": 9603076,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "600.000",
        "price": "4305",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "2064.99045396",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-3805.63",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T02:09:00.000Z",
        "id": 9652733,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4307",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1722.86770164",
        "realizedPnlOrderBuy": "745.48",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T02:07:00.000Z",
        "id": 9345741,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "100.000",
        "price": "4305",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "344.34757712",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-232.94",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-07T01:59:00.000Z",
        "id": 9331199,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1300.000",
        "price": "4302",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "3834.88055012",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-15895.30",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-06T23:59:00.000Z",
        "id": 9302063,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "10.0000",
        "price": "110249",
        "symbol": "BTCUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "44.58956706",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-498.76",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-06T02:02:00.000Z",
        "id": 9351301,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4297",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "838.42218758",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-5055.52",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-06T01:47:00.000Z",
        "id": 9018212,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "300.000",
        "price": "4290",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "573.84846675",
        "realizedPnlOrderBuy": "-2666.98",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T23:48:00.000Z",
        "id": 9142027,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "6.896",
        "price": "4299",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "13.19296344",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-69.85",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T23:45:00.000Z",
        "id": 9408581,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "15.0000",
        "price": "110754",
        "symbol": "BTCUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "514.58765563",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-1480.10",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T23:19:00.000Z",
        "id": 9064855,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "300.000",
        "price": "4292",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1095.51135195",
        "realizedPnlOrderBuy": "-1948.87",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T22:00:00.000Z",
        "id": 9293081,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "30.0000",
        "price": "110674",
        "symbol": "BTCUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1029.55975429",
        "realizedPnlOrderBuy": "-1855.30",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T21:52:00.000Z",
        "id": 9874749,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "291.635",
        "price": "4297",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "54.77500617",
        "realizedPnlOrderBuy": "-3046.49",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T16:50:00.000Z",
        "id": 9415271,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "2058.365",
        "price": "4318",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "3781.12999557",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-58375.26",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T02:16:00.000Z",
        "id": 9672359,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "3494.435",
        "price": "4412",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "8878.72310774",
        "realizedPnlOrderBuy": "-167189.75",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-05T00:16:00.000Z",
        "id": 9163022,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "3798.773",
        "price": "4365",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "11771.00667059",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "95274.16",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-04T19:01:00.000Z",
        "id": 9260249,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "67.0000",
        "price": "111977",
        "symbol": "BTCUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "6358.12038279",
        "realizedPnlOrderBuy": "44679.80",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-03T18:54:00.000Z",
        "id": 9197160,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1000.000",
        "price": "4461",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "2498.01143800",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-3062.87",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-03T18:43:00.000Z",
        "id": 9019936,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "500.000",
        "price": "4457",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1248.47036200",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "5699.27",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-03T17:47:00.000Z",
        "id": 9756383,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "1200.000",
        "price": "4476",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "1823.36933280",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-8845.33",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-03T17:39:00.000Z",
        "id": 9091345,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "900.000",
        "price": "4472",
        "symbol": "ETHUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "877.08766232",
        "realizedPnlOrderBuy": "-7399.32",
        "realizedPnlOrderSell": null,
        "tradeSide": "BUY",
        "orderType": "MARKET"
      },
      {
        "createdAt": "2025-09-03T17:01:00.000Z",
        "id": 9657619,
        "orderId": "100019233585",
        "accountId": "135703",
        "userId": "12057",
        "quantity": "45.6458",
        "price": "111820",
        "symbol": "BTCUSDT",
        "liquidity": "Taker",
        "contractType": "USD_M",
        "fee": "624.13625908",
        "realizedPnlOrderBuy": null,
        "realizedPnlOrderSell": "-7580.61",
        "tradeSide": "SELL",
        "orderType": "MARKET"
      }
    ];
    // Filter fills by startTime and endTime
    const filteredFills = fills.filter(fill => {
      const createdAt = moment(fill.createdAt);
      return createdAt.isSameOrAfter(startTime) && createdAt.isSameOrBefore(endTime);
    });
    // Order filteredFills by createdAt ascending
    // filteredFills.sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());

    // Apply offset and limit for pagination
    const pagedFills = filteredFills.slice(offset, offset + limit);

    // Replace fills with pagedFills for return and totalItem calculation
    const totalItem = filteredFills.length;
    return [pagedFills, Math.ceil(totalItem / paging.size)];
  }

  async getLastTrade(symbol: string): Promise<TradeEntity[]> {
    return this.find({ where: { symbol }, order: { id: "DESC" }, take: 1 });
  }

  async getDataTrade(userId: number, symbol: string, contract: string) {
    const startTime = Date.now();
    let queryTradeBuy: string;
    let queryTradeSell: string;
    if (contract === "COIN_M") {
      queryTradeBuy = `
    SELECT
    t.buyOrderId as buyOrderId,
    t.sellOrderId as sellOrderId,
    t.id as idTrade,
    SUM(t.price) as price,
    SUM(t.quantity) as quantity,
    SUM(t.quantity) / SUM(t.quantity/t.price) as average
  FROM trades t
    ${
      symbol
        ? `WHERE t.symbol = '${symbol}'  AND t.buyUserId = ${userId}`
        : `WHERE t.buyUserId = ${userId}`
    }
    GROUP BY t.buyOrderId
  `;

      queryTradeSell = `
    SELECT 
      t.buyOrderId as buyOrderId,
      t.sellOrderId as sellOrderId,
      t.id as idTrade,
      SUM(t.price) as price,
      SUM(t.quantity) as quantity,
      SUM(t.quantity) / SUM(t.quantity/t.price) as average
    FROM trades t
    ${
      symbol
        ? `WHERE t.symbol = '${symbol}'  AND t.sellUserId = ${userId}`
        : `WHERE t.sellUserId = ${userId}`
    }
    GROUP BY t.sellOrderId
  `;
    } else {
      queryTradeBuy = `
    SELECT
    t.buyOrderId as buyOrderId,
    t.sellOrderId as sellOrderId,
    t.id as idTrade,
    SUM(t.price) as price,
    SUM(t.quantity) as quantity,
    SUM(t.price * t.quantity) / SUM(t.quantity) as average
  FROM trades t
    ${
      symbol
        ? `WHERE t.symbol = '${symbol}'  AND t.buyUserId = ${userId}`
        : `WHERE t.buyUserId = ${userId}`
    }
    GROUP BY t.buyOrderId
  `;

      queryTradeSell = `
    SELECT 
      t.buyOrderId as buyOrderId,
      t.sellOrderId as sellOrderId,
      t.id as idTrade,
      SUM(t.price) as price,
      SUM(t.quantity) as quantity,
      SUM(t.price * t.quantity) / SUM(t.quantity) as average
    FROM trades t
    ${
      symbol
        ? `WHERE t.symbol = '${symbol}'  AND t.sellUserId = ${userId}`
        : `WHERE t.sellUserId = ${userId}`
    }
    GROUP BY t.sellOrderId
  `;
    }

    const [tradeBuy, tradeSell] = await Promise.all([
      this.manager.query(queryTradeBuy),
      this.manager.query(queryTradeSell),
    ]);
    console.log(`get trade time: ${Date.now() - startTime}`);
    return { tradeBuy, tradeSell };
  }
}

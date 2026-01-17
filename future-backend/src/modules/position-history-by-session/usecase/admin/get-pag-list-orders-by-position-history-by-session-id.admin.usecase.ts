import { HttpException, HttpStatus, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { ResponseDto } from "src/shares/dtos/response.dto";
import { GetPagListOrdersPositionHistoryBySessionIdAdminResponse } from "../../response/admin/get-pag-list-orders-by-position-history-by-session-id.admin.response";
import { OrderWithPositionHistoryBySessionRepository } from "src/models/repositories/order-with-position-history-by-session.repository";
import { getQueryLimit } from "src/shares/pagination-util";
import { MAX_RESULT_COUNT } from "src/modules/trade/trade.const";
import BigNumber from "bignumber.js";
import { OrderSide } from "src/shares/enums/order.enum";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { PositionHistoryBySessionRepository } from "src/models/repositories/position-history-by-session.repository";
import { GetPagListOrdersByPositionHistoryBySessionIdDto } from "../../dto/admin/get-pag-list-orders-by-phbs-id.admin.dto";

@Injectable()
export class GetPagListOrdersPositionHistoryBySessionIdAdminUseCase {
  constructor(
    @InjectRepository(PositionHistoryBySessionRepository, "report")
    private readonly positionHistoryRepoReport: PositionHistoryBySessionRepository,
    @InjectRepository(OrderWithPositionHistoryBySessionRepository, "report")
    private readonly orderWithPositionHistoryRepoReport: OrderWithPositionHistoryBySessionRepository,
    @InjectRepository(TradeRepository, "report")
    private readonly tradeRepoReport: TradeRepository
  ) {}

  async execute(
    paging: PaginationDto,
    positionHistoryBySessionId: number,
    query: GetPagListOrdersByPositionHistoryBySessionIdDto
  ): Promise<
    ResponseDto<GetPagListOrdersPositionHistoryBySessionIdAdminResponse[]>
  > {
    const positionHistoryBySession = await this.positionHistoryRepoReport.findOne(
      Number(positionHistoryBySessionId)
    );
    if (!positionHistoryBySession) {
      throw new HttpException(
        "Position History By Session not found",
        HttpStatus.NOT_FOUND
      );
    }

    const { offset, limit } = getQueryLimit(paging, MAX_RESULT_COUNT);
    const orderQueryBuilder = this.orderWithPositionHistoryRepoReport
      .createQueryBuilder("owph")
      .innerJoin("orders", "order", "owph.orderId = order.id")
      .innerJoin(
        "position_history_by_session",
        "positionHistoryBySession",
        "owph.positionHistoryBySessionId = positionHistoryBySession.id"
      )
      .where(`owph.positionHistoryBySessionId = :positionHistoryBySessionId`, {
        positionHistoryBySessionId,
      });

    const totalItems = await orderQueryBuilder.getCount();
    if (totalItems === 0) {
      return {
        data: [],
        metadata: { totalPage: 0, total: 0 },
      };
    }

    if (query.sortBy) {
      if (!query.sortDirection) query.sortDirection = "ASC";
      orderQueryBuilder.orderBy("owph.orderId", query.sortDirection);
    }

    const orderWithPositionHistories = await orderQueryBuilder
      .select([
        "owph.orderId as owph_orderId",
        "owph.positionHistoryBySessionId as owph_positionHistoryBySessionId",

        "order.id as order_id",
        "order.createdAt as order_createdAt",
        "order.side as order_side",
        "order.price as order_price",
        "order.quantity as order_quantity",
        "order.remaining as order_remaining",

        "positionHistoryBySession.id as positionHistoryBySession_id",
        "positionHistoryBySession.positionId as positionHistoryBySession_positionId",
      ])
      .limit(limit)
      .offset(offset)
      .getRawMany();

    // Get trades by orderIds and save to map
    const orderIds = orderWithPositionHistories.map(
      (item) => item.owph_orderId
    );

    // Fetch all trades for these orderIds
    let tradesByOrderId = new Map<number, any[]>();
    const trades = await this.tradeRepoReport
      .createQueryBuilder("trade")
      .where(
        "(trade.buyOrderId IN (:...orderIds) OR trade.sellOrderId IN (:...orderIds))",
        { orderIds }
      )
      .select([
        "trade.id as trade_id",
        "trade.buyOrderId as trade_buyOrderId",
        "trade.sellOrderId as trade_sellOrderId",
        "trade.price as trade_price",
        "trade.buyFee as trade_buyFee",
        "trade.sellFee as trade_sellFee",
        "trade.realizedPnlOrderSell as trade_realizedPnlOrderSell",
        "trade.realizedPnlOrderBuy as trade_realizedPnlOrderBuy",
        "trade.quantity as trade_quantity",
        "trade.buyerIsTaker as trade_buyerIsTaker",
      ])
      .getRawMany();

    // Group trades by orderId
    tradesByOrderId = trades.reduce((map, trade) => {
      // Buy order
      const buyOrderId = trade.trade_buyOrderId;
      if (!map.has(buyOrderId)) {
        map.set(buyOrderId, []);
      }
      map.get(buyOrderId).push(trade);

      // Sell order
      const sellOrderId = trade.trade_sellOrderId;
      if (!map.has(sellOrderId)) {
        map.set(sellOrderId, []);
      }
      map.get(sellOrderId).push(trade);

      return map;
    }, new Map<number, any[]>());

    // Map responses
    const responses = orderWithPositionHistories.map(
      (orderWithPositionHistory) => {
        const response = new GetPagListOrdersPositionHistoryBySessionIdAdminResponse();
        response.orderId = orderWithPositionHistory.owph_orderId;
        response.positionId =
          orderWithPositionHistory.positionHistoryBySession_positionId;
        response.date = orderWithPositionHistory.order_createdAt
          ? new Date(orderWithPositionHistory.order_createdAt).toISOString()
          : null;
        response.direction =
          orderWithPositionHistory.order_side.toString() ===
          OrderSide.BUY.toString()
            ? "Long"
            : "Short";

        // Calculate average price/fee/pnl from trades of this order
        response.avgPrice = orderWithPositionHistory.order_price;
        response.fee = "0";
        response.pnl = "0";
        const tradesOfOrder =
          tradesByOrderId.get(orderWithPositionHistory.owph_orderId) || [];
        if (tradesOfOrder.length > 0) {
          let totalPrices = new BigNumber(0);
          let totalFees = new BigNumber(0);
          let totalPnls = new BigNumber(0);
          let totalFilledQuantity = new BigNumber(0);
          tradesOfOrder.forEach((trade) => {
            // Sum totalPrices
            totalPrices = totalPrices.plus(new BigNumber(trade.trade_price));

            // Sum totalFees
            const buyFee = new BigNumber(trade.trade_buyFee ?? 0);
            const sellFee = new BigNumber(trade.trade_sellFee ?? 0);
            totalFees = totalFees.plus(
              trade.trade_buyOrderId.toString() ===
                orderWithPositionHistory.order_id.toString()
                ? buyFee
                : sellFee
            );

            // Sum pnl
            const buyPnl = new BigNumber(trade.trade_realizedPnlOrderBuy ?? 0);
            const sellPnl = new BigNumber(
              trade.trade_realizedPnlOrderSell ?? 0
            );
            totalPnls = totalPnls.plus(
              trade.trade_buyOrderId.toString() ===
                orderWithPositionHistory.order_id.toString()
                ? buyPnl
                : sellPnl
            );

            // Sum total filled quantity
            const filledQuantity = new BigNumber(trade.trade_quantity ?? 0);
            totalFilledQuantity = totalFilledQuantity.plus(filledQuantity);
          });

          // Check take or maker
          const isTaker = tradesOfOrder.find(
            (trade) =>
              (orderWithPositionHistory.order_side.toString() ===
                OrderSide.BUY.toString() &&
                trade.trade_buyerIsTaker.toString() === "1") ||
              (orderWithPositionHistory.order_side.toString() ===
                OrderSide.SELL.toString() &&
                trade.trade_buyerIsTaker.toString() === "0")
          );
          const isMaker = tradesOfOrder.find(
            (trade) =>
              (orderWithPositionHistory.order_side.toString() ===
                OrderSide.BUY.toString() &&
                trade.trade_buyerIsTaker.toString() === "0") ||
              (orderWithPositionHistory.order_side.toString() ===
                OrderSide.SELL.toString() &&
                trade.trade_buyerIsTaker.toString() === "1")
          );
          if (isMaker && isTaker) response.takerOrMaker = "BOTH";
          else if (isMaker) response.takerOrMaker = "MAKER";
          else if (isTaker) response.takerOrMaker = "TAKER";

          response.avgPrice = totalPrices
            .dividedBy(tradesOfOrder.length)
            .toString();
          response.fee = `-${totalFees.toString()}`;
          if (
            response.direction.toString().toLowerCase() !==
            positionHistoryBySession.side.toString().toLowerCase()
          ) {
            // this is close order
            response.pnl = totalPnls.minus(totalFees).toString();
          }

          response.fillAmount = totalFilledQuantity.toString();
          response.fillAmountInUsdt = totalFilledQuantity
            .multipliedBy(new BigNumber(response.avgPrice))
            .toString();
        }

        return response;
      }
    );

    return {
      data: responses,
      metadata: {
        totalPage: Math.ceil(totalItems / limit),
        total: totalItems,
      },
    };
  }
}

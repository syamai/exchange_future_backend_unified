import { Injectable, Logger } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { KafkaClient } from "../kafka-client/kafka-client";
import { KafkaTopics } from "../enums/kafka.enum";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { InjectRepository } from "@nestjs/typeorm";
import { PositionHistoryBySessionEntity } from "src/models/entities/position_history_by_session.entity";
import { PositionHistoryBySessionRepository } from "src/models/repositories/position-history-by-session.repository";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { OrderWithPositionHistoryBySessionRepository } from "src/models/repositories/order-with-position-history-by-session.repository";
import BigNumber from "bignumber.js";
import { TradeRepository } from "src/models/repositories/trade.repository";

@Console()
@Injectable()
export class CommonToolConsole {
  constructor(
    public readonly kafkaClient: KafkaClient,
    @InjectRepository(PositionHistoryBySessionRepository, "master")
    private readonly positionHistoryBySessionRepoMaster: PositionHistoryBySessionRepository,
    @InjectRepository(MarginHistoryRepository, "report")
    private readonly marginHistoryRepoReport: MarginHistoryRepository,
    @InjectRepository(OrderWithPositionHistoryBySessionRepository, "master")
    private readonly orderWithPositionHistoryBySessionRepoMaster: OrderWithPositionHistoryBySessionRepository,
    @InjectRepository(TradeRepository, "report")
    private readonly tradeRepoReport: TradeRepository
  ) {}
  private readonly logger = new Logger(CommonToolConsole.name);

  @Command({
    command: "tool:send-stop-flag-for-worker <stopCode>",
  })
  async sendStopFlagForWorker(stopCode: string): Promise<void> {
    if (stopCode === CommandCode.STOP_SAVE_ORDERS_FROM_CLIENT.toString()) {
      await this.kafkaClient.send(KafkaTopics.save_order_from_client_v2, {
        createOrderDto: {},
        userId: null,
        tmpOrderId: stopCode,
      });
      this.logger.log(
        `Send ${stopCode} code to save_order_from_client_v2 successfully!`
      );
    } else {
      await this.kafkaClient.send(KafkaTopics.matching_engine_output, [
        {
          code: stopCode,
          accounts: [],
          fundingHistories: [],
          marginHistories: [],
          orders: [],
          positionHistories: [],
          positions: [],
          trades: [],
          transactions: [],
          errors: [],
          liquidatedPositions: [],
        },
      ]);
      this.logger.log(
        `Send ${stopCode} code to matching_engine_output successfully!`
      );
    }
  }

  @Command({
    command: "tool:sync-up-phbs",
  })
  private async syncUpPositionHistoryBySession() {
    const phbses = await this.positionHistoryBySessionRepoMaster.find({
      where: { status: "CLOSED" },
    });

    for (const phbs of phbses) {
      let ophbses = await this.orderWithPositionHistoryBySessionRepoMaster.find(
        {
          where: {
            positionHistoryBySessionId: phbs.id,
          },
        }
      );

      // Fetch all trades for these orderIds
      let tradesByOrderId = new Map<number, any[]>();
      const trades = await this.tradeRepoReport
        .createQueryBuilder("trade")
        .where(
          "(trade.buyOrderId IN (:...orderIds) OR trade.sellOrderId IN (:...orderIds))",
          { orderIds: ophbses.map((o) => o.orderId) }
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
        ])
        .getRawMany();

      // Group trades by orderId
      tradesByOrderId = trades.reduce((map, trade) => {
        // Buy order
        const buyOrderId = Number(trade.trade_buyOrderId);
        if (!map.has(buyOrderId)) {
          map.set(buyOrderId, []);
        }
        map.get(buyOrderId).push(trade);

        // Sell order
        const sellOrderId = Number(trade.trade_sellOrderId);
        if (!map.has(sellOrderId)) {
          map.set(sellOrderId, []);
        }
        map.get(sellOrderId).push(trade);

        return map;
      }, new Map<number, any[]>());

      ///////////////////////////////
      for (let ophbs of ophbses) {
        const marginHistories = await this.marginHistoryRepoReport
          .createQueryBuilder("mh")
          .where("mh.orderId = :orderId", { orderId: ophbs.orderId })
          .andWhere("mh.action IN (:...actions)", {
            actions: ["MATCHING_BUY", "MATCHING_SELL"],
          })
          .orderBy("mh.operationId", "DESC")
          .addOrderBy("mh.id", "DESC")
          .getMany();

        if (!marginHistories || marginHistories.length == 0) continue;
        const marginHistory = marginHistories[0];

        const marginHistoryCurrentQtyAfter = new BigNumber(
          marginHistory.currentQtyAfter
        );
        const marginHistoryEntryValueAfter = new BigNumber(
          marginHistory.entryValueAfter
        );
        const marginHistoryLeverageAfter = new BigNumber(
          marginHistory.leverageAfter
        );
        const marginHistoryEntryPriceAfter = new BigNumber(
          marginHistory.entryPriceAfter
        );

        ophbs.orderMarginAfter = marginHistoryEntryValueAfter
          .abs()
          .dividedBy(marginHistoryLeverageAfter)
          .toFixed();
        ophbs.entryPriceAfter = marginHistoryEntryPriceAfter.toFixed();
        ophbs.currentQtyAfter = marginHistoryCurrentQtyAfter.toFixed();
        ophbs.entryValueAfter = marginHistoryEntryValueAfter.toFixed();

        // Fee
        const tradesOfOrder = tradesByOrderId.get(Number(ophbs.orderId)) || [];
        if (tradesOfOrder.length > 0) {
          let sumTradePrice = new BigNumber(0);
          let sumRealizedPnl = new BigNumber(0);
          let openFees = new BigNumber(0);
          let closeFees = new BigNumber(0);
          tradesOfOrder.forEach((trade) => {
            const buyFee = new BigNumber(trade.trade_buyFee ?? 0);
            const sellFee = new BigNumber(trade.trade_sellFee ?? 0);
            if (ophbs.isOpenOrder) {
              openFees = openFees.plus(
                trade.trade_buyOrderId.toString() === ophbs.orderId.toString()
                  ? buyFee
                  : sellFee
              );
            } else {
              closeFees = closeFees.plus(
                trade.trade_buyOrderId.toString() === ophbs.orderId.toString()
                  ? buyFee
                  : sellFee
              );
            }

            sumTradePrice = sumTradePrice.plus(
              new BigNumber(trade.trade_price)
            );

            // pnl
            const buyPnl = new BigNumber(trade.trade_realizedPnlOrderBuy ?? 0);
            const sellPnl = new BigNumber(
              trade.trade_realizedPnlOrderSell ?? 0
            );
            sumRealizedPnl = sumRealizedPnl.plus(
              new BigNumber(
                trade.trade_buyOrderId.toString() === ophbs.orderId.toString()
                  ? buyPnl
                  : sellPnl
              )
            );
          });
          ophbs.openFee = openFees.toFixed();
          ophbs.closeFee = closeFees.toFixed();
          ophbs.fee = openFees.plus(closeFees).toFixed();
          ophbs.profit = sumRealizedPnl.toFixed();
          ophbs.tradePriceAfter = sumTradePrice
            .dividedBy(tradesOfOrder.length)
            .toFixed();
        }

        ophbs = await this.orderWithPositionHistoryBySessionRepoMaster.save(
          ophbs
        );
      }

      // Update phbs
      ophbses = await this.orderWithPositionHistoryBySessionRepoMaster
        .createQueryBuilder("ophbs")
        .where(
          "ophbs.positionHistoryBySessionId = :positionHistoryBySessionId",
          { positionHistoryBySessionId: phbs.id }
        )
        .getMany();

      const margins = ophbses.map(
        (o) => new BigNumber(o.orderMarginAfter ?? 0)
      );
      const sizes = ophbses.map((o) =>
        new BigNumber(o.currentQtyAfter ?? 0).abs()
      );
      const values = ophbses.map((o) =>
        new BigNumber(o.entryValueAfter ?? 0).abs()
      );
      const fees = ophbses.map((o) => new BigNumber(o.fee ?? 0).abs());
      const openFees = ophbses.map((o) => new BigNumber(o.openFee ?? 0).abs());
      const closeFees = ophbses.map((o) =>
        new BigNumber(o.closeFee ?? 0).abs()
      );
      const entryPrices = ophbses.map(
        (o) => new BigNumber(o.entryPriceAfter ?? 0)
      );
      const closedOrders = ophbses.filter((o) => o.isOpenOrder === false);
      const closePrices = closedOrders.map(
        (o) => new BigNumber(o.tradePriceAfter ?? 0)
      );
      const profits = closedOrders.map((o) => new BigNumber(o.profit ?? 0));

      // Min/Max
      phbs.minMargin = margins
        .reduce(
          (min, v) => (v.lt(min) ? v : min),
          margins[0] ?? new BigNumber(0)
        )
        .toFixed();
      phbs.maxMargin = margins
        .reduce(
          (max, v) => (v.gt(max) ? v : max),
          margins[0] ?? new BigNumber(0)
        )
        .toFixed();

      phbs.sumEntryPrice = entryPrices
        .reduce((sum, v) => sum.plus(v), new BigNumber(0))
        .toFixed();
      phbs.sumMargin = margins
        .reduce((sum, v) => sum.plus(v), new BigNumber(0))
        .toFixed();

      phbs.minSize = sizes
        .reduce((min, v) => (v.lt(min) ? v : min), sizes[0] ?? new BigNumber(0))
        .multipliedBy(phbs.side === "LONG" ? 1 : -1)
        .toFixed();
      phbs.maxSize = sizes
        .reduce((max, v) => (v.gt(max) ? v : max), sizes[0] ?? new BigNumber(0))
        .multipliedBy(phbs.side === "LONG" ? 1 : -1)
        .toFixed();

      phbs.minValue = values
        .reduce(
          (min, v) => (v.lt(min) ? v : min),
          values[0] ?? new BigNumber(0)
        )
        .multipliedBy(phbs.side === "LONG" ? 1 : -1)
        .toFixed();
      phbs.maxValue = values
        .reduce(
          (max, v) => (v.gt(max) ? v : max),
          values[0] ?? new BigNumber(0)
        )
        .multipliedBy(phbs.side === "LONG" ? 1 : -1)
        .toFixed();

      // Fee
      phbs.fee = fees
        .reduce((sum, v) => sum.plus(v), new BigNumber(0))
        .toFixed();
      phbs.openingFee = openFees
        .reduce((sum, v) => sum.plus(v), new BigNumber(0))
        .toFixed();
      phbs.closingFee = closeFees
        .reduce((sum, v) => sum.plus(v), new BigNumber(0))
        .toFixed();

      phbs.sumClosePrice = closePrices
        .reduce((sum, v) => sum.plus(v), new BigNumber(0))
        .toFixed();
      phbs.numOfCloseOrders = closedOrders.length;
      phbs.profit = profits
        .reduce((sum, v) => sum.plus(v), new BigNumber(0))
        .toFixed();
      phbs.pnl = new BigNumber(phbs.profit).minus(phbs.fee).toFixed();

      const maxMargin = new BigNumber(phbs.maxMargin ?? 0);
      phbs.pnlRate = maxMargin.isZero()
        ? new BigNumber(0).toFixed()
        : new BigNumber(phbs.pnl)
            .dividedBy(maxMargin)
            .multipliedBy(100)
            .toFixed();

      await this.positionHistoryBySessionRepoMaster.save(phbs);
      console.log(`Processed id=${phbs.id}`);
    }
  }
}

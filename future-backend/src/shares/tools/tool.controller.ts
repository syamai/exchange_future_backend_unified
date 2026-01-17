import { BadRequestException, Controller, Get, Query } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { OrderEntity } from "src/models/entities/order.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { OrderRepository } from "src/models/repositories/order.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { USDT } from "src/modules/balance/balance.const";
import { RedisClient } from "../redis-client/redis-client";
import { UserStatisticRepository } from "src/models/repositories/user-statistics.repository";
import { PositionHistoriesTmpEntity } from "src/models/entities/position-histories-tmp.entity";
import { PositionHistoriesTmpRepository } from "src/models/repositories/position-histories-tmp.repository";

@Controller("tools")
@ApiTags("Tools")
export class ToolController {
  constructor(
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepoReport: PositionRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(MarginHistoryRepository, "report")
    private readonly marginHistoryRepoReport: MarginHistoryRepository,
    @InjectRepository(OrderRepository, "report")
    public readonly orderRepoReport: OrderRepository,
    @InjectRepository(TradeRepository, "report")
    private tradeRepoReport: TradeRepository,
    private readonly redisClient: RedisClient,

    @InjectRepository(UserStatisticRepository, "report")
    private userStatisticRepoReport: UserStatisticRepository,

    @InjectRepository(PositionHistoriesTmpRepository, "master")
    private positionHistoriesTmpRepoMaster: PositionHistoriesTmpRepository,
  ) 

  {}

  /**
   * Get position history for a user and pair.
   * @param email User's email
   * @param pair Trading pair (e.g., BTCUSDT)
   */
  @Get("/extract-user-position-history")
  public async extractUserPositionHistory(
    @Query("email") email: string,
    @Query("pair") pair: string,
    @Query("exportFile") exportFile: string = "YES"
  ) {
    // 1. Get accountId from email
    const account = await this.accountRepoReport.findOne({
      where: { userEmail: email, asset: USDT },
    });
    if (!account) {
      throw new Error("Account not found");
    }

    // 2. Get positionId from pair and accountId
    const position = await this.positionRepoReport.findOne({
      where: { accountId: account.id, symbol: pair },
    });
    if (!position) {
      return [];
    }

    // 3. Get margin histories for this accountId and positionId, sorted
    const marginHistories = await this.marginHistoryRepoReport.find({
      where: { accountId: account.id, positionId: position.id },
      order: { operationId: "ASC", id: "ASC" },
    });

    const positions = [];
    let positionBuffer: any = {
      pair,
      side: null,
      openOrClosedOrParClosedPosition: "CLOSED",
      pnl: null,
      uPnl: new BigNumber(0),
      entryPrice: null,
      closedPrice: null,
      avgClosedPrice: null,
      maxSize: new BigNumber(0),
      leverage: new Set<number>(),
      maxMargin: null,
      openTime: null,
      closeTime: null,
      sumClosedPrice: new BigNumber(0),
      totalClosedOrder: new BigNumber(0),
    };
    let lastCheckOrder: OrderEntity = null;

    for (const marginHistory of marginHistories) {
      //   const {
      //     // currentQty,
      //     // currentQtyAfter,
      //     // entryPriceAfter,
      //     // entryValueAfter,
      //     // leverage,
      //     // leverageAfter,
      //     // createdAt,
      //     // orderId,
      //     // action,
      //     // closedPrice,
      //   } = marginHistory;

      const marginHistoryCurrentQty = new BigNumber(marginHistory.currentQty);
      const marginHistoryCurrentQtyAfter = new BigNumber(marginHistory.currentQtyAfter);
      const marginHistoryEntryPrice = new BigNumber(marginHistory.entryPrice);
      const marginHistoryEntryPriceAfter = new BigNumber(marginHistory.entryPriceAfter);
      const marginHistoryEntryValueAfter = new BigNumber(marginHistory.entryValueAfter);

      // If opening a new position
      if (marginHistoryCurrentQty.isEqualTo(0) && !marginHistoryCurrentQtyAfter.isEqualTo(0)) {
        positionBuffer.openOrClosedOrParClosedPosition = "OPEN";
        positionBuffer.entryPrice = marginHistoryEntryPriceAfter;
        positionBuffer.openTime = marginHistory.createdAt;
      }

      if (positionBuffer.openOrClosedOrParClosedPosition === "CLOSED") {
        continue;
      }

      // Determine side and maxSize
      if (positionBuffer.maxSize == null || positionBuffer.maxSize.isEqualTo(0))
        positionBuffer.maxSize = marginHistoryEntryValueAfter.abs();
      if (positionBuffer.maxSize.isLessThan(marginHistoryEntryValueAfter.abs()))
        positionBuffer.maxSize = marginHistoryEntryValueAfter.abs();

      if (marginHistoryCurrentQtyAfter.isLessThan(0)) {
        positionBuffer.side = "SHORT";
      } else if (marginHistoryCurrentQtyAfter.isGreaterThan(0)) {
        positionBuffer.side = "LONG";
      }

      positionBuffer.leverage.add(marginHistory.leverage);
      const margin = marginHistoryEntryValueAfter.abs().dividedBy(marginHistory.leverageAfter || 1);
      if (positionBuffer.maxMargin == null || positionBuffer.maxMargin.isEqualTo(0)) positionBuffer.maxMargin = margin;
      if (positionBuffer.maxMargin.isLessThan(margin)) positionBuffer.maxMargin = margin;

      // Check for closing (full or partial)
      const isClosing =
        (positionBuffer.side === "LONG" && marginHistoryCurrentQtyAfter.isLessThan(marginHistoryCurrentQty)) ||
        (positionBuffer.side === "SHORT" && marginHistoryCurrentQtyAfter.isGreaterThan(marginHistoryCurrentQty));

      if (isClosing) {
        if (!lastCheckOrder || Number(lastCheckOrder.id) !== Number(marginHistory.orderId)) {
          // Find order
          lastCheckOrder = await this.orderRepoReport.findOne({
            where: { id: Number(marginHistory.orderId) },
          });
          positionBuffer.sumClosedPrice = positionBuffer.sumClosedPrice.plus(lastCheckOrder?.executedPrice || "0");
          positionBuffer.totalClosedOrder = positionBuffer.totalClosedOrder.plus(1);

          // Find trades and sum realized PnL
          if (marginHistory.action === "MATCHING_BUY") {
            const trades = await this.tradeRepoReport.find({
              where: { buyOrderId: Number(marginHistory.orderId) },
            });
            positionBuffer.uPnl = positionBuffer.uPnl.plus(
              trades.reduce((sum, t) => sum.plus(new BigNumber(t.realizedPnlOrderBuy || 0)), new BigNumber(0))
            );
          } else if (marginHistory.action === "MATCHING_SELL") {
            const trades = await this.tradeRepoReport.find({
              where: { sellOrderId: Number(marginHistory.orderId) },
            });
            positionBuffer.uPnl = positionBuffer.uPnl.plus(
              trades.reduce((sum, t) => sum.plus(new BigNumber(t.realizedPnlOrderSell || 0)), new BigNumber(0))
            );
          }
        }

        if (!marginHistoryCurrentQtyAfter.isEqualTo(0)) {
          positionBuffer.openOrClosedOrParClosedPosition = "PARTIALLY_CLOSED";
        } else {
          // Fully closed
          positionBuffer.openOrClosedOrParClosedPosition = "CLOSED";
          positionBuffer.pnl = positionBuffer.uPnl;
          positionBuffer.uPnl = null;
          positionBuffer.closedPrice = lastCheckOrder ? lastCheckOrder.executedPrice : null;
          positionBuffer.closeTime = marginHistory.createdAt;

          // Convert leverage Set to Array for output
          const outputBuffer = {
            ...positionBuffer,
            leverage: Array.from(positionBuffer.leverage).map((l) => Number(l)),
            entryPrice: positionBuffer.entryPrice.toString(),
            uPnl: positionBuffer.uPnl ? positionBuffer.uPnl.toString() : null,
            pnl: positionBuffer.pnl ? positionBuffer.pnl.toString() : null,
            maxSize: positionBuffer.maxSize ? positionBuffer.maxSize.toString() : null,
            maxMargin: positionBuffer.maxMargin ? positionBuffer.maxMargin.toString() : null,
            avgClosedPrice: positionBuffer.sumClosedPrice.multipliedBy(positionBuffer.totalClosedOrder).toString(),
            sumClosedPrice: positionBuffer.sumClosedPrice.toString(),
            totalClosedOrder: positionBuffer.totalClosedOrder.toString(),
          };
          positions.push(outputBuffer);

          // Reset positionBuffer
          positionBuffer = {
            pair,
            side: null,
            openOrClosedOrParClosedPosition: "CLOSED",
            pnl: null,
            uPnl: new BigNumber(0),
            entryPrice: null,
            closedPrice: null,
            avgClosedPrice: null,
            maxSize: new BigNumber(0),
            leverage: new Set<number>(),
            maxMargin: null,
            openTime: null,
            closeTime: null,
            sumClosedPrice: new BigNumber(0),
            totalClosedOrder: new BigNumber(0),
          };
          lastCheckOrder = null;
        }
      }
    }

    if (positionBuffer.openOrClosedOrParClosedPosition !== "CLOSED") {
      positions.push({
        ...positionBuffer,
        leverage: Array.from(positionBuffer.leverage).map((l) => Number(l)),
        entryPrice: positionBuffer.entryPrice.toString(),
        uPnl: positionBuffer.uPnl ? positionBuffer.uPnl.toString() : null,
        pnl: positionBuffer.pnl ? positionBuffer.pnl.toString() : null,
        maxSize: positionBuffer.maxSize ? positionBuffer.maxSize.toString() : null,
        maxMargin: positionBuffer.maxMargin ? positionBuffer.maxMargin.toString() : null,
        avgClosedPrice: positionBuffer.sumClosedPrice.multipliedBy(positionBuffer.totalClosedOrder).toString(),
        sumClosedPrice: positionBuffer.sumClosedPrice.toString(),
        totalClosedOrder: positionBuffer.totalClosedOrder.toString(),
      });
    }

    for (const position of positions) {
      position.userId = account.userId;
      position.email = account.userEmail;
      position.leverage = Array.isArray(position.leverage) ? position.leverage.join(";") : position.leverage;
    }

    if (exportFile === "NO") {
      return positions;
    }

    ///////////////////////////////////////////////////////////////////
    // Convert positions to CSV file
    const fields = [
      "pair",
      "side",
      "openOrClosedOrParClosedPosition",
      "pnl",
      "uPnl",
      "entryPrice",
      "closedPrice",
      "avgClosedPrice",
      "maxSize",
      "leverage",
      "maxMargin",
      "openTime",
      "closeTime",
      "sumClosedPrice",
      "totalClosedOrder",
    ];

    // Helper to escape CSV values
    function escapeCsvValue(value: any): string {
      if (value === null || value === undefined) return "";
      let str = String(value);
      if (str.includes('"')) str = str.replace(/"/g, '""');
      if (str.includes(",") || str.includes("\n") || str.includes('"')) {
        str = `"${str}"`;
      }
      return str;
    }

    // Build CSV header
    let csv = fields.join(",") + "\n";

    // Build CSV rows
    for (const pos of positions) {
      const row = fields.map((field) => {
        let val = pos[field];
        // For leverage, which is an array, join as string
        if (field === "leverage" && Array.isArray(val)) {
          val = val.join(";");
        }
        return escapeCsvValue(val);
      });
      csv += row.join(",") + "\n";
    }

    // Return as file download (assuming NestJS/Express)
    // Set headers for CSV download
    // If you want to return the CSV as a file download:
    // (Uncomment the following lines if you have access to res object)
    // res.setHeader('Content-Type', 'text/csv');
    // res.setHeader('Content-Disposition', 'attachment; filename="positions.csv"');
    // res.send(csv);

    // Otherwise, return as string in response
    return { data: csv };
  }

  @Get("sync-data-to-tmp-position-histories")
  public async insertDataToTmpPositionHistories() {
    const redisClient = this.redisClient.getInstance();
    const redisLockSyncKey = `lock:sync_tmp_position_histories`;
    const lastSyncTimeKey = "last_sync_tmp_position_histories";
    const lastSyncTime = new Date().getTime();

    // Use SET with NX and EX for atomic check-and-set
    const setResult = await redisClient.set(redisLockSyncKey, "1", "EX", Number.MAX_SAFE_INTEGER, "NX");
    if (setResult !== "OK") {
      throw new BadRequestException("Please wait before making another sync request.");
    }

    try {
      const lastSyncTimeCache = await redisClient.get(lastSyncTimeKey);

      const userEmailsWillBeSync = await this.userStatisticRepoReport
        .createQueryBuilder("us")
        .select(["u.email email"])
        .leftJoin("users", "u", "u.id = us.id")
        .where(`us.updatedAt >= '${new Date(Number(lastSyncTimeCache)).toISOString()}'`)
        .getRawMany();

      const emails = userEmailsWillBeSync.map((re) => re.email);

      const symbols = [
        "BTCUSDT",
        "ETHUSDT",
        "BNBUSDT",
        "LTCUSDT",
        "XRPUSDT",
        "SOLUSDT",
        "TRXUSDT",
        "MATICUSDT",
        "LINKUSDT",
        "MANAUSDT",
        "FILUSDT",
        "ATOMUSDT",
        "AAVEUSDT",
        "DOGEUSDT",
        "DOTUSDT",
        "UNIUSDT",
        "BTCUSD",
        "ETHUSD",
        "BNBUSD",
        "LTCUSD",
        "XRPUSD",
        "SOLUSD",
        "TRXUSD",
        "MATICUSD",
        "LINKUSD",
        "MANAUSD",
        "FILUSD",
        "ATOMUSD",
        "AAVEUSD",
        "DOGEUSD",
        "DOTUSD",
        "UNIUSD",
        "ADAUSDT",
        "SUIUSDT",
        "AVAXUSDT",
        "XLMUSDT",
        "TONUSDT",
        "1000SHIBUSDT",
        "1000PEPEUSDT",
        "TRUMPUSDT",
        "RENDERUSDT",
        "ONDOUSDT",
        "HBARUSDT",
        "ADAUSD",
        "SUIUSD",
        "AVAXUSD",
        "XLMUSD",
        "TONUSD",
        "1000SHIBUSD",
        "1000PEPEUSD",
        "TRUMPUSD",
        "RENDERUSD",
        "ONDOUSD",
        "HBARUSD",
      ];

      for (const email of emails) {
        for (const symbol of symbols) {
          const positionHistories: any = await this.extractUserPositionHistory(email, symbol, 'NO');

          for (const pH of positionHistories) {
            const id = `${pH.userId}_${pH.openTime?.getTime()}_${pH.pair}_${pH.side}_${pH.maxSize}`;
            try {
              const query = `
                  INSERT INTO position_histories_tmp (
                  id,
                  userId,
                  openTime,
                  pair,
                  side,
                  maxSize,
                  openOrClosedOrParClosedPosition,
                  pnl,
                  uPnl,
                  entryPrice,
                  closedPrice,
                  avgClosedPrice,
                  leverage,
                  maxMargin,
                  closeTime,
                  sumClosedPrice,
                  totalClosedOrder,
                  email
                ) VALUES (
                  '${id}',
                  '${pH.userId}',
                  ${pH.openTime ? `'${pH.openTime.toISOString().slice(0, 19).replace("T", " ")}'` : null},
                  '${pH.pair}',
                  '${pH.side}',
                  ${pH.maxSize ? `${pH.maxSize}` : null},
                  '${pH.openOrClosedOrParClosedPosition ? `${pH.openOrClosedOrParClosedPosition}` : null}',
                  ${pH.pnl ? `${pH.pnl}` : null},
                  ${pH.uPnl ? `${pH.uPnl}` : null},
                  ${pH.entryPrice ? `${pH.entryPrice}` : null},
                  ${pH.closedPrice ? `${pH.closedPrice}` : null},
                  ${pH.avgClosedPrice ? `${pH.avgClosedPrice}` : null},
                  '${pH.leverage ? `${pH.leverage}` : null}',
                  ${pH.maxMargin ? `${pH.maxMargin}` : null},
                  ${pH.closeTime ? `'${pH.closeTime.toISOString().slice(0, 19).replace("T", " ")}'` : null},
                  ${pH.sumClosedPrice ? `${pH.sumClosedPrice}` : null},
                  ${pH.totalClosedOrder ? `${pH.totalClosedOrder}` : null},
                  '${pH.email ? `${pH.email}` : null}'
                )
                ON DUPLICATE KEY UPDATE
                  pair = VALUES(pair),
                  side = VALUES(side),
                  maxSize = VALUES(maxSize),
                  openOrClosedOrParClosedPosition = VALUES(openOrClosedOrParClosedPosition),
                  pnl = VALUES(pnl),
                  uPnl = VALUES(uPnl),
                  entryPrice = VALUES(entryPrice),
                  closedPrice = VALUES(closedPrice),
                  avgClosedPrice = VALUES(avgClosedPrice),
                  leverage = VALUES(leverage),
                  maxMargin = VALUES(maxMargin),
                  closeTime = VALUES(closeTime),
                  sumClosedPrice = VALUES(sumClosedPrice),
                  totalClosedOrder = VALUES(totalClosedOrder);
              `;
              console.log(`insert ... id: ${id}`);

              await this.positionHistoriesTmpRepoMaster.query(query);
            } catch (err) {
              if (err.code === "ER_DUP_ENTRY") {
                console.warn(`Duplicate entry — update values...: ${id}`);
              } else {
                throw err; // rethrow unknown errors
              }
            }
          }
        }
      }

      // set last sync time if success
      await redisClient.set(lastSyncTimeKey, lastSyncTime);

      return "kqwen cha na`";
    } catch (error) {
      throw error;
    } finally {
      await redisClient.del(redisLockSyncKey);
    }
  }

  
}

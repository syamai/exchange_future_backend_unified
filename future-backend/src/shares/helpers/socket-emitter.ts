import { Logger } from "@nestjs/common";
import { Emitter } from "@socket.io/redis-emitter";
import { createClient } from "redis";
import { redisConfig } from "src/configs/redis.config";
import { AccountEntity } from "src/models/entities/account.entity";
import { OrderEntity } from "src/models/entities/order.entity";
import { PositionEntity } from "src/models/entities/position.entity";
import { TradeEntity } from "src/models/entities/trade.entity";
import { EventGateway } from "src/modules/events/event.gateway";
import { Notification } from "src/modules/matching-engine/matching-engine.const";
import { OrderbookResponse } from "src/modules/orderbook/orderbook.const";
import { Ticker } from "src/modules/ticker/ticker.const";

export class SocketEmitter {
  private static instance: SocketEmitter;
  public io;
  private logger: Logger;

  private constructor() {
    const redisClient = createClient(redisConfig.port, redisConfig.host);
    this.io = new Emitter(redisClient);
    this.logger = new Logger(SocketEmitter.name);
  }

  public static getInstance(): SocketEmitter {
    if (!SocketEmitter.instance) {
      SocketEmitter.instance = new SocketEmitter();
    }
    return SocketEmitter.instance;
  }

  // public emitTrades(trades: TradeEntity[], symbol: string): void {
  //   this.io
  //     .to(EventGateway.getTradesRoom(symbol))
  //     .emit(`trades_${symbol}`, trades);
  // }

  public emitOrderbook(orderbookResponse: OrderbookResponse, symbol: string, tickSize?: string): void {
    // send every 1 second
    // const currentHandleTime = Date.now();
    // if (this.lastHandleTimes[symbol] && currentHandleTime - this.lastHandleTimes[symbol] < 1000) {
    //   return;
    // }
    // this.lastHandleTimes[symbol] = currentHandleTime;

    const currentOrderbookData = Buffer.from(
      JSON.stringify({ ...orderbookResponse, symbol }),
      "binary"
    );

    // // Compare current data with the last emitted data
    // if (this.lastOrderbookData[symbol] &&
    //   this.lastOrderbookData[symbol].equals(currentOrderbookData)
    // ) {
    //   // this.logger.log(`No changes in orderbook for ${symbol}, skipping emit.`);
    //   return; 
    // }
    // this.lastOrderbookData[symbol] = currentOrderbookData;

    // this.io
    //   .to(EventGateway.getOrderbookRoom(symbol))
    //   .emit(`orderbook_${symbol}_${tickSize}`, currentOrderbookData);

      this.io
      .to(`orderbook_${symbol}`)
      .emit(`orderbook_${symbol}_${tickSize}`, currentOrderbookData);

    // this.logger.log(`Orderbook for ${symbol} emitted successfully.`);
  }

  public emitOrderbookNew(orderbookResponse: OrderbookResponse, symbol: string, tickSize?: string): void {
    // send every 1 second
    // const currentHandleTime = Date.now();
    // if (this.lastHandleTimes[symbol] && currentHandleTime - this.lastHandleTimes[symbol] < 1000) {
    //   return;
    // }
    // this.lastHandleTimes[symbol] = currentHandleTime;

    const currentOrderbookData = Buffer.from(
      JSON.stringify({ ...orderbookResponse, symbol }),
      "binary"
    );

    // // Compare current data with the last emitted data
    // if (this.lastOrderbookData[symbol] &&
    //   this.lastOrderbookData[symbol].equals(currentOrderbookData)
    // ) {
    //   // this.logger.log(`No changes in orderbook for ${symbol}, skipping emit.`);
    //   return; 
    // }
    // this.lastOrderbookData[symbol] = currentOrderbookData;

    const symbolAndTickSize = `${symbol}_${tickSize}`;
    this.io
      .to(`orderbook_${symbolAndTickSize}`)
      .emit(`orderbook_${symbolAndTickSize}`, currentOrderbookData);

    // this.logger.log(`Orderbook for ${symbol} emitted successfully.`);
  }

  public emitTickers(tickers: Ticker[]): void {
    this.io.emit(`tickers`, tickers);
  }

  public emitAccount(userId: number, account: AccountEntity): void {
    this.io.to(`${userId}`).emit(`balance`, account);
  }

  public emitPosition(position: PositionEntity, userId: number): void {
    this.io.to(`${userId}`).emit(`position`, position);
  }

  public emitOrders(orders: OrderEntity[], userId: number): void {
    // console.log({ orders, userId });
    this.io.to(`${userId}`).emit(`orders`, orders);
  }

  public emitNotifications(
    notifications: Notification[],
    userId: number
  ): void {
    this.io.to(`${userId}`).emit(`notifications`, notifications);
  }

  public emitAdjustLeverage(adjustLeverage, userId: number): void {
    this.io.to(`${userId}`).emit(`adjust-leverage`, adjustLeverage);
  }

  public emitAdjustMarginPosition(marginPosition, userId: number): void {
    this.io.to(`${userId}`).emit(`margin-position`, marginPosition);
    // console.log({ userId, marginPosition });
  }
}

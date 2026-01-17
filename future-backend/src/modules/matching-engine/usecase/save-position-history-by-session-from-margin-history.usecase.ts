import { Injectable, Logger } from "@nestjs/common";
import { LinkedQueue } from "src/utils/linked-queue";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { MarginHistoryEntity } from "src/models/entities/margin-history";
import { convertDateFields } from "../helper";
import { InjectRepository } from "@nestjs/typeorm";
import { BotInMemoryService } from "src/modules/bot/bot.in-memory.service";
import { v4 as uuidv4 } from "uuid";
import { AccountRepository } from "src/models/repositories/account.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { PositionRepository } from "src/models/repositories/position.repository";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { UserMarginModeService } from "src/modules/user-margin-mode/user-margin-mode.service";
import { PositionHistoryBySessionRepository } from "src/models/repositories/position-history-by-session.repository";
import { PositionHistoryBySessionEntity } from "src/models/entities/position_history_by_session.entity";
import BigNumber from "bignumber.js";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { In } from "typeorm";
import { OrderWithPositionHistoryBySessionRepository } from "src/models/repositories/order-with-position-history-by-session.repository";
import { OrderWithPositionHistoryBySessionEntity } from "src/models/entities/order_with_position_history_by_session.entity";

@Injectable()
export class SavePositionHistoryBySessionFromMarginHistoryUseCase {
  constructor(
    private readonly botInMemoryService: BotInMemoryService,
    @InjectRepository(AccountRepository, "master")
    private readonly accountRepoMaster: AccountRepository,
    @InjectRepository(UserRepository, "master")
    private readonly userRepoMaster: UserRepository,
    @InjectRepository(PositionRepository, "master")
    private readonly positionRepoMaster: PositionRepository,
    @InjectRepository(InstrumentRepository, "master")
    private readonly instrumentRepoMaster: InstrumentRepository,
    private readonly userMarginModeService: UserMarginModeService,
    @InjectRepository(PositionHistoryBySessionRepository, "master")
    private readonly positionHistoryBySessionRepoMaster: PositionHistoryBySessionRepository,
    @InjectRepository(OrderWithPositionHistoryBySessionRepository, "master")
    private readonly orderWithPositionHistoryBySessionRepoMaster: OrderWithPositionHistoryBySessionRepository,
    private readonly redisClient: RedisClient
  ) {}
  private readonly logger = new Logger(
    SavePositionHistoryBySessionFromMarginHistoryUseCase.name
  );

  private readonly saveQueue = new LinkedQueue<any>();
  private saveInterval = null;
  private readonly MAX_SAVE_QUEUE_SIZE = 100000;
  private readonly cachedNotClosePhbs = new Map<string, PositionHistoryBySessionEntity>(); // key: positionId

  private isIntervalHandlerRunningSet: Set<string> = new Set();
  private shouldStopConsumer: boolean = false;
  private checkExitInterval = null;
  private firstTimeConsumeMessage: number = null;

  public async execute(commands: CommandOutput[]): Promise<void> {
    if (this.shouldStopConsumer) {
      await new Promise((res) => setTimeout(res, 2 ** 31 - 1));
    }

    this.checkHaveStopCommand(
      commands,
      CommandCode.STOP_SAVE_POSITION_HISTORY_BY_SESSION_FROM_MARGIN_HISTORY
    );
    this.setSaveInterval();
    this.setCheckExitInterval();

    //check max size of margin histories queue
    while (this.saveQueue.size() >= this.MAX_SAVE_QUEUE_SIZE) {
      this.logger.log(`Save queue size: ${this.saveQueue.size()}`);
      await new Promise((resolve) => setTimeout(resolve, 50));
    }
    for (const c of commands) {
      if (!c.marginHistories || c.marginHistories?.length == 0) continue;
      this.saveQueue.enqueue(c.marginHistories);
    }
  }

  private setSaveInterval() {
    if (!this.saveInterval) {
      this.saveInterval = setInterval(async () => {
        await this.intervalHandler();
      }, 50);
    }
  }

  private async intervalHandler() {
    const ssid = uuidv4();
    if (this.isIntervalHandlerRunningSet.size > 0) return;
    this.isIntervalHandlerRunningSet.add(ssid);

    const batch = 5000;
    const saveMarginsToProcess = [];
    while (saveMarginsToProcess.length < batch && !this.saveQueue.isEmpty()) {
      saveMarginsToProcess.push(this.saveQueue.dequeue());
    }

    const marginHistoriesToProcess: MarginHistoryEntity[] = [];
    for (const marginHistories of saveMarginsToProcess) {
      for (const margin of marginHistories) {
        const newMargin = convertDateFields(new MarginHistoryEntity(), margin);
        if (newMargin.accountId == null) continue;
        const isBot: boolean = await this.botInMemoryService.checkIsBotAccountId(
          Number(newMargin.accountId)
        );
        if (isBot) continue;
        if (
          newMargin.action !== "MATCHING_BUY" &&
          newMargin.action !== "MATCHING_SELL"
        )
          continue;

        marginHistoriesToProcess.push(newMargin);
        console.log(`newMargin=${newMargin.id}`);
      }
    }

    // Process margin history
    marginHistoriesToProcess.length > 0? this.logger.log(JSON.stringify(marginHistoriesToProcess)): 0;
    marginHistoriesToProcess.sort((a, b) => {
      if (a.id == null && b.id == null) return 0;
      if (a.id == null) return 1;
      if (b.id == null) return -1;
      return Number(a.id) - Number(b.id);
    });

    for (const marginHistory of marginHistoriesToProcess) {
      const marginHistoryCurrentQty = new BigNumber(marginHistory.currentQty);
      const marginHistoryCurrentQtyAfter = new BigNumber(marginHistory.currentQtyAfter);
      
      // Open position 
      if (
        marginHistoryCurrentQty.eq(0) &&
        !marginHistoryCurrentQtyAfter.eq(0)
      ) {
        await this.handleOpenPosition({ marginHistory });
      }

      // User had the position - order is matched - position is not reversed
      if (!marginHistoryCurrentQty.eq(0) && marginHistoryCurrentQtyAfter.multipliedBy(marginHistoryCurrentQty).isGreaterThanOrEqualTo(0)) {
        await this.handleMatchOrder({ marginHistory });
      }

      // Position is reversed
      if (marginHistoryCurrentQty.multipliedBy(marginHistoryCurrentQtyAfter).isLessThan(0)) {
        await this.handleReversePosition({ marginHistory });
      }
    }

    this.isIntervalHandlerRunningSet.delete(ssid);
  }

  private async handleOpenPosition(data: { marginHistory: MarginHistoryEntity }) {
    const { marginHistory } = data;
    const accountEntity = await this.accountRepoMaster.findOne({ where: { id: marginHistory.accountId } });
    const userEntity = await this.userRepoMaster.findOne(accountEntity.userId);
    let positionEntity;
    for (let retryCount = 0; retryCount < 3; retryCount++) {
      positionEntity = await this.positionRepoMaster.findOne({ where: { id: marginHistory.positionId } });
      if (positionEntity) break;
      await new Promise((resolve) => setTimeout(resolve, 1000 * (retryCount + 1)));
    }
    const instrument = await this.instrumentRepoMaster.findOne({ where: { symbol: positionEntity.symbol } });
    if (!instrument) {
      this.logger.warn(`Instrument null symbol=${positionEntity.symbol}`);
      return;
    }

    const marginModeObj = await this.userMarginModeService.getCachedMarginMode(userEntity.id, instrument.id);
    this.logger.debug(`marginModeObj: ${JSON.stringify(marginModeObj)}, typeof ${typeof marginModeObj}`);
    const marginMode = marginModeObj?.marginMode || "ISOLATE";

    // --- Calculate values ---
    const entryValueAfter = new BigNumber((marginHistory.entryValueAfter as any) || 0);
    const leverageAfter = new BigNumber((marginHistory.leverageAfter as any) || 1);
    const absEntryValueAfter = entryValueAfter.abs();
    const marginCalc = absEntryValueAfter.dividedBy(leverageAfter).toFixed();
    const currentQtyAfter = new BigNumber((marginHistory.currentQtyAfter as any) || 0);
    const openFee = new BigNumber((marginHistory.openFee as any) || 0);
    const now = new Date();

    // --- Create PositionHistoryBySessionEntity ---
    const phbs = new PositionHistoryBySessionEntity();
    phbs.userId = userEntity.id;
    phbs.accountId = accountEntity.id;
    phbs.userEmail = userEntity.email;
    phbs.positionId = positionEntity.id;
    phbs.openTime = new Date(marginHistory.createdAt);
    phbs.closeTime = null;
    phbs.symbol = positionEntity.symbol;
    phbs.leverages = marginHistory.leverageAfter;
    phbs.marginMode = marginMode;
    phbs.side = currentQtyAfter.gt(0) ? "LONG" : "SHORT";
    phbs.sumEntryPrice = marginHistory.entryPriceAfter as any;
    phbs.numOfOpenOrders = 1;
    phbs.sumClosePrice = "0";
    phbs.numOfCloseOrders = 0;
    phbs.minMargin = marginCalc;
    phbs.maxMargin = marginCalc;
    phbs.sumMargin = marginCalc;
    phbs.minSize = currentQtyAfter.toFixed();
    phbs.maxSize = currentQtyAfter.toFixed();
    phbs.minValue = entryValueAfter.toFixed();
    phbs.maxValue = entryValueAfter.toFixed();
    phbs.pnl = "0";
    phbs.profit = "0";
    phbs.fee = openFee.toFixed();
    phbs.fundingFee = "0";
    phbs.openingFee = openFee.toFixed();
    phbs.closingFee = "0";
    phbs.pnlRate = "0";
    phbs.status = "OPEN";
    phbs.createdAt = now;
    phbs.updatedAt = now;

    // --- Save to DB and cache ---
    const savedPhbs = await this.positionHistoryBySessionRepoMaster.save(phbs);
    this.cachedNotClosePhbs.set(savedPhbs.positionId.toString(), savedPhbs);

    // --- Create OrderWithPositionHistoryBySessionEntity ---
    await this.createOrderWithPositionHistoryBySessionForOpen({ marginHistory, positionHistoryBySessionId: savedPhbs.id });
  }

  private async handleMatchOrder(data: { marginHistory: MarginHistoryEntity }) {
    const { marginHistory } = data;

    // Get positionHistoryBySession
    let positionHistoryBySession: PositionHistoryBySessionEntity = this.cachedNotClosePhbs.get(marginHistory.positionId.toString());
    if (!positionHistoryBySession) {
      positionHistoryBySession = await this.positionHistoryBySessionRepoMaster.findOne({
        where: {
          positionId: marginHistory.positionId,
          status: In(["OPEN", "PARTIAL_CLOSED"])
        }
      });
      if (!positionHistoryBySession) {
        this.logger.warn(`positionHistoryBySession is null positionId=${marginHistory.positionId}`);
        return;
      }
    }

    const leverages = new Set(positionHistoryBySession.leverages? positionHistoryBySession.leverages.split(','): []).add(marginHistory.leverage);
    positionHistoryBySession.leverages = Array.from(leverages.values()).join(',');

    const marginHistoryCurrentQty = new BigNumber(marginHistory.currentQty);
    const marginHistoryCurrentQtyAfter = new BigNumber(marginHistory.currentQtyAfter);
    
    // Lệnh được khớp để tiếp tục tăng position.curentQty - open order
    if (marginHistoryCurrentQtyAfter.abs().isGreaterThan(marginHistoryCurrentQty.abs())) {
      positionHistoryBySession = await this.matchOrderForOpen({ marginHistory, positionHistoryBySession });
    }

    // Lệnh được khớp để đóng position - close order
    if (marginHistoryCurrentQtyAfter.abs().isLessThan(marginHistoryCurrentQty.abs())) {
      positionHistoryBySession = await this.matchOrderForClose({ marginHistory, positionHistoryBySession });
    }

    // Save positionHistoryBySession
    const savedPhbs = await this.positionHistoryBySessionRepoMaster.save(positionHistoryBySession);
    if (savedPhbs.status === "CLOSED") {
      this.cachedNotClosePhbs.delete(savedPhbs.positionId.toString());
    } else {
      this.cachedNotClosePhbs.set(savedPhbs.positionId.toString(), savedPhbs);
    }
  }

  private async handleReversePosition(data: { marginHistory: MarginHistoryEntity }) {
    await this.handleClosePositionHistoryBySessionWhenReverse(data);
    await this.handleOpenPosition(data);
  }

  private async createOrderWithPositionHistoryBySessionForOpen(data: { marginHistory: MarginHistoryEntity, positionHistoryBySessionId: number }) {
    const { marginHistory, positionHistoryBySessionId } = data;
    const entryValueAfter = new BigNumber((marginHistory.entryValueAfter as any) || 0);
    const leverageAfter = new BigNumber((marginHistory.leverageAfter as any) || 1);
    const absEntryValueAfter = entryValueAfter.abs();
    const marginCalc = absEntryValueAfter.dividedBy(leverageAfter).toFixed();
    const openFee = new BigNumber((marginHistory.openFee as any) || 0);
    const tradePrice = new BigNumber((marginHistory.tradePrice as any) || 0);

    const orderWithPhbs = new OrderWithPositionHistoryBySessionEntity();
    orderWithPhbs.orderId = String(marginHistory.orderId);
    orderWithPhbs.positionHistoryBySessionId = String(positionHistoryBySessionId);
    orderWithPhbs.orderMarginAfter = marginCalc;
    orderWithPhbs.entryPriceAfter = marginHistory.entryPriceAfter as any;
    orderWithPhbs.currentQtyAfter = marginHistory.currentQtyAfter as any;
    orderWithPhbs.entryValueAfter = marginHistory.entryValueAfter as any;
    orderWithPhbs.isOpenOrder = true;
    orderWithPhbs.fee = openFee.toFixed();
    orderWithPhbs.closeFee = "0";
    orderWithPhbs.openFee = openFee.toFixed();
    orderWithPhbs.profit = "0";
    orderWithPhbs.tradePriceAfter = tradePrice.toFixed();

    // --- Save to DB ---
    return await this.orderWithPositionHistoryBySessionRepoMaster.save(orderWithPhbs);
  }

  private async createOrderWithPositionHistoryBySessionForClose(data: { marginHistory: MarginHistoryEntity, positionHistoryBySessionId: number }) {
    const { marginHistory, positionHistoryBySessionId } = data;
    const entryValueAfter = new BigNumber((marginHistory.entryValueAfter as any) || 0);
    const leverageAfter = new BigNumber((marginHistory.leverageAfter as any) || 1);
    const absEntryValueAfter = entryValueAfter.abs();
    const marginCalc = absEntryValueAfter.dividedBy(leverageAfter).toFixed();
    const closeFee = new BigNumber((marginHistory.closeFee as any) || 0);
    const tradePrice = new BigNumber((marginHistory.tradePrice as any) || 0);
    const realizedPnl = new BigNumber((marginHistory.realizedPnl as any) || 0);

    const orderWithPhbs = new OrderWithPositionHistoryBySessionEntity();
    orderWithPhbs.orderId = String(marginHistory.orderId);
    orderWithPhbs.positionHistoryBySessionId = String(positionHistoryBySessionId);
    orderWithPhbs.orderMarginAfter = marginCalc;
    orderWithPhbs.entryPriceAfter = marginHistory.entryPriceAfter as any;
    orderWithPhbs.currentQtyAfter = marginHistory.currentQtyAfter as any;
    orderWithPhbs.entryValueAfter = marginHistory.entryValueAfter as any;
    orderWithPhbs.isOpenOrder = false;
    orderWithPhbs.fee = closeFee.toFixed();
    orderWithPhbs.closeFee = closeFee.toFixed();
    orderWithPhbs.openFee = "0";
    orderWithPhbs.profit = realizedPnl.toFixed();
    orderWithPhbs.tradePriceAfter = tradePrice.toFixed();

    // --- Save to DB ---
    return await this.orderWithPositionHistoryBySessionRepoMaster.save(orderWithPhbs);
  }

  private async matchOrderForOpen(data: { marginHistory: MarginHistoryEntity, positionHistoryBySession: PositionHistoryBySessionEntity }) {
    const { marginHistory, positionHistoryBySession } = data;
    const marginHistoryCurrentQtyAfter = new BigNumber(marginHistory.currentQtyAfter);
    const marginHistoryEntryValueAfter = new BigNumber(marginHistory.entryValueAfter);
    const marginHistoryLeverageAfter = new BigNumber(marginHistory.leverageAfter);
    const marginHistoryEntryPriceAfter = new BigNumber(marginHistory.entryPriceAfter);
    const marginHistoryFee = new BigNumber(marginHistory.fee);
    const marginHistoryOpenFee = new BigNumber(marginHistory.openFee);
    const marginHistoryTradePrice = new BigNumber(marginHistory.tradePrice);


    // Tìm orderWithPositionHistoryBySession theo orderId=marginHistory.orderId và positionHistoryBySessionId
    let orderWithPositionHistoryBySession = await this.orderWithPositionHistoryBySessionRepoMaster.findOne({ where: {
      orderId: marginHistory.orderId,
      positionHistoryBySessionId: positionHistoryBySession.id
    }});
    if (!orderWithPositionHistoryBySession) {
      orderWithPositionHistoryBySession = await this.createOrderWithPositionHistoryBySessionForOpen({ marginHistory, positionHistoryBySessionId: positionHistoryBySession.id });
    } else {
      orderWithPositionHistoryBySession.orderMarginAfter = marginHistoryEntryValueAfter.abs().dividedBy(marginHistoryLeverageAfter).toFixed();
      orderWithPositionHistoryBySession.entryPriceAfter = marginHistoryEntryPriceAfter.toFixed();
      orderWithPositionHistoryBySession.currentQtyAfter = marginHistoryCurrentQtyAfter.toFixed();
      orderWithPositionHistoryBySession.entryValueAfter = marginHistoryEntryValueAfter.toFixed();
      orderWithPositionHistoryBySession.fee = new BigNumber(orderWithPositionHistoryBySession.fee).plus(marginHistoryFee).toFixed();
      orderWithPositionHistoryBySession.openFee = new BigNumber(orderWithPositionHistoryBySession.openFee).plus(marginHistoryOpenFee).toFixed();
      orderWithPositionHistoryBySession.tradePriceAfter = marginHistoryTradePrice.toFixed();
      orderWithPositionHistoryBySession = await this.orderWithPositionHistoryBySessionRepoMaster.save(orderWithPositionHistoryBySession);
    }

    // Tìm toàn bộ orderWithPositionHistoryBySessions theo orderWithPositionHistoryBySession.positionHistoryBySessionId=positionHistoryBySession.id và isOpenOrder=true
    const orderWithPositionHistoryBySessions = await this.orderWithPositionHistoryBySessionRepoMaster
      .createQueryBuilder("ophbs")
      .where('ophbs.positionHistoryBySessionId = :positionHistoryBySessionId', { positionHistoryBySessionId: positionHistoryBySession.id })
      .andWhere('ophbs.isOpenOrder = :isOpenOrder', { isOpenOrder: true })
      .getMany();

    // Convert values to BigNumber
    const entryPrices = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.entryPriceAfter ?? 0));
    const margins = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.orderMarginAfter ?? 0));
    const sizes = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.currentQtyAfter ?? 0).abs());
    const values = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.entryValueAfter ?? 0).abs());
    const fees = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.fee ?? 0).abs());
    const openFees = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.openFee ?? 0).abs());

    positionHistoryBySession.numOfOpenOrders = orderWithPositionHistoryBySessions.length;
    // Sum
    positionHistoryBySession.sumEntryPrice = entryPrices.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.sumMargin = margins.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();

    // Min/Max
    positionHistoryBySession.minMargin = margins.reduce((min, v) => v.lt(min) ? v : min, margins[0] ?? new BigNumber(0)).toFixed();
    positionHistoryBySession.maxMargin = margins.reduce((max, v) => v.gt(max) ? v : max, margins[0] ?? new BigNumber(0)).toFixed();

    positionHistoryBySession.minSize = sizes.reduce((min, v) => v.lt(min) ? v : min, sizes[0] ?? new BigNumber(0)).multipliedBy(positionHistoryBySession.side === "LONG"? 1: -1).toFixed();
    positionHistoryBySession.maxSize = sizes.reduce((max, v) => v.gt(max) ? v : max, sizes[0] ?? new BigNumber(0)).multipliedBy(positionHistoryBySession.side === "LONG"? 1: -1).toFixed();

    positionHistoryBySession.minValue = values.reduce((min, v) => v.lt(min) ? v : min, values[0] ?? new BigNumber(0)).multipliedBy(positionHistoryBySession.side === "LONG"? 1: -1).toFixed();
    positionHistoryBySession.maxValue = values.reduce((max, v) => v.gt(max) ? v : max, values[0] ?? new BigNumber(0)).multipliedBy(positionHistoryBySession.side === "LONG"? 1: -1).toFixed();

    // Fee & Opening Fee
    positionHistoryBySession.fee = fees.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.openingFee = openFees.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    return positionHistoryBySession;
  }

  private async matchOrderForClose(data: { marginHistory: MarginHistoryEntity, positionHistoryBySession: PositionHistoryBySessionEntity }) {
    const { marginHistory, positionHistoryBySession } = data;
    const marginHistoryCurrentQtyAfter = new BigNumber(marginHistory.currentQtyAfter);
    const marginHistoryEntryValueAfter = new BigNumber(marginHistory.entryValueAfter);
    const marginHistoryLeverageAfter = new BigNumber(marginHistory.leverageAfter);
    const marginHistoryEntryPriceAfter = new BigNumber(marginHistory.entryPriceAfter);
    const marginHistoryFee = new BigNumber(marginHistory.fee);
    const marginHistoryCloseFee = new BigNumber(marginHistory.closeFee);
    const marginHistoryTradePrice = new BigNumber(marginHistory.tradePrice);
    const marginHistoryRealizedPnl = new BigNumber(marginHistory.realizedPnl);

    // Tìm orderWithPositionHistoryBySession theo orderId=marginHistory.orderId và positionHistoryBySessionId
    let orderWithPositionHistoryBySession = await this.orderWithPositionHistoryBySessionRepoMaster.findOne({ where: {
      orderId: marginHistory.orderId,
      positionHistoryBySessionId: positionHistoryBySession.id
    }});
    if (!orderWithPositionHistoryBySession) {
      orderWithPositionHistoryBySession = await this.createOrderWithPositionHistoryBySessionForClose({ marginHistory, positionHistoryBySessionId: positionHistoryBySession.id });
    } else {
      orderWithPositionHistoryBySession.orderMarginAfter = marginHistoryEntryValueAfter.abs().dividedBy(marginHistoryLeverageAfter).toFixed();
      orderWithPositionHistoryBySession.entryPriceAfter = marginHistoryEntryPriceAfter.toFixed();
      orderWithPositionHistoryBySession.currentQtyAfter = marginHistoryCurrentQtyAfter.toFixed();
      orderWithPositionHistoryBySession.entryValueAfter = marginHistoryEntryValueAfter.toFixed();
      orderWithPositionHistoryBySession.fee = new BigNumber(orderWithPositionHistoryBySession.fee).plus(marginHistoryFee).toFixed();
      orderWithPositionHistoryBySession.closeFee = new BigNumber(orderWithPositionHistoryBySession.closeFee).plus(marginHistoryCloseFee).toFixed();
      orderWithPositionHistoryBySession.profit = new BigNumber(orderWithPositionHistoryBySession.profit).plus(marginHistoryRealizedPnl).toFixed();
      orderWithPositionHistoryBySession.tradePriceAfter = marginHistoryTradePrice.toFixed();
      orderWithPositionHistoryBySession = await this.orderWithPositionHistoryBySessionRepoMaster.save(orderWithPositionHistoryBySession);
    }

    // Tìm toàn bộ orderWithPositionHistoryBySessions theo orderWithPositionHistoryBySession.positionHistoryBySessionId=positionHistoryBySession.id
    const orderWithPositionHistoryBySessions = await this.orderWithPositionHistoryBySessionRepoMaster
      .createQueryBuilder("ophbs")
      .where('ophbs.positionHistoryBySessionId = :positionHistoryBySessionId', { positionHistoryBySessionId: positionHistoryBySession.id })
      .getMany();

    positionHistoryBySession.closeTime = new Date(marginHistory.createdAt);
    const closedOrders = orderWithPositionHistoryBySessions.filter(o => o.isOpenOrder === false);
    const closePrices = closedOrders.map(o => new BigNumber(o.tradePriceAfter ?? 0));
    const profits = closedOrders.map(o => new BigNumber(o.profit ?? 0));
    const fees = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.fee ?? 0));
    const closeFees = closedOrders.map(o => new BigNumber(o.fee ?? 0));

    positionHistoryBySession.sumClosePrice = closePrices.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.numOfCloseOrders = closedOrders.length;
    positionHistoryBySession.profit = profits.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.fee = fees.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.closingFee = closeFees.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.pnl = new BigNumber(positionHistoryBySession.profit).minus(positionHistoryBySession.fee).toFixed();

    const maxMargin = new BigNumber(positionHistoryBySession.maxMargin ?? 0);
    positionHistoryBySession.pnlRate = maxMargin.isZero()
      ? new BigNumber(0).toFixed()
      : new BigNumber(positionHistoryBySession.pnl).dividedBy(maxMargin).multipliedBy(100).toFixed();

    positionHistoryBySession.status =
      marginHistoryCurrentQtyAfter.isZero() ? "CLOSED" : "PARTIAL_CLOSED";
    return positionHistoryBySession;
  }

  private async handleClosePositionHistoryBySessionWhenReverse(data: { marginHistory: MarginHistoryEntity }) {
    const { marginHistory } = data;

    // Get positionHistoryBySession
    let positionHistoryBySession: PositionHistoryBySessionEntity = this.cachedNotClosePhbs.get(marginHistory.positionId.toString());
    if (!positionHistoryBySession) {
      positionHistoryBySession = await this.positionHistoryBySessionRepoMaster.findOne({
        where: {
          positionId: marginHistory.positionId,
          status: In(["OPEN", "PARTIAL_CLOSED"])
        }
      });
      if (!positionHistoryBySession) {
        this.logger.warn(`positionHistoryBySession is null positionId=${marginHistory.positionId}`);
        return;
      }
    }

    const leverages = new Set(positionHistoryBySession.leverages? positionHistoryBySession.leverages.split(','): []).add(marginHistory.leverage);
    positionHistoryBySession.leverages = Array.from(leverages.values()).join(',');

    const marginHistoryEntryPrice = new BigNumber(marginHistory.entryPrice);
    const marginHistoryOpenFee = new BigNumber(marginHistory.openFee);
    const marginHistoryTradePrice = new BigNumber(marginHistory.tradePrice);
    const leverageAfter = new BigNumber((marginHistory.leverageAfter as any) || 1);
    const entryValue = new BigNumber((marginHistory.entryValue as any) || 0);
    const absEntryValue = entryValue.abs();
    const marginCalc = absEntryValue.dividedBy(leverageAfter).toFixed();
    const closeFee = new BigNumber((marginHistory.closeFee as any) || 0);
    const tradePrice = new BigNumber((marginHistory.tradePrice as any) || 0);

    // Tìm orderWithPositionHistoryBySession theo orderId=marginHistory.orderId và positionHistoryBySessionId
    let orderWithPositionHistoryBySession = await this.orderWithPositionHistoryBySessionRepoMaster.findOne({ where: {
      orderId: marginHistory.orderId,
      positionHistoryBySessionId: positionHistoryBySession.id
    }});
    if (!orderWithPositionHistoryBySession) {
      const orderWithPhbs = new OrderWithPositionHistoryBySessionEntity();
      orderWithPhbs.orderId = String(marginHistory.orderId);
      orderWithPhbs.positionHistoryBySessionId = String(positionHistoryBySession.id);
      orderWithPhbs.orderMarginAfter = marginCalc;
      orderWithPhbs.entryPriceAfter = marginHistory.entryPrice as any;
      orderWithPhbs.currentQtyAfter = "0";
      orderWithPhbs.entryValueAfter = "0";
      orderWithPhbs.isOpenOrder = false;
      orderWithPhbs.fee = closeFee.toFixed();
      orderWithPhbs.closeFee = closeFee.toFixed();
      orderWithPhbs.openFee = "0";
      orderWithPhbs.profit = marginHistory.realizedPnl as any;
      orderWithPhbs.tradePriceAfter = tradePrice.toFixed();
      orderWithPositionHistoryBySession = orderWithPhbs;
    } else {
      orderWithPositionHistoryBySession.orderMarginAfter = marginCalc;
      orderWithPositionHistoryBySession.entryPriceAfter = marginHistoryEntryPrice.toFixed();
      orderWithPositionHistoryBySession.currentQtyAfter = "0";
      orderWithPositionHistoryBySession.entryValueAfter = "0";
      orderWithPositionHistoryBySession.fee = new BigNumber(orderWithPositionHistoryBySession.closeFee).plus(marginHistoryOpenFee).toFixed();
      orderWithPositionHistoryBySession.closeFee = new BigNumber(orderWithPositionHistoryBySession.closeFee).plus(marginHistoryOpenFee).toFixed();
      orderWithPositionHistoryBySession.tradePriceAfter = marginHistoryTradePrice.toFixed();
    }
    orderWithPositionHistoryBySession = await this.orderWithPositionHistoryBySessionRepoMaster.save(orderWithPositionHistoryBySession);

    // Tìm toàn bộ orderWithPositionHistoryBySessions theo orderWithPositionHistoryBySession.positionHistoryBySessionId=positionHistoryBySession.id
    const orderWithPositionHistoryBySessions = await this.orderWithPositionHistoryBySessionRepoMaster
      .createQueryBuilder("ophbs")
      .where('ophbs.positionHistoryBySessionId = :positionHistoryBySessionId', { positionHistoryBySessionId: positionHistoryBySession.id })
      .getMany();

    positionHistoryBySession.closeTime = new Date(marginHistory.createdAt);
    const closedOrders = orderWithPositionHistoryBySessions.filter(o => o.isOpenOrder === false);
    const closePrices = closedOrders.map(o => new BigNumber(o.tradePriceAfter ?? 0));
    const profits = closedOrders.map(o => new BigNumber(o.profit ?? 0));
    const fees = orderWithPositionHistoryBySessions.map(o => new BigNumber(o.fee ?? 0));
    const closeFees = closedOrders.map(o => new BigNumber(o.fee ?? 0));

    positionHistoryBySession.sumClosePrice = closePrices.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.numOfCloseOrders = closedOrders.length;
    positionHistoryBySession.profit = profits.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.fee = fees.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.closingFee = closeFees.reduce((sum, v) => sum.plus(v), new BigNumber(0)).toFixed();
    positionHistoryBySession.pnl = new BigNumber(positionHistoryBySession.profit).minus(positionHistoryBySession.fee).toFixed();

    const maxMargin = new BigNumber(positionHistoryBySession.maxMargin ?? 0);
    positionHistoryBySession.pnlRate = maxMargin.isZero()
      ? new BigNumber(0).toFixed()
      : new BigNumber(positionHistoryBySession.pnl).dividedBy(maxMargin).multipliedBy(100).toFixed();

    positionHistoryBySession.status = "CLOSED";
    const savedPhbs = await this.positionHistoryBySessionRepoMaster.save(positionHistoryBySession);
    this.cachedNotClosePhbs.delete(savedPhbs.positionId.toString());
    return positionHistoryBySession;
  }

  private setCheckExitInterval() {
    if (this.shouldStopConsumer && !this.checkExitInterval) {
      this.checkExitInterval = setInterval(async () => {
        this.checkExitIntervalHandler();
      }, 500);
    }
  }

  private checkExitIntervalHandler() {
    if (
      this.isIntervalHandlerRunningSet.size === 0 &&
      this.saveQueue.isEmpty()
    ) {
      this.logger.log(`Exit consumer!`);
      process.exit(0);
    }
  }

  private checkHaveStopCommand(
    commands: CommandOutput[],
    stopCommandCode: string
  ) {
    if (!this.firstTimeConsumeMessage)
      this.firstTimeConsumeMessage = Date.now();
    if (
      commands.find((c) => c.code == stopCommandCode) &&
      Date.now() - this.firstTimeConsumeMessage > 10000 // at least 10s from firstTimeConsumeMessage
    ) {
      this.shouldStopConsumer = true;
      this.logger.log(`shouldStopConsumer = true`);
    }
  }
}

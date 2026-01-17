import { Injectable, Logger } from "@nestjs/common";
import { BigNumber as BigNumberJS } from "bignumber.js";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import { Connection, In, InsertResult } from "typeorm";
import { BigNumber, Event as EtherEvent } from "ethers";
import * as config from "config";
import {
  ActionType,
  BalanceValidStatus,
  DexLiquidationSide,
  DexTransactionStatus,
  MatchAction,
} from "src/modules/dex/dex.constant";
import { sleep } from "src/shares/helpers/utils";
import { Dex } from "src/configs/dex.config";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import {
  CommandCode,
  CommandOutput,
} from "src/modules/matching-engine/matching-engine.const";
import { DexActionRepository } from "src/models/repositories/dex-action.repository";
import { utils } from "ethers";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { AccountRepository } from "src/models/repositories/account.repository";
import { TransactionStatus } from "src/shares/enums/transaction.enum";
import { DexActionTransactionRepository } from "src/models/repositories/dex-action-transaction.repository";
import { LatestBlockService } from "src/modules/latest-block/latest-block.service";
import { DexActionHistoryRepository } from "src/models/repositories/dex-action-history-repository";
import { FundingHistoryRepository } from "src/models/repositories/funding-history.repository";

const {
  defaultScale,
  blockTimeInMs,
  dexContract,
  chainId,
  matcherWallet,
  provider,
  collateralDecimal,
  actionBatchSize,
} = Dex;
const BLOCK_CONFIRM = Number(config.get<number>("dex.block_confirm"));
const DEX_START_BLOCK = Number(config.get<number>("dex.start_block"));
const TOLERANCE_AMOUNT = new BigNumberJS(0.000015);
const DEX_MAX_IN_PROGRESS_ACTION = Number(
  config.get<number>("dex.max_in_progress_action")
);

@Injectable()
export class DexService {
  private instrumentIds = new Map<string, string>();
  private accountIdsToAddresses = new Map<string, string>();
  private accountAddressesToIds = new Map<string, string>();
  private accountIdsToUserIds = new Map<string, string>();

  constructor(
    private readonly logger: Logger,
    private readonly latestBlockService: LatestBlockService,
    @InjectConnection("master")
    private masterConnection: Connection,
    @InjectRepository(MarginHistoryRepository, "report")
    private reportMarginHistoryRepo: MarginHistoryRepository,
    @InjectRepository(TransactionRepository, "master")
    public readonly transactionRepoMaster: TransactionRepository,
    @InjectRepository(TransactionRepository, "report")
    public readonly reportTransactionRepo: TransactionRepository,
    @InjectRepository(InstrumentRepository, "report")
    private reportInstrumentRepo: InstrumentRepository,
    @InjectRepository(DexActionRepository, "master")
    public readonly dexActionRepo: DexActionRepository,
    @InjectRepository(DexActionRepository, "report")
    public readonly reportDexActionRepo: DexActionRepository,
    @InjectRepository(DexActionTransactionRepository, "master")
    public readonly dexActionTransactionRepo: DexActionTransactionRepository,
    @InjectRepository(DexActionTransactionRepository, "report")
    public readonly reportDexActionTransactionRepo: DexActionTransactionRepository,
    @InjectRepository(DexActionHistoryRepository, "master")
    public readonly dexActionHistoryRepo: DexActionHistoryRepository,
    @InjectRepository(DexActionHistoryRepository, "report")
    public readonly reportDexActionHistoryRepo: DexActionHistoryRepository,
    @InjectRepository(AccountRepository, "report")
    private reportAccountRepo: AccountRepository,
    @InjectRepository(FundingHistoryRepository, "report")
    private reportFundingHistoryRepo: FundingHistoryRepository
  ) {}

  async saveDexActions(
    offset: string,
    commands: CommandOutput[]
  ): Promise<InsertResult | null> {
    const dexActions = [];
    for (const command of commands) {
      if (
        command.code === CommandCode.WITHDRAW &&
        command.data.status === TransactionStatus.APPROVED
      ) {
        dexActions.push(
          await this._withdrawalToDexAction(offset, command.data)
        );
        continue;
      }
      if (
        command.code === CommandCode.PAY_FUNDING &&
        command.fundingHistories
      ) {
        dexActions.push(
          ...(await this._fundingsToDexActions(
            offset,
            command.fundingHistories
          ))
        );
        continue;
      }
      if (command.trades && command.trades.length) {
        dexActions.push(
          ...(await this._tradesToDexActions(offset, command.trades))
        );
      }
    }

    if (dexActions) {
      return this.dexActionRepo.insertIgnore(dexActions);
    }
    return null;
  }

  private async _fundingsToDexActions(offset, fundingHistories) {
    const dexActions = [];
    for (const fundingHistory of fundingHistories) {
      const dexParameter = {
        id: fundingHistory.id,
        operationId: fundingHistory.operationId,
        user: await this._getAccountAddress(fundingHistory.accountId),
        amount: utils
          .parseUnits(fundingHistory.amount.toString(), defaultScale)
          .toString(),
      };
      dexActions.push({
        action: ActionType.FUNDING,
        actionId: fundingHistory.id,
        kafkaOffset: offset,
        rawParameter: fundingHistory,
        dexParameter,
      });
    }
    return dexActions;
  }

  private async _withdrawalToDexAction(offset, data) {
    const dexParameter = {
      id: data.id,
      operationId: data.operationId,
      user: await this._getAccountAddress(data.accountId),
      amount: utils
        .parseUnits(data.amount.toString(), collateralDecimal)
        .toString(),
      fee: utils
        .parseUnits((data.fee || 0).toString(), collateralDecimal)
        .toString(),
    };

    return {
      action: ActionType.WITHDRAW,
      actionId: data.id,
      kafkaOffset: offset,
      rawParameter: data,
      dexParameter,
    };
  }

  private async _tradesToDexActions(offset: string, trades: any) {
    const dexActions = [];
    for (const trade of trades) {
      const instrumentId = await this._getInstrumentId(trade.symbol);

      let liquidationSide = DexLiquidationSide.NONE;
      let bankruptPrice = utils.parseUnits("0", defaultScale);
      let bankruptFee = utils.parseUnits("0", defaultScale);
      if (trade.buyOrder.note === "LIQUIDATION") {
        liquidationSide = DexLiquidationSide.BUY;
        bankruptPrice = utils.parseUnits(trade.buyOrder.price, defaultScale);
        bankruptFee = utils.parseUnits(
          new BigNumberJS(trade.buyOrder.price)
            .times(trade.quantity)
            .times(trade.buyFeeRate)
            .toFixed(defaultScale, BigNumberJS.ROUND_DOWN),
          defaultScale
        );
      } else if (trade.sellOrder.note === "LIQUIDATION") {
        liquidationSide = DexLiquidationSide.SELL;
        bankruptPrice = utils.parseUnits(trade.sellOrder.price, defaultScale);
        bankruptFee = utils.parseUnits(
          new BigNumberJS(trade.sellOrder.price)
            .times(trade.quantity)
            .times(trade.sellFeeRate)
            .toFixed(defaultScale, BigNumberJS.ROUND_DOWN),
          defaultScale
        );
      }

      const dexParameter = {
        id: trade.id,
        operationId: trade.operationId,
        // fake
        buyer: "F3PwpjjRK6ENxtGP6CXWdQrx6x2kdobKCvDcTc4Bh8rF",
        seller: "F3PwpjjRK6ENxtGP6CXWdQrx6x2kdobKCvDcTc4Bh8rF",
        // buyer: await this._getAccountAddress(trade.buyAccountId),
        // seller: await this._getAccountAddress(trade.sellAccountId),
        quantity: utils
          .parseUnits(trade.quantity.toString(), defaultScale)
          .toString(),
        price: utils
          .parseUnits(trade.price.toString(), defaultScale)
          .toString(),
        bankruptPrice: bankruptPrice.toString(),
        liquidationSide,
        buyerFee: utils
          .parseUnits(trade.buyFee.toString(), defaultScale)
          .toString(),
        sellerFee: utils
          .parseUnits(trade.sellFee.toString(), defaultScale)
          .toString(),
        bankruptFee: bankruptFee.toString(),
        instrumentId,
      };

      dexActions.push({
        action: ActionType.TRADE,
        actionId: trade.id,
        kafkaOffset: offset,
        rawParameter: trade,
        dexParameter,
      });
    }
    return dexActions;
  }

  async handlePickDexActions() {
    let nonce = await provider.getTransactionCount(matcherWallet.address);
    this.logger.log(`Trade start, matcher nonce=${nonce}`);

    while (true) {
      const revertRecord = await this.reportDexActionTransactionRepo.findOne({
        select: ["id"],
        where: { status: DexTransactionStatus.REVERT },
      });
      if (revertRecord) {
        throw new Error(`${revertRecord.id} is reverted, need manual check`);
      }

      const sentRecords = await this.dexActionTransactionRepo.find({
        select: ["id"],
        where: {
          status: In([DexTransactionStatus.PENDING, DexTransactionStatus.SENT]),
        },
        skip: DEX_MAX_IN_PROGRESS_ACTION - 1,
        take: 1,
      });
      if (sentRecords.length) {
        this.logger.log(
          `DEX_MAX_IN_PROGRESS_ACTION=${DEX_MAX_IN_PROGRESS_ACTION}, wait`
        );
        await sleep(blockTimeInMs);
        continue;
      }

      const dexActions = await this.dexActionRepo.find({
        where: { dexActionTransactionId: 0 },
        take: actionBatchSize,
        order: { id: "ASC" },
        select: ["id", "action", "actionId", "dexParameter"],
      });
      if (dexActions.length === 0) {
        this.logger.log("No actions found");
        await sleep(blockTimeInMs);
        continue;
      }

      const abiArray = [];
      for (const dexAction of dexActions) {
        if (dexAction.action === ActionType.TRADE) {
          abiArray.push(
            dexContract.interface.encodeFunctionData("trade", [
              [dexAction.dexParameter],
            ])
          );
        } else if (dexAction.action === ActionType.WITHDRAW) {
          abiArray.push(
            dexContract.interface.encodeFunctionData("withdraw", [
              [dexAction.dexParameter],
            ])
          );
        } else if (dexAction.action === ActionType.FUNDING) {
          abiArray.push(
            dexContract.interface.encodeFunctionData("funding", [
              [dexAction.dexParameter],
            ])
          );
        }
      }

      const txData = await dexContract.populateTransaction.multicall(abiArray);
      const gasLimit = await dexContract.estimateGas.multicall(abiArray);
      const tx = {
        chainId,
        gasPrice: BigNumber.from(5000000000), // fix price for now
        gasLimit,
        nonce: BigNumber.from(nonce),
        ...txData,
      };
      const signedTx = await matcherWallet.signTransaction(tx);
      const txid = utils.keccak256(signedTx);

      await this.masterConnection.transaction(async (manager) => {
        const transactionDexActionTransactionRepo = await manager.getCustomRepository(
          DexActionTransactionRepository
        );
        const transactionDexActionRepo = await manager.getCustomRepository(
          DexActionRepository
        );
        const actionTx = await transactionDexActionTransactionRepo.insert({
          txid,
          matcherAddress: matcherWallet.address,
          nonce: nonce.toString(),
          rawTx: signedTx,
        });
        await transactionDexActionRepo.update(
          { id: In(dexActions.map((a) => a.id)) },
          { dexActionTransactionId: actionTx.identifiers[0].id }
        );
      });

      nonce++;
    }
  }

  async handleSendDexActions() {
    // retry for the first time
    await this._retrySentDexTxs();

    let loopTimes = 0;
    while (true) {
      // retry each 10 loop
      if (++loopTimes === 10) {
        await this._retrySentDexTxs();
        loopTimes = 0;
      }

      const revertRecord = await this.reportDexActionTransactionRepo.findOne({
        select: ["id"],
        where: { status: DexTransactionStatus.REVERT },
      });
      if (revertRecord) {
        throw new Error(`${revertRecord.id} is reverted, need manual check`);
      }

      const dexActionTransactions = await this.reportDexActionTransactionRepo.find(
        {
          where: { status: DexTransactionStatus.PENDING },
          take: DEX_MAX_IN_PROGRESS_ACTION,
          order: { id: "ASC" },
          select: ["id", "rawTx"],
        }
      );
      if (dexActionTransactions.length === 0) {
        this.logger.log("No actions found");
        await sleep(blockTimeInMs);
        continue;
      }

      // update status first, avoid sending to blockchain without updating state.
      await this.dexActionTransactionRepo.update(
        { id: In(dexActionTransactions.map((tx) => tx.id)) },
        { status: DexTransactionStatus.SENT }
      );
      try {
        await Promise.all(
          dexActionTransactions.map((tx) => provider.sendTransaction(tx.rawTx))
        );
      } catch (err) {
        await this._retrySentDexTxs();
        throw err;
      }
    }
  }

  async _retrySentDexTxs() {
    this.logger.log("_retrySentDexTxs");
    const sentTxs = await this.reportDexActionTransactionRepo.find({
      where: { status: DexTransactionStatus.SENT },
      select: ["id", "rawTx"],
    });
    if (sentTxs.length) {
      try {
        await Promise.all(
          sentTxs.map((tx) => provider.sendTransaction(tx.rawTx))
        );
      } catch {}
    }
  }

  async handleVerifyDexActions() {
    while (true) {
      const sentRecords = await this.reportDexActionTransactionRepo.find({
        where: { status: DexTransactionStatus.SENT },
        take: 10,
        select: ["id", "txid"],
      });
      if (sentRecords.length === 0) {
        this.logger.log("No actions found");
        await sleep(blockTimeInMs);
        continue;
      }

      const receipts = await Promise.all(
        sentRecords.map(async (record) => ({
          id: record.id,
          data: await provider.getTransactionReceipt(record.txid),
        }))
      );

      const successIds = [];
      const revertIds = [];
      for (const receipt of receipts) {
        if (!receipt.data) {
          continue;
        }

        if (receipt.data.status === 0) {
          revertIds.push(receipt.id);
        } else if (receipt.data.status === 1) {
          successIds.push(receipt.id);
        }
      }

      if (revertIds.length) {
        await this.dexActionTransactionRepo.update(
          { id: In(revertIds) },
          { status: DexTransactionStatus.REVERT }
        );
      }
      if (successIds.length) {
        await this.dexActionTransactionRepo.update(
          { id: In(successIds) },
          { status: DexTransactionStatus.SUCCESS }
        );
      }
    }
  }

  async handleHistoryDexActions() {
    const maxBlockPerLoop = 50;
    const queryTopics = [
      utils.id("UpdateMargin(uint8,uint64,uint64,address,int128,int128)"),
    ];
    const commandName = "dex-action-history";
    const latestBlockInDatabase = await this.latestBlockService.getLatestBlock(
      commandName
    );
    const latestBlock = Number(latestBlockInDatabase?.blockNumber || 0);
    let from = Math.max(latestBlock, DEX_START_BLOCK);

    const innerFunc = async () => {
      const safeLatestBlockOnChain =
        (await provider.getBlockNumber()) - BLOCK_CONFIRM;
      const to = Math.min(from + maxBlockPerLoop, safeLatestBlockOnChain);
      if (to <= from) {
        this.logger.log(`wait`);
        return;
      }
      const events = await dexContract.queryFilter(
        {
          address: dexContract.address,
          topics: queryTopics,
        },
        from,
        to
      );

      if (events.length === 0) {
        this.logger.log(`No events found from ${from} to ${to}`);
      } else {
        const withdrawEvents: EtherEvent[] = [];
        const histories = await Promise.all(
          events.map(async (event) => {
            if (event.args.actionType === 3) {
              withdrawEvents.push(event);
            }

            return {
              txid: event.transactionHash,
              logIndex: event.logIndex,
              address: event.args.user,
              accountId: await this._getAccountId(event.args.user),
              actionId: event.args.actionId.toString(),
              action: this.dexActionTypeToString(event.args.actionType),
              operationId: event.args.operationId.toString(),
              oldMargin: utils
                .formatUnits(event.args.oldMargin, defaultScale)
                .toString(),
              newMargin: utils
                .formatUnits(event.args.newMargin, defaultScale)
                .toString(),
            };
          })
        );

        if (withdrawEvents.length) {
          // await Promise.all(
          //   withdrawEvents.map((withdrawEvent) =>
          //     this.transactionRepoMaster.update(
          //       { id: withdrawEvent.args.actionId.toString() },
          //       { txHash: withdrawEvent.transactionHash, logIndex: withdrawEvent.logIndex },
          //     ),
          //   ),
          // );
          // await this._emitSuccessWithdrawalNotifications(withdrawEvents);
        }

        await this.dexActionHistoryRepo.insertIgnore(histories);
        this.logger.log(`Crawl done from ${from} to ${to}`);
      }
      from = to + 1;

      await this.latestBlockService.saveLatestBlock(commandName, to);
    };

    setInterval(innerFunc, blockTimeInMs);
    return new Promise(() => {});
  }

  async handleBalanceCheckerDexActions() {
    while (true) {
      const pendingRecords = await this.reportDexActionHistoryRepo.find({
        where: { validStatus: BalanceValidStatus.PENDING },
        take: 1,
      });
      if (pendingRecords.length === 0) {
        this.logger.log("No records found");
        await sleep(blockTimeInMs);
        continue;
      }

      for (const pendingRecord of pendingRecords) {
        if (pendingRecord.action === MatchAction.MATCHING_BUY) {
          const matchingBuy = await this.reportMarginHistoryRepo.findOne({
            where: {
              tradeId: pendingRecord.actionId,
              action: MatchAction.MATCHING_BUY,
            },
            select: ["id", "contractMargin", "tradeId"],
          });
          if (!matchingBuy) {
            throw new Error(
              `matchingBuy not found id ${pendingRecord.actionId}`
            );
          }

          const difference = new BigNumberJS(matchingBuy.contractMargin)
            .minus(pendingRecord.newMargin)
            .abs();
          if (difference.isGreaterThan(TOLERANCE_AMOUNT)) {
            throw new Error(`margin not match id ${pendingRecord.id}`);
          }
          await this.dexActionHistoryRepo.update(
            { id: pendingRecord.id },
            { validStatus: BalanceValidStatus.SUCCESS }
          );
        } else if (pendingRecord.action === MatchAction.MATCHING_SELL) {
          const matchingSell = await this.reportMarginHistoryRepo.findOne({
            where: {
              tradeId: pendingRecord.actionId,
              action: MatchAction.MATCHING_SELL,
            },
            select: ["id", "contractMargin", "tradeId"],
          });
          if (!matchingSell) {
            throw new Error(
              `matchingSell not found id ${pendingRecord.actionId}`
            );
          }

          const difference = new BigNumberJS(matchingSell.contractMargin)
            .minus(pendingRecord.newMargin)
            .abs();
          if (difference.isGreaterThan(TOLERANCE_AMOUNT)) {
            throw new Error(`margin not match id ${pendingRecord.id}`);
          }
          await this.dexActionHistoryRepo.update(
            { id: pendingRecord.id },
            { validStatus: BalanceValidStatus.SUCCESS }
          );
        } else if (pendingRecord.action === MatchAction.WITHDRAW) {
          const withdraw = await this.reportTransactionRepo.findOne({
            where: { id: pendingRecord.actionId },
            select: ["id", "amount"],
          });
          if (!withdraw) {
            throw new Error(`withdraw not found id ${pendingRecord.actionId}`);
          }
          if (
            !new BigNumberJS(withdraw.amount).isEqualTo(
              new BigNumberJS(pendingRecord.oldMargin).minus(
                pendingRecord.newMargin
              )
            )
          ) {
            throw new Error(`margin not match id ${pendingRecord.id}`);
          }
          await this.dexActionHistoryRepo.update(
            { id: pendingRecord.id },
            { validStatus: BalanceValidStatus.SUCCESS }
          );
        } else if (pendingRecord.action === MatchAction.FUNDING) {
          const fundingHistory = await this.reportFundingHistoryRepo.findOne({
            where: { id: pendingRecord.actionId },
            select: ["id", "amount"],
          });
          if (!fundingHistory) {
            throw new Error(
              `funding history not found id ${pendingRecord.actionId}`
            );
          }
          if (
            !new BigNumberJS(fundingHistory.amount).isEqualTo(
              new BigNumberJS(pendingRecord.newMargin).minus(
                pendingRecord.oldMargin
              )
            )
          ) {
            throw new Error(`margin not match id ${pendingRecord.id}`);
          }
          await this.dexActionHistoryRepo.update(
            { id: pendingRecord.id },
            { validStatus: BalanceValidStatus.SUCCESS }
          );
        }
      }
    }
  }

  private dexActionTypeToString(status: number) {
    const stringArray = [
      MatchAction.MATCHING_BUY,
      MatchAction.MATCHING_SELL,
      MatchAction.FUNDING,
      MatchAction.WITHDRAW,
    ];
    return stringArray[status];
  }

  private async _getInstrumentId(symbol: string) {
    if (this.instrumentIds.has(symbol)) {
      return this.instrumentIds.get(symbol);
    }

    const instrument = await this.reportInstrumentRepo.findOne({
      where: { symbol },
      select: ["id"],
    });
    if (!instrument) {
      throw new Error(`not found id for instrument symbol ${symbol}`);
    }
    this.instrumentIds.set(symbol, instrument.id.toString());
    return instrument.id;
  }

  private async _getAccountAddress(accountId: string) {
    if (this.accountIdsToAddresses.has(accountId)) {
      return this.accountIdsToAddresses.get(accountId);
    }

    const user = await this.reportAccountRepo
      .createQueryBuilder("Account")
      .innerJoin("users", "User", "User.id = Account.ownerId")
      .where("Account.id = :accountId")
      .setParameters({ accountId })
      .select(["User.address user_address", "Account.ownerId account_owner_id"])
      .getRawOne();
    if (!user) {
      throw new Error(`not found address for account id ${accountId}`);
    }
    this.accountIdsToAddresses.set(accountId, user.user_address);
    this.accountIdsToUserIds.set(accountId, user.account_owner_id);
    this.accountAddressesToIds.set(user.user_address, accountId);
    return user.user_address;
  }

  private async _getAccountOwnerId(accountId: string): Promise<string> {
    if (this.accountIdsToUserIds.has(accountId)) {
      return this.accountIdsToUserIds.get(accountId);
    }

    const user = await this.reportAccountRepo.findOne({
      where: { id: accountId },
      select: ["id"],
    });
    if (!user) {
      throw new Error(`not found address for account id ${accountId}`);
    }
    this.accountIdsToUserIds.set(accountId, user.id.toString());
    return user.id.toString();
  }

  private async _getAccountId(address: string) {
    if (this.accountAddressesToIds.has(address)) {
      return this.accountAddressesToIds.get(address);
    }

    const user = await this.reportAccountRepo
      .createQueryBuilder("Account")
      .innerJoin("users", "User", "User.id = Account.ownerId")
      .where("User.address = :address")
      .setParameters({ address })
      .select(["Account.id account_id"])
      .getRawOne();
    if (!user) {
      throw new Error(`not found account_id for ${address}`);
    }
    this.accountAddressesToIds.set(address, user.account_id);
    this.accountIdsToAddresses.set(user.account_id, address);
    return user.account_id;
  }

  // private async _emitSuccessWithdrawalNotifications(withdrawEvents: EtherEvent[]) {
  //   const notifications: { [key: string]: Notification[] } = {};
  //   for (const withdrawEvent of withdrawEvents) {
  //     // const accountId = await this._getAccountId(withdrawEvent.args.user);
  //     const userId = await this._getAccountOwnerId(accountId);
  //     const withdrawAmount = new BigNumberJS(withdrawEvent.args.oldMargin.toString()).minus(
  //       withdrawEvent.args.newMargin.toString(),
  //     );
  //     const withdrawAmountString = utils.formatUnits(withdrawAmount.toString(), defaultScale).toString();
  //     const notification = {
  //       event: NotificationEvent.WithdrawSuccessfully,
  //       type: NotificationType.success,
  //       title: 'Withdraw successfully!',
  //       message: `Amount: ${withdrawAmountString} USDC`,
  //     };

  //     if (notifications[userId]) {
  //       notifications[userId].push(notification);
  //     } else {
  //       notifications[userId] = [notification];
  //     }
  //   }

  //   for (const [userId, userNotifications] of Object.entries(notifications)) {
  //     await SocketEmitter.getInstance().emitNotifications(userNotifications, Number(userId));
  //   }
  // }
}

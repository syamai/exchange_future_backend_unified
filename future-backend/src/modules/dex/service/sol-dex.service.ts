import { Injectable, Logger } from "@nestjs/common";
import { InjectConnection, InjectRepository } from "@nestjs/typeorm";
import { bs58 } from "@project-serum/anchor/dist/cjs/utils/bytes";
import {
  Transaction as SolTransaction,
  TransactionInstruction,
} from "@solana/web3.js";
import { BigNumber as BigNumberJS } from "bignumber.js";
import * as config from "config";
import { utils } from "ethers";
import { chunk } from "lodash";
import { SolDex } from "src/configs/sol-dex.config";
import { DexAction } from "src/models/entities/dex-action-entity";
import { DexActionTransaction } from "src/models/entities/dex-action-transaction-entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { DexActionHistoryRepository } from "src/models/repositories/dex-action-history-repository";
import { DexActionSolTxRepository } from "src/models/repositories/dex-action-sol-txs.repository";
import { DexActionTransactionRepository } from "src/models/repositories/dex-action-transaction.repository";
import { DexActionRepository } from "src/models/repositories/dex-action.repository";
import { FundingHistoryRepository } from "src/models/repositories/funding-history.repository";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { LatestSignatureRepository } from "src/models/repositories/latest-signature.repository";
import { MarginHistoryRepository } from "src/models/repositories/margin-history.repository";
import { TransactionRepository } from "src/models/repositories/transaction.repository";
import {
  ActionType,
  BalanceValidStatus,
  DexTransactionStatus,
  MatchAction,
} from "src/modules/dex/dex.constant";
import { LatestBlockService } from "src/modules/latest-block/latest-block.service";
import { SotaDexWrapper } from "src/shares/helpers/sotadex-wrapper";
import { sleep } from "src/shares/helpers/utils";
import { Connection, In, MoreThan } from "typeorm";

const {
  defaultScale,
  blockTimeInMs,
  matcherWallet,
  matcherKeypair,
  dexProgram,
  actionBatchSize,
  dexId,
  connection,
  finalizedConnection,
  processedConnection,
  usdcId,
} = SolDex;
const TOLERANCE_AMOUNT = new BigNumberJS(0.000015);
const DEX_MAX_IN_PROGRESS_ACTION = Number(
  config.get<number>("sol_dex.max_in_progress_action")
);

@Injectable()
export class SolDexService {
  private instrumentIds = new Map<string, string>();
  private accountIdsToAddresses = new Map<string, string>();
  private accountAddressesToIds = new Map<string, string>();
  private accountIdsToUserIds = new Map<string, string>();
  private readonly dexWrapper: SotaDexWrapper;

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
    private reportFundingHistoryRepo: FundingHistoryRepository,
    @InjectRepository(DexActionSolTxRepository, "master")
    private dexActionSolTxRepo: DexActionSolTxRepository,
    @InjectRepository(DexActionSolTxRepository, "report")
    private reportDexActionSolTxRepo: DexActionSolTxRepository,
    @InjectRepository(LatestSignatureRepository, "master")
    private latestSignatureRepo: LatestSignatureRepository,
    @InjectRepository(LatestSignatureRepository, "report")
    private reportLatestSignatureRepo: LatestSignatureRepository
  ) {
    this.dexWrapper = new SotaDexWrapper(dexProgram, dexId, usdcId);
  }

  async handlePickDexActions() {
    this.logger.log(
      `Trade start, matcher address=${matcherWallet.publicKey.toBase58()}`
    );

    while (true) {
      const dexActions = await this.dexActionRepo.find({
        where: { dexActionTransactionId: 0 },
        take: actionBatchSize * DEX_MAX_IN_PROGRESS_ACTION,
        order: { id: "ASC" },
        select: ["id", "action", "actionId", "dexParameter"],
      });
      if (dexActions.length === 0) {
        this.logger.log("No actions found");
        await sleep(1000);
        continue;
      }

      const actionChunks = chunk(dexActions, actionBatchSize);
      await Promise.all(
        actionChunks.map((chunk) => this._handlePickDexActions(chunk))
      );
      await sleep(500);
    }
  }

  async handleSendDexActions() {
    while (true) {
      const dexActionTransactions = await this.dexActionTransactionRepo.find({
        where: { status: DexTransactionStatus.PENDING },
        take: DEX_MAX_IN_PROGRESS_ACTION * 2,
        order: { id: "ASC" },
        select: ["id", "rawTx", "status", "txid"],
      });
      if (dexActionTransactions.length === 0) {
        this.logger.log("No actions found");
        await sleep(1000);
        continue;
      }
      const blockHash = (await connection.getLatestBlockhash()).blockhash;

      await Promise.all(
        dexActionTransactions.map((tx) =>
          this._handleSendDexActions(tx, blockHash)
        )
      );

      this.logger.log("handleSendDexActions finish");
      await sleep(500);
    }
  }

  async handleVerifyDexActions() {
    while (true) {
      const sentRecords = await this.reportDexActionTransactionRepo.find({
        where: { status: DexTransactionStatus.SENT },
        take: DEX_MAX_IN_PROGRESS_ACTION,
        select: ["id", "txid"],
      });
      if (sentRecords.length === 0) {
        this.logger.log("No actions found");
        await sleep(1000);
        continue;
      }

      const receipts = await Promise.all(
        sentRecords.map(async (record) => ({
          id: record.id,
          data: await connection.getTransaction(record.txid),
        }))
      );
      const successIds = [];
      const revertIds = [];
      let isAnyEmpty = false;
      for (const receipt of receipts) {
        if (!receipt.data) {
          this.logger.error(
            `DexActionTransaction id=${receipt.id} not found, continue to next loop`
          );
          isAnyEmpty = true;
          break;
        }

        if (receipt.data.meta.err) {
          revertIds.push(receipt.id);
        } else {
          successIds.push(receipt.id);
        }
      }

      if (isAnyEmpty) {
        await sleep(1000);
        continue;
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
      this.logger.log("Verify done");
    }
  }

  async handleCrawlSignature() {
    const service = "handleCrawlSignature";
    const [sotadexAccount] = await this.dexWrapper.getSotadexAccount();
    this.logger.log(
      `handleCrawlSignature sotadexAccount=${sotadexAccount.toBase58()}`
    );
    const latestSignatureRecord = await this.reportLatestSignatureRepo.findOne({
      service,
    });
    let latestSignature = latestSignatureRecord?.signature || null;
    const _dexActionSignature = async () => {
      let reversedSignatures = [];
      let begin = undefined;
      const limit = 100;
      while (true) {
        const options: any = { limit };
        if (begin) {
          options.before = begin;
        }
        const fetchedSignatures = await finalizedConnection.getSignaturesForAddress(
          sotadexAccount,
          options
        );
        reversedSignatures = reversedSignatures.concat(fetchedSignatures);
        this.logger.log(
          `Fetched ${fetchedSignatures.length} signatures, total: ${reversedSignatures.length}`
        );
        if (fetchedSignatures.length === limit) {
          const signatureFound = fetchedSignatures.find(
            (s) => s.signature === latestSignature
          );
          if (signatureFound) {
            break;
          }
        } else if (fetchedSignatures.length < limit) {
          if (latestSignature) {
            const signatureFound = fetchedSignatures.find(
              (s) => s.signature === latestSignature
            );
            if (!signatureFound) {
              throw new Error(
                `rpc endpoint does not have sufficient signature history to reach ${latestSignature}`
              );
            }
          }
          break;
        }
        begin = reversedSignatures[reversedSignatures.length - 1].signature;
        await sleep(500);
      }
      if (reversedSignatures.length === 0) {
        console.log("empty signatures");
        return;
      }
      const signatures = reversedSignatures.reverse();
      const fromIndex = signatures.findIndex(
        (s) => s.signature === latestSignature
      );
      const insertSignatures = signatures.slice(fromIndex + 1);
      await this.saveSignatures(insertSignatures, service);
      if (insertSignatures.length > 0) {
        latestSignature =
          insertSignatures[insertSignatures.length - 1].signature;
      }
    };
    while (true) {
      await _dexActionSignature();
      console.log({ latestSignature });
      await sleep(blockTimeInMs);
    }
  }

  async saveSignatures(
    signatures: { slot: string; signature: string }[],
    service: string
  ): Promise<void> {
    console.log(`Saving ${signatures.length} tx`);
    signatures = [...signatures];
    const batchSize = 10;
    while (signatures.length > 0) {
      const batch = signatures.splice(0, batchSize);
      let retryCount = 0;
      let batchWithLog;
      while (retryCount < 3) {
        retryCount++;
        try {
          batchWithLog = await Promise.all(
            batch.map(async (signature) => {
              const fetchTx = await finalizedConnection.getTransaction(
                signature.signature
              );
              return {
                slot: signature.slot,
                txid: signature.signature,
                logs: JSON.stringify(fetchTx.meta.logMessages),
              };
            })
          );
          break;
        } catch (e) {
          if (retryCount < 3) {
            console.log(e);
            await sleep(2000);
          } else {
            throw e;
          }
        }
      }

      await this.dexActionSolTxRepo.insertIgnore(batchWithLog);
      const latestSignature = batch[batch.length - 1].signature;
      await this.latestSignatureRepo.insertOnDuplicate(
        [{ service, signature: latestSignature }],
        ["signature", "updatedAt"],
        ["service"]
      );
    }
  }

  async handleHistoryDexActions() {
    const service = "handleHistoryDexActions";
    const limit = 100;
    this.logger.log(`handleHistoryDexActions limit=${limit}`);
    const latestSignatureRecord = await this.reportLatestSignatureRepo.findOne({
      service,
    });
    let latestSignature = latestSignatureRecord?.signature || null;

    const _dexActionEvent = async () => {
      let findOptions = {};
      if (latestSignature) {
        const signatureRecord = await this.reportDexActionSolTxRepo.findOne({
          txid: latestSignature,
        });
        if (!signatureRecord) {
          throw new Error(`${latestSignature} not found`);
        }
        findOptions = { id: MoreThan(signatureRecord.id) };
      }
      const solanaTransactions = await this.reportDexActionSolTxRepo.find({
        where: findOptions,
        take: limit,
        order: { id: "ASC" },
      });
      if (solanaTransactions.length === 0) {
        console.log("empty signatures");
        return;
      }

      const transformedEvents = [];
      const withdrawEvents = [];
      for (const solanaTransaction of solanaTransactions) {
        // if (transaction.meta.err) {
        //   continue;
        // }
        const logMessages = JSON.parse(solanaTransaction.logs);
        if (logMessages.includes("Log truncated")) {
          throw new Error(`Log truncated ${solanaTransaction.txid}`);
        }
        const events = this.dexWrapper.extractEvents(logMessages);
        if (events.length === 0) {
          console.log("no events");
          continue;
        }
        for (let i = 0; i < events.length; i++) {
          const currentEvent = events[i];
          if (currentEvent.name !== "UpdateMarginEvent") {
            continue;
          }
          const transformedEvent = {
            txid: solanaTransaction.txid,
            logIndex: i,
            address: currentEvent.data.user.toBase58(),
            accountId: await this._getAccountId(
              currentEvent.data.user.toBase58()
            ),
            actionId: currentEvent.data.actionId.toString(),
            action: this.dexActionTypeToString(currentEvent.data.actionType),
            operationId: currentEvent.data.operationId.toString(),
            oldMargin: utils
              .formatUnits(currentEvent.data.oldMargin.toString(), defaultScale)
              .toString(),
            newMargin: utils
              .formatUnits(currentEvent.data.newMargin.toString(), defaultScale)
              .toString(),
          };

          if (transformedEvent.action === MatchAction.WITHDRAW) {
            withdrawEvents.push(transformedEvent);
          }
          transformedEvents.push(transformedEvent);
        }
      }

      if (transformedEvents.length) {
        if (withdrawEvents.length) {
          // await Promise.all(
          //   withdrawEvents.map((withdrawEvent) =>
          //     this.transactionRepoMaster.update(
          //       { id: withdrawEvent.actionId },
          //       { txHash: withdrawEvent.txid, logIndex: withdrawEvent.logIndex },
          //     ),
          //   ),
          // );
          // await this._emitSuccessWithdrawalNotifications(withdrawEvents);
        }
        await this.dexActionHistoryRepo.insertIgnore(transformedEvents);
      }
      latestSignature = solanaTransactions[solanaTransactions.length - 1].txid;
      await this.latestSignatureRepo.insertOnDuplicate(
        [{ service, signature: latestSignature }],
        ["signature", "updatedAt"],
        ["service"]
      );
    };

    while (true) {
      await _dexActionEvent();
      console.log({ latestSignature });
      await sleep(blockTimeInMs);
    }
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

  private async _handlePickDexActions(dexActions: DexAction[]) {
    if (dexActions.length === 0) {
      return;
    }

    const instructions: TransactionInstruction[] = [];
    for (const dexAction of dexActions) {
      if (dexAction.action === ActionType.TRADE) {
        instructions.push(
          await this.dexWrapper.getTradeInstruction(dexAction.dexParameter)
        );
      } else if (dexAction.action === ActionType.WITHDRAW) {
        instructions.push(
          await this.dexWrapper.getWithdrawInstruction(dexAction.dexParameter)
        );
      } else if (dexAction.action === ActionType.FUNDING) {
        instructions.push(
          await this.dexWrapper.getFundingInstruction(dexAction.dexParameter)
        );
      }
    }
    const transaction = await this.dexWrapper.newTransaction(instructions);

    // const simulateTransaction = await dexProgram.provider.connection.simulateTransaction(transaction);
    // if (simulateTransaction.value.err) {
    //   throw new Error(JSON.stringify(simulateTransaction));
    // } else {
    //   console.log(simulateTransaction.value.logs);
    // }

    transaction.recentBlockhash =
      "HJip7nKatc4DWPfAVq6VHtRvwEvXk2pVtLJrTH7JgLHe";
    transaction.sign(matcherKeypair);
    const txid = bs58.encode(transaction.signature);
    const serializeTx = transaction.serialize();
    const rawTx = serializeTx.toString("base64");

    console.log(`transaction size = ${serializeTx.byteLength}`);

    await this.masterConnection.transaction(async (manager) => {
      const transactionDexActionTransactionRepo = await manager.getCustomRepository(
        DexActionTransactionRepository
      );
      const transactionDexActionRepo = await manager.getCustomRepository(
        DexActionRepository
      );
      const actionTx = await transactionDexActionTransactionRepo.insert({
        txid,
        matcherAddress: matcherWallet.publicKey.toBase58(),
        nonce: dexActions[0].id,
        rawTx,
      });
      await transactionDexActionRepo.update(
        { id: In(dexActions.map((a) => a.id)) },
        { dexActionTransactionId: actionTx.identifiers[0].id }
      );
    });
  }

  private async _handleSendDexActions(
    dexTransaction: DexActionTransaction,
    blockHash: string
  ) {
    const transactionExist = await processedConnection.getSignatureStatus(
      dexTransaction.txid,
      {
        searchTransactionHistory: true,
      }
    );
    if (transactionExist?.value && !transactionExist.value.err) {
      await this.dexActionTransactionRepo.update(
        { id: dexTransaction.id },
        { status: DexTransactionStatus.SENT }
      );
      return;
    }

    const rebuildTransaction = SolTransaction.from(
      Buffer.from(dexTransaction.rawTx, "base64")
    );
    const simulateTransaction = await processedConnection.simulateTransaction(
      rebuildTransaction
    );
    if (simulateTransaction.value.err) {
      throw new Error(JSON.stringify(simulateTransaction));
    } else {
      // console.log(simulateTransaction.value.logs);
    }

    rebuildTransaction.recentBlockhash = blockHash;
    rebuildTransaction.sign(matcherKeypair);
    const txid = bs58.encode(rebuildTransaction.signature);
    const rawTx = rebuildTransaction.serialize();
    await this.dexActionTransactionRepo.update(
      { id: dexTransaction.id },
      { txid, rawTx: rawTx.toString("base64") }
    );

    await processedConnection.sendRawTransaction(rawTx, {
      preflightCommitment: "processed",
      maxRetries: 2,
    });
    await this.dexActionTransactionRepo.update(
      { id: dexTransaction.id },
      { status: DexTransactionStatus.SENT }
    );

    // const rebuildTransactionExist = await processedConnection.getSignatureStatus(dexTransaction.txid);
    // if (rebuildTransactionExist?.value && !rebuildTransactionExist.value.err) {
    //   await this.dexActionTransactionRepo.update({ id: dexTransaction.id }, { status: DexTransactionStatus.SENT });
    //   return;
    // }
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

  // private async _emitSuccessWithdrawalNotifications(withdrawEvents: any[]) {
  //   const notifications: { [key: string]: Notification[] } = {};
  //   for (const withdrawEvent of withdrawEvents) {
  //     const accountId = await this._getAccountId(withdrawEvent.address);
  //     const userId = await this._getAccountOwnerId(accountId);
  //     const withdrawAmount = new BigNumberJS(withdrawEvent.oldMargin).minus(withdrawEvent.newMargin);
  //     const withdrawAmountString = withdrawAmount.toString();
  //     const notification = {
  //       event: NotificationEvent.WithdrawSuccessfully,
  //       type: NotificationType.success,
  //       accountId,
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

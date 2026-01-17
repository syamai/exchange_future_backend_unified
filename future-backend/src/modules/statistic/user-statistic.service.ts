import { Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { TradeEntity } from "src/models/entities/trade.entity";
import { UserStatisticRepository } from "src/models/repositories/user-statistics.repository";
import { BotService } from "../bot/bot.service";
import { convertDateFields } from "../matching-engine/helper";
import { CommandOutput } from "../matching-engine/matching-engine.const";
import { LinkedQueue } from "src/utils/linked-queue";
import { AccountEntity } from "src/models/entities/account.entity";
import { UserRepository } from "src/models/repositories/user.repository";
import { PaginationDto } from "src/shares/dtos/pagination.dto";
import { getQueryLimit } from "src/shares/pagination-util";
import { AccountRepository } from "src/models/repositories/account.repository";

interface UserGainLoss {
  userId: number;
  pnlGain: string;
  pnlLoss: string;
}

@Injectable()
export class UserStatisticService {
  private readonly MAX_ACCOUNT_QUEUE_SIZE = 100;
  private readonly logger = new Logger(UserStatisticService.name);

  constructor(
    private readonly botService: BotService,
    @InjectRepository(UserStatisticRepository, "master")
    private readonly userStatisticRepoMaster: UserStatisticRepository,
    @InjectRepository(UserStatisticRepository, "report")
    private readonly userStatisticRepoReport: UserStatisticRepository,
    @InjectRepository(UserRepository, "report")
    private readonly userRepoReport: UserRepository,
    @InjectRepository(UserRepository, "master")
    private readonly userRepoMaster: UserRepository,
    @InjectRepository(AccountRepository, "report")
    private readonly accountRepoReport: AccountRepository,

  ) {}

  async saveUserGainLoss(commands: CommandOutput[]): Promise<void> {
    const tradeEntities: TradeEntity[] = [];
    for (const command of commands) {
      if (command.trades?.length > 0) {
        tradeEntities.push(...command.trades.map((item) => convertDateFields(new TradeEntity(), item)));
      }
    }
    // console.log("tradeEntities: ", JSON.stringify(tradeEntities));

    if (!tradeEntities.length) {
      return;
    }

    // filter bot
    const userIdSet = new Set<number>();
    for (const entity of tradeEntities) {
      userIdSet.add(entity.buyUserId);
      userIdSet.add(entity.sellUserId);
    }
    const userIdArr = Array.from(userIdSet);
    const isBotArr: boolean[] = await Promise.all(
      userIdArr.map(async (userId) => {
        return this.botService.checkIsBot(userId);
      })
    );
    const botIdMap = new Map<number, boolean>();
    userIdArr.forEach((userId: number, index: number) => {
      botIdMap.set(userId, isBotArr[index]);
    });

    // console.log("botIdMap entries:", Array.from(botIdMap.entries()));

    const userGainLossMap = new Map<number, UserGainLoss>();
    for (const entity of tradeEntities) {
      // console.log(`tradeEntities: ${JSON.stringify(tradeEntities)}`);

      const buyerIsBot = botIdMap.get(entity.buyUserId);
      if (!buyerIsBot) {
        console.log(`entity.buyUserId: ${entity.buyUserId}, entity.realizedPnlOrderBuy: ${entity.realizedPnlOrderBuy}`);

        this.updateUserGainLossMap(entity.buyUserId, entity.realizedPnlOrderBuy, userGainLossMap);
      }

      const sellerIsBot = botIdMap.get(entity.sellUserId);
      if (!sellerIsBot) {
        console.log(`entity.sellUserId: ${entity.sellUserId}, entity.realizedPnlOrderSell: ${entity.realizedPnlOrderSell}`);

        this.updateUserGainLossMap(entity.sellUserId, entity.realizedPnlOrderSell, userGainLossMap);
      }
    }
    if (Array.from(userGainLossMap.values()).length) {
      console.log(`userGainLossMap.values(): ${JSON.stringify(Array.from(userGainLossMap.values()))}`);
    }

    await this.upsertBatchUserGainLoss(Array.from(userGainLossMap.values()));
  }

  private updateUserGainLossMap(userId: number, pnl: string, userGainLossMap: Map<number, UserGainLoss>) {
    console.log(`UserId: ${userId}, pnl: ${pnl}`);

    if (!pnl) return;

    const pnlBigNumber = new BigNumber(pnl);
    // console.log(`pnlBigNumber: ${pnlBigNumber.toFixed()}`);

    if (pnlBigNumber.eq(0)) return;

    let userGainLoss = userGainLossMap.get(userId);
    if (!userGainLoss) {
      userGainLoss = {
        userId,
        pnlGain: "0",
        pnlLoss: "0",
      };
      userGainLossMap.set(userId, userGainLoss);
    }

    // update user pnl
    if (pnlBigNumber.lt(0)) {
      userGainLoss.pnlLoss = new BigNumber(userGainLoss.pnlLoss).plus(pnlBigNumber).toFixed();
      console.log(`userGainLoss.pnlLoss: ${userGainLoss.pnlLoss}`);
    } else {
      userGainLoss.pnlGain = new BigNumber(userGainLoss.pnlGain).plus(pnlBigNumber).toFixed();
    }
  }

  private async upsertBatchUserGainLoss(data: UserGainLoss[]) {
    if (!data.length) {
      return;
    }

    const values = data.map((r) => `('${r.userId}', '${r.pnlGain}', ${r.pnlLoss})`).join(",");

    const query = `
      INSERT INTO user_statistics (id, pnlGain, pnlLoss)
      VALUES ${values}
      ON DUPLICATE KEY UPDATE
      pnlGain = pnlGain + VALUES(pnlGain),
      pnlLoss = pnlLoss + VALUES(pnlLoss);
    `;

    await this.userStatisticRepoMaster.query(query);
  }

  public async saveUserDeposit(data: { userId: number; amount: string; asset: string }) {
    console.log(`[saveUserDeposit] - data: ${JSON.stringify(data)}`);
    const { userId, amount, asset } = data;
    if (await this.botService.checkIsBot(userId)) return;
    if (asset !== "usdt") return;

    const value = `('${userId}', '${amount}')`;
    const query = `
      INSERT INTO user_statistics (id, totalDeposit)
      VALUES ${value}
      ON DUPLICATE KEY UPDATE
      totalDeposit = totalDeposit + VALUES(totalDeposit);
    `;

    await this.userStatisticRepoMaster.query(query);

    // update user has deposited
    const user = await this.userRepoReport.findOne(userId);
    if (user && !user.hasDeposited) {
      user.hasDeposited = true;
      await this.userRepoMaster.save(user);
    }
  }

  public async saveUserWithdrawal(data: { userId: number; amount: string; asset: string }) {
    console.log(`[saveUserWithdrawal] - data: ${JSON.stringify(data)}`);
    const { userId, amount, asset } = data;
    if (await this.botService.checkIsBot(userId)) return;
    if (asset !== "usdt") return;

    const value = `('${userId}', '${amount}')`;
    const query = `
      INSERT INTO user_statistics (id, totalWithdrawal)
      VALUES ${value}
      ON DUPLICATE KEY UPDATE
      totalWithdrawal = totalWithdrawal + VALUES(totalWithdrawal);
    `;

    await this.userStatisticRepoMaster.query(query);
  }

  private static saveAccountQueue = new LinkedQueue<any>();
  private static saveAccountInterval = null;
  public async updateUserPeakAssetWorker(commands: CommandOutput[]): Promise<void> {
    if (!UserStatisticService.saveAccountInterval) {
      UserStatisticService.saveAccountInterval = setInterval(async () => {
        const batch = 50;
        const saveAccountToProcess = [];
        while (saveAccountToProcess.length < batch && !UserStatisticService.saveAccountQueue.isEmpty()) {
          saveAccountToProcess.push(UserStatisticService.saveAccountQueue.dequeue());
        }

        const accountEntities: AccountEntity[] = [];
        for (const accounts of saveAccountToProcess) {
          for (const account of accounts) {
            const newAccount = convertDateFields(new AccountEntity(), account);
            // filter bot account and usdt asset
            if (newAccount.asset === "USDT") {
              accountEntities.push(newAccount);
            }
          }
        }

        // get bot arr
        const accountIdSet = new Set<number>();
        for (const entity of accountEntities) {
          accountIdSet.add(entity.id);
          accountIdSet.add(entity.id);
        }
        const accountIds = Array.from(accountIdSet);
        const isBotAccArr: boolean[] = await Promise.all(
          accountIds.map(async (accountId) => {
            return this.botService.checkIsBotByAccountId(accountId);
          })
        );
        const botAccMap = new Map<number, boolean>();
        accountIds.forEach((accountId, index) => {
          botAccMap.set(accountId, isBotAccArr[index]);
        });

        // const userBalanceMap = new Map<number, string>();
        // for (let account of accountEntities) {
        //   const userId = account.userId;
        //   const newBalance = account.balance;
        //   const currentBalance = userBalanceMap.get(userId);
        //   if (!currentBalance) {
        //     userBalanceMap.set(userId, newBalance);
        //     continue;
        //   }
        //   const isNextBalanceGreater = new BigNumber(currentBalance).lt(newBalance);
        //   if (isNextBalanceGreater) {
        //     userBalanceMap.set(userId, newBalance);
        //   }
        // }

        // const userBalances = Array.from(userBalanceMap.entries());
        if (accountEntities.length) {
          await Promise.all(
            accountEntities.map(async (account) => {
              if (!botAccMap.get(account.id)) {
                return this.updateUserPeakAssetValue(account.userId, account.balance);
              }
            })
          );
        }
      }, 50);
    }

    for (const c of commands) {
      if (!c.accounts || c.accounts?.length == 0) continue;

      //check max size of account queue
      if (UserStatisticService.saveAccountQueue.size() >= this.MAX_ACCOUNT_QUEUE_SIZE) {
        this.logger.warn(
          `saveAccountQueue size=${UserStatisticService.saveAccountQueue.size()} is greater than MAX_ACCOUNT_QUEUE_SIZE, wait 100ms`
        );
        await new Promise((resolve) => setTimeout(resolve, 100));
      }

      UserStatisticService.saveAccountQueue.enqueue(c.accounts);
    }
  }

  private async updateUserPeakAssetValue(userId: number, newPeakAssetValue: string) {
    const query = `update user_statistics SET peakAssetValue = ${newPeakAssetValue} WHERE id = ${userId} and peakAssetValue < ${newPeakAssetValue}`;
    await this.userStatisticRepoMaster.query(query);
  }

  public async getTopGainList() {
    const topGainList = await this.userStatisticRepoReport
      .createQueryBuilder("uS")
      .select([
        "u.uid uid",
        "uS.totalDeposit totalDeposit",
        "a.balance currentAssetValue",
        "uS.pnlGain pnlGain",
        "uS.pnlGain / uS.totalDeposit * 100 as gainPercent",
        "u.id userId",
      ])
      .leftJoin("users", "u", "uS.id = u.id")
      .leftJoin("accounts", "a", "a.userId = u.id and a.asset = 'USDT'")
      .orderBy("uS.pnlGain", "DESC")
      .limit(5)
      .getRawMany();

    return topGainList;
  }

  public async getTopLoserList() {
    const topLoserList = await this.userStatisticRepoReport
      .createQueryBuilder("uS")
      .select([
        "u.uid uid",
        "uS.peakAssetValue peakAssetValue",
        "uS.pnlLoss pnlLoss",
        "uS.pnlLoss / uS.peakAssetValue * 100 as lossPercent",
        "u.id userId",
      ])
      .leftJoin("users", "u", "uS.id = u.id")
      .orderBy("uS.pnlLoss", "ASC")
      .limit(5)
      .getRawMany();

    return topLoserList;
  }

  public async getTopDepositList(coin: string) {
    console.log(coin);

    const topDepositList = await this.userStatisticRepoReport
      .createQueryBuilder("uS")
      .select(["u.uid uid", "'USDT' coinType", "uS.totalDeposit totalDeposit", "u.id userId"])
      .leftJoin("users", "u", "uS.id = u.id")
      .orderBy("uS.totalDeposit", "DESC")
      .limit(5)
      .getRawMany();

    return topDepositList;
  }

  public async getTopWithdrawList(coin: string) {
    console.log(coin);

    const topWithdrawList = await this.userStatisticRepoReport
      .createQueryBuilder("uS")
      .select(["u.uid uid", "'USDT' coinType", "uS.totalWithdrawal totalWithdrawal", "u.id userId"])
      .leftJoin("users", "u", "uS.id = u.id")
      .orderBy("uS.totalWithdrawal", "DESC")
      .limit(5)
      .getRawMany();

    return topWithdrawList;
  }

  public async getNoDepositUsers(paging: PaginationDto) {
    const { offset, limit } = getQueryLimit(paging);

    const qb = this.userRepoReport
      .createQueryBuilder("u")
      .select(["u.uid uid", "u.createdAt createdAt", "u.id userId"])
      .where("hasDeposited = :hasDeposited", { hasDeposited: false })
      .andWhere("isBot = :isBot", { isBot: false })
      .orderBy("u.id", "DESC");

    const [users, count] = await Promise.all([qb.limit(limit).offset(offset).getRawMany(), qb.getCount()]);

    return {
      data: users,
      metadata: {
        totalPage: Math.ceil(count / paging.size),
        total: count,
      },
    };
  }

  public async updateUserTotalTradeVolume(commands: CommandOutput[]): Promise<void> {
    const entities: TradeEntity[] = [];
    for (const command of commands) {
      if (command.trades?.length > 0) {
        entities.push(...command.trades.map((item) => convertDateFields(new TradeEntity(), item)));
      }
    }

    const accountIdSet = new Set<number>();
    for (const entity of entities) {
      accountIdSet.add(entity.buyAccountId);
      accountIdSet.add(entity.sellAccountId);
    }
    const accountIds = Array.from(accountIdSet);
    const isBotAccArr: boolean[] = await Promise.all(
      accountIds.map(async (accountId) => {
        return this.botService.checkIsBotByAccountId(accountId);
      })
    );
    const botAccMap = new Map<number, boolean>();
    accountIds.forEach((accountId, index) => {
      botAccMap.set(accountId, isBotAccArr[index]);
    });

    const updateUserTotalTradeVolumeDB = async (userId: number, amount: string, tradeFee: string) => {
      const value = `('${userId}', '${amount}', '${tradeFee}')`;
      const query = `
        INSERT INTO user_statistics (id, totalTradeVolume, tradeFee)
        VALUES ${value}
        ON DUPLICATE KEY UPDATE
        totalTradeVolume = totalTradeVolume + VALUES(totalTradeVolume),
        tradeFee = tradeFee + VALUES(tradeFee);
      `;

      await this.userStatisticRepoMaster.query(query);
    };

    await Promise.all(
      entities.map(async (entity) => {
        try {
          const buyerAccIsBot = botAccMap.get(entity.buyAccountId);
          const sellerAccIsBot = botAccMap.get(entity.sellAccountId);

          if (!buyerAccIsBot) {
            updateUserTotalTradeVolumeDB(
              entity.buyUserId,
              new BigNumber(entity.quantity).multipliedBy(entity.price).toString(),
              entity.buyFee
            );
          }

          if (!sellerAccIsBot) {
            updateUserTotalTradeVolumeDB(
              entity.sellUserId,
              new BigNumber(entity.quantity).multipliedBy(entity.price).toString(),
              entity.sellFee
            );
          }
        } catch (e) {
          console.log(`err: ${e}`);
        }
      })
    );
  }

  public async getPlayerRealBalanceReport(paging: PaginationDto, orderBy?: string, direction?: "ASC" | "DESC", q?: string) {
    const { offset, limit } = getQueryLimit(paging);
    const sortDirection = direction || "DESC";

    const qb = this.accountRepoReport
      .createQueryBuilder("a")
      .select([
        "u.id id",
        "u.uid uid",
        "u.email email",
        "uS.totalDeposit totalDeposit",
        "uS.totalWithdrawal totalWithdrawal",
        "a.balance balance",
        "a.rewardBalance rewardBalance",
        "a.balance - a.rewardBalance realBalance",
        "uS.totalTradeVolume totalTradeVolume",
        "(uS.pnlGain + uS.pnlLoss) totalProfit",
        "COALESCE(p.currentPosition, 0) currentPosition",
        "COALESCE(o.pendingPosition, 0) pendingPosition",
        "uS.tradeFee tradeFee",
        "uS.updatedAt lastLogin",
      ])
      .leftJoin("user_statistics", "uS", "uS.id = a.userId")
      .leftJoin("users", "u", "a.userId = u.id")
      .leftJoin(
        (subquery) => {
          return subquery
            .select(["p.userId userId", "SUM(abs(p.entryValue)) as currentPosition"])
            .from("positions", "p")
            .where("p.currentQty != '0' and p.userId > 500")
            .groupBy("p.userId");
        },
        "p",
        "p.userId = uS.id"
      )
      .leftJoin(
        (subquery) => {
          return subquery
            .select(["o.userId userId", "SUM(abs(o.remaining * o.price)) as pendingPosition"])
            .from("orders", "o")
            .where("o.status = 'ACTIVE' and o.userId > 500")
            .groupBy("o.userId");
        },
        "o",
        "o.userId = uS.id"
      )
      .andWhere(`a.id > 10000 and a.asset = 'USDT'`);

    // Add sorting
    if (orderBy && sortDirection) {
      if (orderBy === "currentPosition") {
        qb.orderBy(`p.${orderBy}`, sortDirection);
      } else if (orderBy === "pendingPosition") {
        qb.orderBy(`o.${orderBy}`, sortDirection);
      } else if (orderBy === "balance" || orderBy === "rewardBalance") {
        qb.orderBy(`a.${orderBy}`, sortDirection);
      } else if (orderBy === "realBalance") {
        qb.orderBy(`(a.balance - a.rewardBalance)`, sortDirection);
      } else if (orderBy === "id" || orderBy === "uid" || orderBy === "email") {
        qb.orderBy(`u.${orderBy}`, sortDirection);
      } else if (orderBy === "totalProfit") {
        qb.orderBy(`(uS.pnlGain + uS.pnlLoss)`, sortDirection);
      } else if (orderBy === "lastLogin") {
        qb.orderBy(`uS.updatedAt`, sortDirection);
      } else {
        qb.orderBy(`uS.${orderBy}`, sortDirection);
      }
    }

    if (q) {
      qb.andWhere("u.id = :q OR u.uid = :q", { q });
    }

    // console.log(`qquery: ${qb.getSql()}`);
    

    const [users, count] = await Promise.all([
      qb.limit(limit).offset(offset).getRawMany(), 
      qb.getCount()
    ]);

    return {
      data: users,
      metadata: {
        totalPage: Math.ceil(count / paging.size),
        total: count,
      },
    };
  }
}

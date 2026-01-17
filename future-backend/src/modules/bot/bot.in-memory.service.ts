import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { AccountRepository } from "src/models/repositories/account.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { AccountEntity } from "src/models/entities/account.entity";
import { UserEntity } from "src/models/entities/user.entity";

@Injectable()
export class BotInMemoryService {
  constructor(
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository
  ) {}

  private static inMemoryCache = {
    botUserIds: new Map<number, boolean>(),
    botAccountIds: new Map<number, boolean>(),
    botAccountsByUserId: new Map<number, AccountEntity[]>(),
    lastUpdatedTime: 0,
  }; // In-memory cache

  private async loadMemory(): Promise<void> {
    if (
      new Date().getTime() - BotInMemoryService.inMemoryCache.lastUpdatedTime <
      5 * 3600000
    )
      return; // 5 hour

    const botUsers: UserEntity[] = await this.userRepoReport
      .createQueryBuilder("user")
      .where("user.isBot = :isBot", { isBot: true })
      .select(["user.id", "user.isBot"])
      .getMany();

    if (!botUsers || botUsers.length === 0) return;

    const botAccounts: AccountEntity[] = await this.accountRepoReport
      .createQueryBuilder("account")
      .where("account.userId IN (:...userIds)", {
        userIds: botUsers.map((bU) => bU.id),
      })
      .select([
        "account.id",
        "account.userId",
        "account.asset",
        "account.userEmail",
      ])
      .getMany();

    botAccounts.forEach((botAccount) => {
      BotInMemoryService.inMemoryCache.botUserIds.set(
        Number(botAccount.userId),
        true
      );

      BotInMemoryService.inMemoryCache.botAccountIds.set(
        Number(botAccount.id),
        true
      );

      // Load botAccountsByUserId map
      const userId = Number(botAccount.userId);
      if (!BotInMemoryService.inMemoryCache.botAccountsByUserId.has(userId)) {
        BotInMemoryService.inMemoryCache.botAccountsByUserId.set(userId, []);
      }
      BotInMemoryService.inMemoryCache.botAccountsByUserId
        .get(userId)
        .push(botAccount);
    });
    BotInMemoryService.inMemoryCache.lastUpdatedTime = new Date().getTime();
  }

  public async checkIsBotAccountId(accountId: number): Promise<boolean> {
    await this.loadMemory();
    if (!BotInMemoryService.inMemoryCache.botAccountIds.get(Number(accountId)))
      return false;
    return true;
  }

  public async checkIsBotUserId(userId: number): Promise<boolean> {
    await this.loadMemory();
    if (!BotInMemoryService.inMemoryCache.botUserIds.get(Number(userId)))
      return false;
    return true;
  }

  public async getBotAccountIds() {
    await this.loadMemory();
    return Array.from(BotInMemoryService.inMemoryCache.botAccountIds.keys());
  }

  public async getBotAccountIdByUserIdAndAsset(
    userId: number,
    asset: string
  ): Promise<number> {
    await this.loadMemory();
    const accounts = BotInMemoryService.inMemoryCache.botAccountsByUserId.get(
      Number(userId)
    );
    if (!accounts || accounts.length === 0) {
      return null;
    }
    const account = accounts.find((acc) => acc.asset === asset);
    return account ? Number(account.id) : null;
  }

  public async getBotEmailByUserId(userId: number): Promise<string> {
    await this.loadMemory();
    const accounts = BotInMemoryService.inMemoryCache.botAccountsByUserId.get(
      Number(userId)
    );
    if (!accounts || accounts.length === 0) {
      return null;
    }
    return accounts[0].userEmail;
  }

  public getBotUserIdFromSymbol(symbol: string): number {
    const botUserIdBySymbol = {
      BTCUSDT: 51,
      ETHUSDT: 53,
      SOLUSDT: 54,
      XRPUSDT: 55,
      DOGEUSDT: 52,
      ADAUSDT: 62,
      "1000PEPEUSDT": 58,
      LINKUSDT: 56,
      SUIUSDT: 65,
      AVAXUSDT: 63,
      BNBUSDT: 50,
      TRUMPUSDT: 61,
      "1000SHIBUSDT": 60,
      TRXUSDT: 67,
      XLMUSDT: 68,
      ONDOUSDT: 57,
      HBARUSDT: 64,
      TONUSDT: 66,
      RENDERUSDT: 59,
    };

    return botUserIdBySymbol[symbol.toUpperCase()];
  }
}

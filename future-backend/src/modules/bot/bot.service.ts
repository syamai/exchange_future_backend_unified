import { CACHE_MANAGER, Inject, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Cache } from "cache-manager";
import { AccountRepository } from "src/models/repositories/account.repository";
import { OrderRepository } from "src/models/repositories/order.repository";
import { TradeRepository } from "src/models/repositories/trade.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { In } from "typeorm";
import { BOT_ACCOUNT_ID_REDIS_PATTERN, BOT_REDIS_PATTERN } from "./bot.const";

@Injectable()
export class BotService {
  constructor(
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    @InjectRepository(OrderRepository, "master")
    public readonly orderRepoMaster: OrderRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(TradeRepository, "master")
    public readonly tradeRepoMaster: TradeRepository
  ) {
    this.loadBotsFromDB().catch(e => {});
  }

  private getBotRedisKey(id: number): string {
    return `${BOT_REDIS_PATTERN}${id}`;
  }

  private getBotAccountIdRedisKey(id: number): string {
    return `${BOT_ACCOUNT_ID_REDIS_PATTERN}${id}`;
  }

  public async getBotIdsFromDB(): Promise<number[]> {
    // get bot from DB with isBot = true
    const botList = await this.userRepoReport.find({
      select: ["id"],
      where: {
        isBot: true,
      },
    });

    return botList.map((bot) => Number(bot.id));
  }

  private async loadBotsFromDB() {
    const botIds = await this.getBotIdsFromDB();
    for (const botId of botIds) {
      const accounts = await this.accountRepoReport.createQueryBuilder("account")
        .where("account.userId = :userId", { userId: botId })
        .select(["account.id", "account.userId"])
        .getMany();
      const accountIds = accounts.map(acc => acc.id);
      accountIds.forEach(async (accountId) => {
        const botAccountIsRedisKey = this.getBotAccountIdRedisKey(accountId);
        await this.cacheManager.set(botAccountIsRedisKey, "1", { ttl: Number.MAX_SAFE_INTEGER });  
      });
      const botRedisKey = this.getBotRedisKey(botId);
      await this.cacheManager.set(botRedisKey, JSON.stringify(accountIds), { ttl: Number.MAX_SAFE_INTEGER });
    }
  }

  public async addBot(id: number): Promise<void> {
    if (!(await this.checkIsBot(id))) {
      const accounts = await this.accountRepoReport.createQueryBuilder("account")
        .where("account.userId = :userId", { userId: id })
        .select(["account.id", "account.userId"])
        .getMany();
      const accountIds = accounts.map(acc => acc.id);
      accountIds.forEach(async (accountId) => {
        const botAccountIsRedisKey = this.getBotAccountIdRedisKey(accountId);
        await this.cacheManager.set(botAccountIsRedisKey, "1", { ttl: Number.MAX_SAFE_INTEGER });  
      });
      const botRedisKey = this.getBotRedisKey(id);
      this.cacheManager.set(botRedisKey, JSON.stringify(accountIds), { ttl: Number.MAX_SAFE_INTEGER });
    }
  }

  public async checkIsBot(id: number): Promise<boolean> {
    const botRedisKey = this.getBotRedisKey(id);
    return (await this.cacheManager.get(botRedisKey)) !== null;
  }

  public async checkIsBotByAccountId(accountId: number): Promise<boolean> {
    const botAccountIdRedisKey = this.getBotAccountIdRedisKey(accountId);
    return (await this.cacheManager.get(botAccountIdRedisKey)) === "1";
  }

  public async deleteBot(id: number) {
    const accounts = await this.accountRepoReport.createQueryBuilder("account")
        .where("account.userId = :userId", { userId: id })
        .select(["account.id", "account.userId"])
        .getMany();
    const accountIds = accounts.map(acc => acc.id);
    accountIds.forEach(async (accountId) => {
      const botAccountIsRedisKey = this.getBotAccountIdRedisKey(accountId);
      await this.cacheManager.del(botAccountIsRedisKey);  
    });

    const botRedisKey = this.getBotRedisKey(id);
    return this.cacheManager.del(botRedisKey);
  }

  public async loadBotAccountsFromDB() {
    // get bot Account
    const botIds = await this.getBotIdsFromDB();
    // get bot account from DB
    const botAccount = await this.accountRepoReport.find({ where: { userId: In(botIds) } });
    const botAccountIds = botAccount.map((account) => account.id);
    return botAccountIds;
  }
}

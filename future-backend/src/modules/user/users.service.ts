import { MailerService } from "@nestjs-modules/mailer";
import {
  BadRequestException,
  HttpException,
  HttpStatus,
  Injectable,
} from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Like, Repository, Transaction, TransactionRepository } from "typeorm";
import { ApiKey } from "src/models/entities/api-key.entity";
import { UserEntity } from "src/models/entities/user.entity";
import { AccountRepository } from "src/models/repositories/account.repository";
import { ApiKeyRepository } from "src/models/repositories/api-key.repository";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { UserSettingRepository } from "src/models/repositories/user-setting.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { CommandCode } from "src/modules/matching-engine/matching-engine.const";
import { CreateUserDto } from "src/modules/user/dto/create-user.dto";
import { FavoriteMarket } from "src/modules/user/dto/favorite-market.dto";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { UserIsLocked, UserRole, UserStatus } from "src/shares/enums/user.enum";
import { httpErrors } from "src/shares/exceptions";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";
import { CoinInfoRepository } from "src/models/repositories/coin-info.repository";
import { AccountEntity } from "src/models/entities/account.entity";
import { AssetOrder } from "../../shares/enums/order.enum";
import { SetUserTradeSettingDto } from "./dto/set-user-trade-setting.dto";

// eslint-disable-next-line
const Web3 = require("web3");

@Injectable()
export class UserService {
  private web3;

  constructor(
    @InjectRepository(ApiKeyRepository, "master")
    private apiKeyRepository: ApiKeyRepository,
    @InjectRepository(ApiKeyRepository, "report")
    private apiKeyReportRepository: ApiKeyRepository,
    @InjectRepository(UserRepository, "master")
    private usersRepositoryMaster: UserRepository,
    @InjectRepository(UserRepository, "report")
    private usersRepositoryReport: UserRepository,
    @InjectRepository(UserSettingRepository, "master")
    private userSettingRepository: UserSettingRepository,
    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepoReport: InstrumentRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(AccountRepository, "master")
    public readonly accountRepoMaster: AccountRepository,
    private readonly kafkaClient: KafkaClient,
    private readonly mailService: MailerService,
    @InjectRepository(CoinInfoRepository, "master")
    private coinInfoRepository: CoinInfoRepository
  ) {
    this.web3 = new Web3();
  }

  async checkUserIdExisted(id: number): Promise<boolean> {
    const user = await this.usersRepositoryReport.findOne({
      id: id,
    });
    if (user) return true;
    else return false;
  }

  async checkUserAddressExisted(address: string): Promise<boolean> {
    const user = await this.usersRepositoryReport.findOne({
      where: {
        address: address,
      },
      select: ["id"],
    });
    return !!user;
  }

  async findUserById(id: number): Promise<UserEntity> {
    const user = await this.usersRepositoryReport.findOne(
      { id },
      {
        select: [
          "id",
          "position",
          "status",
          "role",
          "isLocked",
          "userType",
          "email",
          "createdAt",
          "updatedAt",
          "notification_token",
          "location",
        ],
      }
    );
    if (!user) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.BAD_REQUEST
      );
    }
    return user;
  }

  async updateStatusUser(userId: number, status: string): Promise<void> {
    await this.usersRepositoryMaster.update(userId, {
      status,
    });
  }

  async findUserByAddress(address: string): Promise<UserEntity> {
    const user = await this.usersRepositoryReport.findOne({
      select: [
        "id",
        "status",
        "role",
        "isLocked",
        "userType",
        "createdAt",
        "updatedAt",
      ],
      where: {
        address: address,
      },
    });
    if (!user) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.BAD_REQUEST
      );
    }
    return user;
  }

  // TODO: transaction?
  async createUser(body: CreateUserDto): Promise<UserEntity> {
    const [{ exist }, existId] = await Promise.all([
      this.checkEmailExist(body.email),
      this.checkUserIdExisted(body.id),
    ]);
    if (exist || existId) {
      throw new HttpException(httpErrors.ACCOUNT_EXISTED, HttpStatus.FOUND);
    }

    const newUser = await this.usersRepositoryMaster.save({
      ...body,
      status: UserStatus.DEACTIVE,
      antiPhishingCode: body.email.split("@")[0],
      location: "_location",
      notification_token: body.email.split("@")[0],
    } as UserEntity);

    const assets = AssetOrder;
    const accountToSave: AccountEntity[] = [];
    for (const asset in assets) {
      const newAccountEntity: AccountEntity = new AccountEntity();

      newAccountEntity.userId = newUser.id;
      newAccountEntity.asset = asset;
      newAccountEntity.balance = "0";

      //EDIT
      newAccountEntity.operationId = 0;
      newAccountEntity.userEmail = body.email;

      accountToSave.push(newAccountEntity);
    }
    const savedAccount = await this.accountRepoMaster.save(accountToSave);

    for (const account of savedAccount) {
      await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
        code: CommandCode.CREATE_ACCOUNT,
        data: account,
      });
    }

    return newUser;
  }

  @Transaction({ connectionName: "master" })
  async createUserWithoutChecking(
    address: string,
    @TransactionRepository(UserEntity)
    transactionRepositoryUser?: Repository<UserEntity>,
    @TransactionRepository(AccountEntity)
    transactionRepositoryAccount?: Repository<AccountEntity>
  ): Promise<UserEntity> {
    const newUser = await transactionRepositoryUser.save({
      address,
      role: UserRole.USER,
      isLocked: UserIsLocked.UNLOCKED,
      status: UserStatus.ACTIVE,
    });
    const newAccountEntity: AccountEntity = new AccountEntity();
    newAccountEntity.userId = newUser.id;
    const savedAccount = await transactionRepositoryAccount.save(
      newAccountEntity
    );

    await this.kafkaClient.send(KafkaTopics.matching_engine_input, {
      code: CommandCode.CREATE_ACCOUNT,
      data: savedAccount,
    });

    return newUser;
  }

  async getUserFavoriteMarket(userId: number): Promise<FavoriteMarket[]> {
    const favoriteMarkets = await this.userSettingRepository.find({
      where: {
        userId: userId,
        key: Like(`${UserSettingRepository.FAVORITE_MARKET}%`),
        isFavorite: true,
      },
    });
    return favoriteMarkets.map((e) => ({
      symbol: e.key.split(`${UserSettingRepository.FAVORITE_MARKET}_`)[1],
    }));
  }

  async updateUserFavoriteMarket(
    userId: number,
    symbol: string,
    isFavorite: boolean
  ): Promise<{ symbol: string; isFavorite: boolean }> {
    const existSymbol = await this.instrumentRepoReport.findOne({
      where: {
        symbol: symbol,
      },
    });

    if (!existSymbol)
      throw new HttpException(
        httpErrors.INSTRUMENT_DOES_NOT_EXIST,
        HttpStatus.BAD_REQUEST
      );
    const key = `${UserSettingRepository.FAVORITE_MARKET}_${symbol}`;
    const checkExist = await this.userSettingRepository.findOne({
      userId: userId,
      key: key,
    });
    if (checkExist) {
      checkExist.isFavorite = isFavorite;
      checkExist.favoritedAt = new Date();
      await this.userSettingRepository.save(checkExist);
    } else {
      await this.userSettingRepository.save({
        userId: userId,
        key: key,
        isFavorite: isFavorite,
        favoritedAt: new Date()
      });
    }
    return {
      symbol,
      isFavorite,
    };
  }

  async checkEmailExist(email: string): Promise<{ exist: boolean }> {
    const user = await this.usersRepositoryReport.findOne({
      where: {
        email: email,
      },
    });
    return {
      exist: user ? true : false,
    };
  }

  async getUserByApiKey(key: string): Promise<{ id: string }> {
    const apiKey = await this.apiKeyReportRepository.findOne({
      where: { key },
      select: ["userId"],
    });
    if (!apiKey) {
      throw new HttpException(
        httpErrors.ACCOUNT_NOT_FOUND,
        HttpStatus.BAD_REQUEST
      );
    }
    return { id: apiKey.userId.toString() };
  }

  async listApiKey(address: string): Promise<ApiKey[]> {
    const user = await this.findUserByAddress(address);
    return this.apiKeyReportRepository.find({
      where: user.id.toString(),
      select: ["key", "createdAt"],
    });
  }

  async createApiKey(address: string): Promise<any> {
    let user: UserEntity;

    if (!(await this.checkUserAddressExisted(address))) {
      user = await this.createUserWithoutChecking(address);
    } else {
      user = await this.findUserByAddress(address);
    }

    const keyPair = this.web3.eth.accounts.create();
    const key = keyPair.address.toLowerCase();
    const secret = keyPair.privateKey;
    const apiKey = { key, secret };

    await this.apiKeyRepository.insert({ key, userId: user.id.toString() });

    return {
      apiKey,
    };
  }

  async deleteApiKey(apiKey: string): Promise<{ apiKey: string }> {
    const deleteApiKey = await this.apiKeyReportRepository.findOne({
      where: { key: apiKey },
      select: ["userId"],
    });
    if (!deleteApiKey) {
      throw new HttpException(`${apiKey} not found`, HttpStatus.NOT_FOUND);
    }
    await this.apiKeyRepository.delete({ key: apiKey });
    return { apiKey };
  }

  async getAntiPhishingCode(userId: number): Promise<string> {
    const user = await this.usersRepositoryReport.findOne({ id: userId });
    if (!user) {
      throw new BadRequestException("user_not_found");
    }

    return user?.antiPhishingCode || null;
  }

  async getAntiPhishingCodeByEmail(email: string): Promise<string> {
    const user = await this.usersRepositoryReport.findOne({ email });
    if (!user) {
      throw new BadRequestException("user_not_found");
    }

    return user?.antiPhishingCode || null;
  }

  async getUserTradeSetting(userId: number) {
    const user = await this.usersRepositoryMaster.findOne({ id: userId });
    if (!user) {
      throw new BadRequestException("user_not_found");
    }

    return {
      allowTrade: user.allowTrade,
      enableTradingFee: user.enableTradingFee,
      isMarketMaker: user.isMarketMaker,
      preTradingPair: user.preTradingPair
    }

  }

  async setUserTradeSetting(body: SetUserTradeSettingDto) {
    let user = await this.usersRepositoryMaster.findOne({ id: body.userId });
    if (!user) {
      throw new BadRequestException("user_not_found");
    }

    Object.assign(user, body)
    const result = await this.usersRepositoryMaster.save(user)
    return {
      allowTrade: result.allowTrade,
      enableTradingFee: result.enableTradingFee,
      isMarketMaker: result.isMarketMaker,
      preTradingPair: result.preTradingPair
    }
  }

  async enablePriceChangeFirebaseNoti(
    userId: number,
    symbol: string,
    enable: boolean
  ): Promise<{ symbol: string; enable: boolean }> {
    const existSymbol = await this.instrumentRepoReport.findOne({
      where: {
        symbol: symbol,
      },
    });

    if (!existSymbol)
      throw new HttpException(
        httpErrors.INSTRUMENT_DOES_NOT_EXIST,
        HttpStatus.BAD_REQUEST
      );
    const key = `${UserSettingRepository.FAVORITE_MARKET}_${symbol}`;
    const checkExist = await this.userSettingRepository.findOne({
      userId: userId,
      key: key,
    });
    if (checkExist) {
      checkExist.enablePriceChangeFireBase = enable;
      await this.userSettingRepository.save(checkExist);
    } 
    return {
      symbol,
      enable,
    };
  }
}

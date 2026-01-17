import { HttpException, HttpStatus, Inject, Injectable, forwardRef } from "@nestjs/common";
import { PositionService } from "../position/position.service";
import { OrderService } from "../order/order.service";
import { USDT, USD } from "./balance.const";
import BigNumber from "bignumber.js";
import { AccountService } from "../account/account.service";
import { AssetTokenDto } from "../account/dto/assets.dto";
import { AssetType } from "../../shares/enums/transaction.enum";
import fetch from "node-fetch";
import { AccountRepository } from "src/models/repositories/account.repository";
import { InjectRepository } from "@nestjs/typeorm";
import { AccountEntity } from "src/models/entities/account.entity";
import { PositionRepository } from "src/models/repositories/position.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { httpErrors } from "src/shares/exceptions";
import { ContractType, OrderStatus } from "src/shares/enums/order.enum";
import { IndexService } from "../index/index.service";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import {
  LIST_COINM,
  LIST_SYMBOL_COINM,
  LIST_SYMBOL_USDM,
} from "../transaction/transaction.const";
import { OrderRepository } from "src/models/repositories/order.repository";
import { In, Not } from "typeorm";
import axios from "axios";
import { AdminGetBalanceByUserDto } from "./dto/admin-balance-by-user.dto";
import { RedisService } from "nestjs-redis";
import { RedisClient } from "src/shares/redis-client/redis-client";
import { OPERATION_ID_DIVISOR } from "src/shares/number-formatter";
@Injectable()
export class BalanceService {
  constructor(
    public readonly indexService: IndexService,
    public readonly positionService: PositionService,
    @InjectRepository(PositionRepository, "report")
    public readonly positionRepoReport: PositionRepository,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    @InjectRepository(OrderRepository, "report")
    public readonly orderRepoReport: OrderRepository,
    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepoReport: InstrumentRepository,
    @Inject(forwardRef(() => OrderService))
    public readonly orderService: OrderService,
    public readonly accountService: AccountService,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(AccountRepository, "master")
    public readonly accountRepoMaster: AccountRepository,
    // private readonly redisService: RedisService,
    private readonly redisClient: RedisClient
  ) {}

  async getUserBalance(userId: number): Promise<AccountEntity[]> {
    const accounts = await this.accountRepoMaster.find({ where: { userId } });
    
    // Get accounts from cache with highest scores
    // const cachedAccounts = [];
    
    // for (const account of accounts) {
    //   const key = `accounts:userId_${userId}:accountId_${account.id}`;
    //   const members = await this.redisClient.getInstance().zrevrange(key, 0, 0, 'WITHSCORES');
    //   if (!members || members.length == 0) continue;
      
    //   const cachedAccount = JSON.parse(members[members.length - 2]);
    //   const cachedScore = parseInt(members[members.length - 1]);
    //   const accountOperationId = Number(
    //     (
    //       BigInt(account.operationId.toString()) %
    //       OPERATION_ID_DIVISOR
    //     ).toString()
    //   );

    //   if (cachedScore >= accountOperationId) {
    //     cachedAccounts.push(cachedAccount);
    //   }
    // }

    // // Replace DB accounts with cached accounts where applicable
    // for (const cachedAccount of cachedAccounts) {
    //   const dbAccountIndex = accounts.findIndex(a => Number(a.id) === Number(cachedAccount.id));
    //   if (dbAccountIndex !== -1) {
    //     accounts[dbAccountIndex] = {...accounts[dbAccountIndex], ...cachedAccount};
    //   }
    // }

    return accounts;
  }

  async getAssets(userId: number) {
    const prices = await fetch(`${process.env.SPOT_URL_API}/api/v1/prices`, {
      method: "get",
    });
    const resp = await prices.json();
    const account = await this.accountRepoReport.find({ where: { userId } });
    // eslint-disable-next-line @typescript-eslint/ban-types
    const listPriceFilter: object = {};
    for (const [key, value] of Object.entries(resp.data)) {
      if (key.includes("usd_") || key.includes("btc_")) {
        listPriceFilter[`${key}`] = value;
      }
    }

    const assets: AssetTokenDto[] = [];
    let totalEstimateUSD = new BigNumber(0);

    account.map((a) => {
      const priceOfCoins = Object.values(listPriceFilter).filter(
        (p) => a.asset.toLowerCase() === p.coin
      );
      const newAsset = {
        asset: a.asset,
        balance: a.balance,
        estimateBTC: "0",
        estimateUSD: "0",
      };
      if (a.asset.toLowerCase() === "usd") {
        newAsset["estimateBTC"] =
          Object.values(listPriceFilter).find(
            (p) =>
              p.coin === "usdt" && p.currency === AssetType.BTC.toLowerCase()
          )?.price || "0";
        newAsset["estimateUSD"] =
          Object.values(listPriceFilter).find(
            (p) =>
              p.coin === "usdt" && p.currency === AssetType.USD.toLowerCase()
          )?.price || "0";
      }

      if (priceOfCoins.length) {
        newAsset["estimateBTC"] =
          priceOfCoins.find(
            (poc) => poc.currency === AssetType.BTC.toLowerCase()
          )?.price || "0";
        newAsset["estimateUSD"] =
          priceOfCoins.find(
            (poc) => poc.currency === AssetType.USD.toLowerCase()
          )?.price || "0";
      }

      if (newAsset["estimateUSD"]) {
        assets.push(newAsset);
        totalEstimateUSD = totalEstimateUSD.plus(newAsset["estimateUSD"]);
      }
    });
    return {
      assets,
      totalWalletBalance: totalEstimateUSD.toString(),
    };
  }

  async formatAccountBeforeResponse(account) {
    const [usdtAsset, usdAsset] = await Promise.all([
      await this.calAvailableBalance(account.usdtBalance, account.id, USDT),
      await this.calAvailableBalance(account.usdBalance, account.id, USD),
    ]);
    account.usdtAvailableBalance = usdtAsset.availableBalance;
    account.usdAvailableBalance = usdAsset.availableBalance;
    account.orderMargin = usdtAsset.orderMargin;
    account.positionMarginIsolate = usdtAsset.positionMarginIsolate;
    account.positionMarginCross = usdtAsset.positionMarginCross;
    account.unrealizedPNL = usdtAsset.unrealizedPNL;
    account.positionMargin = usdtAsset.positionMargin;
    return account;
  }

  async calAvailableBalance(
    walletBalance: string,
    accountId: number,
    asset: string
  ) {
    const position = await this.positionService.calPositionMarginForAcc(
      accountId,
      asset
    );
    const orderMargin = await this.orderService.calOrderMargin(
      accountId,
      asset
    );
    const availableBalance = new BigNumber(walletBalance)
      .minus(position.positionMargin)
      .minus(orderMargin)
      .plus(position.unrealizedPNL)
      .toString();

    return {
      availableBalance,
      orderMargin,
      positionMargin: position.positionMargin,
      unrealizedPNL: position.unrealizedPNL,
      positionMarginCross: position.positionMarginCross,
      positionMarginIsolate: position.positionMarginIsIsolate,
    };
  }

  async convertTokenToUSd() {}

  async convertTokenToBTC() {}

  async getInforBalance(userId: number, asset?: string) {
    const user = await this.userRepoReport.findOne({ where: { id: userId } });
    const response = {};
    if (!user) {
      throw new HttpException(httpErrors.USER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
    if (asset) {
      if (![...LIST_COINM, "USDT", "USD"].includes(asset)) {
        throw new HttpException(
          httpErrors.SYMBOL_DOES_NOT_EXIST,
          HttpStatus.BAD_REQUEST
        );
      }
      const result = await this.getInforBalanceBySymbol(userId, asset);
      response[`${asset}`] = result;
    } else {
      const listAsset = [...LIST_COINM, "USDT", "USD"];
      for (const itemAsset of listAsset) {
        const result = await this.getInforBalanceBySymbol(userId, itemAsset);
        response[`${itemAsset}`] = result;
      }
    }
    return response;
  }

  private async getInforBalanceBySymbol(userId: number, asset: string) {
    const symbols = this.getSymbolOfAsset(asset);
    const account = await this.accountRepoReport.findOne({
      where: { asset, userId },
    });
    if (!account) {
      return null;
    }
    const resultAsset = {};
    let unrealizedPnlOfCross = "0";
    const totalCost = await this.calOrderMargin(userId, symbols);
    let totalPositionMargin = "0";
    let totalPnlOfAsset = "0";
    let unrealizedPNL = "0";
    let positionMargin = "0";
    let totalAllocated = "0";
    for (const symbol of symbols) {
      const [oraclePrice, position, instrument, indexPrice] = await Promise.all(
        [
          this.indexService.getOraclePrices([symbol]),
          this.positionRepoReport.findOne({
            where: { symbol, userId, currentQty: Not("0") },
          }),
          this.instrumentRepoReport.findOne({ where: { symbol } }),
          this.indexService.getIndexPrices([symbol]),
        ]
      );
      if (!position) {
        continue;
      }
      // resultAsset[`${symbol}`] = {};
      // resultAsset[`${symbol}`]['indexPrice'] = indexPrice[0];
      // resultAsset[`${symbol}`]['markPrice'] = oraclePrice[0];
      // resultAsset[`${symbol}`]['marginType'] = position.isCross ? 'CROSS' : 'ISOLATED';
      // resultAsset[`${symbol}`]['liquidationPrice'] = position.liquidationPrice;
      // position.r;
      let allocatedMargin = "0";
      const sideValue = +position.currentQty > 0 ? 1 : -1;
      switch (position.contractType) {
        case ContractType.COIN_M:
          // calculate position margin
          if (position.isCross) {
            positionMargin = new BigNumber(
              (Math.abs(+position.currentQty) * +instrument.multiplier) /
                (+position.leverage * +oraclePrice[0])
            ).toString();
            allocatedMargin = positionMargin;
            totalAllocated = new BigNumber(totalAllocated)
              .plus(new BigNumber(allocatedMargin))
              .toString();
          } else {
            positionMargin = new BigNumber(+position.positionMargin).toString();
            allocatedMargin = new BigNumber(position.positionMargin)
              .plus(position.adjustMargin)
              .toString();
            totalAllocated = new BigNumber(totalAllocated)
              .plus(new BigNumber(allocatedMargin))
              .toString();
          }
          console.log({
            allocatedMargin,
            position,
            indexPrice,
            oraclePrice,
            resultAsset,
          });

          unrealizedPNL = new BigNumber(
            Math.abs(+position.currentQty) *
              +instrument.multiplier *
              (1 / +position.entryPrice - 1 / +oraclePrice[0]) *
              sideValue
          ).toString();
          break;
        case ContractType.USD_M:
          //calculate position margin
          if (position.isCross) {
            positionMargin = new BigNumber(
              (Math.abs(+position.currentQty) * +oraclePrice[0]) /
                +position.leverage
            ).toString();
            allocatedMargin = positionMargin;
            totalAllocated = new BigNumber(totalAllocated)
              .plus(new BigNumber(allocatedMargin))
              .toString();
          } else {
            positionMargin = new BigNumber(+position.positionMargin).toString();
            // check again funding fee
            allocatedMargin = new BigNumber(position.positionMargin)
              .plus(position.adjustMargin)
              .toString();
            totalAllocated = new BigNumber(totalAllocated)
              .plus(new BigNumber(allocatedMargin))
              .toString();
          }
          // console.log({ allocatedMargin, position, indexPrice, oraclePrice, resultAsset });

          //calculate pnl
          unrealizedPNL = new BigNumber(
            Math.abs(+position.currentQty) *
              (+oraclePrice[0] - +position.entryPrice) *
              sideValue
          ).toString();
          break;
        default:
          break;
      }
      const unrealizedPNLAdd = position.isCross ? unrealizedPNL : 0;
      unrealizedPnlOfCross = new BigNumber(unrealizedPnlOfCross)
        .plus(new BigNumber(unrealizedPNLAdd))
        .toString();
      totalPositionMargin = new BigNumber(totalPositionMargin)
        .plus(new BigNumber(positionMargin))
        .toString();
      totalPnlOfAsset = new BigNumber(totalPnlOfAsset)
        .plus(new BigNumber(unrealizedPNL))
        .toString();
    }
    console.log("check available", {
      totalAllocated,
      totalCost,
      unrealizedPnlOfCross,
    });
    const availableBalance = new BigNumber(+account.balance)
      .minus(new BigNumber(totalAllocated))
      .minus(totalCost)
      .plus(unrealizedPnlOfCross)
      .toString();
    resultAsset["availableBalance"] = availableBalance;
    resultAsset["totalPnlOfAsset"] = totalPnlOfAsset;
    resultAsset["totalBalance"] = account.balance;
    resultAsset["positionMargin"] = totalAllocated.toString();
    return resultAsset;
    // return result;
  }

  private getSymbolOfAsset(asset: string): string[] {
    const listSymbol = [...LIST_SYMBOL_COINM, ...LIST_SYMBOL_USDM];
    if (asset === "USDT") {
      return listSymbol.filter((symbol) => symbol.includes("USDT"));
    } else if (asset === "USD") {
      return listSymbol.filter(
        (symbol) => !symbol.includes("USDM") && !symbol.includes("USDT")
      );
    } else {
      return listSymbol.filter((symbol) => symbol.includes(`${asset}USDM`));
    }
  }

  private async calOrderMargin(userId: number, symbols: string[]) {
    try {
      const result = await this.orderRepoReport
        .createQueryBuilder("o")
        .where("o.symbol In (:symbols)", { symbols })
        .andWhere("o.userId = :userId", { userId })
        .andWhere("o.status IN (:status)", {
          status: [OrderStatus.ACTIVE, OrderStatus.UNTRIGGERED],
        })
        .select("SUM(o.cost) as totalCost")
        .getRawOne();

      return result.totalCost ? result.totalCost : 0;
    } catch (error) {
      console.log(error);

      throw new HttpException(httpErrors.ORDER_NOT_FOUND, HttpStatus.NOT_FOUND);
    }
  }

  public async getTotalUserBalances() {
    const [instruments] = await Promise.all([
      this.instrumentRepoReport.find({ select: ["symbol"] }),
    ]);
    const lstCoinSupport = [
      ...new Set(
        instruments
          .map((instrument) => instrument.symbol)
          .map((symbol) => {
            if (symbol.includes("USDM")) {
              return symbol.split("USDM")[0];
            } else if (symbol.includes("USDT")) {
              return "USDT";
            } else {
              return "USD";
            }
          })
      ),
    ];
    return await this.accountRepoReport
      .createQueryBuilder("a")
      .select(["a.asset as asset", "sum(a.balance) as totalBalance"])
      .where({
        asset: In(lstCoinSupport),
      })
      .groupBy("a.asset")
      .getRawMany();
  }

  async adminGetListUserBalances(userIds: number[]) {
    const exchangeRate = async (asset: string) => {
      function extractAsset(asset: string) {
        const match = asset.match(/^(\d+)([A-Z]+)$/);
        if (match) {
          return {
            multiplier: parseInt(match[1], 10),
            assetName: match[2],
          };
        } else {
          return {
            multiplier: null,
            assetName: asset,
          };
        }
      }
      let { multiplier, assetName } = extractAsset(asset);
      asset = assetName.toLowerCase();

      try {
        if (asset === "usdt" || asset === "usd") return "1";
        const { data } = await axios.get(`https://api.coinbase.com/v2/exchange-rates?currency=${asset}`);
        const rateUsdt = data?.data?.rates?.USDT;
        return multiplier ? new BigNumber(rateUsdt).multipliedBy(multiplier).toString() : rateUsdt;
      } catch (error) {
        console.log(error);
        return "1";
      }
    };
    //get list user balances from accounts

    const distinctAssets = await this.accountRepoMaster
      .createQueryBuilder("a")
      .select("DISTINCT a.asset")
      .where({
        userId: In(userIds),
      })
      .andWhere('a.asset is not null')
      .getRawMany();

    if (!distinctAssets.length) {
      throw new Error();
    }
    //get usdt value of asset
    const getExchangeRates = distinctAssets.map((dA) => {
      return exchangeRate(dA.asset);
    });
    const exchangeRates = await Promise.all(getExchangeRates);

    const assetUsdts = {};
    for (let i = 0; i < distinctAssets.length; i++) {
      assetUsdts[distinctAssets[i].asset] = exchangeRates[i];
    }

    const userBalances = await this.accountRepoMaster
      .createQueryBuilder("a")
      .select(["a.userId", "a.balance", "a.asset"])
      .where({
        userId: In(userIds),
      })
      .getMany();

    const userAssetBalances = {};
    const handleAsset = (uS, userAssetBalances) => {
      // calculate total USDT[uS.userId]

      const newUsdtBalance = new BigNumber(userAssetBalances[uS.userId]["usdtBalance"])
        .plus(new BigNumber(uS.balance).times(new BigNumber(assetUsdts[uS.asset])))
        .toString();

      userAssetBalances[uS.userId]["usdtBalance"] = newUsdtBalance;
      // add detail balance
      if (Number(uS.balance) !== 0) {
        userAssetBalances[uS.userId]["detail"][uS.asset] = uS.balance;
      }
    };
    for (const uS of userBalances) {
      if (!userAssetBalances[uS.userId]) {
        userAssetBalances[uS.userId] = {
          usdtBalance: 0,
          detail: {},
        };
      }
      handleAsset(uS, userAssetBalances);
    }
    return userAssetBalances;
  }
  
  async adminGetBalanceByUser(query: AdminGetBalanceByUserDto) {
    const assets = await this.accountRepoMaster
    .createQueryBuilder("a")
    .where({
      userId: query.userId,
    })
    .getMany();

    return assets.map(a => {
      return {
        coin: a.asset,
        inOrder: 0,
        available: a.balance,
        total: a.balance
      }
    })
  }
}

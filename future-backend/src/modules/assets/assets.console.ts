import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Command, Console } from "nestjs-console";
import { AssetsEntity } from "src/models/entities/assets.entity";
import { AssetsRepository } from "src/models/repositories/assets.repository";

@Console()
@Injectable()
export default class AssetsSeedCommand {
  constructor(
    @InjectRepository(AssetsRepository, "master")
    public readonly assetsRepository: AssetsRepository
  ) {}

  @Command({
    command: "seed:assets",
    description: "seed assets",
  })
  async seedAssets(): Promise<void> {
    await this.assetsRepository
      .createQueryBuilder()
      .insert()
      .into(AssetsEntity)
      .values([
        {
          asset: "BTCUSDT",
        },
        {
          asset: "ETHUSDT",
        },
        {
          asset: "BNBUSDT",
        },
        {
          asset: "LTCUSDT",
        },
        {
          asset: "XRPUSDT",
        },
        {
          asset: "SOLUSDT",
        },
        {
          asset: "TRXUSDT",
        },
        {
          asset: "MATICUSDT",
        },
        {
          asset: "LINKUSDT",
        },
        {
          asset: "MANAUSDT",
        },
        {
          asset: "FILUSDT",
        },
        {
          asset: "ATOMUSDT",
        },
        {
          asset: "AAVEUSDT",
        },
        {
          asset: "DOGEUSDT",
        },
        {
          asset: "DOTUSDT",
        },
        {
          asset: "UNIUSDT",
        },
        {
          asset: "ETHUSD",
        },
        {
          asset: "BNBUSD",
        },
        {
          asset: "LTCUSD",
        },
        {
          asset: "XRPUSD",
        },
        {
          asset: "USDTUSD",
        },
        {
          asset: "SOLUSD",
        },
        {
          asset: "TRXUSD",
        },
        {
          asset: "MATICUSD",
        },
        {
          asset: "LINKUSD",
        },
        {
          asset: "MANAUSD",
        },
        {
          asset: "FILUSD",
        },
        {
          asset: "ATOMUSD",
        },
        {
          asset: "AAVEUSD",
        },
        {
          asset: "DOGEUSD",
        },
        {
          asset: "DOTUSD",
        },
        {
          asset: "UNIUSD",
        },
      ])
      .execute();
  }
}

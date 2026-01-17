import { CACHE_MANAGER, Inject, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { Cache } from "cache-manager";
import { AssetsRepository } from "src/models/repositories/assets.repository";
import { ContractType } from "src/shares/enums/order.enum";
import { AssetType } from "../transaction/transaction.const";
import { ASSETS_TTL } from "./assets.constants";
import { CreateAssetDto } from "./dto/create-asset.dto";


@Injectable()
export class AssetsService {
  constructor(
    @InjectRepository(AssetsRepository, "report")
    public readonly assetsRepoReport: AssetsRepository,
    @InjectRepository(AssetsRepository, "master")
    public readonly assetsRepoMaster: AssetsRepository,
    @Inject(CACHE_MANAGER) private cacheManager: Cache
  ) {}

  async findAllAssets(contractType: ContractType) {
    let assetsCache: string[] = [];
    switch (contractType) {
      case ContractType.COIN_M:
        assetsCache = await this.getAssetByType(AssetType.COIN_M);
        break;
      case ContractType.USD_M:
        assetsCache = await this.getAssetByType(AssetType.USD_M);
        break;
      case ContractType.ALL:
        const allAssets = await Promise.all([this.getAssetByType(AssetType.COIN_M), this.getAssetByType(AssetType.USD_M)]);
        assetsCache = [...allAssets[0], ...allAssets[1]];
        break;
    }

    return assetsCache;
  }

  async createAsset(dto: CreateAssetDto) {
    const { assetType, asset } = dto;
    const findAsset = await this.assetsRepoReport.findOne({
      where: { assetType, asset },
    });
    if (findAsset) {
      return findAsset;
    }
    const newAsset = this.assetsRepoMaster.create(dto);
    await this.assetsRepoMaster.save(newAsset);
    await this.cacheManager.del(assetType);
    return newAsset;
  }

  async deleteAsset(id: number) {
    await this.assetsRepoMaster.delete(id);
    return "ok";
  }

  async getAssetByType(assetType: AssetType) {
    const assetsListCache = await this.cacheManager.get<string[]>(assetType);
    if (assetsListCache?.length > 0) {
      return assetsListCache;
    }

    // find assets in db
    const assets = await this.assetsRepoReport.find({
      where: { assetType },
    });
    const assetsList = assets.map((item) => item.asset);
    await this.cacheManager.set(assetType, assetsList, {
      ttl: ASSETS_TTL,
    });
    return assetsList;
  }
}

import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { LatestBlockEntity } from "src/models/entities/latest-block.entity";
import { LatestBlockRepository } from "src/models/repositories/latest-block.repository";

@Injectable()
export class LatestBlockService {
  constructor(
    @InjectRepository(LatestBlockRepository, "master")
    public readonly latestBlockRepoMaster: LatestBlockRepository
  ) {}

  async saveLatestBlock(
    service: string,
    block: number,
    latestBlockRepository?: LatestBlockRepository
  ): Promise<void> {
    if (!latestBlockRepository) {
      latestBlockRepository = this.latestBlockRepoMaster;
    }
    await latestBlockRepository.saveLatestBlock(service, block);
  }

  async getLatestBlock(service: string): Promise<LatestBlockEntity> {
    let latestBlock = await this.latestBlockRepoMaster.findOne({ service });
    if (!latestBlock) {
      latestBlock = new LatestBlockEntity();
      latestBlock.service = service;
      await this.latestBlockRepoMaster.insert(latestBlock);
    }
    return latestBlock;
  }

  async updateLatestBlockStatus(
    latestBlock: LatestBlockEntity
  ): Promise<LatestBlockEntity> {
    latestBlock.updatedAt = new Date();
    return await this.latestBlockRepoMaster.save(latestBlock);
  }
}

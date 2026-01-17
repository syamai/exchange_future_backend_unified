import { LatestBlockEntity } from "src/models/entities/latest-block.entity";
import { EntityRepository, Repository } from "typeorm";

@EntityRepository(LatestBlockEntity)
export class LatestBlockRepository extends Repository<LatestBlockEntity> {
  async saveLatestBlock(service: string, block: number): Promise<void> {
    const latestBlock = new LatestBlockEntity();
    latestBlock.blockNumber = block;
    latestBlock.updatedAt = new Date();
    await this.update({ service }, latestBlock);
  }
}
